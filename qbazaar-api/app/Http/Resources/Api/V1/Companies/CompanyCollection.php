<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Companies;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Str;

/**
 * A page of the companies directory. Expects `businessProfile` and the cover
 * media loaded by CompanyDirectory.
 */
class CompanyCollection extends ResourceCollection
{
    private const ABOUT_EXCERPT_LENGTH = 160;

    /**
     * @param LengthAwarePaginator<int, User> $page
     * @param array<string, true> $viewerFollows ids of listed companies the viewer follows
     */
    public function __construct(
        LengthAwarePaginator $page,
        private readonly array $viewerFollows,
    ) {
        parent::__construct($page);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        $rows = [];

        foreach ($this->collection ?? [] as $company) {
            if ($company instanceof User) {
                $rows[] = $this->row($company);
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(User $company): array
    {
        $profile = $company->businessProfile;

        return [
            'id' => $company->id,
            'business_name' => $profile->business_name ?? $company->full_name,
            'about' => $profile?->about !== null ? Str::limit($profile->about, self::ABOUT_EXCERPT_LENGTH) : null,
            'avatar_url' => $company->avatar_url,
            'cover_url' => $company->businessCoverUrl(),
            'followers_count' => $company->followers_count,
            'active_ads_count' => $company->active_ads_count,
            'rating_avg' => (float) $company->rating_avg,
            'rating_count' => $company->rating_count,
            'is_following' => isset($this->viewerFollows[$company->id]),
            'joined_at' => $company->created_at->toIso8601String(),
        ];
    }
}
