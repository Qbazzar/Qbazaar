<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Jobs\Catalog\WarmCatalogCacheJob;
use App\Models\Ad;
use App\Models\User;
use App\Services\Ledger\LedgerReconciler;
use Closure;
use Database\Seeders\Demo\DemoClock;
use Database\Seeders\Demo\DemoContext;
use Database\Seeders\Demo\DemoMedia;
use Database\Seeders\Demo\DemoOptions;
use Database\Seeders\Demo\DemoRandom;
use Database\Seeders\Demo\Steps\SeedDeals;
use Database\Seeders\Demo\Steps\SeedEngagement;
use Database\Seeders\Demo\Steps\SeedListings;
use Database\Seeders\Demo\Steps\SeedModeration;
use Database\Seeders\Demo\Steps\SeedPeople;
use Database\Seeders\Demo\Steps\SeedSocialGraph;
use Database\Seeders\Demo\UpcomingFeatures;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * A realistic Qatar marketplace to click through: staff for every role,
 * private and business members, listings in every status with photos,
 * follows, favourites, chats, offers, orders, commission, reports and
 * support tickets.
 *
 * Everything that has rules goes through the production actions and
 * services, so the data obeys the same invariants as real traffic and the
 * ledger reconciles at the end. Driven by `php artisan qbazaar:demo`.
 */
class DemoDataSeeder extends Seeder
{
    private ?DemoContext $context = null;

    public function __construct(
        private readonly DemoMedia $media,
        private readonly UpcomingFeatures $upcoming,
        private readonly LedgerReconciler $reconciler,
    ) {}

    public function run(?DemoOptions $options = null): void
    {
        $options ??= new DemoOptions;
        $this->ensureNoDemoDataYet();

        $context = $this->context = new DemoContext($options, new DemoRandom, new DemoClock);
        app(LocationSeeder::class)->backfillCoordinates();

        $this->withDemoRuntime($options, fn () => Ad::withoutSyncingToSearch(function () use ($context): void {
            $this->step('Staff and members', fn () => app(SeedPeople::class)->run($context));
            $this->step('Follows and saved searches', fn () => app(SeedSocialGraph::class)->run($context));
            $this->step('Listings and photos', fn () => app(SeedListings::class)->run($context));
            $this->step('Favourites and browsing history', fn () => app(SeedEngagement::class)->run($context));
            $this->step('Chats, offers, orders and commission', fn () => app(SeedDeals::class)->run($context));
            $this->step('Reports, tickets and suspensions', fn () => app(SeedModeration::class)->run($context));
            $this->step('Features in progress', function () use ($context): void {
                $this->upcoming->seedPurchaseRequests($context);
                $this->upcoming->seedWithdrawals($context);
                $this->upcoming->seedPromotions($context);
            });
        }));

        $this->step('Ledger reconciliation', fn () => $this->assertLedgerReconciles());
        $this->step('Catalog cache', fn () => WarmCatalogCacheJob::dispatchSync());
    }

    /**
     * The accounts to sign in with, for the summary.
     *
     * @return list<array{string, string}> label and email
     */
    public function accounts(): array
    {
        if ($this->context === null) {
            return [];
        }

        $staff = array_map(fn (string $role, User $user): array => [$role, $user->email], array_keys($this->context->staff), $this->context->staff);
        $members = array_map(fn (User $user): array => [$user->isBusiness() ? 'business member' : 'private member', $user->email], array_slice($this->context->members, 0, 2));

        return [...$staff, ...$members];
    }

    private function ensureNoDemoDataYet(): void
    {
        if (User::withTrashed()->where('email', 'like', '%@' . DemoOptions::EMAIL_DOMAIN)->exists()) {
            throw new RuntimeException('Demo data is already in this database. Run `php artisan qbazaar:demo --fresh` to rebuild it.');
        }
    }

    /**
     * Listeners and jobs run inline so notifications, inbox counters and
     * ledger postings exist when the run ends; image conversions go to the
     * app's own queue unless asked to run inline. Nothing is mailed or
     * broadcast, and search is reindexed once at the end.
     *
     * @param Closure(): void $callback
     */
    private function withDemoRuntime(DemoOptions $options, Closure $callback): void
    {
        $mediaConnection = $options->syncMedia ? 'sync' : (string) config('queue.default');
        $overrides = [
            'queue.default' => 'sync',
            'media-library.queue_connection_name' => $mediaConnection,
            'broadcasting.default' => 'null',
            'mail.default' => 'array',
        ];
        $previous = array_combine(array_keys($overrides), array_map(fn (string $key): mixed => config($key), array_keys($overrides)));

        config($overrides);
        $this->media->useConnection($mediaConnection);

        try {
            $callback();
        } finally {
            config($previous);
            $this->media->cleanUp();
        }
    }

    private function assertLedgerReconciles(): void
    {
        $report = $this->reconciler->run();

        if (! $report->isClean()) {
            throw new RuntimeException('The demo ledger does not reconcile: ' . json_encode($report->toArray(), JSON_PRETTY_PRINT));
        }
    }

    /**
     * @param Closure(): mixed $work
     */
    private function step(string $label, Closure $work): void
    {
        if ($this->command === null) {
            $work();

            return;
        }

        $this->command->outputComponents()->task($label, $work);
    }
}
