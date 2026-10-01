<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Switches off password sign-in and password sign-up once every client uses
 * email codes (AUTH_PASSWORD_LOGIN_ENABLED=false).
 */
class EnsurePasswordLoginEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('qbazaar.auth.password_login_enabled')) {
            throw new DomainException(ErrorCode::AUTH_PASSWORD_LOGIN_DISABLED);
        }

        return $next($request);
    }
}
