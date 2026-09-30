<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\PlatformSetting;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\DB;

class UpdatePlatformSettingsAction
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /**
     * Saves only the values that changed, one audit entry each.
     *
     * @param array<string, mixed> $values keyed by PlatformSetting value
     */
    public function execute(array $values, User $actor): void
    {
        DB::transaction(function () use ($values, $actor): void {
            foreach (PlatformSetting::cases() as $setting) {
                if (! array_key_exists($setting->value, $values)) {
                    continue;
                }

                $old = $this->settings->value($setting);
                $new = $setting->definition()->type->cast($values[$setting->value]);

                if ($new === $old) {
                    continue;
                }

                $this->settings->put($setting, $new, $actor);

                activity('settings')
                    ->causedBy($actor)
                    ->event('updated')
                    ->withProperties(['key' => $setting->value, 'old' => $old, 'new' => $new])
                    ->log('Platform setting updated');
            }
        });
    }
}
