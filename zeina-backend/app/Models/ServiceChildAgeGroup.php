<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceChildAgeGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id', 'name_ar', 'name_en', 'age_from', 'age_to',
        'calculation_type', 'children_count', 'required_worker_count', 'status', 'note'
    ];

    protected $casts = [
        'age_from' => 'integer',
        'age_to' => 'integer',
        'children_count' => 'integer',
        'required_worker_count' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            $cc = $model->children_count ?? 1;
            $wc = $model->required_worker_count ?? 1;
            
            // Build English Note
            if ($cc == 1 && $wc == 1) {
                $noteEn = "1 babysitter for each child";
            } else {
                $noteEn = "{$wc} babysitter" . ($wc > 1 ? 's' : '') . " for every {$cc} children";
            }

            // Build Arabic Note
            if ($cc == 1 && $wc == 1) {
                $noteAr = "مربية واحدة لكل طفل";
            } else {
                $noteAr = "{$wc} جليسة لكل {$cc} أطفال";
            }

            $model->note = "{$noteEn} ({$noteAr})";
        });
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}
