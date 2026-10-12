<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Enums\IntegrationFieldKind;
use App\Enums\IntegrationSetting;
use App\Models\IntegrationSettingRow;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Read model and writer for the integration settings. The cache holds the
 * rows exactly as stored (secrets stay ciphertext) and is busted on every
 * write. An empty value means "not set", so callers fall back to .env config.
 */
class IntegrationSettings
{
    /** @var array<string, array{value: string, secret: bool}>|null */
    private ?array $rowsMemo = null;

    /** @var array<string, string|null> */
    private array $valueMemo = [];

    private const string ROWS_KEY = 'integration_settings_rows';

    private const string PUBLIC_KEY = 'integration_settings_public';

    public function get(IntegrationSetting $setting): ?string
    {
        if (array_key_exists($setting->value, $this->valueMemo)) {
            return $this->valueMemo[$setting->value];
        }

        $row = $this->rows()[$setting->value] ?? null;

        $value = match (true) {
            $row === null, $row['value'] === '' => null,
            $row['secret'] => $this->decrypt($row['value']),
            default => $row['value'],
        };

        return $this->valueMemo[$setting->value] = $value;
    }

    public function isSet(IntegrationSetting $setting): bool
    {
        return $this->get($setting) !== null;
    }

    public function enabled(IntegrationSetting $setting): ?bool
    {
        $value = $this->get($setting);

        return $value === null ? null : $value === '1';
    }

    /**
     * "•••• 1234" for a stored secret, null when nothing is stored. Short
     * secrets reveal nothing.
     */
    public function masked(IntegrationSetting $setting): ?string
    {
        $value = $this->get($setting);

        if ($value === null) {
            return null;
        }

        // Multi-line secrets (PEM, JSON) and short tokens reveal nothing, not even a suffix.
        $revealsSuffix = $setting->definition()->kind !== IntegrationFieldKind::TEXTAREA && mb_strlen($value) >= 12;

        return $revealsSuffix ? '•••• ' . mb_substr($value, -4) : '••••';
    }

    public function put(IntegrationSetting $setting, string $value, User $actor): void
    {
        $definition = $setting->definition();

        IntegrationSettingRow::query()->updateOrCreate(
            ['key' => $setting->value],
            [
                'value' => $definition->secret ? Crypt::encryptString($value) : $value,
                'is_secret' => $definition->secret,
                'updated_by' => $actor->id,
            ],
        );

        $this->flushAfterCommit();
    }

    public function forget(IntegrationSetting $setting): void
    {
        IntegrationSettingRow::query()->whereKey($setting->value)->delete();

        $this->flushAfterCommit();
    }

    /**
     * Only the fields flagged public; the single source for the public endpoint.
     *
     * @return array<string, mixed>
     */
    public function publicPayload(): array
    {
        /** @var array<string, mixed> */
        return Cache::rememberForever(self::PUBLIC_KEY, fn (): array => $this->buildPublicPayload());
    }

    /**
     * Built from the registry's public flag so a field is exposed only by
     * declaring itself public there.
     *
     * @return array<string, mixed>
     */
    private function buildPublicPayload(): array
    {
        $payload = [];

        foreach (IntegrationSetting::cases() as $setting) {
            $definition = $setting->definition();
            $path = $setting->publicPath();

            if (! $definition->public || $path === null) {
                continue;
            }

            $value = $definition->kind === IntegrationFieldKind::TOGGLE
                ? (bool) $this->enabled($setting)
                : $this->get($setting);

            Arr::set($payload, $path, $value);
        }

        return $payload;
    }

    /**
     * @return array<string, array{value: string, secret: bool}>
     */
    private function rows(): array
    {
        /** @var array<string, array{value: string, secret: bool}> */
        return $this->rowsMemo ??= Cache::rememberForever(
            self::ROWS_KEY,
            fn (): array => IntegrationSettingRow::query()->get()
                ->mapWithKeys(fn (IntegrationSettingRow $row): array => [
                    $row->key => ['value' => (string) $row->getRawOriginal('value'), 'secret' => $row->is_secret],
                ])->all(),
        );
    }

    private function decrypt(string $ciphertext): ?string
    {
        try {
            return Crypt::decryptString($ciphertext);
        } catch (DecryptException) {
            // APP_KEY rotated: treat as unset so the .env fallback applies.
            return null;
        }
    }

    private function flushAfterCommit(): void
    {
        $this->forgetMemo();

        DB::afterCommit(function (): void {
            $this->forgetMemo();
            Cache::forget(self::ROWS_KEY);
            Cache::forget(self::PUBLIC_KEY);
        });
    }

    private function forgetMemo(): void
    {
        $this->rowsMemo = null;
        $this->valueMemo = [];
    }
}
