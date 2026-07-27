<?php

namespace App\Service\Admin;

use App\Models\DeparturePoint;

class DeparturePointService
{
    public function getPaginated(array $filters, int $perPage = 15)
    {
        $query = DeparturePoint::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name', 'asc')->paginate($perPage);
    }

    public function getCounts()
    {
        return [
            'all' => DeparturePoint::count(),
        ];
    }

    public function createPoint(array $data): DeparturePoint
    {
        return DeparturePoint::create($data);
    }

    public function updatePoint(DeparturePoint $point, array $data): DeparturePoint
    {
        $point->update($data);
        return $point;
    }

    public function deletePoint(DeparturePoint $point): bool
    {
        return $point->delete();
    }
}
