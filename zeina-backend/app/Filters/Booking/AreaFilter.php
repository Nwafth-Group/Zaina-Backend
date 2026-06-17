<?php

namespace App\Filters\Booking;

use App\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filter bookings by the area of their address.
 */
class AreaFilter extends Filter
{
    protected function key(): string
    {
        return 'area_id';
    }

    protected function apply(Builder $query, mixed $value): void
    {
        $query->whereHas('address', static fn (Builder $q) => $q->where('area_id', $value));
    }
}
