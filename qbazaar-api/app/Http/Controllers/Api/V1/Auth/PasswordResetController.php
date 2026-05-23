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
 */
class PasswordResetController extends Controller
{
    public function forgot(ForgotPasswordRequest $request, SendPasswordResetLinkAction $action): JsonResponse
    {
        $action->execute((string) $request->validated('email'));

        return response()->json(
            ['message_key' => 'messages.auth.reset_link_sent'],
            Response::HTTP_ACCEPTED,
        );
    }

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
