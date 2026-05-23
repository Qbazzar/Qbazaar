<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Account\BlockedUserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Account
 */
class BlockedUsersController extends Controller
{
    /**
     * Paginated list of users the caller has blocked.
     *
     * Page size is 20 (matches the platform default for paginated lists);
     * older blocks appear last.
     *
     * @authenticated
     */
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('view', $user);

        $paginator = $user->blockedUsers()
            ->orderByPivot('created_at', 'desc')
            ->paginate(20);

        return BlockedUserResource::collection($paginator);
    }
}
