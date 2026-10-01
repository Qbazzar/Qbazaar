<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Social\ReplaceBusinessCoverAction;
use App\Actions\Social\UpdateBusinessProfileAction;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Account\UpdateBusinessProfileRequest;
use App\Http\Requests\Api\V1\Account\UploadBusinessCoverRequest;
use App\Http\Resources\Api\V1\Users\BusinessProfileResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * The signed-in business account's own profile (about, legal info, contact,
 * opening hours, cover banner).
 *
 * @group Account
 */
class BusinessProfileController extends Controller
{
    /**
     * @authenticated
     *
     * @throws DomainException
     */
    public function show(Request $request): JsonResponse
    {
        $owner = $this->owner($request);

        if (! $owner->isBusiness()) {
            throw new DomainException(ErrorCode::BUSINESS_ACCOUNT_REQUIRED);
        }

        return $this->respond($request, $owner);
    }

    /**
     * @authenticated
     *
     * @throws DomainException
     */
    public function update(UpdateBusinessProfileRequest $request, UpdateBusinessProfileAction $updateProfile): JsonResponse
    {
        $owner = $this->owner($request);

        $updateProfile($owner, $request->profileAttributes());

        return $this->respond($request, $owner);
    }

    /**
     * @authenticated
     *
     * @throws DomainException
     */
    public function uploadCover(UploadBusinessCoverRequest $request, ReplaceBusinessCoverAction $cover): JsonResponse
    {
        $owner = $this->owner($request);

        /** @var UploadedFile $file */
        $file = $request->file('cover');
        $cover->upload($owner, $file);

        return $this->respond($request, $owner);
    }

    /**
     * @authenticated
     *
     * @throws DomainException
     */
    public function removeCover(Request $request, ReplaceBusinessCoverAction $cover): JsonResponse
    {
        $owner = $this->owner($request);

        $cover->remove($owner);

        return $this->respond($request, $owner);
    }

    private function owner(Request $request): User
    {
        /** @var User $owner */
        $owner = $request->user();

        $this->authorize('update', $owner);

        return $owner;
    }

    private function respond(Request $request, User $owner): JsonResponse
    {
        $owner->load(['businessProfile', 'media']);

        return response()->json((new BusinessProfileResource($owner, publicView: false))->toArray($request));
    }
}
