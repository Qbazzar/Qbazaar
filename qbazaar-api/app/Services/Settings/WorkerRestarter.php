<?php

declare(strict_types=1);

namespace App\Services\Settings;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queue workers boot once and keep the config they started with, so saving
 * integration credentials asks Horizon to terminate; the supervisor
 * (systemd) starts it again with the new settings.
 */
class WorkerRestarter
{
    public function restart(): void
    {
        try {
            Artisan::call('horizon:terminate');
        } catch (Throwable $e) {
            Log::warning('integration_settings.worker_restart_failed', ['exception' => $e::class]);
        }
    }
}
