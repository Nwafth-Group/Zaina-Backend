<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Driver extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'mobile',
        'email',
        'gender',
        'vehicle_type',
        'vehicle_plate',
        'vehicle_capacity',
        'status',
    ];

    protected $casts = [
        'vehicle_capacity' => 'integer',
    ];

    public function media()
    {
        return $this->morphMany(Media::class, 'model');
    }

    public function getImageUrlAttribute()
    {
        $media = $this->media()->first();
        if ($media && $media->path) {
            return Storage::disk($media->disk ?? 'public')->url($media->path);
        }
        return null;
    }
}
