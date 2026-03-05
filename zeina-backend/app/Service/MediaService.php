<?php

namespace App\Service;

use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;


class MediaService
{
    /**
     * Upload a file and create a media record.
     *
     * @param UploadedFile $file
     * @param string $collection
     * @param string|null $disk
     * @return Media
     */
    public function upload(UploadedFile $file, Model $model, string $collection = 'default', string $disk = 'public'): Media
    {
        $path = $file->store($collection, $disk);
        try {
            return Media::create([
                'model_type' => get_class($model),
                'model_id'   => $model->getKey(),
                'collection' => $collection,
                'file_name'  => basename($path),
                'mime_type'  => $file->getMimeType(),
                'size'       => $file->getSize(),
                'disk'       => $disk,
                'path'       => $path,
                'title'      => $file->getClientOriginalName(),
                'date'       => now()->toDateString(),
            ]);
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        }
    }

    public function uploadOne(UploadedFile $file, Model $model, string $collection = 'default', string $disk = 'public'): Media
{
    $media = $model->media;
    if($media)
        $this->delete($media);
        
    return $this->upload($file, $model, $collection, $disk);
}

    /**
     * Delete the file and the media record.
     *
     * @param Media $media
     * @return bool
     */
    public function delete(Media $media): bool
    {
        if (Storage::disk($media->disk)->exists($media->path)) {
            Storage::disk($media->disk)->delete($media->path);
        }

        return $media->delete();
    }

    /**
     * Get the full URL of the media file.
     *
     * @param Media $media
     * @return string
     */
    public function get(Media $media): string
    {
        return Storage::disk($media->disk)->url($media->path);
    }
}
