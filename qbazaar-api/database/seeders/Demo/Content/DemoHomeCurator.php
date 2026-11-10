<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Content;

use App\Enums\AdStatus;
use App\Enums\HomeSection;
use App\Enums\HomeSectionMode;
use App\Models\Ad;
use App\Models\BusinessProfile;
use App\Models\HomeSectionItem;
use App\Models\User;
use App\Services\Business\FeaturedCompanyCurator;
use App\Services\Catalog\HomeSectionCurator;
use Database\Seeders\Demo\DemoOptions;

/**
 * Fills the home page curation the way staff would in the admin panel: the
 * "Best selling" rail with the design's featured ads and the featured
 * companies with its business sellers. It only fills what staff have not
 * curated yet, so it never overrides a real choice.
 */
final class DemoHomeCurator
{
    public function __construct(
        private readonly DesignManifest $design,
        private readonly HomeSectionCurator $sections,
        private readonly FeaturedCompanyCurator $companies,
    ) {}

    /**
     * @return array{ads: int, companies: int} what was (or in dry-run would be) put on the home page
     */
    public function curate(bool $dryRun): array
    {
        $admin = User::query()->where('email', 'super-admin@' . DemoOptions::EMAIL_DOMAIN)->first();

        if ($admin === null) {
            return ['ads' => 0, 'companies' => 0];
        }

        return [
            'ads' => $this->curateBestSelling($admin, $dryRun),
            'companies' => $this->featureCompanies($admin, $dryRun),
        ];
    }

    private function curateBestSelling(User $admin, bool $dryRun): int
    {
        $section = HomeSection::BEST_SELLING;

        if (HomeSectionItem::query()->where('section', $section->value)->exists()) {
            return 0;
        }

        $ads = Ad::query()
            ->whereIn('title', $this->featuredTitles())
            ->where('status', AdStatus::ACTIVE->value)
            ->orderByDesc('price')
            ->limit($section->limit())
            ->get();

        if (! $dryRun && $ads->isNotEmpty()) {
            $this->sections->setMode($admin, $section, HomeSectionMode::MANUAL);
            $ads->each(fn (Ad $ad) => $this->sections->add($admin, $section, $ad));
        }

        return $ads->count();
    }

    private function featureCompanies(User $admin, bool $dryRun): int
    {
        if (BusinessProfile::query()->whereNotNull('featured_position')->exists()) {
            return 0;
        }

        $emails = [];

        foreach ($this->design->personas() as $persona) {
            if ($persona['kind'] === 'business') {
                $emails[] = $persona['email_local'] . '@' . DemoOptions::EMAIL_DOMAIN;
            }
        }

        $owners = User::query()->whereIn('email', $emails)->get()->filter(fn (User $user): bool => $user->isBusiness())->values();

        if (! $dryRun) {
            $owners->each(fn (User $owner) => $this->companies->feature($admin, $owner));
        }

        return $owners->count();
    }

    /**
     * @return list<string>
     */
    private function featuredTitles(): array
    {
        $titles = [];

        foreach ($this->design->ads() as $ad) {
            if (in_array('featured', $ad['badges'], true)) {
                array_push($titles, $ad['title']['en'], $ad['title']['ar']);
            }
        }

        return $titles;
    }
}
