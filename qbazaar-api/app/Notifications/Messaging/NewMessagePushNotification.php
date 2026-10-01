<?php

declare(strict_types=1);

namespace App\Notifications\Messaging;

use App\Enums\ChatMessageKey;
use App\Enums\MessageType;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Str;

class NewMessagePushNotification extends ChatPushNotification
{
    private const int PREVIEW_LENGTH = 120;

    public function __construct(
        public readonly Message $message,
        public readonly User $sender,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $locale = $this->resolveLocale($notifiable);
        $body = $this->message->body === '' && $this->message->type === MessageType::IMAGE
            ? (string) __(ChatMessageKey::IMAGE->translationKey(), [], $locale)
            : $this->message->body;

        return [
            'category' => 'message.new',
            'title' => (string) __('messages.notifications.message_new.title', ['name' => $this->sender->full_name], $locale),
            'body' => Str::limit($body, self::PREVIEW_LENGTH),
            'cta_url' => $this->conversationUrl($this->message->conversation_id),
            'conversation_id' => $this->message->conversation_id,
            'message_id' => $this->message->id,
        ];
    }
}
