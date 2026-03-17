<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AreaProvider extends Model
{
    /** @use HasFactory<\Database\Factories\AreaProviderFactory> */
    use HasFactory;

    protected $fillable = [
    'provider_id',
    'city_id',
    'area_id',
    'is_enabled',
    'created_by',
    ];
}
