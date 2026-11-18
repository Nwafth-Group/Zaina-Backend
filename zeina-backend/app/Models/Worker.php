<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Worker extends Model
{
    /** @use HasFactory<\Database\Factories\WorkerFactory> */
    use HasFactory;

    public const ALL_DAYS = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

    protected $fillable = [
        'name',
        'nationality',
        'avg_rating',
        'status',
        'phone_number',
        'id_number',
        'address',
        'gender',
        'team_id',
        'work_days',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'avg_rating' => 'float',
            'work_days' => 'array',
        ];
    }

    public function getEffectiveWorkDays(): array
    {
        if (is_array($this->work_days) && !empty($this->work_days)) {
            return array_values(array_unique(array_map('strtolower', $this->work_days)));
        }
        return self::ALL_DAYS;
    }

    public function worksOnDay(string $dayName): bool
    {
        return in_array(strtolower($dayName), $this->getEffectiveWorkDays(), true);
    }

    public function isWorkingOnDate(\Carbon\Carbon|string $date): bool
    {
        $dayName = $date instanceof \Carbon\Carbon
            ? $date->format('l')
            : \Carbon\Carbon::parse($date)->format('l');

        return $this->worksOnDay($dayName);
    }

    public function getVacationDays(): array
    {
        return array_values(array_diff(self::ALL_DAYS, $this->getEffectiveWorkDays()));
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'service_workers');
    }

    public function assignments()
    {
        // العامل لديه العديد من التخصيصات في الجدول الوسيط
        return $this->hasMany(BookingAssignment::class, 'worker_id');
    }

    public function bookings()
    {
        return $this->belongsToMany(Booking::class, 'booking_assignments', 'worker_id', 'booking_id')
            ->wherePivot('status', 'active') // فقط الحجزات النشطة
            ->withTimestamps();
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'model');
    }

    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function absences()
    {
        return $this->hasMany(WorkerAbsence::class);
    }

    /**
     * Check if the worker has any upcoming or in-progress bookings.
     */
    public function hasUpcomingOrInProgressBookings(): bool
    {
        // 1. Direct assignment on bookings table
        $hasDirect = Booking::where('worker_id', $this->id)
            ->whereIn('status', ['Upcoming', 'inService'])
            ->exists();

        if ($hasDirect) {
            return true;
        }

        // 2. Active assignment in booking_assignments for upcoming/inService bookings
        $hasAssignment = BookingAssignment::where('worker_id', $this->id)
            ->where('status', 'active')
            ->whereHas('booking', function ($q) {
                $q->whereIn('status', ['Upcoming', 'inService']);
            })
            ->exists();

        if ($hasAssignment) {
            return true;
        }

        // 3. Child booking visits in progress or upcoming
        return \Illuminate\Support\Facades\DB::table('booking_visits')
            ->join('bookings', 'booking_visits.booking_id', '=', 'bookings.id')
            ->join('booking_assignments', 'booking_assignments.booking_id', '=', 'bookings.id')
            ->where('booking_assignments.worker_id', $this->id)
            ->where('booking_assignments.status', 'active')
            ->whereIn('booking_visits.status', ['Upcoming', 'inService'])
            ->exists();
    }

    /**
     * Count upcoming or in-progress bookings for this worker.
     */
    public function activeBookingsCount(): int
    {
        $directCount = Booking::where('worker_id', $this->id)
            ->whereIn('status', ['Upcoming', 'inService'])
            ->count();

        $assignmentCount = BookingAssignment::where('worker_id', $this->id)
            ->where('status', 'active')
            ->whereHas('booking', function ($q) {
                $q->whereIn('status', ['Upcoming', 'inService']);
            })
            ->count();

        return max($directCount, $assignmentCount);
    }
}
