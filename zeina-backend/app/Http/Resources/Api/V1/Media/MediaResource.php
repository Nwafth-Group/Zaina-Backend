<?php

namespace App\Http\Resources\Api\V1\Media;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MediaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $baseData = [
            'id'            => $this->id,
            'file_name'     => $this->file_name,
            'file_url'      => $this->getFileUrl(),
        ];

        return array_merge($baseData);
    }

    public function getFileUrl(): string
    {
        $url = Storage::disk($this->disk)->url($this->path);

        if (str_starts_with($url, 'https://')) {
            $url = 'http://' . substr($url, 8);
        }

        return $url;
    }
}
