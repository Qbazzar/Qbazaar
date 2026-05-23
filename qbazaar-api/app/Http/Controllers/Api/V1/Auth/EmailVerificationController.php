<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpFoundation\Response;

/**
 * Email-verification endpoints.
 *
 *  - `send()`  — POST, auth:sanctum required. (Re)issues the verification mail.
 *    Returns 202; idempotent: if the user is already verified we still 202
 *    (with a different message_key) so the UI doesn't have to special-case.
 *  - `verify()` — GET, signed URL middleware verifies the signature.
 *    Idempotent: already-verified users still get 200, NOT 410. We treat 410
 *    only for genuinely-invalid signatures (caught by the signed middleware
 *    itself before this method runs).
 */
class EmailVerificationController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(
                ['message_key' => 'messages.auth.email_already_verified'],
                Response::HTTP_ACCEPTED,
            );
        }

        $user->sendEmailVerificationNotification();

        return response()->json(
            ['message_key' => 'messages.auth.email_verification_sent'],
            Response::HTTP_ACCEPTED,
        );
    }

    public function verify(Request $request, string $id, string $hash): JsonResponse
    {
        /** @var User|null $user */
        $user = User::query()->find($id);

        if ($user === null) {
            throw new DomainException(ErrorCode::USER_NOT_FOUND);
        }

        // Defence in depth: the `signed` route middleware already verified
        // the URL signature, but we still recompute the hash on the email
        // address so a signed URL minted for user A can't verify user B
        // even if the IDs were swapped after signing.
        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            throw new DomainException(ErrorCode::AUTH_TOKEN_INVALID);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'email_verified' => true,
                'message_key' => 'messages.auth.email_already_verified',
            ]);
        }

        $user->markEmailAsVerified();
        Event::dispatch(new Verified($user));

        return response()->json([
            'email_verified' => true,
            'message_key' => 'messages.auth.email_verified',
        ]);
    }
}
