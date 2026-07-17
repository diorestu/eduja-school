<?php

namespace App\Services;

use App\Models\BillingItem;

class BillingService
{
    public function activeBills(?int $schoolId = null)
    {
        return BillingItem::query()
            ->when($schoolId, fn ($query) => $query->where('school_id', $schoolId))
            ->latest()
            ->get();
    }
}
