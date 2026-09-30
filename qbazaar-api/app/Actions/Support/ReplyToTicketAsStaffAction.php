<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Enums\SupportTicketStatus;
use App\Models\SupportReply;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\Support\SupportTicketRepliedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Posts a staff reply, advances the ticket through its workflow and lets the
 * owner know. The owner is notified after commit so a rolled-back reply
 * never reaches their inbox.
 */
class ReplyToTicketAsStaffAction
{
    public function __invoke(SupportTicket $ticket, User $staff, string $body): SupportReply
    {
        $reply = DB::transaction(function () use ($ticket, $staff, $body): SupportReply {
            /** @var SupportTicket $locked */
            $locked = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);

            $reply = SupportReply::query()->create([
                'ticket_id' => $locked->id,
                'author_id' => $staff->id,
                'is_staff' => true,
                'body' => $body,
            ]);

            $locked->forceFill([
                'last_replied_at' => now(),
                'status' => $this->statusAfterStaffReply($locked->status)->value,
            ])->save();

            return $reply;
        });

        $reply->setRelation('ticket', $ticket->refresh());

        DB::afterCommit(fn () => $this->notifyOwner($reply));

        return $reply;
    }

    private function statusAfterStaffReply(SupportTicketStatus $current): SupportTicketStatus
    {
        return match ($current) {
            SupportTicketStatus::OPEN => SupportTicketStatus::IN_PROGRESS,
            SupportTicketStatus::IN_PROGRESS => SupportTicketStatus::WAITING_USER,
            default => $current,
        };
    }

    private function notifyOwner(SupportReply $reply): void
    {
        $ticket = $reply->ticket;
        $notification = new SupportTicketRepliedNotification($reply);

        if ($ticket->user !== null) {
            $ticket->user->notify($notification);

            return;
        }

        if ($ticket->email !== null) {
            Notification::route('mail', $ticket->email)->notify($notification);
        }
    }
}
