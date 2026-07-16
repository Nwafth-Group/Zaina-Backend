<?php

namespace App\Http\Resources\Api\V1\CompanyInfo;

use App\Http\Resources\Api\V1\Media\MediaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyInfoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $logo = $this->media->where('collection', 'company_info/logo')->first();

        return [
            'id' => $this->id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'name' => app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'facebook_link' => $this->facebook_link,
            'instagram_link' => $this->instagram_link,
            'twitter_link' => $this->twitter_link,
            'snapchat_link' => $this->snapchat_link,
            'tiktok_link' => $this->tiktok_link,
            'linkedin_link' => $this->linkedin_link,
            'google_play_link' => $this->google_play_link,
            'app_store_link' => $this->app_store_link,
            'vat_percentage' => (float) $this->vat_percentage,
            'logo_url' => $logo ? (new MediaResource($logo))->getFileUrl() : null,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
