<?php

namespace App\Filters\Category;

use App\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filters categories by hierarchy level: `main` (top-level, no parent) or
 * `sub` (has a parent).
 */
class TypeFilter extends Filter
{
    protected function key(): string
    {
        return 'type';
    }

    protected function apply(Builder $query, mixed $value): void
    {
        if ($value === 'main') {
            $query->whereNull('parent_id');
        } elseif ($value === 'sub') {
            $query->whereNotNull('parent_id');
        }
    }
}
