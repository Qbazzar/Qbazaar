<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\Demo\Content\DemoContentRefresher;
use Database\Seeders\Demo\Content\RefreshOptions;
use Illuminate\Console\Command;

class RefreshDemoContentCommand extends Command
{
    protected $signature = 'demo:refresh-content
        {--dry-run : Show what would change and change nothing}
        {--days=60 : How many days refreshed demo ads stay live}
        {--keep-expired : Leave expired demo ads expired instead of bringing them back}
        {--curate-home : Also fill the home "Best selling" rail and featured companies, when staff have not curated them}
        {--force : Allow running in production}';

    protected $description = 'Replace the placeholder listings and profiles of the demo accounts with the content of the original design, and give their ads a fresh expiry. Touches only @demo.qbazaar.qa accounts and what they own.';

    public function handle(DemoContentRefresher $refresher): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 365]]);

        if ($days === false) {
            $this->components->error('--days must be a whole number between 1 and 365.');

            return self::INVALID;
        }

        if (! $dryRun && $this->laravel->isProduction() && ! $this->option('force')) {
            $this->components->error('Refusing to change demo content in production. Re-run with --force, or with --dry-run to preview.');

            return self::FAILURE;
        }

        $startedAt = microtime(true);
        $report = $refresher->run(new RefreshOptions(
            dryRun: $dryRun,
            days: $days,
            reviveExpired: ! $this->option('keep-expired'),
            curateHome: (bool) $this->option('curate-home'),
        ));

        $this->table(['What', 'Count'], $report->rows());
        $this->components->info(sprintf(
            '%s in %.1f s.%s',
            $dryRun ? 'Dry run, nothing was changed' : 'Demo content refreshed',
            microtime(true) - $startedAt,
            $dryRun ? '' : ' Photo conversions are queued: keep `php artisan queue:work --queue=media,default` running until they finish.',
        ));

        return self::SUCCESS;
    }
}
