<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Enums\SupportTicketStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\SupportReply;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Posts the owner's reply. A ticket waiting on the user goes back to the open
 * queue so it shows up again in the agents' "needs attention" view.
 */
class ReplyToTicketAsOwnerAction
{
    /**
     * @throws DomainException
     */
    public function __invoke(SupportTicket $ticket, User $owner, string $body): SupportReply
    {
        $reply = DB::transaction(function () use ($ticket, $owner, $body): SupportReply {
            /** @var SupportTicket $locked */
            $locked = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($locked->status->isTerminal()) {
                throw new DomainException(ErrorCode::TICKET_INVALID_TRANSITION);
            }

            $reply = SupportReply::query()->create([
                'ticket_id' => $locked->id,
                'author_id' => $owner->id,
                'is_staff' => false,
                'body' => $body,
            ]);

            $patch = ['last_replied_at' => now()];
            if ($locked->status === SupportTicketStatus::WAITING_USER) {
                $patch['status'] = SupportTicketStatus::OPEN->value;
            }
            $locked->forceFill($patch)->save();

            return $reply;
        });

        return $reply->setRelation('author', $owner);
    }
}
