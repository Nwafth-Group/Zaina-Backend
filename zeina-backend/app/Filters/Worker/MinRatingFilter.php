<?php

namespace App\Filters\Worker;

use App\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class MinRatingFilter extends Filter
{
    protected function key(): string
    {
        return 'min_rating';
    }

    protected function apply(Builder $query, mixed $value): void
    {
        $query->where('avg_rating', '>=', (float) $value);
    }
}
