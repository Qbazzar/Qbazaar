<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

/**
 * Tiny DTO returned from a Notification's `toTwilio()` method. Kept separate
 * from the channel so notifications can build the body without depending on
 * the Twilio SDK at construction time (which matters for testing — see
 * Notification::fake()).
 */
final readonly class TwilioSmsMessage
{
    public function __construct(
        public string $body,
    ) {}
}
