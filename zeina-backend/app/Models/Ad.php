<?php

namespace App\Models;

use Database\Factories\AdFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ad extends Model
{
    /** @use HasFactory<AdFactory> */
    use HasFactory ,SoftDeletes;

    protected $fillable = [
        'title_en',
        'title_ar',
        'status',
    ];

    public function media()
    {
        return $this->morphMany(Media::class, 'model');
    }
}
