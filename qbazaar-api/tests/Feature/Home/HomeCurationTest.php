<?php

declare(strict_types=1);

use App\Actions\Catalog\GetHomeFeedAction;
use App\Enums\HomeSection;
use App\Enums\HomeSectionMode;
use App\Models\Ad;
use App\Models\HomeSectionItem;
use App\Models\User;
use App\Services\Business\CompanyVerification;
use App\Services\Business\FeaturedCompanyCurator;
use App\Services\Catalog\HomeSectionCurator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\getJson;

use Tests\Concerns\CreatesAds;

uses(RefreshDatabase::class, CreatesAds::class);

beforeEach(function (): void {
    Notification::fake();
    $this->seedReferenceData();
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create()->assignRole('super_admin');
    $this->seller = User::factory()->create();
});

/**
 * @return list<string>
 */
function homeRailIds(string $rail): array
{
    return collect(getJson('/api/v1/home')->assertOk()->json('data.' . $rail))->pluck('id')->all();
}

describe('hand-picked rails', function (): void {
    it('shows the listed picks of a manual section in staff order', function (): void {
        $curator = app(HomeSectionCurator::class);
        $first = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'published_at' => now()->subDays(20)]);
        $second = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'published_at' => now()->subDays(25)]);
        Ad::factory()->active()->create(['user_id' => $this->seller->id]);

        $curator->add($this->admin, HomeSection::RECOMMENDED, $second);
        $curator->add($this->admin, HomeSection::RECOMMENDED, $first);
        $curator->reorder($this->admin, HomeSection::RECOMMENDED, [
            HomeSectionItem::query()->where('ad_id', $first->id)->value('id') => 1,
        ]);
        $curator->setMode($this->admin, HomeSection::RECOMMENDED, HomeSectionMode::MANUAL);

        expect(homeRailIds('recommended'))->toBe([$first->id, $second->id]);
    });

    it('skips picks that are no longer listed', function (): void {
        $curator = app(HomeSectionCurator::class);
        $live = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'favorites_count' => 1]);
        $soon = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'favorites_count' => 1]);
        $curator->add($this->admin, HomeSection::BEST_SELLING, $soon);
        $curator->add($this->admin, HomeSection::BEST_SELLING, $live);
        $curator->setMode($this->admin, HomeSection::BEST_SELLING, HomeSectionMode::MANUAL);

        $soon->forceFill(['status' => 'sold'])->save();

        expect(homeRailIds('best_selling'))->toBe([$live->id]);
    });

    it('falls back to the automatic rule when no pick is listed any more', function (): void {
        $curator = app(HomeSectionCurator::class);
        $loved = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'favorites_count' => 40]);
        $picked = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'favorites_count' => 1]);
        $curator->add($this->admin, HomeSection::BEST_SELLING, $picked);
        $curator->setMode($this->admin, HomeSection::BEST_SELLING, HomeSectionMode::MANUAL);

        $picked->forceFill(['status' => 'expired'])->save();

        expect(homeRailIds('best_selling'))->toBe([$loved->id]);
    });

    it('ignores the picks while the section runs automatically', function (): void {
        $loved = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'favorites_count' => 40]);
        $picked = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'favorites_count' => 1]);
        app(HomeSectionCurator::class)->add($this->admin, HomeSection::BEST_SELLING, $picked);

        expect(homeRailIds('best_selling'))->toBe([$loved->id, $picked->id]);
    });

    it('serves a staff change on the next request, without waiting for the warmer', function (): void {
        $loved = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'favorites_count' => 40]);
        $picked = Ad::factory()->active()->create(['user_id' => $this->seller->id, 'favorites_count' => 1]);

        expect(homeRailIds('best_selling'))->toBe([$loved->id, $picked->id]);

        $curator = app(HomeSectionCurator::class);
        $curator->add($this->admin, HomeSection::BEST_SELLING, $picked);
        $curator->setMode($this->admin, HomeSection::BEST_SELLING, HomeSectionMode::MANUAL);

        expect(homeRailIds('best_selling'))->toBe([$picked->id]);
    });

    it('reads the curation with a fixed number of queries', function (): void {
        $curator = app(HomeSectionCurator::class);
        $curator->setMode($this->admin, HomeSection::RECOMMENDED, HomeSectionMode::MANUAL);
        $curator->add($this->admin, HomeSection::RECOMMENDED, Ad::factory()->active()->create(['user_id' => $this->seller->id]));

        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            app(GetHomeFeedAction::class)->execute();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $count(); // warms the category tree and place counters, cached apart from the feed
        $few = $count();

        foreach (range(1, 8) as $i) {
            $curator->add($this->admin, HomeSection::RECOMMENDED, Ad::factory()->active()->create(['user_id' => User::factory()->create()->id]));
        }

        expect($count())->toBe($few);
    });
});

describe('featured companies on the home screen', function (): void {
    it('leads the sellers rail with the featured companies in staff order, then the busiest shops', function (): void {
        $busy = User::factory()->business()->create();
        Ad::factory()->count(3)->active()->create(['user_id' => $busy->id]);
        $quietFeatured = User::factory()->business()->create();
        $topFeatured = User::factory()->business()->create();
        Ad::factory()->active()->create(['user_id' => $topFeatured->id]);
        $hiddenFeatured = User::factory()->business()->suspended()->create();

        $curator = app(FeaturedCompanyCurator::class);
        $curator->feature($this->admin, $topFeatured);
        $curator->feature($this->admin, $hiddenFeatured);
        $curator->feature($this->admin, $quietFeatured);
        app(CompanyVerification::class)->verify($this->admin, $topFeatured);

        $sellers = getJson('/api/v1/home')->assertOk()->json('data.featured_sellers');

        expect(collect($sellers)->pluck('id')->all())->toBe([$topFeatured->id, $quietFeatured->id, $busy->id])
            ->and($sellers[0]['is_featured'])->toBeTrue()
            ->and($sellers[0]['business_verified'])->toBeTrue()
            ->and($sellers[1]['business_verified'])->toBeFalse()
            ->and($sellers[2]['is_featured'])->toBeFalse();
    });
});
