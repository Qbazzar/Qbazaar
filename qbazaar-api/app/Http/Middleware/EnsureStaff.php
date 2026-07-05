<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the custom /manage panel to staff roles only. Mirrors the access rule
 * enforced by the Filament panel (super_admin / moderator / support) so both
 * surfaces share the same authorization boundary.
 */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('manage.login');
        }

        if (! $user->hasAnyRole(['super_admin', 'moderator', 'support'])) {
            abort(403);
        }

        return $next($request);
    }
}
