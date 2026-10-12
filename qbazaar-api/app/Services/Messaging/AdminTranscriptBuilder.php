<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\MessageType;
use App\Models\Message;
use App\Support\Money;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Turns a page of messages into the rows the admin transcript draws: the
 * text in the admin's language, and where each run of one sender starts.
 */
class AdminTranscriptBuilder
{
    public function __construct(private readonly ChatMessageRenderer $renderer) {}

    /**
     * @param iterable<Message> $messages
     * @return Collection<int, array{message: Message, text: string, isEvent: bool, startsGroup: bool}>
     */
    public function build(iterable $messages, ?string $locale = null): Collection
    {
        $previousSender = null;
        $rows = collect();

        foreach ($messages as $message) {
            $isEvent = $message->type === MessageType::SYSTEM;
            $rows->push([
                'message' => $message,
                'text' => $this->text($message, $locale),
                'isEvent' => $isEvent,
                'startsGroup' => $isEvent || $message->sender_id !== $previousSender,
            ]);
            $previousSender = $isEvent ? null : $message->sender_id;
        }

        return $rows;
    }

    private function text(Message $message, ?string $locale): string
    {
        $fallback = $message->type === MessageType::IMAGE && $message->body === ''
            ? (string) __('chat.image', [], $locale)
            : $message->body;

        return $this->renderer->render($message->message_key, $this->params($message->params), $fallback, $locale);
    }

    /**
     * @param array<string, mixed>|null $params
     * @return array<string, mixed>|null
     */
    private function params(?array $params): ?array
    {
        if ($params === null || ! isset($params['amount']) || ! is_numeric($params['amount'])) {
            return $params;
        }

        try {
            $params['amount'] = Money::of((string) $params['amount']);
        } catch (InvalidArgumentException) {
            // Keep the stored value rather than hide a malformed amount.
        }

        return $params;
    }
}
