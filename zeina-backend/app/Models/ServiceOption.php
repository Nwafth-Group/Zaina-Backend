<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'unit_price',
        'min_qty',
        'max_qty',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'min_qty' => 'integer',
        'max_qty' => 'integer',
        'sort_order' => 'integer',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}
