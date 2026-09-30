<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Enums\PlatformSetting;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Typed read/write access to the admin-editable platform settings. Stored
 * overrides are cached as one map; a setting without a stored row falls back
 * to its config default.
 */
class SettingsService
{
    private const string CACHE_KEY = 'platform_settings';

    public function integer(PlatformSetting $setting): int
    {
        return (int) $this->value($setting);
    }

    public function decimal(PlatformSetting $setting): string
    {
        return (string) $this->value($setting);
    }

    public function value(PlatformSetting $setting): int|string
    {
        $definition = $setting->definition();
        $stored = $this->storedValues()[$setting->value] ?? null;

        return $stored === null
            ? $definition->defaultValue()
            : $definition->type->cast($stored);
    }

    /**
     * @return array<string, int|string>
     */
    public function all(): array
    {
        $values = [];

        foreach (PlatformSetting::cases() as $setting) {
            $values[$setting->value] = $this->value($setting);
        }

        return $values;
    }

    public function put(PlatformSetting $setting, int|string $value, User $actor): void
    {
        $definition = $setting->definition();

        Setting::query()->updateOrCreate(
            ['key' => $setting->value],
            [
                'value' => (string) $definition->type->cast($value),
                'type' => $definition->type,
                'group' => $definition->group,
                'updated_by' => $actor->id,
            ],
        );

        // Forgetting before the commit would let a concurrent read re-cache the old rows.
        DB::afterCommit(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * @return array<string, string>
     */
    private function storedValues(): array
    {
        /** @var array<string, string> */
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => Setting::query()->pluck('value', 'key')->all(),
        );
    }
}
