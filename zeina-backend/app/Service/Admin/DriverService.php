<?php

namespace App\Service\Admin;

use App\Models\Driver;
use App\Models\Media;

class DriverService
{
    public function getPaginated(array $filters, int $perPage = 15)
    {
        $query = Driver::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('mobile', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function getCounts(array $filters = [])
    {
        return [
            'all' => Driver::count(),
            'active' => Driver::where('status', 'active')->count(),
            'inactive' => Driver::where('status', 'inactive')->count(),
        ];
    }

    public function createDriver(array $data, $imageFile = null): Driver
    {
        $driver = Driver::create([
            'name'             => $data['name'],
            'mobile'           => $data['mobile'],
            'email'            => $data['email'] ?? null,
            'gender'           => $data['gender'],
            'vehicle_type'     => $data['vehicle_type'] ?? null,
            'vehicle_plate'    => $data['vehicle_plate'] ?? null,
            'vehicle_capacity' => isset($data['vehicle_capacity']) ? (int) $data['vehicle_capacity'] : 5,
            'status'           => $data['status'],
        ]);

        if ($imageFile) {
            $path = $imageFile->store('drivers', 'public');
            Media::create([
                'model_type' => Driver::class,
                'model_id' => $driver->id,
                'file_name' => $imageFile->getClientOriginalName(),
                'mime_type' => $imageFile->getClientMimeType(),
                'size' => $imageFile->getSize(),
                'path' => 'storage/' . $path,
                'collection' => 'avatar',
                'disk' => 'public',
            ]);
        }

        return $driver;
    }

    public function updateDriver(Driver $driver, array $data, $imageFile = null): Driver
    {
        $driver->update([
            'name'             => $data['name'],
            'mobile'           => $data['mobile'],
            'email'            => $data['email'] ?? null,
            'gender'           => $data['gender'],
            'vehicle_type'     => $data['vehicle_type'] ?? $driver->vehicle_type,
            'vehicle_plate'    => $data['vehicle_plate'] ?? $driver->vehicle_plate,
            'vehicle_capacity' => isset($data['vehicle_capacity']) ? (int) $data['vehicle_capacity'] : $driver->vehicle_capacity,
            'status'           => $data['status'],
        ]);

        if ($imageFile) {
            Media::where('model_type', Driver::class)->where('model_id', $driver->id)->delete();
            $path = $imageFile->store('drivers', 'public');
            Media::create([
                'model_type' => Driver::class,
                'model_id' => $driver->id,
                'file_name' => $imageFile->getClientOriginalName(),
                'mime_type' => $imageFile->getClientMimeType(),
                'size' => $imageFile->getSize(),
                'path' => 'storage/' . $path,
                'collection' => 'avatar',
                'disk' => 'public',
            ]);
        }

        return $driver;
    }

    public function deleteDriver(Driver $driver): bool
    {
        Media::where('model_type', Driver::class)->where('model_id', $driver->id)->delete();
        return $driver->delete();
    }
}
