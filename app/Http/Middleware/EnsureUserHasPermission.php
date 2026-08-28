<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function handle(
        Request $request,
        Closure $next,
        string $permission,
        string ...$defaultRoles,
    ): Response {
        $user = $request->user();

        if (! $user || ! app(PermissionService::class)->can($user, $permission, $defaultRoles)) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
