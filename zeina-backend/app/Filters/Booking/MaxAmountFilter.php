<?php

namespace App\Filters\Booking;

use App\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filter bookings whose total is at or below the given amount.
 */
class MaxAmountFilter extends Filter
{
    protected function key(): string
    {
        return 'max_amount';
    }

    protected function apply(Builder $query, mixed $value): void
    {
        $query->where('total_amount', '<=', $value);
    }
}
