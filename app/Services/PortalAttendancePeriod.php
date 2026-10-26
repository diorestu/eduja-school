<?php
namespace App\Services;

use Illuminate\Support\Carbon;

class PortalAttendancePeriod
{
    public function dates(): array
    {
        $data = request()->validate(['period'=>['nullable','in:day,week,month,semester'],'attendance_date'=>['nullable','date_format:Y-m-d']]);
        $anchor = Carbon::parse($data['attendance_date'] ?? today()->toDateString());
        [$start,$end] = match ($data['period'] ?? 'month') {
            'day'=>[$anchor->copy()->startOfDay(),$anchor->copy()->endOfDay()],
            'week'=>[$anchor->copy()->startOfWeek(),$anchor->copy()->endOfWeek()],
            'semester'=>[$anchor->copy()->month($anchor->month<=6?1:7)->startOfMonth(),$anchor->copy()->month($anchor->month<=6?6:12)->endOfMonth()],
            default=>[$anchor->copy()->startOfMonth(),$anchor->copy()->endOfMonth()],
        };
        return [$start->toDateString(),$end->toDateString()];
    }
}
