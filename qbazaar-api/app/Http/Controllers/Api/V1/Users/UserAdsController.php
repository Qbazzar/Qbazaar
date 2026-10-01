<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Users;

use App\Enums\UserStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Ads\AdSummaryResource;
use App\Models\Ad;
use App\Models\User;
use App\Services\Ads\ViewerFavorites;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Users
 */
class UserAdsController extends Controller
{
    private const PER_PAGE = 20;

    /**
     * Public, paginated list of a user's active ads.
     *
     * Mirrors the public feed ordering (latest published first) and is
     * restricted to ACTIVE ads via the model scope, so drafts, pending,
     * sold and expired listings never leak on a public profile. The page
     * total is the seller's stored active-ads counter rather than a COUNT.
     *
     * @unauthenticated
     *
     * @throws DomainException
     */
    public function __invoke(Request $request, User $user, ViewerFavorites $favorites): AnonymousResourceCollection
    {
        if ($user->status !== UserStatus::ACTIVE) {
            throw new DomainException(ErrorCode::USER_NOT_FOUND);
        }

        $paginator = Ad::query()
            ->forUser($user)
            ->active()
            ->orderedForFeed()
            ->with(['category', 'location', 'primaryImage'])
            ->paginate(self::PER_PAGE, total: $user->active_ads_count);

        $favorites->mark($this->viewer($request), $paginator->items());

        return AdSummaryResource::collection($paginator);
    }
}
