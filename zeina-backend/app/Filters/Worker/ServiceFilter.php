<?php

namespace App\Filters\Worker;

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
        $query->whereHas('services', function (Builder $q) use ($value): void {
            $q->where('services.id', $value);
        });
    }
}
