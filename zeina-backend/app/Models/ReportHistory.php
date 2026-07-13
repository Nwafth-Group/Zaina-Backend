<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportHistory extends Model
{
    protected $fillable = [
        'report_id',
        'old_status',
        'new_status',
        'note',
        'created_by',
    ];

    public function report()
    {
        return $this->belongsTo(Report::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
