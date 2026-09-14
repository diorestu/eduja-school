<?php

namespace App\Contracts;

interface GpsAttendancePolicy
{
    /** @return array{accepted: bool, reason?: string, latitude?: float, longitude?: float, photo_path?: string|null} */
    public function evaluate(int $schoolId, float $latitude, float $longitude, array $metadata = []): array;
}
