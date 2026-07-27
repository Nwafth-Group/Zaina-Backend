<?php

namespace App\Enums\Admin;

/**
 * Booking lifecycle statuses as managed by the admin. Centralises the
 * values that were previously hard-coded across the dashboard, the status
 * endpoint and its validation.
 */
enum BookingStatus: string
{
    case Upcoming = 'Upcoming';
    case InService = 'inService';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
