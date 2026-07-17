<?php

namespace App\Http\Middleware;

use App\Services\SchoolContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchoolIsSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $context = app(SchoolContext::class);
        $context->ensureDefaultSchool($user);

        if (! session('active_school_id') && $context->availableSchools($user)->count() > 1) {
            return redirect('/school/select');
        }

        return $next($request);
    }
}
