<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyInfo extends Model
{
    use HasFactory;

    protected $table = 'company_infos';

    protected $fillable = [
        'name_ar',
        'name_en',
        'email',
        'phone',
        'website',
        'facebook_link',
        'instagram_link',
        'twitter_link',
        'snapchat_link',
        'tiktok_link',
        'linkedin_link',
        'google_play_link',
        'app_store_link',
        'vat_percentage',
        'tax_number',
    ];

    /**
     * Get the media associated with this company info.
     */
    public function media()
    {
        return $this->morphMany(Media::class, 'model');
    }
}
