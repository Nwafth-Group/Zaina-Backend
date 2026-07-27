<?php

namespace App\Service\Admin;

use App\Enums\Admin\BookingStatus;
use App\Enums\Admin\PaymentStatus;
use App\Models\Booking;
use App\Models\Service;
use App\Models\ServiceZone;
use App\Models\User;
use App\Models\Worker;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private const TREND_DAYS = 14;
    private const TOP_SERVICES = 5;
    private const UPCOMING_LIMIT = 5;
    private const CURRENCY = 'EGP';

    public function stats(?string $governorate = null, ?int $serviceZoneId = null): array
    {
        // Available locations for filter dropdown
        $governorates = ServiceZone::distinct()
            ->whereNotNull('governorate')
            ->pluck('governorate')
            ->values()
            ->all();

        $serviceZones = ServiceZone::select('id', 'name', 'governorate')
            ->where('status', 'active')
            ->get()
            ->toArray();

        return [
            'locations' => [
                'governorates' => $governorates,
                'service_zones' => $serviceZones,
            ],
            'cards' => $this->cards($governorate, $serviceZoneId),
            'bookings_by_status' => $this->countByColumn('status', BookingStatus::values(), $governorate, $serviceZoneId),
            'payments_by_status' => $this->countByColumn('payment_status', PaymentStatus::values(), $governorate, $serviceZoneId),
            'trend' => $this->trend($governorate, $serviceZoneId),
            'top_services' => $this->topServices($governorate, $serviceZoneId),
            'upcoming' => $this->upcoming($governorate, $serviceZoneId),
        ];
    }

    private function applyLocationFilter($query, ?string $governorate = null, ?int $serviceZoneId = null)
    {
        if (!empty($serviceZoneId)) {
            $query->whereHas('address', function ($q) use ($serviceZoneId) {
                $q->where('service_zone_id', $serviceZoneId);
            });
        } elseif (!empty($governorate)) {
            $query->whereHas('address', function ($q) use ($governorate) {
                $q->where('governorate', $governorate)
                  ->orWhereHas('serviceZone', function ($sq) use ($governorate) {
                      $sq->where('governorate', $governorate);
                  });
            });
        }

        return $query;
    }

    private function cards(?string $governorate = null, ?int $serviceZoneId = null): array
    {
        $revenueQuery = $this->applyLocationFilter(Booking::where('status', BookingStatus::Completed->value), $governorate, $serviceZoneId);
        $totalBookingsQuery = $this->applyLocationFilter(Booking::query(), $governorate, $serviceZoneId);
        $upcomingBookingsQuery = $this->applyLocationFilter(Booking::where('status', BookingStatus::Upcoming->value), $governorate, $serviceZoneId);

        return [
            'revenue' => (float) $revenueQuery->sum('total_amount'),
            'currency' => self::CURRENCY,
            'bookings_total' => $totalBookingsQuery->count(),
            'bookings_upcoming' => $upcomingBookingsQuery->count(),
            'active_services' => Service::where('status', 'active')->count(),
            'workers' => Worker::where('status', 'active')->count(),
            'customers' => User::where('type', 'customer')->count(),
        ];
    }

    private function countByColumn(string $column, array $expected, ?string $governorate = null, ?int $serviceZoneId = null): array
    {
        $query = $this->applyLocationFilter(Booking::query(), $governorate, $serviceZoneId);

        $counts = $query->select($column, DB::raw('count(*) as aggregate'))
            ->groupBy($column)
            ->pluck('aggregate', $column);

        return collect($expected)
            ->mapWithKeys(fn (string $value): array => [$value => (int) ($counts[$value] ?? 0)])
            ->all();
    }

    private function trend(?string $governorate = null, ?int $serviceZoneId = null): array
    {
        $start = Carbon::today()->subDays(self::TREND_DAYS - 1);

        $query = Booking::whereDate('booking_date', '>=', $start->toDateString());
        $this->applyLocationFilter($query, $governorate, $serviceZoneId);

        $byDay = $query->get(['booking_date', 'total_amount', 'status'])
            ->groupBy(fn (Booking $booking): string => Carbon::parse($booking->booking_date)->toDateString());

        return collect(range(0, self::TREND_DAYS - 1))
            ->map(function (int $offset) use ($start, $byDay): array {
                $day = $start->copy()->addDays($offset);
                $rows = $byDay->get($day->toDateString(), collect());

                return [
                    'date' => $day->toDateString(),
                    'label' => $day->format('M d'),
                    'count' => $rows->count(),
                    'revenue' => (float) $rows->where('status', BookingStatus::Completed->value)->sum('total_amount'),
                ];
            })
            ->all();
    }

    private function topServices(?string $governorate = null, ?int $serviceZoneId = null): array
    {
        $query = Booking::query();
        $this->applyLocationFilter($query, $governorate, $serviceZoneId);

        return $query->select('service_id', DB::raw('count(*) as aggregate'))
            ->groupBy('service_id')
            ->orderByDesc('aggregate')
            ->limit(self::TOP_SERVICES)
            ->with('service:id,name_ar,name_en')
            ->get()
            ->map(fn (Booking $row): array => [
                'id' => $row->service_id,
                'name' => $row->service ? $this->localizedName($row->service) : '—',
                'count' => (int) $row->aggregate,
            ])
            ->all();
    }

    private function upcoming(?string $governorate = null, ?int $serviceZoneId = null): array
    {
        $query = Booking::where('status', BookingStatus::Upcoming->value);
        $this->applyLocationFilter($query, $governorate, $serviceZoneId);

        return $query->with(['customer', 'service'])
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->limit(self::UPCOMING_LIMIT)
            ->get()
            ->map(fn (Booking $booking): array => [
                'id' => $booking->id,
                'booking_ref' => $booking->booking_ref,
                'customer_name' => $this->customerName($booking),
                'service_name' => $booking->service ? $this->localizedName($booking->service) : null,
                'booking_date' => $booking->booking_date ? Carbon::parse($booking->booking_date)->format('d M, Y') : null,
                'time_slot' => $this->timeSlot($booking),
                'total_amount' => (float) $booking->total_amount,
                'currency' => self::CURRENCY,
            ])
            ->all();
    }

    private function localizedName(Service $service): string
    {
        return app()->getLocale() === 'ar' ? $service->name_ar : $service->name_en;
    }

    private function customerName(Booking $booking): ?string
    {
        if (! $booking->customer) {
            return null;
        }

        return trim(($booking->customer->first_name ?? '').' '.($booking->customer->last_name ?? ''));
    }

    private function timeSlot(Booking $booking): ?string
    {
        if (! $booking->start_time || ! $booking->end_time) {
            return null;
        }

        return Carbon::parse($booking->start_time)->format('h:i A').' - '.Carbon::parse($booking->end_time)->format('h:i A');
    }
}
