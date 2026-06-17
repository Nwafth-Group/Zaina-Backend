<?php

namespace App\Filters\Booking;

use App\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filter bookings whose total is at or above the given amount.
 */
class MinAmountFilter extends Filter
{
    protected function key(): string
    {
        return 'min_amount';
    }

    protected function apply(Builder $query, mixed $value): void
    {
        $query->where('total_amount', '>=', $value);
    }
}
