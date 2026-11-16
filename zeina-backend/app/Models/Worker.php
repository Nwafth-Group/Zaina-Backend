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
}
