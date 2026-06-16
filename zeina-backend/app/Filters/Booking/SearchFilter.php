<?php

namespace App\Filters\Booking;

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
            $q->where('booking_ref', 'like', '%'.$value.'%')
                ->orWhereHas('customer', function (Builder $c) use ($value): void {
                    $c->where('first_name', 'like', '%'.$value.'%')
                        ->orWhere('last_name', 'like', '%'.$value.'%')
                        ->orWhere('phone_number', 'like', '%'.$value.'%');
                })
                ->orWhereHas('service', function (Builder $s) use ($value): void {
                    $s->where('name_ar', 'like', '%'.$value.'%')
                        ->orWhere('name_en', 'like', '%'.$value.'%');
                });
        });
    }
}
