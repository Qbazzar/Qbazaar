<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\RegisterUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\AuthResponseResource;
use App\Services\Auth\DeviceFingerprintService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Auth
 */
class RegisterController extends Controller
{
    /**
     * Register a new user
     *
     * Validates per RegisterRequest (mirrors the OpenAPI RegisterRequest schema),
     * creates the user, and returns 201 with the standard AuthResponseEnvelope.
     *
     * Phone and email verification are deferred to Sprint 1 Wave 2 — registered
     * users are immediately usable (`status=active`) but `phone_verified` and
     * `email_verified` start false; downstream middleware will gate sensitive
     * actions on those flags later.
     *
     * @unauthenticated
     *
     * @response 201 scenario="Success" {
     *   "success": true,
     *   "data": {
     *     "user": {
     *       "id": "01HF5KX9Y6XR7Z9R3E0HK2X6FC",
     *       "full_name": "Ahmed Al-Ali",
     *       "email": "ahmed@example.qa",
     *       "phone": "+97455123456",
     *       "account_type": "private",
     *       "status": "active",
     *       "email_verified": false,
     *       "phone_verified": false,
     *       "language": "ar"
     *     },
     *     "tokens": {
     *       "access_token": "1|abcd...",
     *       "refresh_token": "rt_01hf5kx9y6xr7z9r3e0hk2x6fc...",
     *       "token_type": "Bearer",
     *       "expires_in": 900
     *     }
     *   }
     * }
     *
     * @response 422 scenario="Validation failed" {
     *   "success": false,
     *   "error": {
     *     "code": "VALIDATION_FAILED",
     *     "message_key": "errors.validation.failed",
     *     "message": "The given data was invalid.",
     *     "details": { "email": ["The email has already been taken."] }
     *   }
     * }
     */
    public function __invoke(RegisterRequest $request, RegisterUserAction $action, DeviceFingerprintService $fingerprints): JsonResponse
    {
        /** @var array{full_name:string,email:string,phone:string,password:string,account_type:string,language?:string} $validated */
        $validated = $request->validated();

        $result = $action->execute(
            $validated,
            $fingerprints->fingerprintFromRequest($request),
            (string) $request->ip(),
            $fingerprints->labelFromRequest($request),
        );

        return response()->json(
            (new AuthResponseResource($result['user'], $result['tokens']))->toArray(),
            Response::HTTP_CREATED,
        );
    }
}
