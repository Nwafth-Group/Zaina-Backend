<?php

namespace App\Filters\Booking;

use App\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class ServiceFilter extends Filter
{
    protected function key(): string
    {
        return 'service_id';
    }

    protected function apply(Builder $query, mixed $value): void
    {
        $query->where('service_id', $value);
    }
}
