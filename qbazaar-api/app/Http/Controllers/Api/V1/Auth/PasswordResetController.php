<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\ResetPasswordAction;
use App\Actions\Auth\SendPasswordResetLinkAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forgot- and reset-password endpoints.
 *
 *  - `forgot()` is intentionally silent on unknown email (anti-enumeration);
 *    the response is always 202 + `{ message_key }`.
 *  - `reset()` returns 200 on success. On a bad/expired token the action
 *    raises a VALIDATION_FAILED DomainException with field-level detail so
 *    the contract's 422 ValidationError shape is preserved.
 *
 * @group Auth
 */
class PasswordResetController extends Controller
{
    /**
     * Send password-reset link
     *
     * Always returns 202, even on unknown emails (anti-enumeration). Dispatches
     * a localised reset-password email when the address matches an account.
     *
     * @unauthenticated
     *
     * @response 202 scenario="Success" {
     *   "success": true,
     *   "data": { "message_key": "messages.auth.reset_link_sent" }
     * }
     */
    public function forgot(ForgotPasswordRequest $request, SendPasswordResetLinkAction $action): JsonResponse
    {
        $action->execute((string) $request->validated('email'));

        return response()->json(
            ['message_key' => 'messages.auth.reset_link_sent'],
            Response::HTTP_ACCEPTED,
        );
    }

    /**
     * Reset the password using a token
     *
     * Verifies the (email, token) pair via Laravel's Password broker and sets
     * the new password. Also burns ALL refresh tokens + Sanctum PATs for the
     * user — every device is logged out.
     *
     * @unauthenticated
     *
     * @response 200 scenario="Success" {
     *   "success": true,
     *   "data": { "message_key": "messages.auth.password_reset_success" }
     * }
     *
     * @response 422 scenario="Invalid token" {
     *   "success": false,
     *   "error": {
     *     "code": "VALIDATION_FAILED",
     *     "message_key": "errors.validation.failed",
     *     "message": "The given data was invalid.",
     *     "details": { "token": ["This password reset token is invalid."] }
     *   }
     * }
     */
    public function reset(ResetPasswordRequest $request, ResetPasswordAction $action): JsonResponse
    {
        $action->execute(
            email: (string) $request->validated('email'),
            token: (string) $request->validated('token'),
            password: (string) $request->validated('password'),
        );

        return response()->json([
            'message_key' => 'messages.auth.password_reset_success',
        ]);
    }
}
