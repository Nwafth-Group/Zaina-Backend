<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Worker extends Model
{
    /** @use HasFactory<\Database\Factories\WorkerFactory> */
    use HasFactory; 

    protected $fillable = [
        'name',
        'nationality',
        'rating',
        'status',
    ];

    public function services()
    {
        return $this->belongsToMany(Service::class,'service_workers');
    } 

    public function media()
    {
        return $this->morphMany(Media::class,'model');
    }

}
