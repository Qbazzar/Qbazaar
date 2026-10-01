<?php

declare(strict_types=1);

namespace App\Listeners\Messaging;

use App\Actions\Reports\ReportFlaggedChatMessageAction;
use App\Enums\MessageType;
use App\Events\Messaging\MessageSent;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Runs auto-moderation on user-written chat messages off the send path,
 * so screening never delays or blocks delivery.
 */
class ScreenChatMessage implements ShouldQueue
{
    public string $queue = 'low';

    public function __construct(private readonly ReportFlaggedChatMessageAction $reportFlaggedMessage) {}

    public function handle(MessageSent $event): void
    {
        if ($event->message->type !== MessageType::TEXT) {
            return;
        }

        if (! (bool) config('moderation.enabled', true) || ! (bool) config('qbazaar.messaging.auto_report_flagged_messages', true)) {
            return;
        }

        $this->reportFlaggedMessage->execute($event->message);
    }
}
