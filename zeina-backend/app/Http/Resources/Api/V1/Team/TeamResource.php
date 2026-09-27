<?php

namespace App\Http\Resources\Api\V1\Team;

use App\Http\Resources\Api\V1\Media\MediaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $workers = $this->relationLoaded('workers') ? $this->workers : $this->workers()->get();
        $services = $this->relationLoaded('services') ? $this->services : $this->services()->get();

        return [
            'id' => $this->id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'code' => $this->code,
            'status' => $this->status,
            'notes' => $this->notes,
            'workers_count' => $workers->count(),
            'bookings_count' => $this->bookings()->count(),
            'workers' => $workers->map(function ($w) {
                $media = $w->media ? ($w->media->where('collection', 'worker/image')->first() ?? $w->media->first()) : null;
                return [
                    'id' => $w->id,
                    'name' => $w->name,
                    'phone_number' => $w->phone_number,
                    'nationality' => $w->nationality,
                    'gender' => $w->gender,
                    'rating' => $w->avg_rating,
                    'status' => $w->status,
                    'image' => $media ? (new MediaResource($media))->getFileUrl() : null,
                ];
            })->values()->all(),
            'services' => $services->map(function ($s) {
                return [
                    'id' => $s->id,
                    'name_ar' => $s->name_ar,
                    'name_en' => $s->name_en,
                    'service_code' => $s->service_code,
                    'status' => $s->status,
                ];
            })->values()->all(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
