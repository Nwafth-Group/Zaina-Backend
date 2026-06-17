<?php

namespace App\Service\Admin;

use App\Enums\Admin\BookingStatus;
use App\Enums\Admin\PaymentStatus;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use App\Models\Worker;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds the admin dashboard statistics. Each metric group is its own method so
 * the responsibilities stay separated and individually testable.
 */
class DashboardService
{
    private const TREND_DAYS = 14;

    private const TOP_SERVICES = 5;

    private const UPCOMING_LIMIT = 5;

    private const CURRENCY = 'EGP';

    /**
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        return [
            'cards' => $this->cards(),
            'bookings_by_status' => $this->countByColumn('status', BookingStatus::values()),
            'payments_by_status' => $this->countByColumn('payment_status', PaymentStatus::values()),
            'trend' => $this->trend(),
            'top_services' => $this->topServices(),
            'upcoming' => $this->upcoming(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cards(): array
    {
        return [
            'revenue' => (float) Booking::where('status', BookingStatus::Completed->value)->sum('total_amount'),
            'currency' => self::CURRENCY,
            'bookings_total' => Booking::count(),
            'bookings_upcoming' => Booking::where('status', BookingStatus::Upcoming->value)->count(),
            'active_services' => Service::where('status', 'active')->count(),
            'workers' => Worker::where('status', 'active')->count(),
            'customers' => User::where('type', 'customer')->count(),
        ];
    }

    /**
     * Count bookings grouped by a column, zero-filling every expected value so
     * the UI always receives a complete set.
     *
     * @param  list<string>  $expected
     * @return array<string, int>
     */
    private function countByColumn(string $column, array $expected): array
    {
        $counts = Booking::select($column, DB::raw('count(*) as aggregate'))
            ->groupBy($column)
            ->pluck('aggregate', $column);

        return collect($expected)
            ->mapWithKeys(fn (string $value): array => [$value => (int) ($counts[$value] ?? 0)])
            ->all();
    }

    /**
     * Daily bookings & completed-revenue series for the trend window. Grouped in
     * PHP so the query stays portable across MySQL (prod) and sqlite (tests).
     *
     * @return list<array<string, mixed>>
     */
    private function trend(): array
    {
        $start = Carbon::today()->subDays(self::TREND_DAYS - 1);

        $byDay = Booking::whereDate('booking_date', '>=', $start->toDateString())
            ->get(['booking_date', 'total_amount', 'status'])
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

    /**
     * @return list<array<string, mixed>>
     */
    private function topServices(): array
    {
        return Booking::select('service_id', DB::raw('count(*) as aggregate'))
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

    /**
     * @return list<array<string, mixed>>
     */
    private function upcoming(): array
    {
        return Booking::where('status', BookingStatus::Upcoming->value)
            ->with(['customer', 'service'])
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
