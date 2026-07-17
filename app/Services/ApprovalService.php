<?php

namespace App\Services;

use App\Models\ApprovalRequest;

class ApprovalService
{
    public function pending(?int $schoolId = null)
    {
        return ApprovalRequest::query()
            ->when($schoolId, fn ($query) => $query->where('school_id', $schoolId))
            ->where('status', 'pending')
            ->latest()
            ->get();
    }
}
