<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\Support\SupportTicketCreatedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Opens a support ticket for a signed-in user or a guest, then alerts the
 * staff who work the support queue.
 */
class SubmitSupportTicketAction
{
    private const array HANDLER_ROLES = ['super_admin', 'support'];

    /**
     * @param array{subject:string,category:string,body:string,email?:string|null} $payload
     */
    public function __invoke(?User $user, array $payload): SupportTicket
    {
        $ticket = SupportTicket::query()->create([
            'user_id' => $user?->id,
            'email' => $user === null ? ($payload['email'] ?? null) : null,
            'subject' => $payload['subject'],
            'category' => $payload['category'],
            'body' => $payload['body'],
        ]);

        $handlers = $this->handlers();

        if ($handlers->isNotEmpty()) {
            Notification::send($handlers, new SupportTicketCreatedNotification($ticket));
        }

        return $ticket;
    }

    /**
     * @return Collection<int, User>
     */
    private function handlers(): Collection
    {
        // whereHas instead of the role() scope: a role missing from the DB
        // must yield no recipients, not throw RoleDoesNotExist mid-submission.
        return User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', self::HANDLER_ROLES))
            ->get();
    }
}
