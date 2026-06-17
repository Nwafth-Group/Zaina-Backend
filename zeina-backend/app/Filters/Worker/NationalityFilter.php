<?php

namespace App\Filters\Worker;

use App\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class NationalityFilter extends Filter
{
    protected function key(): string
    {
        return 'nationality';
    }

    protected function apply(Builder $query, mixed $value): void
    {
        $query->where('nationality', $value);
    }
}
