<?php

declare(strict_types=1);

namespace App\Data\Messaging;

use App\Enums\ChatMessageKey;
use App\Enums\MessageType;

/**
 * A chat message about to be written. System and offer bubbles carry a
 * translation key instead of fixed text so every reader sees them in their
 * own language.
 */
final readonly class ChatMessageDraft
{
    /**
     * @param array<string, scalar|null> $params
     */
    private function __construct(
        public MessageType $type,
        public string $body = '',
        public ?ChatMessageKey $key = null,
        public array $params = [],
    ) {}

    public static function text(string $body): self
    {
        return new self(MessageType::TEXT, $body);
    }

    /**
     * @param array<string, scalar|null> $params
     */
    public static function system(ChatMessageKey $key, array $params = []): self
    {
        return new self(MessageType::SYSTEM, key: $key, params: $params);
    }

    public static function offer(ChatMessageKey $key, string $amount, string $currency, ?string $note): self
    {
        $params = ['amount' => $amount, 'currency' => $currency];

        if ($note !== null && $note !== '') {
            $params['note'] = $note;
        }

        return new self(MessageType::OFFER, key: $key, params: $params);
    }
}
