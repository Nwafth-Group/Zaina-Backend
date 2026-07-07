<?php

namespace App\Service\Customer;

use App\Models\BookingAssignment;
use App\Models\Category;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerService
{
    /**
     * List active services with optional type filtering under active categories.
     */
    public function listServices(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $query = Service::with(['category', 'media', 'startingPrice'])
            ->where('status', 'active')
            ->whereHas('category', function (Builder $q) {
                $q->where('status', 'active');
            });

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->paginate($perPage);
    }


    /**
     * Show service details if service and its parent category are active.
     */
    public function showService(Service $service): Service
    {
        if ($service->status !== 'active' || ($service->category && $service->category->status !== 'active')) {
            abort(404, __('messages.service_not_available'));
        }

        return $service->load(['rules', 'media', 'schedules']);
    }

    /**
     * Get preferred and other available workers for a specific shift/period date.
     */
    public function getAvailableWorkers(Service $service, string $date, string $startTime): array
    {
        $periodsCount = 1;
        $periodHours = $service->duration->service_period_hours ?? 2;
        $totalMinutes = $periodsCount * $periodHours * 60;

        $start = Carbon::parse($startTime);
        $end = $start->copy()->addMinutes($totalMinutes);

        $startStr = $start->format('H:i:s');
        $endStr = $end->format('H:i:s');

        // Retrieve busy worker IDs during this period
        $busyWorkerIds = BookingAssignment::where('status', 'active')
            ->whereHas('booking', function ($bookingQ) use ($date, $startStr, $endStr) {
                $bookingQ->where('booking_date', $date)
                         ->whereIn('status', ['Upcoming', 'inService'])
                         ->where(function ($timeQ) use ($startStr, $endStr) {
                             $timeQ->where('start_time', '<', $endStr)
                                   ->where('end_time', '>', $startStr);
                         });
            })
            ->pluck('worker_id')
            ->unique()
            ->toArray();

        // Retrieve active workers excluding busy ones
        $availableWorkers = $service->workers()
            ->where('status', 'active')
            ->whereNotIn('workers.id', $busyWorkerIds)
            ->get();

        $userId = auth('sanctum')->id(); 

        // Preferred workers matching past completed bookings for this customer and service
        $preferredIds = BookingAssignment::whereHas('booking', function($q) use ($userId, $service) {
                $q->where('customer_id', $userId)
                  ->where('service_id', $service->id)
                  ->where('status', 'completed');
            })
            ->pluck('worker_id')
            ->unique()
            ->toArray();

        return [
            'preferred' => $availableWorkers->whereIn('id', $preferredIds)->values(),
            'others'    => $availableWorkers->whereNotIn('id', $preferredIds)->values(),
        ];
    }

    /**
     * Get available shifts and blocks for a service on a given date.
     */
    public function getShiftsForDate(Service $service, string $dateStr): array
    {
        $date = Carbon::parse($dateStr);
        $dayName = strtolower($date->format('l'));
        
        $schedules = $service->schedules()->where('day', $dayName)->where('is_active', true)->get();

        $shiftsData = [];
        foreach ($schedules as $schedule) {
            $shiftsData[] = [
                'id' => $schedule->id,
                'start_time' => Carbon::parse($schedule->start_time)->format('H:i'),
                'end_time' => Carbon::parse($schedule->end_time)->format('H:i'),
            ];
        }

        return $shiftsData;
    }
}
