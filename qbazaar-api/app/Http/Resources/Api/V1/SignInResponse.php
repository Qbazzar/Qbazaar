<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Services\Auth\SignInResult;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shapes every sign-in outcome the same way across password, email code,
 * Google / Apple and device verification:
 *  - 200 `{ user, tokens }` signed in, 201 when the call also created the account;
 *  - 202 `{ device_verification_required, challenge_token, … }` held at the
 *    new-device check.
 */
final readonly class SignInResponse
{
    public function __construct(
        private SignInResult $result,
    ) {}

    public function toResponse(): JsonResponse
    {
        $challenge = $this->result->challenge;

        if ($challenge !== null) {
            return response()->json([
                'device_verification_required' => true,
                'challenge_token' => $challenge->token,
                'sent_to' => $challenge->maskedPhone,
                'expires_in' => $challenge->expiresIn,
                'can_resend_in' => $challenge->canResendIn,
            ], Response::HTTP_ACCEPTED);
        }

        assert($this->result->tokens !== null);

        return response()->json(
            (new AuthResponseResource($this->result->user, $this->result->tokens))->toArray(),
            $this->result->registered ? Response::HTTP_CREATED : Response::HTTP_OK,
        );
    }
}
