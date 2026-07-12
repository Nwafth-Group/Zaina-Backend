<?php

namespace App\Filters\Employee;

use App\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class CreatedToFilter extends Filter
{
    protected function key(): string
    {
        return 'created_to';
    }

    protected function apply(Builder $query, mixed $value): void
    {
        $query->whereDate('created_at', '<=', $value);
    }
}
