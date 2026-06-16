<?php

namespace App\Filters\Employee;

use App\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class TypeFilter extends Filter
{
    protected function key(): string
    {
        return 'type';
    }

    protected function apply(Builder $query, mixed $value): void
    {
        $query->where('type', $value);
    }
}
