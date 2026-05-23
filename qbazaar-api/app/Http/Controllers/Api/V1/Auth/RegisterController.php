<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\RegisterUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\AuthResponseResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RegisterController extends Controller
{
    /**
     * POST /api/v1/auth/register
     *
     * Validates per RegisterRequest (mirrors the OpenAPI RegisterRequest schema),
     * creates the user, and returns 201 with the standard AuthResponseEnvelope.
     *
     * Phone and email verification are deferred to Sprint 1 Wave 2 — registered
     * users are immediately usable (`status=active`) but `phone_verified` and
     * `email_verified` start false; downstream middleware will gate sensitive
     * actions on those flags later.
     */
    public function __invoke(RegisterRequest $request, RegisterUserAction $action): JsonResponse
    {
        $platform = $request->attributes->get('client_platform');
        $fingerprint = is_string($platform) ? $platform : null;

        /** @var array{full_name:string,email:string,phone:string,password:string,account_type:string,language?:string} $validated */
        $validated = $request->validated();

        $result = $action->execute($validated, $fingerprint);

        return response()->json(
            (new AuthResponseResource($result['user'], $result['tokens']))->toArray(),
            Response::HTTP_CREATED,
        );
    }
}
