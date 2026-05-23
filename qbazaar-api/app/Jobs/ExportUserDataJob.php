<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Notifications\DataExportReadyNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Activitylog\Models\Activity;

/**
 * Builds the user's personal-data export and emails them a signed link.
 *
 * Output layout:
 *  - `storage/app/private/exports/{user_id}-{timestamp}.json`
 *  - Filename embeds both pieces so the download route can verify the file
 *    belongs to the caller, and so admin tooling can scan disk usage with
 *    a simple glob.
 *
 * Contents (denormalised JSON, GDPR-style "everything we hold about you"):
 *   user           : the public profile + privacy settings
 *   refresh_tokens : id, device fingerprint, created_at, expires_at (no hashes)
 *   otp_codes      : id, kind, used_at, created_at (no codes)
 *   activity_log   : event, properties, created_at
 *   blocked_users  : the ids the caller blocked
 *
 * Sensitive secrets (password hashes, raw tokens, raw OTPs) are NEVER
 * exported — only metadata.
 */
class ExportUserDataJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $userId,
        public readonly string $exportId,
    ) {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        /** @var User|null $user */
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        $payload = $this->buildPayload($user);
        $disk = Storage::disk('local');
        $path = "exports/{$this->exportId}.json";

        $disk->put(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        );

        $expiresInHours = (int) config('qbazaar.account.data_export_link_ttl_hours', 48);

        $signedUrl = URL::temporarySignedRoute(
            'api.v1.account.data-export.download',
            Carbon::now()->addHours($expiresInHours),
            ['id' => $this->exportId],
        );

        $user->notify(new DataExportReadyNotification(
            downloadUrl: $signedUrl,
            expiresInHours: $expiresInHours,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(User $user): array
    {
        return [
            'generated_at' => Carbon::now()->toIso8601String(),
            'export_id' => $this->exportId,
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'account_type' => $user->account_type->value,
                'status' => $user->status->value,
                'email_verified' => (bool) $user->email_verified,
                'phone_verified' => (bool) $user->phone_verified,
                'language' => $user->language->value,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'created_at' => $user->created_at->toIso8601String(),
                'privacy_settings' => $user->privacySettings()->toArray(),
            ],
            'refresh_tokens' => DB::table('refresh_tokens')
                ->where('user_id', $user->id)
                ->get(['id', 'device_fingerprint', 'expires_at', 'used_at', 'created_at'])
                ->map(fn ($r): array => (array) $r)
                ->all(),
            // OTP rows are keyed by phone, not user_id — fetch what we have
            // for the user's current number; the hashed code is intentionally
            // excluded.
            'otp_codes' => DB::table('otp_codes')
                ->where('phone', $user->phone)
                ->get(['id', 'expires_at', 'used_at', 'attempts', 'created_at'])
                ->map(fn ($r): array => (array) $r)
                ->all(),
            'activity_log' => Activity::query()
                ->where('causer_type', $user::class)
                ->where('causer_id', $user->id)
                ->orderBy('created_at')
                ->get(['log_name', 'event', 'description', 'properties', 'created_at'])
                ->map(fn (Activity $a): array => [
                    'log_name' => $a->log_name,
                    'event' => $a->event,
                    'description' => $a->description,
                    'properties' => $a->properties,
                    'created_at' => $a->created_at?->toIso8601String(),
                ])
                ->all(),
            'blocked_users' => DB::table('user_blocks')
                ->where('blocker_id', $user->id)
                ->pluck('blocked_id')
                ->all(),
        ];
    }
}
