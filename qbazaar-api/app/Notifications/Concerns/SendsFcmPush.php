<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

use App\Models\User;
use NotificationChannels\Fcm\FcmMessage;

/**
 * Adds web-push (FCM) delivery to an existing notification.
 *
 * The push payload derives from the SAME toArray() data the database channel
 * persists, so the web push and the in-app bell feed can never disagree.
 *
 * The payload is data-only (no top-level `notification` block): with a
 * `notification` block the firebase web SDK auto-displays its own copy AND
 * still invokes onBackgroundMessage, so web users would see two notifications
 * — the SDK one icon-less with a dead click (no fcm_options.link). Data-only
 * gives the service worker full display/click control. Native apps can later
 * add platform-scoped (android/apns) notification blocks without regressing web.
 *
 * The whole feature is gated on the Firebase service-account credentials
 * actually existing — the configuration that ships today has none, so via()
 * never returns the FCM channel and the kreait Messaging client is never
 * resolved (resolving it without credentials throws at container time).
 */
trait SendsFcmPush
{
    /**
     * The canonical payload both the database channel and toFcm() build from.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(mixed $notifiable): array;

    /**
     * Should this notifiable receive a push for this notification?
     */
    protected function fcmEnabledFor(object $notifiable): bool
    {
        // Only Users own device tokens — AnonymousNotifiable and admin test
        // sends have no deviceTokens() relation to route through.
        if (! $notifiable instanceof User) {
            return false;
        }

        if (! $this->fcmCredentialsExist()) {
            return false;
        }

        // Use the already-loaded relation when available; otherwise accept
        // one extra EXISTS query per notification — fine at MVP scale.
        return $notifiable->relationLoaded('deviceTokens')
            ? $notifiable->deviceTokens->isNotEmpty()
            : $notifiable->deviceTokens()->exists();
    }

    /**
     * Build the FCM message from the canonical toArray() payload.
     */
    public function toFcm(object $notifiable): FcmMessage
    {
        $payload = $this->toArray($notifiable);

        $title = isset($payload['title']) && is_string($payload['title']) && $payload['title'] !== ''
            ? $payload['title']
            : (string) config('app.name');

        $body = isset($payload['body']) && is_string($payload['body'])
            ? $payload['body']
            : null;

        return FcmMessage::create()
            // FCM rejects non-string data values (FcmMessage::data() enforces
            // it), hence the explicit casts.
            ->data([
                'title' => $title,
                'body' => (string) $body,
                'category' => (string) ($payload['category'] ?? ''),
                'cta_url' => (string) ($payload['cta_url'] ?? ''),
            ]);
    }

    /**
     * True when the kreait package would be able to authenticate: the default
     * firebase project has credentials configured AND (for file paths) the
     * file exists on disk. Mirrors FirebaseProjectManager's resolution rules.
     *
     * Note: kreait also accepts a decoded service-account array as credentials;
     * this gate treats that shape as absent (push skipped, never a crash).
     */
    private function fcmCredentialsExist(): bool
    {
        $project = (string) config('firebase.default', 'app');
        $credentials = config('firebase.projects.' . $project . '.credentials');

        // The package also accepts ['file' => ...] shaped config.
        if (is_array($credentials)) {
            $credentials = $credentials['file'] ?? null;
        }

        if (! is_string($credentials) || $credentials === '') {
            return false;
        }

        // Inline JSON credentials need no file on disk.
        if (str_starts_with($credentials, '{')) {
            return true;
        }

        $isAbsolute = str_starts_with($credentials, '/') || str_contains($credentials, ':\\');

        return is_file($isAbsolute ? $credentials : base_path($credentials));
    }
}
