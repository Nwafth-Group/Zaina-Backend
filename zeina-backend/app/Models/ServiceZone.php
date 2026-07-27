<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'governorate',
        'city',
        'polygon_coordinates',
        'status',
    ];

    protected $casts = [
        'polygon_coordinates' => 'array',
    ];

    public function services()
    {
        return $this->belongsToMany(Service::class, 'service_service_zones', 'service_zone_id', 'service_id');
    }

    /**
     * Ray-Casting Algorithm to check if a point (lat, lng) is inside the polygon.
     */
    public function containsPoint($lat, $lng): bool
    {
        $points = $this->polygon_coordinates;
        if (!is_array($points) || count($points) < 3) {
            return false;
        }

        $inside = false;
        $numPoints = count($points);

        for ($i = 0, $j = $numPoints - 1; $i < $numPoints; $j = $i++) {
            $xi = (float) ($points[$i]['lat'] ?? $points[$i][0] ?? 0);
            $yi = (float) ($points[$i]['lng'] ?? $points[$i][1] ?? 0);
            $xj = (float) ($points[$j]['lat'] ?? $points[$j][0] ?? 0);
            $yj = (float) ($points[$j]['lng'] ?? $points[$j][1] ?? 0);

            $intersect = (($yi > $lng) !== ($yj > $lng)) &&
                ($lat < ($xj - $xi) * ($lng - $yi) / (($yj - $yi) ?: 0.000001) + $xi);

            if ($intersect) {
                $inside = !$inside;
            }
        }

        return $inside;
    }
}
