<?php

namespace App\Services;

use App\Contracts\GpsAttendancePolicy;

class ConfiguredGpsAttendancePolicy implements GpsAttendancePolicy
{
    public function __construct(private readonly array $configuration = []) {}

    public function evaluate(int $schoolId, float $latitude, float $longitude, array $metadata = []): array
    {
        $config = $this->configuration['schools'][$schoolId] ?? null;
        if (! is_array($config)
            || ! is_numeric($config['latitude'] ?? null)
            || ! is_numeric($config['longitude'] ?? null)
            || ! is_numeric($config['radius_meters'] ?? null)
            || (float) $config['radius_meters'] <= 0) {
            return ['accepted' => false, 'reason' => 'Konfigurasi GPS sekolah tidak tersedia.'];
        }

        $distance = $this->distanceMeters($latitude, $longitude, (float) $config['latitude'], (float) $config['longitude']);
        if ($distance > (float) $config['radius_meters']) {
            return ['accepted' => false, 'reason' => 'Koordinat berada di luar radius sekolah.'];
        }

        return [
            'accepted' => true,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'photo_path' => $metadata['photo_path'] ?? null,
            'distance_meters' => round($distance, 2),
        ];
    }

    private function distanceMeters(float $latitude, float $longitude, float $targetLatitude, float $targetLongitude): float
    {
        $earthRadius = 6371000;
        $latDelta = deg2rad($targetLatitude - $latitude);
        $longitudeDelta = deg2rad($targetLongitude - $longitude);
        $latitude = deg2rad($latitude);
        $targetLatitude = deg2rad($targetLatitude);
        $a = sin($latDelta / 2) ** 2 + cos($latitude) * cos($targetLatitude) * sin($longitudeDelta / 2) ** 2;

        return $earthRadius * 2 * asin(min(1, sqrt($a)));
    }
}
