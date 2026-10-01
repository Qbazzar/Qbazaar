<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\ChatMessageKey;
use App\Models\Conversation;
use Illuminate\Support\Str;

/**
 * Turns a stored `message_key` + `params` pair into text in the reader's
 * language. Rows without a key are user-written and come back unchanged.
 */
class ChatMessageRenderer
{
    public const PREVIEW_LENGTH = 160;

    /**
     * @param array<string, mixed>|null $params
     */
    public function render(?string $key, ?array $params, string $fallback, ?string $locale = null): string
    {
        $messageKey = $key !== null ? ChatMessageKey::tryFrom($key) : null;

        if ($messageKey === null) {
            return $fallback;
        }

        $params ??= [];
        $text = (string) __($messageKey->translationKey(), $this->replacements($params), $locale);

        // The note is the proposer's own words, so it is appended verbatim
        // instead of passing through the translator's placeholder replacement.
        $note = $params['note'] ?? null;

        return is_string($note) && $note !== '' ? $text . ' — ' . $note : $text;
    }

    public function preview(Conversation $conversation, ?string $locale = null): ?string
    {
        if ($conversation->last_message_preview === null) {
            return null;
        }

        return Str::limit(
            $this->render($conversation->last_message_key, $conversation->last_message_params, $conversation->last_message_preview, $locale),
            self::PREVIEW_LENGTH,
        );
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, string>
     */
    private function replacements(array $params): array
    {
        $replacements = [];

        foreach ($params as $name => $value) {
            if ($name !== 'note' && is_scalar($value)) {
                $replacements[(string) $name] = (string) $value;
            }
        }

        return $replacements;
    }
}
