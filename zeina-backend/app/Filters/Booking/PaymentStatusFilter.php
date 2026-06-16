<?php

namespace App\Filters\Booking;

use App\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class PaymentStatusFilter extends Filter
{
    protected function key(): string
    {
        return 'payment_status';
    }

    protected function apply(Builder $query, mixed $value): void
    {
        $query->where('payment_status', $value);
    }
}
