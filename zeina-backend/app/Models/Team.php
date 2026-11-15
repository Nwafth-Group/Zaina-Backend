<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name_ar',
        'name_en',
        'code',
        'status',
        'notes',
    ];

    public function workers()
    {
        return $this->hasMany(Worker::class);
    }

    public function members()
    {
        return $this->workers();
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'service_teams')->withTimestamps();
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Check if the team has any services/bookings in progress or upcoming.
     */
    public function hasUpcomingOrInProgressBookings(): bool
    {
        $hasBooking = $this->bookings()
            ->whereIn('status', ['Upcoming', 'inService'])
            ->exists();

        if ($hasBooking) {
            return true;
        }

        return \Illuminate\Support\Facades\DB::table('booking_visits')
            ->join('bookings', 'booking_visits.booking_id', '=', 'bookings.id')
            ->where('bookings.team_id', $this->id)
            ->whereIn('booking_visits.status', ['Upcoming', 'inService'])
            ->exists();
    }

    /**
     * Count upcoming or in-progress bookings for this team.
     */
    public function activeBookingsCount(): int
    {
        return $this->bookings()
            ->whereIn('status', ['Upcoming', 'inService'])
            ->count();
    }
}
