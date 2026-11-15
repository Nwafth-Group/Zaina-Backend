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
        $term = trim((string) $value);
        if ($term === '') {
            return;
        }

        $query->where(function ($q) use ($term) {
            $q->where('name', 'like', '%'.$term.'%')
              ->orWhere('nationality', 'like', '%'.$term.'%')
              ->orWhere('phone_number', 'like', '%'.$term.'%')
              ->orWhere('id_number', 'like', '%'.$term.'%');
        });
    }
}
