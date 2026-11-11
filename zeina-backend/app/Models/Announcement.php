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
        'title_ar',
        'title_en',
        'body',
        'body_ar',
        'body_en',
    ];

    protected static function booted(): void
    {
        static::saving(function (Announcement $announcement) {
            // Keep legacy title & body synced automatically
            if (empty($announcement->title)) {
                $announcement->title = $announcement->title_ar ?: ($announcement->title_en ?: '');
            }
            if (empty($announcement->body)) {
                $announcement->body = $announcement->body_ar ?: ($announcement->body_en ?: '');
            }
            if (empty($announcement->title_ar) && ! empty($announcement->title)) {
                $announcement->title_ar = $announcement->title;
            }
            if (empty($announcement->title_en) && ! empty($announcement->title)) {
                $announcement->title_en = $announcement->title;
            }
            if (empty($announcement->body_ar) && ! empty($announcement->body)) {
                $announcement->body_ar = $announcement->body;
            }
            if (empty($announcement->body_en) && ! empty($announcement->body)) {
                $announcement->body_en = $announcement->body;
            }
        });
    }

    public function getTitleAttribute($value): string
    {
        $locale = app()->getLocale();
        if ($locale === 'ar') {
            return $this->attributes['title_ar'] ?? $this->attributes['title'] ?? $this->attributes['title_en'] ?? '';
        }

        return $this->attributes['title_en'] ?? $this->attributes['title'] ?? $this->attributes['title_ar'] ?? '';
    }

    public function getBodyAttribute($value): string
    {
        $locale = app()->getLocale();
        if ($locale === 'ar') {
            return $this->attributes['body_ar'] ?? $this->attributes['body'] ?? $this->attributes['body_en'] ?? '';
        }

        return $this->attributes['body_en'] ?? $this->attributes['body'] ?? $this->attributes['body_ar'] ?? '';
    }

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
