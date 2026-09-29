<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Category extends Model implements Auditable
{
    /** @use HasFactory<\Database\Factories\CategoryFactory> */
    use HasFactory , SoftDeletes , \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name_ar',
        'name_en',
        'parent_id',
        'status',
        'sort_order',
        'created_by',
        'updated_by',
    ]; 

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->created_by = auth()->id();
              if (!$model->sort_order) {
            $model->sort_order = Category::max('sort_order') + 1;
        }
        });

        static::updating(function ($model) {
            $model->updated_by = auth()->id();
        });
    }

    public function services(){
        return $this->hasMany(Service::class, 'category_id');
    }

    public function createdBy(){
        return $this->belongsTo(User::class, 'created_by');
    }
    public function updatedBy(){
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function media(){
        return $this->morphMany(Media::class, 'model');
    }

    public function parent(){
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function subcategories()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }
}
