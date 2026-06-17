<?php

namespace App\Filters\Employee;

use App\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class RoleFilter extends Filter
{
    protected function key(): string
    {
        return 'role';
    }

    protected function apply(Builder $query, mixed $value): void
    {
        $query->whereHas('roles', function (Builder $q) use ($value): void {
            $q->where('name', $value);
        });
    }
}
