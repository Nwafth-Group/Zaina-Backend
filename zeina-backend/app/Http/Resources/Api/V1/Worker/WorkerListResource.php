<?php

namespace App\Http\Resources\Api\V1\Worker;

use App\Http\Resources\Api\V1\Media\MediaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkerListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $media = $this->media->where('collection', 'worker/image')->first();

        $services = $this->relationLoaded('services') ? $this->services : $this->services()->get();
        $assignedServices = $services->map(fn($s) => [
            'id' => $s->id,
            'name_ar' => $s->name_ar,
            'name_en' => $s->name_en,
            'service_code' => $s->service_code,
        ])->values()->all();

        $primaryService = $services->first();
        $assignedService = $primaryService ? [
            'id' => $primaryService->id,
            'name_ar' => $primaryService->name_ar,
            'name_en' => $primaryService->name_en,
            'service_code' => $primaryService->service_code,
        ] : null;

        $team = $this->relationLoaded('team') ? $this->team : $this->team()->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'nationality' => $this->nationality,
            'gender' => $this->gender,
            'rating' => $this->avg_rating,
            'status' => $this->status,
            'team_id' => $this->team_id,
            'team' => $team ? [
                'id' => $team->id,
                'name_ar' => $team->name_ar,
                'name_en' => $team->name_en,
                'code' => $team->code,
            ] : null,
            'assigned_service' => $assignedService,
            'assigned_services' => $assignedServices,
            'services' => $assignedServices,
            'image' => $media ? (new MediaResource($media))->getFileUrl() : null,
            'bookings_count' => $this->bookings()->count(),
        ];
    }
}
