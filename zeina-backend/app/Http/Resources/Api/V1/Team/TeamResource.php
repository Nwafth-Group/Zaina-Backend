<?php

namespace App\Http\Resources\Api\V1\Team;

use App\Http\Resources\Api\V1\Booking\AdminBookingResource;
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

        $isDetailView = $request->route('team') !== null || $request->filled('include_details');

        return [
            'id' => $this->id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'code' => $this->code,
            'status' => $this->status,
            'notes' => $this->notes,
            'workers_count' => $workers->count(),
            'bookings_count' => $this->bookings()->count(),
            'stats' => [
                'total_bookings' => $this->bookings()->count(),
                'completed_bookings' => $this->bookings()->where('status', 'completed')->count(),
                'upcoming_bookings' => $this->bookings()->where('status', 'Upcoming')->count(),
                'in_service_bookings' => $this->bookings()->where('status', 'inService')->count(),
            ],
            'latest_bookings' => $isDetailView
                ? AdminBookingResource::collection(
                    $this->bookings()
                        ->with(['customer', 'service', 'workers'])
                        ->orderByDesc('created_at')
                        ->limit(20)
                        ->get()
                )
                : [],
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
