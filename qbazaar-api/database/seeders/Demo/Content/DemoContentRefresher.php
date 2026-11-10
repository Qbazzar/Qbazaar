<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Content;

use App\Enums\AdStatus;
use App\Jobs\Catalog\WarmCatalogCacheJob;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\Demo\DemoMedia;
use Database\Seeders\Demo\DemoOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Brings the demo accounts of an existing database in line with the design:
 * their names, avatars and shop profiles, and their listings.
 *
 * It only ever reaches rows that belong to demo accounts (e-mail ending in
 * the demo domain). Old placeholder ads are removed, except those other
 * records depend on (orders, offers, chats, reviews, promotions, reports,
 * or favourites of real users): those stay and only get design photos.
 * Photo conversions and the search index are queued.
 *
 * @phpstan-import-type DesignAd from DesignManifest
 */
final class DemoContentRefresher
{
    private const int CHUNK = 100;

    /** Tables whose rows point at an ad and must outlive it. */
    private const array DEPENDENT_TABLES = ['orders', 'offers', 'conversations', 'purchase_requests', 'reviews', 'ad_promotions'];

    public function __construct(
        private readonly DesignManifest $design,
        private readonly DesignPersonaApplier $personas,
        private readonly DesignAdWriter $writer,
        private readonly DemoMedia $media,
        private readonly DemoAdRenewer $renewer,
        private readonly DemoHomeCurator $homeCurator,
        private readonly DemoSearchSync $search,
    ) {}

    public function run(RefreshOptions $options): RefreshReport
    {
        $dryRun = $options->dryRun;
        $report = new RefreshReport;
        $touched = [];
        $this->media->useConnection((string) config('queue.default'));

        Ad::withoutSyncingToSearch(function () use ($options, $dryRun, $report, &$touched): void {
            $owners = $this->updateAccounts($report, $dryRun);
            $this->retireOldAds($report, $dryRun, $touched);
            $this->writeDesignAds($owners, $report, $dryRun, $touched);
            $this->renew($options, $report, $touched);

            if ($options->curateHome) {
                ['ads' => $report->homeAdsCurated, 'companies' => $report->companiesFeatured] = $this->homeCurator->curate($dryRun);
            }
        });

        $report->adsReindexed = $dryRun ? count(array_unique($touched)) : $this->search->reindex($touched);

        if (! $dryRun) {
            WarmCatalogCacheJob::dispatch();
        }

        return $report;
    }

    /**
     * @param list<string> $touched
     */
    private function renew(RefreshOptions $options, RefreshReport $report, array &$touched): void
    {
        $renewal = $this->renewer->renew($options->dryRun, $options->days, $options->reviveExpired, restagger: true);

        $report->adsExtended = $renewal['extended'];
        $report->adsRevived = $renewal['revived'];
        array_push($touched, ...$renewal['ids']);
    }

    /**
     * @return array<string, User> demo accounts by the key of the design seller they play
     */
    private function updateAccounts(RefreshReport $report, bool $dryRun): array
    {
        $emails = [];

        foreach ($this->design->personas() as $persona) {
            $emails[$persona['email_local'] . '@' . DemoOptions::EMAIL_DOMAIN] = $persona;
        }

        $users = User::query()->whereIn('email', array_keys($emails))->get()->keyBy('email');
        $owners = [];

        foreach ($emails as $email => $persona) {
            $user = $users->get($email);

            if ($user === null) {
                $report->accountsMissing++;

                continue;
            }

            $report->accountsMatched++;
            $owners[$persona['key']] = $user;

            if (! $dryRun && $this->personas->apply($user, $persona)) {
                $report->accountsUpdated++;
            }
        }

        return $owners;
    }

    /**
     * @param list<string> $touched
     */
    private function retireOldAds(RefreshReport $report, bool $dryRun, array &$touched): void
    {
        $designTitles = $this->designTitles();

        Ad::query()
            ->whereIn('user_id', $this->demoUserIds())
            ->with(['media', 'category:id,slug'])
            ->chunkById(self::CHUNK, function (Collection $ads) use ($designTitles, $report, $dryRun, &$touched): void {
                $old = $ads->reject(fn (Ad $ad): bool => isset($designTitles[$ad->title]) || $this->hasOnlyDesignPhotos($ad));
                $blocked = $this->dependentAdIds($old->map(static fn (Ad $ad): string => $ad->id)->values()->all());

                $this->keepWithDesignPhotos($old->filter(fn (Ad $ad): bool => isset($blocked[$ad->id])), $report, $dryRun, $touched);
                $this->remove($old->reject(fn (Ad $ad): bool => isset($blocked[$ad->id])), $report, $dryRun);
            });
    }

    /**
     * Generated listings made by the demo seeder already carry design photos; only
     * ads with a drawn placeholder (or none) are old content.
     */
    private function hasOnlyDesignPhotos(Ad $ad): bool
    {
        $keys = $this->media->designKeysOf($ad->getMedia('images'));

        return $keys !== [] && ! in_array(null, $keys, true);
    }

    /**
     * @param Collection<int, Ad> $ads
     * @param list<string> $touched
     */
    private function keepWithDesignPhotos(Collection $ads, RefreshReport $report, bool $dryRun, array &$touched): void
    {
        foreach ($ads as $ad) {
            $report->adsKeptForHistory++;
            $keys = $this->photoKeysFor($ad);

            if ($keys === [] || $this->media->designKeysOf($ad->getMedia('images')) === $keys) {
                continue;
            }

            $report->adsKeptPhotosSwapped++;
            $touched[] = $ad->id;

            if (! $dryRun) {
                $this->media->syncDesignPhotos($ad, $keys);
            }
        }
    }

    /**
     * @param Collection<int, Ad> $ads
     */
    private function remove(Collection $ads, RefreshReport $report, bool $dryRun): void
    {
        $report->placeholderAdsRemoved += $ads->count();

        if ($dryRun || $ads->isEmpty()) {
            return;
        }

        $this->search->forget($ads);
        $ids = $ads->pluck('id')->all();

        DB::transaction(function () use ($ids): void {
            DB::table('favorites')->whereIn('ad_id', $ids)->delete();
            DB::table('recently_viewed')->whereIn('ad_id', $ids)->delete();
        });

        // One by one: the model event is what deletes the photo files.
        $ads->each(static fn (Ad $ad): ?bool => $ad->forceDelete());
    }

    /**
     * @param array<string, User> $owners
     * @param list<string> $touched
     */
    private function writeDesignAds(array $owners, RefreshReport $report, bool $dryRun, array &$touched): void
    {
        $ads = $this->design->ads();
        $categories = Category::query()->whereIn('slug', array_unique(array_column($ads, 'category')))->get()->keyBy('slug');
        $districts = Location::query()->whereIn('slug', array_values($this->design->locations()))->get()->keyBy('slug');
        $existing = $this->existingKeys($owners);

        foreach ($ads as $design) {
            $owner = $owners[$design['seller']] ?? null;
            $category = $categories->get($design['category']);
            $district = $districts->get($this->design->districtSlug($design['location']));

            if ($owner === null || $category === null || $district === null) {
                $report->designAdsSkipped++;

                continue;
            }

            $present = isset($existing[$owner->id . '|' . $design['title'][$owner->language->value]]);
            $present ? $report->designAdsKept++ : $report->designAdsCreated++;

            if (! $dryRun) {
                $touched[] = $this->writer->ensure($design, $owner, $category, $district, AdStatus::ACTIVE)->id;
            } else {
                $touched[] = $design['key'];
            }
        }
    }

    /**
     * @param array<string, User> $owners
     * @return array<string, true> "ownerId|title" of the ads the design owners already have
     */
    private function existingKeys(array $owners): array
    {
        $ids = array_map(static fn (User $user): string => $user->id, array_values($owners));
        $keys = [];

        foreach (Ad::query()->whereIn('user_id', $ids)->get(['user_id', 'title']) as $ad) {
            $keys[$ad->user_id . '|' . $ad->title] = true;
        }

        return $keys;
    }

    /**
     * @return array<string, true> both language titles of every design ad
     */
    private function designTitles(): array
    {
        $titles = [];

        foreach ($this->design->ads() as $ad) {
            $titles[$ad['title']['en']] = true;
            $titles[$ad['title']['ar']] = true;
        }

        return $titles;
    }

    /**
     * Two design photos that fit the ad's category, always the same ones for the same ad.
     *
     * @return list<string>
     */
    private function photoKeysFor(Ad $ad): array
    {
        $pool = $this->design->pool((string) $ad->category->slug);

        if ($pool === []) {
            return [];
        }

        $start = crc32($ad->id) % count($pool);

        return array_values(array_unique([$pool[$start], $pool[($start + 1) % count($pool)]]));
    }

    /**
     * @param array<int, string> $adIds
     * @return array<string, true> ids of the ads something else depends on
     */
    private function dependentAdIds(array $adIds): array
    {
        if ($adIds === []) {
            return [];
        }

        $blocked = [];
        $queries = [
            ...array_map(static fn (string $table): \Illuminate\Database\Query\Builder => DB::table($table)->whereIn('ad_id', $adIds)->select('ad_id'), self::DEPENDENT_TABLES),
            DB::table('reports')->where('target_type', 'ad')->whereIn('target_id', $adIds)->select('target_id as ad_id'),
            ...array_map(fn (string $table): \Illuminate\Database\Query\Builder => DB::table($table)->whereIn('ad_id', $adIds)->whereNotNull('user_id')
                ->whereNotIn('user_id', $this->demoUserIds()->getQuery())->select('ad_id'), ['favorites', 'recently_viewed']),
        ];

        foreach ($queries as $query) {
            foreach ($query->distinct()->pluck('ad_id') as $id) {
                $blocked[(string) $id] = true;
            }
        }

        return $blocked;
    }

    /**
     * @return Builder<User>
     */
    private function demoUserIds(): Builder
    {
        return User::query()->select('id')->where('email', 'like', '%@' . DemoOptions::EMAIL_DOMAIN);
    }
}
