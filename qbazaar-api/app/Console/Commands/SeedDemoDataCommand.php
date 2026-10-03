<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Ad;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Demo\DemoOptions;
use Database\Seeders\Demo\DemoSummary;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SeedDemoDataCommand extends Command
{
    protected $signature = 'qbazaar:demo
        {--fresh : Drop every table, migrate and seed the reference data first}
        {--users=60 : Marketplace members to create (staff accounts come on top)}
        {--ads=400 : Listings to create}
        {--sync-media : Run image conversions inline instead of queueing them}
        {--force : Allow running in production (asks for a typed confirmation)}';

    protected $description = 'Fill the database with a realistic Qatar demo marketplace: members, listings with photos, chats, offers, orders, commission, reports and tickets.';

    private const int MIN_USERS = 4;

    private const int MAX_USERS = 2_000;

    private const int MIN_ADS = 10;

    private const int MAX_ADS = 20_000;

    public function handle(DemoSummary $summary): int
    {
        $users = $this->boundedOption('users', self::MIN_USERS, self::MAX_USERS);
        $ads = $this->boundedOption('ads', self::MIN_ADS, self::MAX_ADS);

        if ($users === null || $ads === null) {
            return self::INVALID;
        }

        if ($this->laravel->isProduction() && ! $this->confirmProductionRun()) {
            return self::FAILURE;
        }

        $startedAt = microtime(true);
        $options = new DemoOptions(
            users: $users,
            ads: $ads,
            password: $this->laravel->isProduction() ? Str::password(20, symbols: false) : DemoOptions::LOCAL_PASSWORD,
            syncMedia: (bool) $this->option('sync-media'),
        );

        if ($this->option('fresh')) {
            $this->rebuildSchema();
        }

        $seeder = $this->laravel->make(DemoDataSeeder::class);
        $seeder->setContainer($this->laravel)->setCommand($this);

        try {
            $seeder->__invoke(['options' => $options]);
        } catch (RuntimeException $failure) {
            $this->components->error($failure->getMessage());

            return self::FAILURE;
        }

        $this->syncSearchIndex((bool) $this->option('fresh'));
        $this->report($summary, $seeder, $options, microtime(true) - $startedAt);

        return self::SUCCESS;
    }

    private function boundedOption(string $name, int $min, int $max): ?int
    {
        $value = filter_var($this->option($name), FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);

        if ($value === false) {
            $this->components->error("--{$name} must be a whole number between {$min} and {$max}.");

            return null;
        }

        return $value;
    }

    /**
     * Demo accounts with printed passwords have no place on a live site, so
     * production needs both --force and the database name typed back.
     */
    private function confirmProductionRun(): bool
    {
        if (! $this->option('force')) {
            $this->components->error('Refusing to generate demo data in production. Re-run with --force if you really mean it.');

            return false;
        }

        $database = (string) config('database.connections.' . config('database.default') . '.database');
        $warning = $this->option('fresh') ? 'This DROPS EVERY TABLE and fills' : 'This fills';
        $typed = $this->ask("{$warning} the production database [{$database}] with demo data. Type its name to continue");

        if ($typed !== $database) {
            $this->components->error('Confirmation did not match; nothing was changed.');

            return false;
        }

        return true;
    }

    private function rebuildSchema(): void
    {
        $this->call('migrate:fresh', ['--force' => true]);

        foreach (DatabaseSeeder::REFERENCE_SEEDERS as $seeder) {
            $this->call('db:seed', ['--class' => $seeder, '--force' => true]);
        }
    }

    /**
     * Seeding never touches the search engine; the index is rebuilt here in
     * one pass. A search server that is down only costs a warning.
     */
    private function syncSearchIndex(bool $fresh): void
    {
        if (config('scout.driver') !== 'meilisearch') {
            $this->components->info('Search driver is "' . config('scout.driver') . '", nothing to index.');

            return;
        }

        config(['scout.queue' => false]);

        try {
            if ($fresh) {
                $this->call('scout:flush', ['model' => Ad::class]);
            }

            $this->call('scout:sync-index-settings');
            $this->call('scout:import', ['model' => Ad::class]);
        } catch (Throwable $failure) {
            $this->components->warn('Search index not updated (' . $failure->getMessage() . '). Start Meilisearch and run: php artisan scout:import "App\Models\Ad"');
        }
    }

    private function report(DemoSummary $summary, DemoDataSeeder $seeder, DemoOptions $options, float $seconds): void
    {
        $this->newLine();
        $this->table(['Entity', 'Count'], $summary->rows());

        $this->table(
            ['Account', 'Email', 'Password'],
            array_map(fn (array $account): array => [...$account, $options->password], $seeder->accounts()),
        );

        $this->components->info(sprintf(
            'Every demo account (member01…, buyer, seller and staff, all @%s) uses that password. Done in %.1f s.',
            DemoOptions::EMAIL_DOMAIN,
            $seconds,
        ));

        if (! $options->syncMedia && config('queue.default') !== 'sync') {
            $this->components->warn('Photo conversions were queued: keep `php artisan queue:work --queue=media,default` running until they finish.');
        }
    }
}
