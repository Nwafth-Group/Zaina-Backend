<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceCoverage extends Model
{
    /** @use HasFactory<\Database\Factories\ServiceCoverageFactory> */
    use HasFactory;

    protected $fillable = [
        'service_id', 'city_id', 'area_id', 'status'
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }
}
