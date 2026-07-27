<?php

namespace App\Service\Admin;

use App\Models\Booking;
use App\Models\TransportGroup;
use App\Models\ServiceZone;
use Illuminate\Support\Carbon;

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
            $govName = is_object($rawGov) ? ($rawGov->name_ar ?? $rawGov->name_en ?? 'القاهرة') : (string)$rawGov;

            $rawZone = $zone?->name ?? 'منطقة غير محددة';
            $zoneName = is_object($rawZone) ? ($rawZone->name_ar ?? $rawZone->name_en ?? 'منطقة غير محددة') : (string)$rawZone;

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

        // Group by Start Time
        $timeGroups = $filteredBookings->groupBy(function ($b) {
            return substr($b->start_time ?? '09:00', 0, 5);
        });

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
        $startTime = $group->start_time;

        // Rule 3.2: Verify departure time is before start time
        $warning = null;
        if (strtotime($departureTime) >= strtotime($startTime)) {
            $warning = 'تنبيه: وقت الانطلاق يجب أن يكون قبل وقت بدء الخدمة!';
        }

        $group->update([
            'departure_point_id' => $data['departure_point_id'],
            'departure_time' => $departureTime,
            'driver_id' => $data['driver_id'],
            'notes' => $data['notes'] ?? null,
            'status' => 'ready_for_transport',
        ]);

        if (!empty($data['booking_ids'])) {
            $group->bookings()->sync($data['booking_ids']);
        }

        return $group->load(['departurePoint', 'driver', 'bookings']);
    }
}
