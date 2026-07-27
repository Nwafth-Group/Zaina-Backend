<?php

namespace App\Service\Admin;

use App\Models\ServiceZone;

class ServiceZoneService
{
    public function getPaginated(array $filters, int $perPage = 15)
    {
        $query = ServiceZone::with('services:id,name_ar,name_en');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('governorate', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('name', 'asc')->paginate($perPage);
    }

    public function getCounts()
    {
        return [
            'all' => ServiceZone::count(),
            'active' => ServiceZone::where('status', 'active')->count(),
            'inactive' => ServiceZone::where('status', 'inactive')->count(),
        ];
    }

    public function createZone(array $data): ServiceZone
    {
        $zone = ServiceZone::create([
            'name' => $data['name'],
            'governorate' => $data['governorate'] ?? null,
            'city' => $data['city'] ?? null,
            'polygon_coordinates' => $data['polygon_coordinates'],
            'status' => $data['status'],
        ]);

        if (!empty($data['service_ids'])) {
            $zone->services()->sync($data['service_ids']);
        }

        return $zone->load('services:id,name_ar,name_en');
    }

    public function updateZone(ServiceZone $zone, array $data): ServiceZone
    {
        $zone->update([
            'name' => $data['name'],
            'governorate' => $data['governorate'] ?? null,
            'city' => $data['city'] ?? null,
            'polygon_coordinates' => $data['polygon_coordinates'],
            'status' => $data['status'],
        ]);

        if (isset($data['service_ids'])) {
            $zone->services()->sync($data['service_ids']);
        }

        return $zone->load('services:id,name_ar,name_en');
    }

    public function deleteZone(ServiceZone $zone): bool
    {
        $zone->services()->detach();
        return $zone->delete();
    }

    public function findZoneForPoint(float $lat, float $lng): ?ServiceZone
    {
        $activeZones = ServiceZone::where('status', 'active')->with('services:id,name_ar,name_en')->get();

        foreach ($activeZones as $zone) {
            if ($zone->containsPoint($lat, $lng)) {
                return $zone;
            }
        }

        return null;
    }
}
