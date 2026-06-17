<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
 *
 * @group Auth
 */
class EmailVerificationController extends Controller
{
    /**
     * (Re)send the email-verification link
     *
     * @authenticated
     *
     * @response 202 scenario="Sent" {
     *   "success": true,
     *   "data": { "message_key": "messages.auth.email_verification_sent" }
     * }
     *
     * @response 202 scenario="Already verified" {
     *   "success": true,
     *   "data": { "message_key": "messages.auth.email_already_verified" }
     * }
     */
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

    /**
     * Verify email via signed URL
     *
     * Hit via the link in the verification email. Backed by Laravel's `signed`
     * middleware — tampered/expired URLs respond 403 before this method runs.
     *
     * @urlParam id string required ULID of the user. Example: 01HF5KX9Y6XR7Z9R3E0HK2X6FC
     * @urlParam hash string required `sha1(user.email)` — defence-in-depth on top of the signature. Example: 04b9f5...
     *
     * @unauthenticated
     *
     * @response 200 scenario="Verified" {
     *   "success": true,
     *   "data": {
     *     "email_verified": true,
     *     "message_key": "messages.auth.email_verified"
     *   }
     * }
     *
     * @response 200 scenario="Already verified" {
     *   "success": true,
     *   "data": {
     *     "email_verified": true,
     *     "message_key": "messages.auth.email_already_verified"
     *   }
     * }
     */
    public function verify(Request $request, string $id, string $hash): Response
    {
        // XHR callers (the frontend re-validating) get JSON and exceptions;
        // browsers hitting the link directly get redirected to the web app's
        // result page so they never see raw JSON.
        $wantsHtml = ! $request->expectsJson()
            || str_contains((string) $request->header('Accept'), 'text/html');

        /** @var User|null $user */
        $user = User::query()->find($id);

        if ($user === null) {
            if ($wantsHtml) {
                return $this->redirectToResult('invalid');
            }

            throw new DomainException(ErrorCode::USER_NOT_FOUND);
        }

        // Defence in depth: the `signed` route middleware already verified
        // the URL signature, but we still recompute the hash on the email
        // address so a signed URL minted for user A can't verify user B
        // even if the IDs were swapped after signing.
        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            if ($wantsHtml) {
                return $this->redirectToResult('invalid');
            }

            throw new DomainException(ErrorCode::AUTH_TOKEN_INVALID);
        }

        if ($user->hasVerifiedEmail()) {
            if ($wantsHtml) {
                return $this->redirectToResult('already');
            }

            return response()->json([
                'email_verified' => true,
                'message_key' => 'messages.auth.email_already_verified',
            ]);
        }

        $user->markEmailAsVerified();
        Event::dispatch(new Verified($user));

        if ($wantsHtml) {
            return $this->redirectToResult('success');
        }

        return response()->json([
            'email_verified' => true,
            'message_key' => 'messages.auth.email_verified',
        ]);
    }

    /**
     * Redirect a browser to the web app's verification-result page.
     */
    private function redirectToResult(string $status): RedirectResponse
    {
        $webBase = rtrim((string) config('qbazaar.web_url'), '/');

        return redirect()->away($webBase . '/verify-email/result?status=' . $status);
    }
}
