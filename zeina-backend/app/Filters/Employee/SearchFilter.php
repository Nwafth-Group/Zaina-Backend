<?php

namespace App\Filters\Employee;

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
            $q->where('first_name', 'like', '%'.$value.'%')
                ->orWhere('last_name', 'like', '%'.$value.'%')
                ->orWhere('phone_number', 'like', '%'.$value.'%')
                ->orWhere('email', 'like', '%'.$value.'%');
        });
    }
}
