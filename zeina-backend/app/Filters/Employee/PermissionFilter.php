<?php

namespace App\Filters\Employee;

use App\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class PermissionFilter extends Filter
{
    protected function key(): string
    {
        return 'permission';
    }

    protected function apply(Builder $query, mixed $value): void
    {
        if (is_string($value) && !empty($value)) {
            $permissions = array_filter(array_map('trim', explode(',', $value)));
            if (!empty($permissions)) {
                $query->permission($permissions);
            }
        } elseif (is_array($value) && !empty($value)) {
            $query->permission($value);
        }
    }
}
