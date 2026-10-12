<?php

declare(strict_types=1);

namespace App\Services\Admin\Dashboard;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Asks a disk about a file that does not exist: Flysystem answers `false`
 * when the storage is reachable and throws when it is not.
 *
 * Remote disks are rebuilt for the probe with short connect and read timeouts
 * and no retries, so a dead bucket cannot hold the dashboard. The shared disk
 * configuration (uploads, conversions) is left untouched.
 */
class StorageProbe
{
    private const string PROBE_PATH = '.dashboard-health-probe';

    /** @throws Throwable when the storage cannot be reached */
    public function check(string $disk): void
    {
        $config = (array) config("filesystems.disks.{$disk}");

        $filesystem = ($config['driver'] ?? null) === 's3'
            ? Storage::build($config + ['http' => ['connect_timeout' => 1, 'timeout' => 2], 'retries' => 0])
            : Storage::disk($disk);

        $filesystem->getDriver()->fileExists(self::PROBE_PATH);
    }
}
