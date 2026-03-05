<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

use OwenIt\Auditing\Contracts\Auditable;

class Media extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    
    protected $fillable = [
        'collection',
        'date',
        'description',
        'disk',
        'file_name',
        'mime_type',
        'model_id',
        'model_type',
        'path',
        'size',
        'title',
        'custom_properties'
    ];

    protected $casts = [
        'custom_properties' => 'array',
    ];

    // Relationships
    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
