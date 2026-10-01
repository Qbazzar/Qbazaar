<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Companies;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Companies\ListCompaniesRequest;
use App\Http\Resources\Api\V1\Companies\CompanyCollection;
use App\Models\User;
use App\Services\Users\CompanyDirectory;
use App\Services\Users\FollowQueries;

/**
 * @group Companies
 */
class CompanyController extends Controller
{
    /**
     * GET /api/v1/companies — directory of active business accounts.
     *
     * @unauthenticated
     */
    public function __invoke(ListCompaniesRequest $request, CompanyDirectory $directory, FollowQueries $follows): CompanyCollection
    {
        $page = $directory->search($request->term());

        // Public route: the default guard is `web`, so read the optional Bearer token explicitly.
        /** @var User|null $viewer */
        $viewer = $request->user('sanctum');

        $companyIds = array_values(array_map(static fn (User $company): string => $company->id, $page->items()));

        return new CompanyCollection($page, $viewer === null ? [] : $follows->followedAmong($viewer, $companyIds));
    }
}
