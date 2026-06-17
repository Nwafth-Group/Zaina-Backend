<?php

namespace App\Filters\Worker;

use App\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class SearchFilter extends Filter
{
    protected function key(): string
    {
        return 'search';
    }

    protected function apply(Builder $query, mixed $value): void
    {
        $query->where('name', 'like', '%'.$value.'%');
    }
}
