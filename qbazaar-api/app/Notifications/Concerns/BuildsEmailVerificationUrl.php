<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Builds the email-verification link that lands the user on the qbazaar-web
 * verification page (NOT the raw API route, which would render JSON in a
 * browser).
 *
 * We still mint a signed URL for the API route `api.v1.auth.verify-email` so
 * the `signature`/`expires` pair stays valid for the API call the frontend
 * re-issues. We then lift those query params onto the frontend URL:
 *
 *   {web_url}/verify-email?id=&hash=&expires=&signature=
 *
 * Shared between EmailVerificationNotification and WelcomeNotification so the
 * signing recipe lives in exactly one place.
 */
trait BuildsEmailVerificationUrl
{
    private function buildFrontendVerificationUrl(User $user): string
    {
        $id = (string) $user->getKey();
        $hash = sha1($user->getEmailForVerification());
        $minutes = (int) config('auth.verification.expire', 60);

        $signedApiUrl = URL::temporarySignedRoute(
            'api.v1.auth.verify-email',
            Carbon::now()->addMinutes($minutes),
            ['id' => $id, 'hash' => $hash],
        );

        parse_str((string) parse_url($signedApiUrl, PHP_URL_QUERY), $params);

        $query = http_build_query([
            'id' => $id,
            'hash' => $hash,
            'expires' => $params['expires'] ?? '',
            'signature' => $params['signature'] ?? '',
        ]);

        $webBase = rtrim((string) config('qbazaar.web_url'), '/');

        return $webBase . '/verify-email?' . $query;
    }
}
