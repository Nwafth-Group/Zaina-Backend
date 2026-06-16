<?php

namespace App\Filters\Worker;

use App\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class ServiceFilter extends Filter
{
    /**
     * Sentinel value that selects workers with no linked services.
     */
    private const NONE = 'none';

    protected function key(): string
    {
        return 'service_id';
    }

    protected function apply(Builder $query, mixed $value): void
    {
        if ($value === self::NONE) {
            $query->whereDoesntHave('services');

            return;
        }

        $query->whereHas('services', function (Builder $q) use ($value): void {
            $q->where('services.id', $value);
        });
    }
}
