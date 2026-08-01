<?php

namespace App\Service\Admin;

use App\Models\Booking;
use App\Models\TransportGroup;
use App\Models\ServiceZone;
use Illuminate\Support\Carbon;
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
            // Find or initialize transport group DB record
            $group = TransportGroup::firstOrCreate([
                'service_date' => $date,
                'governorate' => $governorate,
                'area_name' => $areaName,
                'start_time' => $startTime,
            ], [
                'status' => 'not_prepared',
            ]);

            $group->load(['departurePoint', 'driver']);

            $workersCount = 0;
            $workersList = [];

            foreach ($batchBookings as $b) {
                $workers = $b->assignedWorkers->count() > 0 ? $b->assignedWorkers : $b->workers;
                $serviceAddr = $b->address?->formatted_address ?? $b->address?->street ?? '—';
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
                        ];
                    }
                } else {
                    $workersCount++;
                }
            }

            // Sync booking pivot
            $group->bookings()->syncWithoutDetaching($batchBookings->pluck('id'));

            $groupsResult[] = [
                'group_id' => $group->id,
                'start_time' => $startTime,
                'bookings_count' => $batchBookings->count(),
                'workers_count' => $workersCount,
                'departure_point' => $group->departurePoint,
                'departure_time' => $group->departure_time,
                'driver' => $group->driver,
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
     * Update departure details for a transport group.
     */
    public function updateGroupDispatch(TransportGroup $group, array $data): TransportGroup
    {
        $departureTime = $data['departure_time'];
        $driverId = $data['driver_id'] ?? null;
        $startTime = $group->start_time;

        $deptTs = strtotime('1970-01-01 ' . $departureTime);
        $startTs = strtotime('1970-01-01 ' . $startTime);

        // 1. Departure time must be at least 1 hour before shift start time
        if ($deptTs > ($startTs - 3600)) {
            throw ValidationException::withMessages([
                'departure_time' => 'وقت الانطلاق يجب أن يكون قبل ساعة على الأقل من بداية الشفت! / Departure time must be at least 1 hour before shift start time.',
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
                    // Less than 1 hour difference (3600 seconds)
                    return abs($deptTs - $otherDeptTs) < 3600;
                });

            if ($conflictingGroup) {
                $otherFormattedTime = Carbon::parse($conflictingGroup->departure_time)->format('h:i A');
                $otherShiftTime = Carbon::parse($conflictingGroup->start_time)->format('h:i A');
                throw ValidationException::withMessages([
                    'driver_id' => "السائق مخصص بالفعل لرحلة شفت آخر ({$otherShiftTime}) في نفس توقيت الانطلاق ({$otherFormattedTime})! لا يمكن تعيين نفس السائق لنفس التوقيت، يجب أن تكون الرحلة التالية بعد ساعة على الأقل.",
                ]);
            }
        }

        $group->update([
            'departure_point_id' => $data['departure_point_id'],
            'departure_time' => $departureTime,
            'driver_id' => $driverId,
            'notes' => $data['notes'] ?? null,
            'status' => 'ready_for_transport',
        ]);

        if (!empty($data['booking_ids'])) {
            $group->bookings()->sync($data['booking_ids']);
        }

        return $group->fresh(['departurePoint', 'driver', 'bookings']);
    }

    /**
     * Export transport group batch details to styled HTML .xls format (same as booking export).
     */
    public function exportGroupExcel(int $groupId)
    {
        $group = TransportGroup::with(['departurePoint', 'driver', 'bookings.assignedWorkers', 'bookings.workers', 'bookings.address.serviceZone', 'bookings.customer', 'bookings.service'])->findOrFail($groupId);

        $company = \App\Models\CompanyInfo::first();
        $companyName = $company?->name_ar ?? 'زينة للخدمات المنزلية';
        $currentDateStr = now()->locale('ar')->isoFormat('dddd، D MMMM YYYY - h:mm A');

        $driverName = $group->driver ? ($group->driver->name . ' (' . ($group->driver->mobile ?? $group->driver->phone ?? '—') . ')') : 'غير محدد';
        $depPoint = $group->departurePoint?->name ?? 'غير محددة';
        $depTime = $group->departure_time ? \Carbon\Carbon::parse($group->departure_time)->format('h:i A') : 'غير محدد';
        $shiftTime = \Carbon\Carbon::parse($group->start_time)->format('h:i A');

        $workersList = [];
        foreach ($group->bookings as $b) {
            $workers = $b->assignedWorkers->count() > 0 ? $b->assignedWorkers : $b->workers;
            $custName = $b->customer?->full_name ?? (trim(($b->customer?->first_name ?? '') . ' ' . ($b->customer?->last_name ?? '')) ?: null) ?? $b->customer?->name ?? '—';
            $custPhone = $b->customer?->phone_number ?? $b->customer?->mobile ?? $b->customer?->phone ?? '—';
            $serviceName = $b->service?->name_ar ?? $b->service?->name_en ?? '—';
            $serviceAddr = $b->address?->formatted_address ?? $b->address?->street ?? '—';
            $visitTime = substr($b->start_time ?? '09:00', 0, 5) . ($b->end_time ? ' - ' . substr($b->end_time, 0, 5) : '');

            if ($workers->count() > 0) {
                foreach ($workers as $w) {
                    $wPhone = $w->phone_number ?? $w->mobile ?? $w->phone ?? '—';
                    $workersList[] = [
                        'worker_name' => $w->name ?? trim(($w->first_name ?? '') . ' ' . ($w->last_name ?? '')),
                        'worker_phone' => $wPhone,
                        'worker_address' => $w->address ?? 'سكن العاملات الرئيسي',
                        'booking_number' => $b->booking_ref ?? ('BKG-' . $b->id),
                        'customer_name' => $custName,
                        'customer_phone' => $custPhone,
                        'service_name' => $serviceName,
                        'visit_time' => $visitTime,
                        'service_address' => $serviceAddr,
                    ];
                }
            }
        }

        $totalWorkers = count($workersList);

        $filename = 'transport_batch_export_' . now()->format('Y-m-d_Hi') . '.xls';

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($companyName, $currentDateStr, $group, $shiftTime, $depPoint, $depTime, $driverName, $totalWorkers, $workersList) {
            echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
            echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
            echo '<style>';
            echo 'body { font-family: "Segoe UI", Tahoma, Arial, sans-serif; direction: rtl; }';
            echo 'table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }';
            echo 'th, td { border: 1px solid #CBD5E1; padding: 10px 14px; text-align: center; vertical-align: middle; }';
            echo '.header-brand { background-color: #E91E63; color: #FFFFFF; font-size: 22px; font-weight: bold; text-align: center; padding: 16px; }';
            echo '.header-meta { background-color: #FDF0F3; color: #881337; font-size: 15px; font-weight: bold; text-align: center; padding: 12px; border: 1px solid #F43F5E; }';
            echo '.th-column { background-color: #D94A78; color: #FFFFFF; font-size: 14px; font-weight: bold; text-align: center; padding: 12px; }';
            echo '.tr-even { background-color: #F8FAFC; }';
            echo '.tr-odd { background-color: #FFFFFF; }';
            echo '</style></head>';
            echo '<body dir="rtl">';

            echo '<table>';
            echo '<tr><td colSpan="10" class="header-brand">👑 '.$companyName.' — Zeina Home Services</td></tr>';
            echo '<tr><td colSpan="10" class="header-meta">📅 <b>تاريخ التصدير:</b> '.$currentDateStr.' &nbsp;|&nbsp; 📍 <b>المنطقة:</b> '.$group->governorate.' - '.$group->area_name.'</td></tr>';
            echo '<tr><td colSpan="10" class="header-meta">⏱️ <b>فترة الانطلاق:</b> '.$shiftTime.' &nbsp;|&nbsp; 🚏 <b>نقطة الانطلاق:</b> '.$depPoint.' &nbsp;|&nbsp; 🚀 <b>وقت الانطلاق:</b> '.$depTime.' &nbsp;|&nbsp; 👨‍✈️ <b>السائق:</b> '.$driverName.' &nbsp;|&nbsp; 👩‍🍳 <b>عدد العاملات:</b> '.$totalWorkers.'</td></tr>';
            echo '<tr><td colSpan="10" style="height: 10px; border: none;"></td></tr>';
            echo '</table>';

            echo '<table>';
            echo '<thead><tr>';
            $cols = [
                '#', 'اسم العاملة (Worker Name)', 'رقم جوال العاملة (Worker Mobile)', 'عنوان سكن العاملة (Worker Address)',
                'رقم الحجز (Booking Ref)', 'اسم العميل (Customer Name)', 'رقم جوال العميل (Customer Phone)',
                'الخدمة (Service)', 'توقيت الزيارة (Visit Time Slot)', 'عنوان تقديم الخدمة (Service Address)'
            ];
            foreach ($cols as $col) {
                echo '<th class="th-column">'.$col.'</th>';
            }
            echo '</tr></thead>';
            echo '<tbody>';

            foreach ($workersList as $idx => $w) {
                $rowClass = ($idx % 2 === 0) ? 'tr-even' : 'tr-odd';
                echo '<tr class="'.$rowClass.'">';
                echo '<td>'.($idx + 1).'</td>';
                echo '<td><b>'.htmlspecialchars($w['worker_name']).'</b></td>';
                echo '<td><span dir="ltr">'.htmlspecialchars($w['worker_phone']).'</span></td>';
                echo '<td>'.htmlspecialchars($w['worker_address']).'</td>';
                echo '<td><b style="color: #E91E63;">'.htmlspecialchars($w['booking_number']).'</b></td>';
                echo '<td><b>'.htmlspecialchars($w['customer_name']).'</b></td>';
                echo '<td><span dir="ltr">'.htmlspecialchars($w['customer_phone']).'</span></td>';
                echo '<td>'.htmlspecialchars($w['service_name']).'</td>';
                echo '<td><span dir="ltr">'.htmlspecialchars($w['visit_time']).'</span></td>';
                echo '<td>'.htmlspecialchars($w['service_address']).'</td>';
                echo '</tr>';
            }

            if (empty($workersList)) {
                echo '<tr><td colSpan="10" style="padding: 20px; color: #94A3B8;">لا توجد بيانات عاملات في هذه المجموعة.</td></tr>';
            }

            echo '</tbody></table></body></html>';
        };

        return response()->stream($callback, 200, $headers);
    }
}
