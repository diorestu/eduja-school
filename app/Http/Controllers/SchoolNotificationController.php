<?php

namespace App\Http\Controllers;

use App\Models\SchoolNotification;
use App\Services\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SchoolNotificationController extends Controller
{
    public function read(Request $request, SchoolNotification $notification, SchoolContext $context): RedirectResponse
    {
        abort_unless((int) $notification->school_id === $context->activeSchoolIdFor($request->user()), 404);
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 403);
        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return redirect($notification->url);
    }
}
