<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\Demo\Content\DemoAdRenewer;
use Database\Seeders\Demo\Content\DemoSearchSync;
use Illuminate\Console\Command;

class KeepDemoAdsAliveCommand extends Command
{
    protected $signature = 'demo:keep-alive
        {--dry-run : Show what would change and change nothing}
        {--days= : How many days renewed ads stay live (default: qbazaar.demo.keep_alive_days)}
        {--force : Allow running in production}';

    protected $description = 'Give the ads of demo accounts that are about to expire (or already did) a fresh expiry, so a demo site never empties. Scheduled daily only when DEMO_KEEP_ALIVE is on.';

    public function handle(DemoAdRenewer $renewer, DemoSearchSync $search): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $days = filter_var($this->option('days') ?? config('qbazaar.demo.keep_alive_days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 365]]);

        if ($days === false) {
            $this->components->error('--days must be a whole number between 1 and 365.');

            return self::INVALID;
        }

        if (! $dryRun && $this->laravel->isProduction() && ! $this->option('force')) {
            $this->components->error('Refusing to change demo ads in production. Re-run with --force, or with --dry-run to preview.');

            return self::FAILURE;
        }

        $result = $renewer->renew($dryRun, $days, reviveExpired: true, restagger: false);

        if (! $dryRun) {
            $search->reindex($result['ids']);
        }

        $this->components->info(sprintf(
            '%s: %d live demo ads extended to %d days, %d expired ones brought back.',
            $dryRun ? 'Dry run, nothing was changed' : 'Done',
            $result['extended'],
            $days,
            $result['revived'],
        ));

        return self::SUCCESS;
    }
}
