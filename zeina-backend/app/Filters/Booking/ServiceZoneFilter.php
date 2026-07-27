<?php

namespace App\Filters\Booking;

use App\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filter bookings by Geo-Fenced Service Zone.
 */
class ServiceZoneFilter extends Filter
{
    protected function key(): string
    {
        return 'service_zone_id';
    }

    protected function apply(Builder $query, mixed $value): void
    {
        $query->whereHas('address', static fn (Builder $q) => $q->where('service_zone_id', $value));
    }
}
