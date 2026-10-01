<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;

/**
 * Staff move a ticket to any status by hand, including reopening a closed
 * one; only replies follow the automatic workflow.
 */
class ChangeTicketStatusAction
{
    public function __invoke(SupportTicket $ticket, SupportTicketStatus $status): SupportTicket
    {
        $ticket->forceFill(['status' => $status])->save();

        return $ticket;
    }
}
