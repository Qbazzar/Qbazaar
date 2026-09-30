<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the /admin panel to staff roles. Per-screen access is enforced on each
 * route with Spatie's `permission:` middleware.
 */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('admin.login');
        }

        if (! $user->isStaff()) {
            abort(403);
        }

        return $next($request);
    }
}
