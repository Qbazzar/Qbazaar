<?php

declare(strict_types=1);

namespace App\Data\Account;

use App\Enums\NotificationPreferenceChannel;
use App\Enums\NotificationTopic;

/**
 * Per-user email and push switches for every notification topic, stored as
 * JSON on `users.notification_preferences`.
 *
 * Every topic is on until the user turns it off, and a topic added later
 * starts on for existing rows, because missing keys fall back to the default.
 */
final class NotificationPreferences
{
    /**
     * @param array<string, bool> $email topic value => enabled
     * @param array<string, bool> $push topic value => enabled
     */
    private function __construct(
        private readonly array $email,
        private readonly array $push,
    ) {}

    public static function defaults(): self
    {
        return self::fromArray([]);
    }

    /**
     * @param array<array-key, mixed> $stored
     */
    public static function fromArray(array $stored): self
    {
        return new self(
            self::normalise($stored[NotificationPreferenceChannel::EMAIL->value] ?? []),
            self::normalise($stored[NotificationPreferenceChannel::PUSH->value] ?? []),
        );
    }

    public function allows(NotificationTopic $topic, NotificationPreferenceChannel $channel): bool
    {
        return $this->for($channel)[$topic->value];
    }

    /**
     * @return array<string, bool>
     */
    public function for(NotificationPreferenceChannel $channel): array
    {
        return $channel === NotificationPreferenceChannel::EMAIL ? $this->email : $this->push;
    }

    /**
     * Returns a copy with the given switches changed; topics left out keep
     * their current value.
     *
     * @param array<string, bool> $email
     * @param array<string, bool> $push
     */
    public function with(array $email = [], array $push = []): self
    {
        return new self(
            self::normalise([...$this->email, ...$email]),
            self::normalise([...$this->push, ...$push]),
        );
    }

    /**
     * @return array{email: array<string, bool>, push: array<string, bool>}
     */
    public function toArray(): array
    {
        return [
            NotificationPreferenceChannel::EMAIL->value => $this->email,
            NotificationPreferenceChannel::PUSH->value => $this->push,
        ];
    }

    /**
     * Keeps only known topics, in enum order, filling the missing ones.
     *
     * @return array<string, bool>
     */
    private static function normalise(mixed $switches): array
    {
        $switches = is_array($switches) ? $switches : [];
        $normalised = [];

        foreach (NotificationTopic::values() as $topic) {
            $normalised[$topic] = (bool) ($switches[$topic] ?? true);
        }

        return $normalised;
    }
}
