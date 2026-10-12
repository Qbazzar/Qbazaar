<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Enums\IntegrationSetting;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Overlays stored integration settings on the runtime config. A setting that
 * is empty leaves the .env-backed config value untouched (the fallback).
 * Runs at boot, so long-lived workers pick changes up after a restart.
 */
class IntegrationConfigApplier
{
    public function __construct(
        private readonly IntegrationSettings $settings,
        private readonly Repository $config,
    ) {}

    public function applySafely(): void
    {
        try {
            $this->apply();
        } catch (Throwable $e) {
            // Table not migrated yet (fresh install) or cache down: .env alone applies.
            // Class only; a message could carry credential text.
            Log::warning('integration_settings.overlay_failed', ['exception' => $e::class]);
        }
    }

    public function apply(): void
    {
        $this->email();
        $this->sms();
        $this->google();
        $this->apple();
        $this->push();
        $this->turnstile();
        $this->payment();
    }

    private function email(): void
    {
        $key = $this->settings->get(IntegrationSetting::EMAIL_API_KEY);
        $this->set('services.resend.key', $key);

        if ($this->settings->enabled(IntegrationSetting::EMAIL_ENABLED) === true) {
            $this->config->set('mail.default', 'resend');
        }

        $this->set('mail.from.address', $this->settings->get(IntegrationSetting::EMAIL_FROM_ADDRESS));
        $this->set('mail.from.name', $this->settings->get(IntegrationSetting::EMAIL_FROM_NAME));

        $replyTo = $this->settings->get(IntegrationSetting::EMAIL_REPLY_TO);
        if ($replyTo !== null) {
            Mail::alwaysReplyTo($replyTo);
        }
    }

    private function sms(): void
    {
        $this->set('services.twilio.sid', $this->settings->get(IntegrationSetting::SMS_ACCOUNT_SID));
        $this->set('services.twilio.token', $this->settings->get(IntegrationSetting::SMS_AUTH_TOKEN));
        $this->set('services.twilio.messaging_service_sid', $this->settings->get(IntegrationSetting::SMS_MESSAGING_SERVICE_SID));
        $this->set('services.twilio.from', $this->settings->get(IntegrationSetting::SMS_FROM_NUMBER));
    }

    private function google(): void
    {
        $this->appendClientId('services.google.client_ids', $this->settings->get(IntegrationSetting::GOOGLE_CLIENT_ID));
        $this->set('services.google.client_secret', $this->settings->get(IntegrationSetting::GOOGLE_CLIENT_SECRET));
        $this->set('services.google.ga4_measurement_id', $this->settings->get(IntegrationSetting::GOOGLE_GA4_MEASUREMENT_ID));
    }

    private function apple(): void
    {
        $this->appendClientId('services.apple.client_ids', $this->settings->get(IntegrationSetting::APPLE_SERVICE_ID));
        $this->set('services.apple.team_id', $this->settings->get(IntegrationSetting::APPLE_TEAM_ID));
        $this->set('services.apple.key_id', $this->settings->get(IntegrationSetting::APPLE_KEY_ID));
        $this->set('services.apple.private_key', $this->settings->get(IntegrationSetting::APPLE_PRIVATE_KEY));
    }

    private function push(): void
    {
        $json = $this->settings->get(IntegrationSetting::PUSH_SERVICE_ACCOUNT);
        $this->set('services.fcm.service_account', $json);

        if ($json !== null) {
            $decoded = json_decode($json, true);
            $this->set('services.fcm.project_id', is_array($decoded) ? ($decoded['project_id'] ?? null) : null);
        }
    }

    private function turnstile(): void
    {
        $this->set('services.turnstile.secret', $this->settings->get(IntegrationSetting::TURNSTILE_SECRET_KEY));
        $this->set('services.turnstile.site_key', $this->settings->get(IntegrationSetting::TURNSTILE_SITE_KEY));

        $enabled = $this->settings->enabled(IntegrationSetting::TURNSTILE_ENABLED);
        if ($enabled !== null) {
            // Same testing exemption as config/services.php.
            $this->config->set('services.turnstile.enabled', $enabled && ! app()->environment('testing'));
        }
    }

    private function payment(): void
    {
        $map = [
            'provider' => IntegrationSetting::PAYMENT_PROVIDER,
            'mode' => IntegrationSetting::PAYMENT_MODE,
            'merchant_id' => IntegrationSetting::PAYMENT_MERCHANT_ID,
            'public_key' => IntegrationSetting::PAYMENT_PUBLIC_KEY,
            'secret_key' => IntegrationSetting::PAYMENT_SECRET_KEY,
            'webhook_secret' => IntegrationSetting::PAYMENT_WEBHOOK_SECRET,
        ];

        foreach ($map as $name => $setting) {
            $this->set("services.payment.{$name}", $this->settings->get($setting));
        }

        $enabled = $this->settings->enabled(IntegrationSetting::PAYMENT_ENABLED);
        if ($enabled !== null) {
            $this->config->set('services.payment.enabled', $enabled);
        }
    }

    private function set(string $key, mixed $value): void
    {
        if ($value !== null && $value !== '') {
            $this->config->set($key, $value);
        }
    }

    private function appendClientId(string $key, ?string $clientId): void
    {
        if ($clientId === null) {
            return;
        }

        $existing = array_filter(array_map('trim', explode(',', (string) $this->config->get($key, ''))));
        $this->config->set($key, implode(',', array_unique([...$existing, $clientId])));
    }
}
