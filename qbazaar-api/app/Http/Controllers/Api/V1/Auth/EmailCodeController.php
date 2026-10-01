<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\SendEmailSignInCodeAction;
use App\Actions\Auth\VerifyEmailSignInCodeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\EmailCodeSendRequest;
use App\Http\Requests\Api\V1\Auth\EmailCodeVerifyRequest;
use App\Http\Resources\Api\V1\SignInResponse;
use App\Services\Auth\DeviceFingerprintService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Passwordless sign-in and sign-up with a code mailed to the user.
 *
 * @group Auth
 */
class EmailCodeController extends Controller
{
    /**
     * Send a sign-in code to an email
     *
     * Answers 202 for any address so it cannot be used to discover accounts.
     *
     * @unauthenticated
     */
    public function send(EmailCodeSendRequest $request, SendEmailSignInCodeAction $action): JsonResponse
    {
        $result = $action->execute((string) $request->validated('email'));

        return response()->json([
            'sent_to' => $result->recipient,
            'expires_in' => $result->expiresIn,
            'can_resend_in' => $result->canResendIn,
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Verify the email code and sign in (or sign up)
     *
     * 200 signed in, 201 account created, 202 new-device SMS challenge,
     * 422 AUTH_011 when no account uses the email and no sign-up details came.
     *
     * @unauthenticated
     */
    public function verify(EmailCodeVerifyRequest $request, VerifyEmailSignInCodeAction $action, DeviceFingerprintService $devices): JsonResponse
    {
        $result = $action->execute(
            email: (string) $request->validated('email'),
            code: (string) $request->validated('code'),
            registration: $request->registrationDetails(),
            device: $devices->contextFromRequest($request),
        );

        return (new SignInResponse($result))->toResponse();
    }
}
