<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Media\MediaDiskMigrator;
use App\Services\Media\MediaMigrationOutcome;
use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MigrateMediaStorageCommand extends Command
{
    protected $signature = 'media:migrate-storage
        {--dry-run : Report what would move without copying anything}
        {--chunk=200 : Media rows loaded per query}';

    protected $description = 'Copy media files to the disks configured for their collection (e.g. local to Cloudflare R2) and repoint the rows. Safe to re-run.';

    public function handle(MediaDiskMigrator $migrator): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = max(1, (int) $this->option('chunk'));

        $counts = [
            MediaMigrationOutcome::Migrated->name => 0,
            MediaMigrationOutcome::AlreadyInPlace->name => 0,
            MediaMigrationOutcome::SourceMissing->name => 0,
        ];
        $missing = [];

        $progress = $this->output->createProgressBar(Media::query()->count());
        $progress->start();

        Media::query()->chunkById($chunkSize, function ($chunk) use ($migrator, $dryRun, &$counts, &$missing, $progress): void {
            foreach ($chunk as $media) {
                /** @var Media $media */
                $outcome = $migrator->migrate($media, $dryRun);
                $counts[$outcome->name]++;

                if ($outcome === MediaMigrationOutcome::SourceMissing) {
                    $missing[] = (string) $media->getKey();
                }

                $progress->advance();
            }
        });

        $progress->finish();
        $this->newLine(2);

        $this->table(
            ['Outcome', 'Media'],
            [
                [$dryRun ? 'Would migrate' : 'Migrated', $counts[MediaMigrationOutcome::Migrated->name]],
                ['Already in place', $counts[MediaMigrationOutcome::AlreadyInPlace->name]],
                ['Source file missing', $counts[MediaMigrationOutcome::SourceMissing->name]],
            ],
        );

        if ($missing !== []) {
            $this->warn('Media with a missing source file (left untouched): ' . implode(', ', $missing));
        }

        return self::SUCCESS;
    }
}
