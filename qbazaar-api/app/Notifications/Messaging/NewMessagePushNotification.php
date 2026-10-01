<?php

declare(strict_types=1);

namespace App\Notifications\Messaging;

use App\Enums\NotificationTopic;
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

    protected function topic(): NotificationTopic
    {
        return NotificationTopic::USER_MESSAGES;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'category' => 'message.new',
            'title' => (string) __('messages.notifications.message_new.title', ['name' => $this->sender->full_name], $this->resolveLocale($notifiable)),
            'body' => Str::limit($this->message->body, self::PREVIEW_LENGTH),
            'cta_url' => $this->conversationUrl($this->message->conversation_id),
            'conversation_id' => $this->message->conversation_id,
            'message_id' => $this->message->id,
        ];
    }
}
