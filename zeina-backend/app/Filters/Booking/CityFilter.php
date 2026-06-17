<?php

namespace App\Filters\Booking;

use App\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filter bookings by city, resolved through the booking address' area.
 */
class CityFilter extends Filter
{
    protected function key(): string
    {
        return 'city_id';
    }

    protected function apply(Builder $query, mixed $value): void
    {
        $query->whereHas('address.area', static fn (Builder $q) => $q->where('city_id', $value));
    }
}
