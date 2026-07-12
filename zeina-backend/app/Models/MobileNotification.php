<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MobileNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'title_en',
        'title_ar',
        'content_en',
        'content_ar',
        'audience',
        'send_date',
        'send_time',
        'platforms',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'platforms' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
