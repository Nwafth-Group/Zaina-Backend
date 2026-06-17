<?php

namespace App\Filters\Service;

use App\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class CategoryFilter extends Filter
{
    protected function key(): string
    {
        return 'category_id';
    }

    protected function apply(Builder $query, mixed $value): void
    {
        $query->where('category_id', $value);
    }
}
