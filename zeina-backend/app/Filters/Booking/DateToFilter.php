<?php

namespace App\Filters\Booking;

use App\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class DateToFilter extends Filter
{
    protected function key(): string
    {
        return 'date_to';
    }

    protected function apply(Builder $query, mixed $value): void
    {
        $query->whereDate('booking_date', '<=', $value);
    }
}
