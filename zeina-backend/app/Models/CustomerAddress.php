<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'service_zone_id', 'latitude', 'longitude', 
        'label', 'street', 'building_name', 'building_no', 
        'floor', 'apartment_no', 'contact_name', 'phone_number', 
        'secondary_phone', 'serviceable_status', 'notes', 
        'is_default', 'is_active'
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

    protected $appends = ['formatted_address'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function serviceZone()
    {
        return $this->belongsTo(ServiceZone::class, 'service_zone_id');
    }

    public function getCityAttribute() {
        return $this->serviceZone?->city;
    }

    public function getFormattedAddressAttribute(): string
    {
        $parts = array_filter([
            $this->street,
            $this->building_name ? ('مبنى ' . $this->building_name) : null,
            $this->building_no ? ('رقم ' . $this->building_no) : null,
            $this->apartment_no ? ('شقة ' . $this->apartment_no) : null,
            $this->serviceZone?->name,
        ]);

        return !empty($parts) ? implode('، ', $parts) : ($this->label ?? $this->notes ?? '—');
    }
}
