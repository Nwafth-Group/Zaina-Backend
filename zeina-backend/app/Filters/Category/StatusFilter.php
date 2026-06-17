<?php

namespace App\Filters\Category;

use App\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class StatusFilter extends Filter
{
    protected function key(): string
    {
        return 'status';
    }

    protected function apply(Builder $query, mixed $value): void
    {
        $query->where('status', $value);
    }
}
