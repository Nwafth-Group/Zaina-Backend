<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    /** @use HasFactory<\Database\Factories\AnnouncementFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'body',
    ];

    public function media()
    {
        return $this->morphMany(Media::class, 'model');
    }

    /**
     * Customers this announcement is delivered to. The pivot's `read_at` tracks
     * whether each reader has opened it (null = delivered but unread).
     */
    public function readers()
    {
        return $this->belongsToMany(User::class, 'announcement_user', 'announcement_id', 'user_id')
            ->withPivot('read_at')
            ->withTimestamps();
    }
}
