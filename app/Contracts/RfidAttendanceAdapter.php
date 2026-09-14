<?php

namespace App\Contracts;

interface RfidAttendanceAdapter
{
    /** @return array{person_type?: string, person_id?: int, school_id?: int, source?: string, rfid_uid?: string, reason?: string} */
    public function resolve(string $uid, int $schoolId): array;
}
