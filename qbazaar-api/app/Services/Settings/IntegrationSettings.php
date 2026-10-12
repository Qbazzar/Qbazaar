<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Enums\IntegrationSetting;
use App\Models\IntegrationSettingRow;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
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
    private const string ROWS_KEY = 'integration_settings_rows';

    private const string PUBLIC_KEY = 'integration_settings_public';

    public function get(IntegrationSetting $setting): ?string
    {
        $row = $this->rows()[$setting->value] ?? null;

        if ($row === null || $row['value'] === '') {
            return null;
        }

        return $row['secret'] ? $this->decrypt($row['value']) : $row['value'];
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

        return mb_strlen($value) >= 12 ? '•••• ' . mb_substr($value, -4) : '••••';
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
     * @return array<string, mixed>
     */
    private function buildPublicPayload(): array
    {
        $payload = [
            'contact' => [
                'support_email' => $this->get(IntegrationSetting::CONTACT_SUPPORT_EMAIL),
                'phone' => $this->get(IntegrationSetting::CONTACT_PHONE),
                'whatsapp' => $this->get(IntegrationSetting::CONTACT_WHATSAPP),
                'address' => ['ar' => $this->get(IntegrationSetting::CONTACT_ADDRESS_AR), 'en' => $this->get(IntegrationSetting::CONTACT_ADDRESS_EN)],
                'working_hours' => ['ar' => $this->get(IntegrationSetting::CONTACT_HOURS_AR), 'en' => $this->get(IntegrationSetting::CONTACT_HOURS_EN)],
            ],
            'social' => [],
            'analytics' => ['ga4_measurement_id' => $this->get(IntegrationSetting::GOOGLE_GA4_MEASUREMENT_ID)],
            'antibot' => [
                'turnstile_enabled' => (bool) $this->enabled(IntegrationSetting::TURNSTILE_ENABLED),
                'turnstile_site_key' => $this->get(IntegrationSetting::TURNSTILE_SITE_KEY),
            ],
        ];

        foreach ([
            'instagram' => IntegrationSetting::SOCIAL_INSTAGRAM, 'x' => IntegrationSetting::SOCIAL_X,
            'facebook' => IntegrationSetting::SOCIAL_FACEBOOK, 'tiktok' => IntegrationSetting::SOCIAL_TIKTOK,
            'snapchat' => IntegrationSetting::SOCIAL_SNAPCHAT, 'youtube' => IntegrationSetting::SOCIAL_YOUTUBE,
            'linkedin' => IntegrationSetting::SOCIAL_LINKEDIN,
        ] as $name => $setting) {
            $payload['social'][$name] = $this->get($setting);
        }

        return $payload;
    }

    /**
     * @return array<string, array{value: string, secret: bool}>
     */
    private function rows(): array
    {
        /** @var array<string, array{value: string, secret: bool}> */
        return Cache::rememberForever(
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
        DB::afterCommit(function (): void {
            Cache::forget(self::ROWS_KEY);
            Cache::forget(self::PUBLIC_KEY);
        });
    }
}
