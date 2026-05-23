<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Uploads;

use App\Actions\Account\UploadAvatarAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Account\UploadAvatarRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

/**
 * @group Uploads
 */
class AvatarUploadController extends Controller
{
    /**
     * Replace the signed-in user's avatar.
     *
     * Multipart upload with the `avatar` field. We validate MIME + 5 MB cap
     * via the FormRequest, push the file through Spatie MediaLibrary so the
     * `thumb` + `medium` conversions run synchronously, then return the
     * three URLs for the frontend to render immediately.
     *
     * @authenticated
     *
     * @response 200 scenario="Uploaded" {
     *   "success": true,
     *   "data": {
     *     "avatar_url": "https://cdn.qbazaar.qa/avatars/u_01.jpg",
     *     "avatar_thumb_url": "https://cdn.qbazaar.qa/avatars/u_01-thumb.jpg",
     *     "avatar_medium_url": "https://cdn.qbazaar.qa/avatars/u_01-medium.jpg"
     *   }
     * }
     */
    public function __invoke(UploadAvatarRequest $request, UploadAvatarAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('manageAvatar', $user);

        /** @var UploadedFile $file */
        $file = $request->file('avatar');

        $action->execute($user, $file);

        // Refresh so the freshly-added Media row is visible to the URL
        // accessors on the model.
        $user->refresh();

        return response()->json([
            'avatar_url' => $user->avatarOriginalUrl(),
            'avatar_thumb_url' => $user->avatarThumbUrl(),
            'avatar_medium_url' => $user->avatarMediumUrl(),
        ]);
    }
}
