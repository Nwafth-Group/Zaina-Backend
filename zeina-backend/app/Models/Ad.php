<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ad extends Model
{
    /** @use HasFactory<\Database\Factories\AdFactory> */
    use HasFactory ,SoftDeletes;

    protected $fillable = [
        'link',
        'status',
    ];

    public function media()
    {
        return $this->morphMany(Media::class, 'model');
    }
}
