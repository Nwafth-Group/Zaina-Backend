<?php

namespace App\Filters\Category;

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
        $query->where(function (Builder $q) use ($value): void {
            $q->where('name_ar', 'like', '%'.$value.'%')
                ->orWhere('name_en', 'like', '%'.$value.'%');
        });
    }
}
