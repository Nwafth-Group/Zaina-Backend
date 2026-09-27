<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkerAbsence extends Model
{
    use HasFactory;

    protected $fillable = [
        'worker_id',
        'absence_start',
        'absence_end',
        'reason',
        'status',
        'recorded_by',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'absence_start' => 'datetime',
            'absence_end' => 'datetime',
        ];
    }

    public function worker()
    {
        return $this->belongsTo(Worker::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
