<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Business\SubmitBusinessApplicationAction;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Account\SubmitBusinessApplicationRequest;
use App\Http\Resources\Api\V1\Account\BusinessApplicationResource;
use App\Models\User;
use App\Services\Business\BusinessApplicationQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in user's application to become a verified business: read its
 * state, apply, or answer staff's request for more information.
 *
 * @group Account
 */
class BusinessApplicationController extends Controller
{
    /**
     * GET /api/v1/account/business-application — the latest application, or
     * null when the user never applied.
     *
     * @authenticated
     */
    public function show(Request $request, BusinessApplicationQueue $applications): JsonResponse
    {
        $application = $applications->latestOf($this->applicant($request));

        if ($application === null) {
            // A bare null body would encode as {}; the envelope keeps `data` null.
            return response()->json(['success' => true, 'data' => null]);
        }

        return response()->json((new BusinessApplicationResource($application))->toArray($request));
    }

    /**
     * POST /api/v1/account/business-application — apply, or resubmit an
     * application staff asked more information about.
     *
     * @authenticated
     *
     * @throws DomainException BIZ_002 while an application waits for review, BIZ_004 when already verified
     */
    public function store(SubmitBusinessApplicationRequest $request, SubmitBusinessApplicationAction $submit): JsonResponse
    {
        $application = $submit->execute($this->applicant($request), $request->details(), $request->documents());
        $application->load('media');

        return response()->json((new BusinessApplicationResource($application))->toArray($request), 201);
    }

    private function applicant(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('update', $user);

        return $user;
    }
}
