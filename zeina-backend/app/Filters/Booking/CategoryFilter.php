<?php

namespace App\Filters\Booking;

use App\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filter bookings by the category of their service. Accepts a single id, a comma
 * separated list or an array (`category_ids`) — matching any of them.
 */
class CategoryFilter extends Filter
{
    protected function key(): string
    {
        return 'category_ids';
    }

    protected function apply(Builder $query, mixed $value): void
    {
        $ids = is_array($value) ? $value : explode(',', (string) $value);
        $ids = array_filter(array_map('trim', $ids), static fn ($id): bool => $id !== '');

        if (empty($ids)) {
            return;
        }

        $query->whereHas('service', static function (Builder $q) use ($ids): void {
            $q->whereIn('category_id', $ids);
        });
    }
}
