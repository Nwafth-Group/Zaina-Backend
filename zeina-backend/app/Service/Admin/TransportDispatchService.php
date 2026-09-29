<?php

namespace App\Service\Admin;

use App\Models\Booking;
use App\Models\CompanyInfo;
use App\Models\DeparturePoint;
use App\Models\Driver;
use App\Models\ServiceZone;
use App\Models\TransportGroup;
use App\Models\TransportGroupWorker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransportDispatchService
{
    /**
     * Get aggregated region list for a given service date.
     */
    public function getDailyOverview(?string $date = null, ?string $governorate = null, ?string $area = null, ?string $startTime = null, ?string $status = null)
    {
        $query = Booking::query()
            ->with(['address.serviceZone', 'assignedWorkers', 'workers', 'service'])
            ->whereNotIn('status', ['cancelled', 'Cancelled', 'refunded', 'Refunded']);

        if (!empty($date)) {
            $query->whereDate('booking_date', $date);
        }

        if ($governorate) {
            $query->whereHas('address', function ($q) use ($governorate) {
                $q->where('governorate', $governorate);
            });
        }

        if ($startTime) {
            $query->where('start_time', 'like', $startTime . '%');
        }

        $bookings = $query->get();

        // Group by Region (Governorate + ServiceZone)
        $regionsMap = [];

        foreach ($bookings as $booking) {
            $zone = $booking->address?->serviceZone;

            $rawGov = $zone?->governorate ?? $booking->address?->governorate ?? 'القاهرة';
            $govName = is_object($rawGov) ? ($rawGov->name_ar ?? $rawGov->name_en ?? 'القاهرة') : (string) $rawGov;

            $rawZone = $zone?->name ?? 'منطقة غير محددة';
            $zoneName = is_object($rawZone) ? ($rawZone->name_ar ?? $rawZone->name_en ?? 'منطقة غير محددة') : (string) $rawZone;

            $zoneId = $zone?->id;
            $bStartTime = substr($booking->start_time ?? '09:00', 0, 5);

            if ($area && strpos($zoneName, $area) === false) {
                continue;
            }

            $key = $govName . '___' . $zoneName;

            if (!isset($regionsMap[$key])) {
                $regionsMap[$key] = [
                    'governorate' => $govName,
                    'area' => $zoneName,
                    'service_zone_id' => $zoneId,
                    'bookings' => collect(),
                    'workers_count' => 0,
                    'earliest_start_time' => $bStartTime,
                ];
            }

            $regionsMap[$key]['bookings']->push($booking);
            $assigned = $booking->assignedWorkers->count() ?: $booking->workers->count();
            $workersCount = $assigned ?: 1;
            $regionsMap[$key]['workers_count'] += $workersCount;

            if (strtotime($bStartTime) < strtotime($regionsMap[$key]['earliest_start_time'])) {
                $regionsMap[$key]['earliest_start_time'] = $bStartTime;
            }
        }

        // Calculate status for each region
        $items = [];
        foreach ($regionsMap as $key => $data) {
            $zoneId = $data['service_zone_id'];
            $governorate = $data['governorate'];
            $areaName = $data['area'];

            // Fetch existing groups for this region and date
            $existingGroups = TransportGroup::when(!empty($date), fn($q) => $q->where('service_date', $date))
                ->where('governorate', $governorate)
                ->where('area_name', $areaName)
                ->get();

            $prepStatus = 'not_prepared'; // default 'غير مجهزة'
            if ($existingGroups->count() > 0) {
                $readyCount = $existingGroups->where('status', 'ready_for_transport')->count();
                if ($readyCount === $existingGroups->count()) {
                    $prepStatus = 'ready_for_transport'; // 'جاهز للنقل'
                } elseif ($readyCount > 0) {
                    $prepStatus = 'partially_prepared'; // 'محدد جزئياً'
                }
            }

            if ($status && $prepStatus !== $status) {
                continue;
            }

            $items[] = [
                'governorate' => $governorate,
                'area' => $areaName,
                'service_zone_id' => $zoneId,
                'bookings_count' => $data['bookings']->count(),
                'workers_count' => $data['workers_count'],
                'earliest_start_time' => $data['earliest_start_time'],
                'prep_status' => $prepStatus,
                'groups_count' => $existingGroups->count(),
            ];
        }

        return [
            'service_date' => $date,
            'summary' => [
                'total_regions' => count($items),
                'total_bookings' => array_sum(array_column($items, 'bookings_count')),
                'total_workers' => array_sum(array_column($items, 'workers_count')),
            ],
            'items' => $items,
        ];
    }

    /**
     * Get detailed start-time batches for a specific date and region.
     */
    public function getRegionGroupDetails(string $date, string $governorate, string $areaName)
    {
        $bookings = Booking::query()
            ->with(['address.serviceZone', 'assignedWorkers', 'workers', 'service', 'customer'])
            ->whereDate('booking_date', $date)
            ->whereNotIn('status', ['cancelled', 'Cancelled', 'refunded', 'Refunded'])
            ->get();

        // Filter by governorate / area
        $filteredBookings = $bookings->filter(function ($b) use ($governorate, $areaName) {
            $zone = $b->address?->serviceZone;
            $gov = $b->address?->governorate ?? $zone?->governorate ?? 'عمان';
            $area = $zone?->name ?? $b->address?->city ?? 'غير محدد';
            return ($gov === $governorate || str_contains($gov, $governorate))
                && ($area === $areaName || str_contains($area, $areaName));
        });

        if ($filteredBookings->isEmpty()) {
            $filteredBookings = $bookings;
        }

        // Sort bookings by start time
        $sortedBookings = $filteredBookings->sortBy(function ($b) {
            return strtotime('1970-01-01 ' . ($b->start_time ?? '09:00:00'));
        });

        // Group into shift batches where start times within 1 hour belong to the same batch
        $timeGroups = [];
        foreach ($sortedBookings as $b) {
            $timeStr = substr($b->start_time ?? '09:00', 0, 5);
            $timeTs = strtotime('1970-01-01 ' . $timeStr . ':00');

            $matchedGroupKey = null;
            foreach ($timeGroups as $baseStartTime => $groupBookings) {
                $baseTs = strtotime('1970-01-01 ' . $baseStartTime . ':00');
                $diffInSeconds = abs($timeTs - $baseTs);
                if ($diffInSeconds <= 3600) { // 1 hour threshold
                    $matchedGroupKey = $baseStartTime;
                    break;
                }
            }

            if ($matchedGroupKey !== null) {
                $timeGroups[$matchedGroupKey]->push($b);
            } else {
                $timeGroups[$timeStr] = collect([$b]);
            }
        }

        $groupsResult = [];

        foreach ($timeGroups as $startTime => $batchBookings) {
            // Find or initialize transport group DB record with default departure time 60 mins before start
            $defaultDepTime = Carbon::parse('1970-01-01 ' . $startTime)->subMinutes(60)->format('H:i');
            $group = TransportGroup::firstOrCreate([
                'service_date' => $date,
                'governorate' => $governorate,
                'area_name' => $areaName,
                'start_time' => $startTime,
            ], [
                'departure_time' => $defaultDepTime,
                'status' => 'not_prepared',
            ]);

            $group->load(['departurePoint', 'driver']);

            $workersCount = 0;
            $workersList = [];

            foreach ($batchBookings as $b) {
                $workers = $b->assignedWorkers->count() > 0 ? $b->assignedWorkers : $b->workers;
                $serviceAddr = $b->address?->formatted_address ?? $b->address?->street ?? '—';
                $teamName = $b->team ? ($b->team->name_ar ?? $b->team->name_en) : null;

                if ($workers->count() > 0) {
                    foreach ($workers as $w) {
                        $workersCount++;
                        $custName = $b->customer?->full_name
                            ?? (trim(($b->customer?->first_name ?? '') . ' ' . ($b->customer?->last_name ?? '')) ?: null)
                            ?? $b->customer?->name
                            ?? '—';
                        $custPhone = $b->customer?->phone_number ?? $b->customer?->mobile ?? $b->customer?->phone ?? $b->address?->phone_number ?? '—';
                        $workerPhone = $w->phone_number ?? $w->mobile ?? $w->phone ?? '—';

                        $workersList[] = [
                            'id' => $w->id,
                            'name' => $w->name ?? trim(($w->first_name ?? '') . ' ' . ($w->last_name ?? '')),
                            'mobile' => $workerPhone,
                            'phone' => $workerPhone,
                            'address' => $w->address ?? 'سكن العاملات الرئيسي',
                            'customer_name' => $custName,
                            'customer_phone' => $custPhone,
                            'service_name' => $b->service?->name_ar ?? $b->service?->name_en ?? '—',
                            'service_name_ar' => $b->service?->name_ar ?? '—',
                            'service_name_en' => $b->service?->name_en ?? $b->service?->name_ar ?? '—',
                            'service_address' => $serviceAddr,
                            'visit_time' => substr($b->start_time ?? '09:00', 0, 5) . ($b->end_time ? ' - ' . substr($b->end_time, 0, 5) : ''),
                            'booking_id' => $b->id,
                            'booking_number' => $b->booking_ref ?? ('BKG-' . $b->id),
                            'team_id' => $b->team_id,
                            'team_name' => $teamName,
                        ];
                    }
                } else {
                    $workersCount++;
                }
            }

            // Sync booking pivot
            $group->bookings()->syncWithoutDetaching($batchBookings->pluck('id'));

            $effectiveDepTime = $group->departure_time ?: $defaultDepTime;

            $groupsResult[] = [
                'group_id' => $group->id,
                'start_time' => $startTime,
                'bookings_count' => $batchBookings->count(),
                'workers_count' => $workersCount,
                'departure_point' => $group->departurePoint,
                'departure_time' => $effectiveDepTime,
                'driver' => $group->driver,
                'vehicle_type' => $group->vehicle_type ?? $group->driver?->vehicle_type ?? 'حافلة نقل زينة',
                'vehicle_plate' => $group->vehicle_plate ?? $group->driver?->vehicle_plate ?? '—',
                'vehicle_capacity' => (int) ($group->vehicle_capacity ?? $group->driver?->vehicle_capacity ?? 5),
                'passengers_count' => $workersCount,
                'notes' => $group->notes,
                'status' => $group->status,
                'bookings' => $batchBookings->map(function ($b) {
                    $custName = $b->customer?->full_name
                        ?? (trim(($b->customer?->first_name ?? '') . ' ' . ($b->customer?->last_name ?? '')) ?: null)
                        ?? $b->customer?->name
                        ?? '—';
                    $custPhone = $b->customer?->phone_number ?? $b->customer?->mobile ?? $b->customer?->phone ?? $b->address?->phone_number ?? '—';
                    return [
                        'id' => $b->id,
                        'booking_number' => $b->booking_ref ?? ('BKG-' . $b->id),
                        'customer_name' => $custName,
                        'customer_phone' => $custPhone,
                        'service_name' => $b->service?->name_ar ?? $b->service?->name_en ?? '—',
                        'service_address' => $b->address?->formatted_address ?? $b->address?->street ?? '—',
                        'start_time' => substr($b->start_time ?? '09:00', 0, 5),
                        'end_time' => $b->end_time ? substr($b->end_time, 0, 5) : '—',
                        'status' => $b->status,
                        'team_id' => $b->team_id,
                        'team_name' => $b->team ? ($b->team->name_ar ?? $b->team->name_en) : null,
                        'workers' => ($b->assignedWorkers->count() > 0 ? $b->assignedWorkers : $b->workers)->map(fn($w) => $w->name ?? ($w->first_name . ' ' . $w->last_name)),
                    ];
                }),
                'workers_list' => $workersList,
            ];
        }

        return [
            'date' => $date,
            'governorate' => $governorate,
            'area' => $areaName,
            'total_bookings' => $filteredBookings->count(),
            'total_workers' => array_sum(array_column($groupsResult, 'workers_count')),
            'groups' => $groupsResult,
        ];
    }

    /**
     * Update departure details for a transport group with Document V.17 rules:
     * 1. Departure time must be at least 60 minutes before shift start time.
     * 2. Driver & Vehicle collision prevention (no overlapping trips, >= 60 min gap).
     * 3. Vehicle passenger capacity validation (capacity >= required crew).
     */
    public function updateGroupDispatch(TransportGroup $group, array $data): TransportGroup
    {
        $departureTime = $data['departure_time'];
        $driverId = $data['driver_id'] ?? null;
        $driver = $driverId ? \App\Models\Driver::find($driverId) : null;
        $startTime = $group->start_time;

        $deptTs = strtotime('1970-01-01 ' . $departureTime);
        $startTs = strtotime('1970-01-01 ' . $startTime);

        // 1. Departure time must be at least 1 hour (60 minutes) before shift start time
        if ($deptTs > ($startTs - 3600)) {
            throw ValidationException::withMessages([
                'departure_time' => 'وقت انطلاق السائق يجب أن يكون قبل 60 دقيقة على الأقل من موعد بدء الحجز/الشفت! / Driver departure time must be at least 60 minutes before booking/shift start time.',
            ]);
        }

        // 2. Driver Collision Check: Same driver cannot be assigned to another dispatch at the same time (must be at least 1 hour apart)
        if (!empty($driverId)) {
            $conflictingGroup = TransportGroup::query()
                ->where('service_date', $group->service_date)
                ->where('driver_id', $driverId)
                ->where('id', '!=', $group->id)
                ->whereNotNull('departure_time')
                ->get()
                ->first(function ($otherGroup) use ($deptTs) {
                    $otherDeptTs = strtotime('1970-01-01 ' . $otherGroup->departure_time);
                    return abs($deptTs - $otherDeptTs) < 3600;
                });

            if ($conflictingGroup) {
                $otherFormattedTime = Carbon::parse($conflictingGroup->departure_time)->format('h:i A');
                $otherShiftTime = Carbon::parse($conflictingGroup->start_time)->format('h:i A');
                throw ValidationException::withMessages([
                    'driver_id' => "السائق مخصص بالفعل لرحلة شفت آخر ({$otherShiftTime}) في نفس توقيت الانطلاق ({$otherFormattedTime})! لا يمكن تعيين نفس السائق لنفس التوقيت، يجب أن تكون الرحلة التالية بعد ساعة على الأقل. / Driver is already assigned to another shift at ({$otherFormattedTime}). Dispatches must be at least 1 hour apart.",
                ]);
            }
        }

        // 3. Vehicle Collision Check (if plate is given or driver has vehicle plate)
        $vehiclePlate = $data['vehicle_plate'] ?? $driver?->vehicle_plate;
        if (!empty($vehiclePlate)) {
            $conflictingVehicleGroup = TransportGroup::query()
                ->where('service_date', $group->service_date)
                ->where('id', '!=', $group->id)
                ->where(function ($q) use ($vehiclePlate) {
                    $q->where('vehicle_plate', $vehiclePlate)
                      ->orWhereHas('driver', fn($dq) => $dq->where('vehicle_plate', $vehiclePlate));
                })
                ->whereNotNull('departure_time')
                ->get()
                ->first(function ($otherGroup) use ($deptTs) {
                    $otherDeptTs = strtotime('1970-01-01 ' . $otherGroup->departure_time);
                    return abs($deptTs - $otherDeptTs) < 3600;
                });

            if ($conflictingVehicleGroup) {
                $otherFormattedTime = Carbon::parse($conflictingVehicleGroup->departure_time)->format('h:i A');
                throw ValidationException::withMessages([
                    'driver_id' => "المركبة (لوحة: {$vehiclePlate}) مخصصة لرحلة أخرى في نفس التوقيت ({$otherFormattedTime})! لا يمكن حجز نفس المركبة لرحلتين متداخلتين. / Vehicle is already assigned to another dispatch at ({$otherFormattedTime}).",
                ]);
            }
        }

        // 4. Vehicle Capacity Check (Must fit the crew: e.g. 3 workers for specialized team)
        $vehicleCapacity = (int) ($data['vehicle_capacity'] ?? $driver?->vehicle_capacity ?? 5);
        $totalPassengers = 0;
        foreach ($group->bookings as $booking) {
            if ($booking->team_id || ($booking->service && $booking->service->isTeamBased())) {
                $totalPassengers += 3;
            } else {
                $assignedCount = $booking->assignedWorkers->count() ?: $booking->workers->count();
                $totalPassengers += max(1, $assignedCount);
            }
        }

        if ($vehicleCapacity < $totalPassengers) {
            throw ValidationException::withMessages([
                'driver_id' => "سعة المركبة ({$vehicleCapacity} ركاب) غير كافية لنقل أعضاء الفريق/العمالة ({$totalPassengers} عمال)! يرجى اختيار مركبة بسعة أكبر. / Vehicle capacity ({$vehicleCapacity} seats) is insufficient for the crew ({$totalPassengers} workers).",
            ]);
        }

        $vehicleType = $data['vehicle_type'] ?? $driver?->vehicle_type ?? 'حافلة نقل زينة';

        $group->update([
            'departure_point_id' => $data['departure_point_id'],
            'departure_time' => $departureTime,
            'driver_id' => $driverId,
            'vehicle_type' => $vehicleType,
            'vehicle_plate' => $vehiclePlate,
            'vehicle_capacity' => $vehicleCapacity,
            'notes' => $data['notes'] ?? null,
            'status' => 'ready_for_transport',
        ]);

        if (!empty($data['booking_ids'])) {
            $group->bookings()->sync($data['booking_ids']);
        }

        return $group->fresh(['departurePoint', 'driver', 'bookings']);
    }

    /**
     * Export transport group batch details to redesigned HTML .xls format (Document V.17).
     * Includes:
     * - Departure time (60 minutes before booking)
     * - Booking & shift service time
     * - Customer location & address
     * - Transported team & workers
     * - Vehicle type, plate & capacity
     */
    public function exportGroupExcel(int $groupId)
    {
        $group = TransportGroup::with([
            'departurePoint',
            'driver',
            'bookings.assignedWorkers',
            'bookings.workers',
            'bookings.address.serviceZone',
            'bookings.customer',
            'bookings.service',
            'bookings.team.workers'
        ])->findOrFail($groupId);

        $company = \App\Models\CompanyInfo::first();
        $companyName = $company?->name_ar ?? 'زينة للخدمات المنزلية';
        $currentDateStr = now()->locale('ar')->isoFormat('dddd، D MMMM YYYY - h:mm A');

        $driverName = $group->driver ? ($group->driver->name . ' (' . ($group->driver->mobile ?? $group->driver->phone ?? '—') . ')') : 'غير محدد';
        $depPoint = $group->departurePoint?->name ?? 'غير محددة';
        $depTime = $group->departure_time ? \Carbon\Carbon::parse($group->departure_time)->format('h:i A') : 'غير محدد';
        $shiftTime = \Carbon\Carbon::parse($group->start_time)->format('h:i A');

        $vehicleType = $group->vehicle_type ?? $group->driver?->vehicle_type ?? 'حافلة نقل مخصصة';
        $vehiclePlate = $group->vehicle_plate ?? $group->driver?->vehicle_plate ?? '—';
        $vehicleCapacity = (int) ($group->vehicle_capacity ?? $group->driver?->vehicle_capacity ?? 5);

        // Build list of transport items per booking
        $bookingItems = [];
        $totalWorkersCount = 0;

        foreach ($group->bookings as $b) {
            $custName = $b->customer?->full_name
                ?? (trim(($b->customer?->first_name ?? '') . ' ' . ($b->customer?->last_name ?? '')) ?: null)
                ?? $b->customer?->name
                ?? '—';
            $custPhone = $b->customer?->phone_number ?? $b->customer?->mobile ?? $b->customer?->phone ?? '—';
            $serviceName = $b->service?->name_ar ?? $b->service?->name_en ?? '—';
            $serviceAddr = $b->address?->formatted_address ?? $b->address?->street ?? '—';
            $visitTime = substr($b->start_time ?? '09:00', 0, 5) . ($b->end_time ? ' - ' . substr($b->end_time, 0, 5) : '');

            // Calculate departure time for this specific booking (60 minutes before booking start_time)
            $bookingStart = Carbon::parse('1970-01-01 ' . ($b->start_time ?? $group->start_time));
            $bookingDepTime = $bookingStart->copy()->subMinutes(60)->format('h:i A');

            // Team or individual workers
            $teamName = $b->team ? ($b->team->name_ar ?? $b->team->name_en) : null;
            $workers = $b->assignedWorkers->count() > 0 ? $b->assignedWorkers : $b->workers;

            $crewNames = $workers->map(function ($w) {
                return $w->name ?? trim(($w->first_name ?? '') . ' ' . ($w->last_name ?? ''));
            })->filter()->values()->all();

            $crewText = '';
            if ($teamName) {
                $crewText = "⭐ <b>{$teamName}</b>";
                if (!empty($crewNames)) {
                    $crewText .= ' (' . implode('، ', $crewNames) . ')';
                }
            } elseif (!empty($crewNames)) {
                $crewText = implode('، ', $crewNames);
            } else {
                $crewText = 'عمالة غير معينة';
            }

            $countThisBooking = count($crewNames) ?: ($b->number_of_workers ?: 1);
            $totalWorkersCount += $countThisBooking;

            $bookingItems[] = [
                'booking_ref' => $b->booking_ref ?? ('BKG-' . $b->id),
                'departure_time' => $bookingDepTime,
                'booking_time' => $visitTime,
                'service_name' => $serviceName,
                'team_and_crew' => $crewText,
                'crew_count' => $countThisBooking,
                'customer_name' => $custName,
                'customer_phone' => $custPhone,
                'service_address' => $serviceAddr,
                'status' => $b->status ?? 'Upcoming',
            ];
        }

        $filename = 'driver_dispatch_' . $group->service_date . '_' . preg_replace('/[^A-Za-z0-9]/', '', $group->start_time) . '.xls';

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($companyName, $currentDateStr, $group, $shiftTime, $depPoint, $depTime, $driverName, $vehicleType, $vehiclePlate, $vehicleCapacity, $totalWorkersCount, $bookingItems) {
            echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
            echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
            echo '<style>';
            echo 'body { font-family: "Segoe UI", Tahoma, Arial, sans-serif; direction: rtl; text-align: right; }';
            echo 'table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }';
            echo 'th, td { border: 1px solid #CBD5E1; padding: 10px 12px; text-align: center; vertical-align: middle; }';
            echo '.header-brand { background-color: #E91E63; color: #FFFFFF; font-size: 20px; font-weight: bold; text-align: center; padding: 16px; letter-spacing: 0.5px; }';
            echo '.header-meta { background-color: #FDF2F4; color: #881337; font-size: 13px; font-weight: bold; text-align: start; padding: 12px 18px; border: 1px solid #F43F5E; }';
            echo '.header-meta-grid { background-color: #FFF1F2; font-size: 13px; font-weight: bold; text-align: center; padding: 10px; border: 1px solid #FB7185; }';
            echo '.th-column { background-color: #BE185D; color: #FFFFFF; font-size: 13px; font-weight: bold; text-align: center; padding: 12px 8px; }';
            echo '.tr-even { background-color: #F8FAFC; }';
            echo '.tr-odd { background-color: #FFFFFF; }';
            echo '.badge-time { background-color: #FEF3C7; color: #92400E; font-weight: bold; padding: 4px 8px; border-radius: 4px; }';
            echo '.badge-departure { background-color: #D1FAE5; color: #065F46; font-weight: bold; padding: 4px 8px; border-radius: 4px; }';
            echo '</style></head>';
            echo '<body dir="rtl">';

            echo '<table>';
            echo '<tr><td colSpan="11" class="header-brand">👑 '.$companyName.' — Zeina Home Services<br><span style="font-size: 14px; font-weight: normal;">🚗 جدول رحلات السائقين والنقل الميداني (Driver Dispatch Schedule - V.17)</span></td></tr>';
            echo '<tr><td colSpan="11" class="header-meta">📅 <b>تاريخ الخدمة:</b> '.$group->service_date.' &nbsp;|&nbsp; 📍 <b>المنطقة:</b> '.$group->governorate.' - '.$group->area_name.' &nbsp;|&nbsp; 🚏 <b>نقطة الانطلاق:</b> '.$depPoint.' &nbsp;|&nbsp; 🕒 <b>تاريخ ووقت إصدار الملف:</b> '.$currentDateStr.'</td></tr>';
            echo '<tr><td colSpan="11" class="header-meta-grid">⏱️ <b>موعد التحرك المعتمد:</b> <span class="badge-departure">'.$depTime.' (قبل الحجز بساعة)</span> &nbsp;&nbsp;|&nbsp;&nbsp; ⏰ <b>موعد بدء الحجوزات:</b> <span class="badge-time">'.$shiftTime.'</span> &nbsp;&nbsp;|&nbsp;&nbsp; 👨‍✈️ <b>السائق:</b> '.$driverName.' &nbsp;&nbsp;|&nbsp;&nbsp; 🚘 <b>المركبة:</b> '.$vehicleType.' (لوحة: '.$vehiclePlate.') &nbsp;&nbsp;|&nbsp;&nbsp; 👥 <b>سعة المركبة والركاب:</b> '.$vehicleCapacity.' مقاعد (المطلوب: '.$totalWorkersCount.' عمال)</td></tr>';
            echo '<tr><td colSpan="11" style="height: 8px; border: none;"></td></tr>';
            echo '</table>';

            echo '<table>';
            echo '<thead><tr>';
            $cols = [
                '#',
                'رقم الحجز (Booking Ref)',
                'موعد التحرك (Departure - 60 Min)',
                'موعد الحجز (Booking Time)',
                'الخدمة المطلوبة (Service)',
                'الفريق المنقول / العمالة (Transported Team & Crew)',
                'اسم العميل (Customer Name)',
                'جوال العميل (Customer Phone)',
                'موقع العميل وعنوان الخدمة (Customer Location & Address)',
                'المركبة ولوحتها (Vehicle & Plate)',
                'السائق المسؤول (Assigned Driver)'
            ];
            foreach ($cols as $col) {
                echo '<th class="th-column">'.$col.'</th>';
            }
            echo '</tr></thead>';
            echo '<tbody>';

            foreach ($bookingItems as $idx => $item) {
                $rowClass = ($idx % 2 === 0) ? 'tr-even' : 'tr-odd';
                echo '<tr class="'.$rowClass.'">';
                echo '<td>'.($idx + 1).'</td>';
                echo '<td><b style="color: #BE185D;">'.htmlspecialchars($item['booking_ref']).'</b></td>';
                echo '<td><span class="badge-departure" dir="ltr">'.htmlspecialchars($item['departure_time']).'</span></td>';
                echo '<td><span class="badge-time" dir="ltr">'.htmlspecialchars($item['booking_time']).'</span></td>';
                echo '<td><b>'.htmlspecialchars($item['service_name']).'</b></td>';
                echo '<td style="text-align: start;">'.$item['team_and_crew'].'</td>';
                echo '<td><b>'.htmlspecialchars($item['customer_name']).'</b></td>';
                echo '<td><span dir="ltr">'.htmlspecialchars($item['customer_phone']).'</span></td>';
                echo '<td style="text-align: start;">'.htmlspecialchars($item['service_address']).'</td>';
                echo '<td>'.htmlspecialchars($vehicleType).' <br><small style="color: #64748B;">('.htmlspecialchars($vehiclePlate).')</small></td>';
                echo '<td><b>'.htmlspecialchars($driverName).'</b></td>';
                echo '</tr>';
            }

            if (empty($bookingItems)) {
                echo '<tr><td colSpan="11" style="padding: 24px; color: #94A3B8;">لا توجد رحلات أو حجوزات مسجلة في هذا الشفت.</td></tr>';
            }

            echo '</tbody></table></body></html>';
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get shift-based overview for a given date.
     * Groups active bookings by their start_time (shift).
     */
    public function getShiftsOverview(?string $date = null, array $filters = []): array
    {
        $date = $date ?: Carbon::today('Africa/Cairo')->toDateString();
        $query = Booking::query()
            ->with(['address.serviceZone', 'assignedWorkers', 'workers', 'service', 'team.workers'])
            ->whereDate('booking_date', $date)
            ->whereNotIn('status', ['cancelled', 'Cancelled', 'refunded', 'Refunded']);

        if (!empty($filters['governorate'])) {
            $gov = $filters['governorate'];
            $query->where(function ($q) use ($gov) {
                $q->whereHas('address', fn($aq) => $aq->where('governorate', $gov))
                  ->orWhereHas('address.serviceZone', fn($zq) => $zq->where('governorate', $gov));
            });
        }

        if (!empty($filters['start_time'])) {
            $query->where('start_time', 'like', $filters['start_time'] . '%');
        }

        $bookings = $query->get();

        // Group bookings by start_time (formatted H:i)
        $shiftsMap = [];

        foreach ($bookings as $b) {
            $timeStr = substr($b->start_time ?? '09:00', 0, 5);
            if (!isset($shiftsMap[$timeStr])) {
                $shiftsMap[$timeStr] = [
                    'start_time' => $timeStr,
                    'bookings' => collect(),
                    'workers_count' => 0,
                    'worker_ids' => [],
                ];
            }

            $shiftsMap[$timeStr]['bookings']->push($b);
            $workers = $b->assignedWorkers->count() > 0 ? $b->assignedWorkers : $b->workers;
            $count = $workers->count() ?: 1;
            $shiftsMap[$timeStr]['workers_count'] += $count;
            foreach ($workers as $w) {
                $shiftsMap[$timeStr]['worker_ids'][] = $w->id;
            }
        }

        // Fetch existing transport groups and assigned workers for this date
        $transportGroups = TransportGroup::with(['driver', 'departurePoint', 'groupWorkers'])
            ->where('service_date', $date)
            ->get();

        $shiftsResult = [];
        $totalBookingsAll = 0;
        $totalWorkersAll = 0;
        $totalAssignedAll = 0;

        // Sort shifts by start_time
        ksort($shiftsMap);

        foreach ($shiftsMap as $timeStr => $data) {
            $shiftGroups = $transportGroups->filter(function ($tg) use ($timeStr) {
                return substr($tg->start_time, 0, 5) === $timeStr;
            });

            // Count distinct workers assigned in transport_group_workers for this shift
            $assignedWorkerIds = [];
            $driverIds = [];
            foreach ($shiftGroups as $sg) {
                if ($sg->driver_id) {
                    $driverIds[$sg->driver_id] = true;
                }
                foreach ($sg->groupWorkers as $gw) {
                    $assignedWorkerIds[$gw->worker_id] = true;
                }
            }

            $totalWorkers = $data['workers_count'];
            $assignedCount = count($assignedWorkerIds);
            $unassignedCount = max(0, $totalWorkers - $assignedCount);
            $driversCount = count($driverIds);

            $prepStatus = 'not_prepared';
            if ($totalWorkers > 0 && $assignedCount >= $totalWorkers) {
                $prepStatus = 'ready_for_transport';
            } elseif ($assignedCount > 0) {
                $prepStatus = 'partially_prepared';
            }

            if (!empty($filters['status']) && $prepStatus !== $filters['status']) {
                continue;
            }

            $totalBookingsAll += $data['bookings']->count();
            $totalWorkersAll += $totalWorkers;
            $totalAssignedAll += $assignedCount;

            $shiftsResult[] = [
                'start_time' => $timeStr,
                'bookings_count' => $data['bookings']->count(),
                'workers_count' => $totalWorkers,
                'assigned_workers_count' => $assignedCount,
                'unassigned_workers_count' => $unassignedCount,
                'drivers_count' => $driversCount,
                'status' => $prepStatus,
            ];
        }

        return [
            'service_date' => $date,
            'summary' => [
                'total_shifts' => count($shiftsResult),
                'total_bookings' => $totalBookingsAll,
                'total_workers' => $totalWorkersAll,
                'assigned_workers' => $totalAssignedAll,
                'unassigned_workers' => max(0, $totalWorkersAll - $totalAssignedAll),
            ],
            'shifts' => $shiftsResult,
        ];
    }

    /**
     * Get detailed bookings and driver manifest for a specific date and shift start time.
     */
    public function getShiftDetails(string $date, string $startTime, array $filters = []): array
    {
        $startTimePrefix = substr($startTime, 0, 5);

        $query = Booking::query()
            ->with(['address.serviceZone', 'assignedWorkers', 'workers', 'service', 'customer', 'team.workers'])
            ->whereDate('booking_date', $date)
            ->where('start_time', 'like', $startTimePrefix . '%')
            ->whereNotIn('status', ['cancelled', 'Cancelled', 'refunded', 'Refunded']);

        if (!empty($filters['governorate'])) {
            $gov = $filters['governorate'];
            $query->where(function ($q) use ($gov) {
                $q->whereHas('address', fn($aq) => $aq->where('governorate', $gov))
                  ->orWhereHas('address.serviceZone', fn($zq) => $zq->where('governorate', $gov));
            });
        }

        if (!empty($filters['area'])) {
            $area = $filters['area'];
            $query->where(function ($q) use ($area) {
                $q->whereHas('address.serviceZone', fn($zq) => $zq->where('name', 'like', "%{$area}%"))
                  ->orWhereHas('address', fn($aq) => $aq->where('city', 'like', "%{$area}%"));
            });
        }

        $bookings = $query->orderBy('start_time')->get();

        // Fetch TransportGroups for this shift
        $groups = TransportGroup::with([
            'driver',
            'departurePoint',
            'groupWorkers.worker',
            'groupWorkers.booking.customer',
            'groupWorkers.booking.address',
            'groupWorkers.booking.service',
        ])
        ->where('service_date', $date)
        ->where('start_time', 'like', $startTimePrefix . '%')
        ->get();

        // Build worker assignment map: [booking_id . '_' . worker_id => ['group' => ..., 'gw' => ...]]
        $workerAssignmentMap = [];
        foreach ($groups as $g) {
            foreach ($g->groupWorkers as $gw) {
                $workerAssignmentMap[$gw->booking_id . '_' . $gw->worker_id] = [
                    'group' => $g,
                    'group_worker' => $gw,
                ];
            }
        }

        $formattedBookings = [];
        $totalWorkersCount = 0;
        $totalAssignedCount = 0;

        foreach ($bookings as $b) {
            $workers = $b->assignedWorkers->count() > 0 ? $b->assignedWorkers : $b->workers;
            $custName = $b->customer?->full_name
                ?? (trim(($b->customer?->first_name ?? '') . ' ' . ($b->customer?->last_name ?? '')) ?: null)
                ?? $b->customer?->name
                ?? '—';
            $custPhone = $b->customer?->phone_number ?? $b->customer?->mobile ?? $b->customer?->phone ?? $b->address?->phone_number ?? '—';
            $serviceAddr = $b->address?->formatted_address ?? $b->address?->street ?? '—';
            $zone = $b->address?->serviceZone;
            $govName = $zone?->governorate ?? $b->address?->governorate ?? 'القاهرة';
            $areaName = $zone?->name ?? $b->address?->city ?? '—';
            $teamName = $b->team ? ($b->team->name_ar ?? $b->team->name_en) : null;

            $bookingWorkers = [];

            if ($workers->count() > 0) {
                foreach ($workers as $w) {
                    $totalWorkersCount++;
                    $key = $b->id . '_' . $w->id;
                    $isAssigned = isset($workerAssignmentMap[$key]);
                    if ($isAssigned) {
                        $totalAssignedCount++;
                        $assignment = $workerAssignmentMap[$key];
                        $g = $assignment['group'];
                        $gw = $assignment['group_worker'];

                        $assignedDriver = $g->driver ? [
                            'id' => $g->driver->id,
                            'name' => $g->driver->name,
                            'mobile' => $g->driver->mobile,
                            'vehicle_type' => $g->vehicle_type ?? $g->driver->vehicle_type ?? 'حافلة نقل زينة',
                            'vehicle_plate' => $g->vehicle_plate ?? $g->driver->vehicle_plate ?? '—',
                            'vehicle_capacity' => (int) ($g->vehicle_capacity ?? $g->driver->vehicle_capacity ?? 5),
                        ] : null;

                        $departurePoint = $g->departurePoint ? [
                            'id' => $g->departurePoint->id,
                            'name' => $g->departurePoint->name,
                            'address' => $g->departurePoint->address,
                        ] : null;

                        $bookingWorkers[] = [
                            'worker_id' => $w->id,
                            'name' => $w->name ?? trim(($w->first_name ?? '') . ' ' . ($w->last_name ?? '')),
                            'phone' => $w->phone_number ?? $w->mobile ?? $w->phone ?? '—',
                            'is_assigned' => true,
                            'transport_group_id' => $g->id,
                            'dropoff_sequence' => $gw->dropoff_sequence,
                            'assigned_driver' => $assignedDriver,
                            'departure_point' => $departurePoint,
                            'departure_time' => $g->departure_time,
                        ];
                    } else {
                        $bookingWorkers[] = [
                            'worker_id' => $w->id,
                            'name' => $w->name ?? trim(($w->first_name ?? '') . ' ' . ($w->last_name ?? '')),
                            'phone' => $w->phone_number ?? $w->mobile ?? $w->phone ?? '—',
                            'is_assigned' => false,
                            'transport_group_id' => null,
                            'dropoff_sequence' => 0,
                            'assigned_driver' => null,
                            'departure_point' => null,
                            'departure_time' => null,
                        ];
                    }
                }
            } else {
                $totalWorkersCount++;
            }

            $formattedBookings[] = [
                'id' => $b->id,
                'booking_ref' => $b->booking_ref ?? ('BKG-' . $b->id),
                'service_name' => $b->service?->name_ar ?? $b->service?->name_en ?? '—',
                'customer_name' => $custName,
                'customer_phone' => $custPhone,
                'governorate' => $govName,
                'area' => $areaName,
                'service_address' => $serviceAddr,
                'start_time' => substr($b->start_time ?? '09:00', 0, 5),
                'end_time' => $b->end_time ? substr($b->end_time, 0, 5) : '—',
                'status' => $b->status,
                'team_id' => $b->team_id,
                'team_name' => $teamName,
                'is_service_started' => in_array($b->status, ['inService', 'completed', 'cancelled'])
                    || Carbon::now('Africa/Cairo')->gte(Carbon::parse("{$b->booking_date} {$b->start_time}", 'Africa/Cairo')),
                'is_locked' => in_array($b->status, ['inService', 'completed', 'cancelled'])
                    || Carbon::now('Africa/Cairo')->gte(Carbon::parse("{$b->booking_date} {$b->start_time}", 'Africa/Cairo')),
                'workers' => $bookingWorkers,
            ];
        }

        // Build Driver Manifests (table below)
        $driverManifests = [];
        foreach ($groups as $g) {
            if (!$g->driver) {
                continue;
            }

            $passengers = [];
            foreach ($g->groupWorkers as $gw) {
                $w = $gw->worker;
                $b = $gw->booking;
                if (!$w || !$b) continue;

                $cName = $b->customer?->full_name
                    ?? (trim(($b->customer?->first_name ?? '') . ' ' . ($b->customer?->last_name ?? '')) ?: null)
                    ?? $b->customer?->name
                    ?? '—';
                $cPhone = $b->customer?->phone_number ?? $b->customer?->mobile ?? $b->customer?->phone ?? $b->address?->phone_number ?? '—';
                $sAddr = $b->address?->formatted_address ?? $b->address?->street ?? '—';

                $pBookingStarted = in_array($b->status, ['inService', 'completed', 'cancelled'])
                    || Carbon::now('Africa/Cairo')->gte(Carbon::parse("{$b->booking_date} {$b->start_time}", 'Africa/Cairo'));

                $passengers[] = [
                    'group_worker_id' => $gw->id,
                    'worker_id' => $w->id,
                    'worker_name' => $w->name ?? trim(($w->first_name ?? '') . ' ' . ($w->last_name ?? '')),
                    'worker_phone' => $w->phone_number ?? $w->mobile ?? $w->phone ?? '—',
                    'booking_id' => $b->id,
                    'booking_ref' => $b->booking_ref ?? ('BKG-' . $b->id),
                    'customer_name' => $cName,
                    'customer_phone' => $cPhone,
                    'destination_address' => $sAddr,
                    'service_name' => $b->service?->name_ar ?? $b->service?->name_en ?? '—',
                    'dropoff_sequence' => $gw->dropoff_sequence,
                    'is_service_started' => $pBookingStarted,
                    'is_locked' => $pBookingStarted,
                ];
            }

            $cap = (int) ($g->vehicle_capacity ?? $g->driver->vehicle_capacity ?? 5);
            $pCount = count($passengers);

            $driverManifests[] = [
                'transport_group_id' => $g->id,
                'driver_id' => $g->driver->id,
                'driver_name' => $g->driver->name,
                'driver_phone' => $g->driver->mobile ?? $g->driver->phone ?? '—',
                'vehicle_type' => $g->vehicle_type ?? $g->driver->vehicle_type ?? 'حافلة نقل زينة',
                'vehicle_plate' => $g->vehicle_plate ?? $g->driver->vehicle_plate ?? '—',
                'vehicle_capacity' => $cap,
                'assigned_passengers_count' => $pCount,
                'remaining_capacity' => max(0, $cap - $pCount),
                'is_full' => $pCount >= $cap,
                'departure_point' => $g->departurePoint ? [
                    'id' => $g->departurePoint->id,
                    'name' => $g->departurePoint->name,
                ] : null,
                'departure_time' => $g->departure_time,
                'notes' => $g->notes,
                'status' => $g->status,
                'passengers' => $passengers,
            ];
        }

        $shiftDateCarbon = Carbon::parse("{$date} {$startTimePrefix}:00", 'Africa/Cairo');
        $isOverallShiftStarted = Carbon::now('Africa/Cairo')->gte($shiftDateCarbon);

        return [
            'service_date' => $date,
            'start_time' => $startTimePrefix,
            'is_shift_started' => $isOverallShiftStarted,
            'is_locked' => $isOverallShiftStarted,
            'total_bookings' => count($formattedBookings),
            'total_workers' => $totalWorkersCount,
            'assigned_workers' => $totalAssignedCount,
            'unassigned_workers' => max(0, $totalWorkersCount - $totalAssignedCount),
            'bookings' => $formattedBookings,
            'driver_manifests' => $driverManifests,
        ];
    }

    /**
     * Assign a single worker to a specific driver / transport group.
     */
    public function assignWorkerToDriver(array $data): array
    {
        $date = $data['service_date'];
        $startTime = substr($data['start_time'], 0, 5);
        $departureTime = $data['departure_time'];
        $driverId = $data['driver_id'];
        $workerId = $data['worker_id'];
        $bookingId = $data['booking_id'];
        $depPointId = $data['departure_point_id'] ?? null;
        $notes = $data['notes'] ?? null;

        $driver = Driver::findOrFail($driverId);
        $booking = Booking::findOrFail($bookingId);

        // Guard: If worker already has an assigned driver and service has started, cannot change or reassign
        $isBookingStarted = in_array($booking->status, ['inService', 'completed', 'cancelled'])
            || Carbon::now('Africa/Cairo')->gte(Carbon::parse("{$booking->booking_date} {$booking->start_time}", 'Africa/Cairo'));

        $isAlreadyAssigned = TransportGroupWorker::where('booking_id', $bookingId)
            ->where('worker_id', $workerId)
            ->exists();

        if ($isBookingStarted && $isAlreadyAssigned) {
            throw ValidationException::withMessages([
                'booking_id' => 'لا يمكن تغيير السائق بعد بدء موعد الخدمة لأن العاملة معينة لسائق بالفعل! / Cannot change driver after service has started.',
            ]);
        }

        // 1. Departure time check: must be at least 1 hour (60 minutes) before shift start time for future shifts
        $deptTs = strtotime('1970-01-01 ' . $departureTime);
        $startTs = strtotime('1970-01-01 ' . $startTime . ':00');

        if (!$isBookingStarted && $deptTs > ($startTs - 3600)) {
            throw ValidationException::withMessages([
                'departure_time' => 'وقت انطلاق السائق يجب أن يكون قبل 60 دقيقة على الأقل من موعد بدء الشفت! / Driver departure time must be at least 60 minutes before shift start time.',
            ]);
        }

        // 2. Driver collision check: driver cannot be on another active transport group at conflicting departure time (< 60 mins)
        $conflictingGroup = TransportGroup::query()
            ->where('service_date', $date)
            ->where('driver_id', $driverId)
            ->whereNotNull('departure_time')
            ->where('start_time', '!=', $startTime)
            ->get()
            ->first(function ($otherGroup) use ($deptTs) {
                $otherDeptTs = strtotime('1970-01-01 ' . $otherGroup->departure_time);
                return abs($deptTs - $otherDeptTs) < 3600;
            });

        if ($conflictingGroup) {
            $otherShift = Carbon::parse($conflictingGroup->start_time)->format('h:i A');
            $otherDep = Carbon::parse($conflictingGroup->departure_time)->format('h:i A');
            throw ValidationException::withMessages([
                'driver_id' => "السائق معين لرحلة أخرى في شفت ({$otherShift}) وموعد تحرك ({$otherDep}). يجب أن يكون الفارق ساعة على الأقل.",
            ]);
        }

        return DB::transaction(function () use ($date, $startTime, $departureTime, $driver, $driverId, $workerId, $bookingId, $booking, $depPointId, $notes) {
            // Find or create TransportGroup for this driver and shift
            $group = TransportGroup::firstOrCreate([
                'service_date' => $date,
                'start_time' => $startTime,
                'driver_id' => $driverId,
            ], [
                'departure_point_id' => $depPointId,
                'departure_time' => $departureTime,
                'vehicle_type' => $driver->vehicle_type ?? 'حافلة نقل زينة',
                'vehicle_plate' => $driver->vehicle_plate ?? '—',
                'vehicle_capacity' => (int) ($driver->vehicle_capacity ?? 5),
                'notes' => $notes,
                'status' => 'not_prepared',
            ]);

            // Update departure details if provided
            $group->update([
                'departure_point_id' => $depPointId ?: $group->departure_point_id,
                'departure_time' => $departureTime ?: $group->departure_time,
                'vehicle_type' => $group->vehicle_type ?: ($driver->vehicle_type ?? 'حافلة نقل زينة'),
                'vehicle_plate' => $group->vehicle_plate ?: ($driver->vehicle_plate ?? '—'),
                'vehicle_capacity' => $group->vehicle_capacity ?: ((int) ($driver->vehicle_capacity ?? 5)),
            ]);

            // Check vehicle capacity
            $currentPassengersCount = TransportGroupWorker::where('transport_group_id', $group->id)
                ->where('worker_id', '!=', $workerId)
                ->count();

            $cap = (int) ($group->vehicle_capacity ?? $driver->vehicle_capacity ?? 5);
            if ($currentPassengersCount >= $cap) {
                throw ValidationException::withMessages([
                    'driver_id' => "مركبة السائق ممتلئة بالكامل ({$cap} مقاعد). يرجى اختيار سائق آخر أو زيادة سعة المركبة.",
                ]);
            }

            // Remove worker from any other transport group on this date and shift
            $existingGroupsForShift = TransportGroup::where('service_date', $date)
                ->where('start_time', $startTime)
                ->pluck('id');

            TransportGroupWorker::whereIn('transport_group_id', $existingGroupsForShift)
                ->where('worker_id', $workerId)
                ->delete();

            // Link worker to this TransportGroup
            TransportGroupWorker::create([
                'transport_group_id' => $group->id,
                'booking_id' => $bookingId,
                'worker_id' => $workerId,
                'dropoff_sequence' => $currentPassengersCount + 1,
                'status' => 'assigned',
            ]);

            // Ensure booking is in transport_group_bookings
            $group->bookings()->syncWithoutDetaching([$bookingId]);

            // Update status
            $group->update(['status' => 'ready_for_transport']);

            return [
                'transport_group_id' => $group->id,
                'worker_id' => $workerId,
                'booking_id' => $bookingId,
                'driver_id' => $driverId,
                'driver_name' => $driver->name,
                'departure_time' => $group->departure_time,
            ];
        });
    }

    /**
     * Unassign a worker from a transport group.
     */
    public function unassignWorker(int $bookingId, int $workerId, ?int $transportGroupId = null): bool
    {
        $booking = Booking::findOrFail($bookingId);

        // Guard: Cannot unassign if service has already started
        $isBookingStarted = in_array($booking->status, ['inService', 'completed', 'cancelled'])
            || Carbon::now('Africa/Cairo')->gte(Carbon::parse("{$booking->booking_date} {$booking->start_time}", 'Africa/Cairo'));

        if ($isBookingStarted) {
            throw ValidationException::withMessages([
                'booking_id' => 'لا يمكن فك ارتباط السائق بعد بدء موعد الخدمة! / Cannot unassign driver after service has started.',
            ]);
        }

        $query = TransportGroupWorker::where('booking_id', $bookingId)
            ->where('worker_id', $workerId);

        if ($transportGroupId) {
            $query->where('transport_group_id', $transportGroupId);
        }

        $records = $query->get();
        foreach ($records as $rec) {
            $groupId = $rec->transport_group_id;
            $rec->delete();

            // Check if group is now empty
            $remaining = TransportGroupWorker::where('transport_group_id', $groupId)->count();
            if ($remaining === 0) {
                $g = TransportGroup::find($groupId);
                if ($g) {
                    $g->update(['status' => 'not_prepared']);
                }
            }
        }

        return true;
    }

    /**
     * Export manifest for a specific driver's trip.
     */
    public function exportDriverManifestExcel(int $transportGroupId)
    {
        $group = TransportGroup::with([
            'departurePoint',
            'driver',
            'groupWorkers.worker',
            'groupWorkers.booking.customer',
            'groupWorkers.booking.address',
            'groupWorkers.booking.service',
        ])->findOrFail($transportGroupId);

        $company = CompanyInfo::first();
        $companyName = $company?->name_ar ?? 'زينة للخدمات المنزلية';
        $currentDateStr = now()->locale('ar')->isoFormat('dddd، D MMMM YYYY - h:mm A');

        $driverName = $group->driver ? ($group->driver->name . ' (' . ($group->driver->mobile ?? '—') . ')') : 'غير محدد';
        $depPoint = $group->departurePoint?->name ?? 'غير محددة';
        $depTime = $group->departure_time ? Carbon::parse($group->departure_time)->format('h:i A') : 'غير محدد';
        $shiftTime = Carbon::parse($group->start_time)->format('h:i A');

        $vehicleType = $group->vehicle_type ?? $group->driver?->vehicle_type ?? 'حافلة نقل زينة';
        $vehiclePlate = $group->vehicle_plate ?? $group->driver?->vehicle_plate ?? '—';
        $vehicleCapacity = (int) ($group->vehicle_capacity ?? $group->driver?->vehicle_capacity ?? 5);

        $passengers = [];
        foreach ($group->groupWorkers as $gw) {
            $w = $gw->worker;
            $b = $gw->booking;
            if (!$w || !$b) continue;

            $cName = $b->customer?->full_name
                ?? (trim(($b->customer?->first_name ?? '') . ' ' . ($b->customer?->last_name ?? '')) ?: null)
                ?? $b->customer?->name
                ?? '—';
            $cPhone = $b->customer?->phone_number ?? $b->customer?->mobile ?? $b->customer?->phone ?? $b->address?->phone_number ?? '—';
            $sAddr = $b->address?->formatted_address ?? $b->address?->street ?? '—';

            $passengers[] = [
                'worker_name' => $w->name ?? trim(($w->first_name ?? '') . ' ' . ($w->last_name ?? '')),
                'worker_phone' => $w->phone_number ?? $w->mobile ?? $w->phone ?? '—',
                'booking_ref' => $b->booking_ref ?? ('BKG-' . $b->id),
                'service_name' => $b->service?->name_ar ?? $b->service?->name_en ?? '—',
                'customer_name' => $cName,
                'customer_phone' => $cPhone,
                'address' => $sAddr,
            ];
        }

        $filename = 'driver_manifest_' . preg_replace('/[^A-Za-z0-9]/', '', $group->driver?->name ?? 'driver') . '_' . $group->service_date . '.xls';

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($companyName, $currentDateStr, $group, $shiftTime, $depPoint, $depTime, $driverName, $vehicleType, $vehiclePlate, $vehicleCapacity, $passengers) {
            echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
            echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
            echo '<style>';
            echo 'body { font-family: "Segoe UI", Tahoma, Arial, sans-serif; direction: rtl; text-align: right; }';
            echo 'table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }';
            echo 'th, td { border: 1px solid #CBD5E1; padding: 10px 12px; text-align: center; vertical-align: middle; }';
            echo '.header-brand { background-color: #E91E63; color: #FFFFFF; font-size: 20px; font-weight: bold; text-align: center; padding: 16px; letter-spacing: 0.5px; }';
            echo '.header-meta { background-color: #FDF2F4; color: #881337; font-size: 13px; font-weight: bold; text-align: start; padding: 12px 18px; border: 1px solid #F43F5E; }';
            echo '.header-meta-grid { background-color: #FFF1F2; font-size: 13px; font-weight: bold; text-align: center; padding: 10px; border: 1px solid #FB7185; }';
            echo '.th-column { background-color: #BE185D; color: #FFFFFF; font-size: 13px; font-weight: bold; text-align: center; padding: 12px 8px; }';
            echo '.tr-even { background-color: #F8FAFC; }';
            echo '.tr-odd { background-color: #FFFFFF; }';
            echo '.badge-time { background-color: #FEF3C7; color: #92400E; font-weight: bold; padding: 4px 8px; border-radius: 4px; }';
            echo '.badge-departure { background-color: #D1FAE5; color: #065F46; font-weight: bold; padding: 4px 8px; border-radius: 4px; }';
            echo '</style></head>';
            echo '<body dir="rtl">';

            echo '<table>';
            echo '<tr><td colSpan="9" class="header-brand">👑 '.$companyName.' — Zeina Home Services<br><span style="font-size: 14px; font-weight: normal;">🚗 كشف رحلة السائق الميداني (Driver Passenger Manifest)</span></td></tr>';
            echo '<tr><td colSpan="9" class="header-meta">📅 <b>تاريخ الخدمة:</b> '.$group->service_date.' &nbsp;|&nbsp; ⏰ <b>شفت الخدمة:</b> '.$shiftTime.' &nbsp;|&nbsp; 🚏 <b>نقطة الانطلاق:</b> '.$depPoint.' &nbsp;|&nbsp; 🕒 <b>وقت الإصدار:</b> '.$currentDateStr.'</td></tr>';
            echo '<tr><td colSpan="9" class="header-meta-grid">⏱️ <b>موعد التحرك:</b> <span class="badge-departure">'.$depTime.'</span> &nbsp;&nbsp;|&nbsp;&nbsp; 👨‍✈️ <b>السائق:</b> '.$driverName.' &nbsp;&nbsp;|&nbsp;&nbsp; 🚘 <b>المركبة:</b> '.$vehicleType.' (لوحة: '.$vehiclePlate.') &nbsp;&nbsp;|&nbsp;&nbsp; 👥 <b>الحمولة:</b> '.count($passengers).' / '.$vehicleCapacity.' مقاعد</td></tr>';
            echo '<tr><td colSpan="9" style="height: 8px; border: none;"></td></tr>';
            echo '</table>';

            echo '<table>';
            echo '<thead><tr>';
            $cols = [
                '#',
                'اسم العاملة (Worker)',
                'جوال العاملة (Worker Phone)',
                'رقم الحجز (Booking Ref)',
                'الخدمة المطلوبة (Service)',
                'اسم العميل (Customer Name)',
                'جوال العميل (Customer Phone)',
                'عنوان التوصيل (Dropoff Address)',
                'حالة التوصيل (Status)'
            ];
            foreach ($cols as $col) {
                echo '<th class="th-column">'.$col.'</th>';
            }
            echo '</tr></thead>';
            echo '<tbody>';

            foreach ($passengers as $idx => $p) {
                $rowClass = ($idx % 2 === 0) ? 'tr-even' : 'tr-odd';
                echo '<tr class="'.$rowClass.'">';
                echo '<td>'.($idx + 1).'</td>';
                echo '<td><b>'.htmlspecialchars($p['worker_name']).'</b></td>';
                echo '<td><span dir="ltr">'.htmlspecialchars($p['worker_phone']).'</span></td>';
                echo '<td><b style="color: #BE185D;">'.htmlspecialchars($p['booking_ref']).'</b></td>';
                echo '<td>'.htmlspecialchars($p['service_name']).'</td>';
                echo '<td><b>'.htmlspecialchars($p['customer_name']).'</b></td>';
                echo '<td><span dir="ltr">'.htmlspecialchars($p['customer_phone']).'</span></td>';
                echo '<td style="text-align: start;">'.htmlspecialchars($p['address']).'</td>';
                echo '<td><span class="badge-departure">تم التعيين</span></td>';
                echo '</tr>';
            }

            if (empty($passengers)) {
                echo '<tr><td colSpan="9" style="padding: 24px; color: #94A3B8;">لا توجد عاملات مسندة لهذا السائق حتى الآن.</td></tr>';
            }

            echo '</tbody></table></body></html>';
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export all drivers manifests for a specific shift.
     */
    public function exportShiftManifestExcel(string $date, string $startTime)
    {
        $startTimePrefix = substr($startTime, 0, 5);

        $groups = TransportGroup::with([
            'departurePoint',
            'driver',
            'groupWorkers.worker',
            'groupWorkers.booking.customer',
            'groupWorkers.booking.address',
            'groupWorkers.booking.service',
        ])
        ->where('service_date', $date)
        ->where('start_time', 'like', $startTimePrefix . '%')
        ->get();

        $company = CompanyInfo::first();
        $companyName = $company?->name_ar ?? 'زينة للخدمات المنزلية';
        $currentDateStr = now()->locale('ar')->isoFormat('dddd، D MMMM YYYY - h:mm A');
        $shiftTime = Carbon::parse($startTimePrefix)->format('h:i A');

        $filename = 'shift_manifest_' . $date . '_' . preg_replace('/[^A-Za-z0-9]/', '', $startTimePrefix) . '.xls';

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($companyName, $currentDateStr, $date, $shiftTime, $groups) {
            echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
            echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
            echo '<style>';
            echo 'body { font-family: "Segoe UI", Tahoma, Arial, sans-serif; direction: rtl; text-align: right; }';
            echo 'table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }';
            echo 'th, td { border: 1px solid #CBD5E1; padding: 10px 12px; text-align: center; vertical-align: middle; }';
            echo '.header-brand { background-color: #E91E63; color: #FFFFFF; font-size: 20px; font-weight: bold; text-align: center; padding: 16px; letter-spacing: 0.5px; }';
            echo '.header-meta { background-color: #FDF2F4; color: #881337; font-size: 13px; font-weight: bold; text-align: start; padding: 12px 18px; border: 1px solid #F43F5E; }';
            echo '.th-column { background-color: #BE185D; color: #FFFFFF; font-size: 13px; font-weight: bold; text-align: center; padding: 12px 8px; }';
            echo '.th-sub { background-color: #FCE7F3; color: #831843; font-size: 14px; font-weight: bold; text-align: start; padding: 10px 14px; }';
            echo '.tr-even { background-color: #F8FAFC; }';
            echo '.tr-odd { background-color: #FFFFFF; }';
            echo '.badge-time { background-color: #FEF3C7; color: #92400E; font-weight: bold; padding: 4px 8px; border-radius: 4px; }';
            echo '.badge-departure { background-color: #D1FAE5; color: #065F46; font-weight: bold; padding: 4px 8px; border-radius: 4px; }';
            echo '</style></head>';
            echo '<body dir="rtl">';

            echo '<table>';
            echo '<tr><td colSpan="10" class="header-brand">👑 '.$companyName.' — Zeina Home Services<br><span style="font-size: 14px; font-weight: normal;">📋 كشف الترحيل الشامل للشفت (Comprehensive Shift Transport Manifest)</span></td></tr>';
            echo '<tr><td colSpan="10" class="header-meta">📅 <b>تاريخ الخدمة:</b> '.$date.' &nbsp;|&nbsp; ⏰ <b>شفت الخدمة:</b> '.$shiftTime.' &nbsp;|&nbsp; 👥 <b>عدد السائقين النشطين:</b> '.$groups->count().' &nbsp;|&nbsp; 🕒 <b>وقت الإصدار:</b> '.$currentDateStr.'</td></tr>';
            echo '</table>';

            foreach ($groups as $group) {
                $driverName = $group->driver ? ($group->driver->name . ' (' . ($group->driver->mobile ?? '—') . ')') : 'غير محدد';
                $depPoint = $group->departurePoint?->name ?? 'غير محددة';
                $depTime = $group->departure_time ? Carbon::parse($group->departure_time)->format('h:i A') : 'غير محدد';
                $vehicleType = $group->vehicle_type ?? $group->driver?->vehicle_type ?? 'حافلة نقل زينة';
                $vehiclePlate = $group->vehicle_plate ?? $group->driver?->vehicle_plate ?? '—';
                $vehicleCapacity = (int) ($group->vehicle_capacity ?? $group->driver?->vehicle_capacity ?? 5);

                $passengers = [];
                foreach ($group->groupWorkers as $gw) {
                    $w = $gw->worker;
                    $b = $gw->booking;
                    if (!$w || !$b) continue;

                    $cName = $b->customer?->full_name
                        ?? (trim(($b->customer?->first_name ?? '') . ' ' . ($b->customer?->last_name ?? '')) ?: null)
                        ?? $b->customer?->name
                        ?? '—';
                    $cPhone = $b->customer?->phone_number ?? $b->customer?->mobile ?? $b->customer?->phone ?? $b->address?->phone_number ?? '—';
                    $sAddr = $b->address?->formatted_address ?? $b->address?->street ?? '—';

                    $passengers[] = [
                        'worker_name' => $w->name ?? trim(($w->first_name ?? '') . ' ' . ($w->last_name ?? '')),
                        'worker_phone' => $w->phone_number ?? $w->mobile ?? $w->phone ?? '—',
                        'booking_ref' => $b->booking_ref ?? ('BKG-' . $b->id),
                        'service_name' => $b->service?->name_ar ?? $b->service?->name_en ?? '—',
                        'customer_name' => $cName,
                        'customer_phone' => $cPhone,
                        'address' => $sAddr,
                    ];
                }

                echo '<table>';
                echo '<tr><td colSpan="9" class="th-sub">👨‍✈️ <b>السائق:</b> '.$driverName.' &nbsp;|&nbsp; 🚘 <b>المركبة:</b> '.$vehicleType.' ('.$vehiclePlate.') &nbsp;|&nbsp; 🚏 <b>نقطة الانطلاق:</b> '.$depPoint.' &nbsp;|&nbsp; ⏱️ <b>موعد التحرك:</b> '.$depTime.' &nbsp;|&nbsp; 👥 <b>الحمولة:</b> '.count($passengers).' / '.$vehicleCapacity.'</td></tr>';
                echo '<thead><tr>';
                $cols = [
                    '#',
                    'اسم العاملة (Worker)',
                    'جوال العاملة',
                    'رقم الحجز',
                    'الخدمة',
                    'اسم العميل',
                    'جوال العميل',
                    'عنوان التوصيل',
                    'حالة التوصيل'
                ];
                foreach ($cols as $col) {
                    echo '<th class="th-column">'.$col.'</th>';
                }
                echo '</tr></thead>';
                echo '<tbody>';

                foreach ($passengers as $idx => $p) {
                    $rowClass = ($idx % 2 === 0) ? 'tr-even' : 'tr-odd';
                    echo '<tr class="'.$rowClass.'">';
                    echo '<td>'.($idx + 1).'</td>';
                    echo '<td><b>'.htmlspecialchars($p['worker_name']).'</b></td>';
                    echo '<td><span dir="ltr">'.htmlspecialchars($p['worker_phone']).'</span></td>';
                    echo '<td><b style="color: #BE185D;">'.htmlspecialchars($p['booking_ref']).'</b></td>';
                    echo '<td>'.htmlspecialchars($p['service_name']).'</td>';
                    echo '<td><b>'.htmlspecialchars($p['customer_name']).'</b></td>';
                    echo '<td><span dir="ltr">'.htmlspecialchars($p['customer_phone']).'</span></td>';
                    echo '<td style="text-align: start;">'.htmlspecialchars($p['address']).'</td>';
                    echo '<td><span class="badge-departure">تم التعيين</span></td>';
                    echo '</tr>';
                }

                if (empty($passengers)) {
                    echo '<tr><td colSpan="9" style="padding: 16px; color: #94A3B8;">لا توجد عاملات معينات لهذا السائق في هذا الشفت.</td></tr>';
                }

                echo '</tbody></table>';
            }

            if ($groups->isEmpty()) {
                echo '<p style="text-align: center; color: #64748B; padding: 24px;">لا توجد رحلات أو سائقين معينين في هذا الشفت بعد.</p>';
            }

            echo '</body></html>';
        };

        return response()->stream($callback, 200, $headers);
    }
}
