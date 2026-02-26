<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    /** @use HasFactory<\Database\Factories\ServiceFactory> */
    use HasFactory;

    protected $fillable = [
        'category_id',
        'city_id',
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'is_active',
    ];

    public function category(){
        return $this->belongsTo(Category::class, 'category_id');
    }
}
