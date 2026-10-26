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
        if (! $context->ensureDefaultSchool($user)) {
            abort_unless($request->isMethod('GET') || $request->isMethod('HEAD'), 403);

            return redirect()->route('school.select');
        }

        return $next($request);
    }
}
