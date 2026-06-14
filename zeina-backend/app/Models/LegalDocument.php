<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegalDocument extends Model
{
    /** @use HasFactory<\Database\Factories\LegalDeocumentFactory> */
    use HasFactory;
    protected $table = 'legal_documents';
    protected $fillable = [
        'type', // 'terms' or 'privacy'
        'title_en',
        'title_ar',
        'content_en',
        'content_ar',
        'sort_order',
        'is_active',
    ];
}
