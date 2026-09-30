<?php

namespace App\Services;

class PortalMapService
{
    public function forSchool(int $schoolId): array
    {
        $location = config('services.attendance.gps.schools.'.$schoolId);
        $token = config('services.mapbox.public_token');

        if (! filled($token) || ! is_array($location)
            || ! is_numeric($location['latitude'] ?? null)
            || ! is_numeric($location['longitude'] ?? null)
            || ! is_numeric($location['radius_meters'] ?? null)) {
            return ['enabled' => false];
        }

        return ['enabled' => true, 'token' => $token, 'style' => config('services.mapbox.style', 'mapbox://styles/mapbox/streets-v12'), 'center' => [(float) $location['longitude'], (float) $location['latitude']], 'radiusMeters' => (float) $location['radius_meters']];
    }
}
