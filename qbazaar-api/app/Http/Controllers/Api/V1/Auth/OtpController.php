<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\SendOtpAction;
use App\Actions\Auth\VerifyOtpAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\OtpSendRequest;
use App\Http\Requests\Api\V1\Auth\OtpVerifyRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phone-OTP endpoints. All three methods are deliberately thin — heavy
 * lifting lives in App\Actions\Auth\SendOtpAction / VerifyOtpAction so the
 * cooldown + max-per-hour rules can be unit-tested without bringing HTTP
 * into the picture.
 *
 * @group Auth
 */
class OtpController extends Controller
{
    /**
     * Send an OTP to a phone
     *
     * Issues a fresh 6-digit code and dispatches it via SMS (and email, when
     * a matching user exists). Returns 202.
     *
     * @unauthenticated
     *
     * @response 202 scenario="Success" {
     *   "success": true,
     *   "data": {
     *     "sent_to": "+97455123456",
     *     "expires_in": 300,
     *     "can_resend_in": 60
     *   }
     * }
     *
     * @response 429 scenario="Cooldown / hourly cap" {
     *   "success": false,
     *   "error": {
     *     "code": "AUTH_006",
     *     "message_key": "errors.auth.rate.limited",
     *     "message": "Too many auth requests. Please try again later.",
     *     "details": null
     *   }
     * }
     */
    public function send(OtpSendRequest $request, SendOtpAction $action): JsonResponse
    {
        $result = $action->execute((string) $request->validated('phone'));

        return response()->json(
            [
                'sent_to' => $result->phone,
                'expires_in' => $result->expiresIn,
                'can_resend_in' => $result->canResendIn,
            ],
            Response::HTTP_ACCEPTED,
        );
    }

    /**
     * Verify an OTP
     *
     * Returns 200 + { phone_verified: true } on success. Errors:
     *  - 410 AUTH_004 when the active OTP has expired
     *  - 422 AUTH_005 when the code is wrong or attempts exhausted
     *
     * @unauthenticated
     *
     * @response 200 scenario="Success" {
     *   "success": true,
     *   "data": { "phone_verified": true }
     * }
     *
     * @response 410 scenario="OTP expired" {
     *   "success": false,
     *   "error": {
     *     "code": "AUTH_004",
     *     "message_key": "errors.auth.otp.expired",
     *     "message": "OTP has expired.",
     *     "details": null
     *   }
     * }
     *
     * @response 422 scenario="Wrong code" {
     *   "success": false,
     *   "error": {
     *     "code": "AUTH_005",
     *     "message_key": "errors.auth.otp.invalid",
     *     "message": "Invalid OTP code.",
     *     "details": null
     *   }
     * }
     */
    public function verify(OtpVerifyRequest $request, VerifyOtpAction $action): JsonResponse
    {
        $result = $action->execute(
            phone: (string) $request->validated('phone'),
            code: (string) $request->validated('code'),
        );

        return response()->json([
            'phone_verified' => $result->phoneVerified,
        ]);
    }

    /**
     * Resend the active OTP
     *
     * Same response shape as send-otp — the cooldown / hourly-cap throttles
     * are enforced inside SendOtpAction so both endpoints stay aligned and
     * cannot be played against each other.
     *
     * @unauthenticated
     *
     * @response 202 scenario="Success" {
     *   "success": true,
     *   "data": {
     *     "sent_to": "+97455123456",
     *     "expires_in": 300,
     *     "can_resend_in": 60
     *   }
     * }
     */
    public function resend(OtpSendRequest $request, SendOtpAction $action): JsonResponse
    {
        $result = $action->execute((string) $request->validated('phone'));

        return response()->json(
            [
                'sent_to' => $result->phone,
                'expires_in' => $result->expiresIn,
                'can_resend_in' => $result->canResendIn,
            ],
            Response::HTTP_ACCEPTED,
        );
    }
}
