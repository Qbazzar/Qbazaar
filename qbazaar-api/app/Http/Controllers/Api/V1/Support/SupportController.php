<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Support;

use App\Actions\Support\SubmitSupportTicketAction;
use App\Enums\SupportTicketStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Support\MakeSupportTicketRequest;
use App\Http\Requests\Api\V1\Support\ReplySupportTicketRequest;
use App\Models\SupportReply;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupportController extends Controller
{
    /**
     * POST /api/v1/support/tickets — anyone can submit; auth users get their tickets attached.
     */
    public function store(MakeSupportTicketRequest $request, SubmitSupportTicketAction $submitTicket): JsonResponse
    {
        /** @var array{subject:string,category:string,body:string,email?:string} $payload */
        $payload = $request->validated();

        $ticket = $submitTicket($request->submitter(), $payload);

        $fresh = $ticket->fresh(['replies.author']) ?? $ticket;

        return response()->json($this->ticketPayload($fresh), 201);
    }

    /**
     * GET /api/v1/account/support/tickets — paginated list of caller's tickets.
     */
    public function myTickets(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $page = SupportTicket::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->withCount('replies')
            ->paginate(20);

        $items = $page->getCollection()
            ->map(fn (SupportTicket $t): array => $this->ticketListPayload($t))
            ->all();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/account/support/tickets/{id} — full ticket + replies.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $ticket = $this->findOwnedOrFail($user, $id);

        return response()->json($this->ticketPayload($ticket));
    }

    /**
     * POST /api/v1/account/support/tickets/{id}/reply — user posts a reply.
     */
    public function reply(ReplySupportTicketRequest $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $ticket = $this->findOwnedOrFail($user, $id);

        if ($ticket->status->isTerminal()) {
            throw new DomainException(ErrorCode::TICKET_INVALID_TRANSITION);
        }

        /** @var array{body:string} $payload */
        $payload = $request->validated();

        $reply = DB::transaction(function () use ($ticket, $user, $payload): SupportReply {
            $r = SupportReply::query()->create([
                'ticket_id' => $ticket->id,
                'author_id' => $user->id,
                'is_staff' => false,
                'body' => $payload['body'],
            ]);

            $patch = ['last_replied_at' => now()];
            // Bringing a waiting_user ticket back into the open queue when the
            // user replies keeps the support agent's "needs attention" view fresh.
            if ($ticket->status === SupportTicketStatus::WAITING_USER) {
                $patch['status'] = SupportTicketStatus::OPEN->value;
            }
            $ticket->forceFill($patch)->save();

            return $r;
        });

        return response()->json($this->replyPayload($reply->load('author')), 201);
    }

    private function findOwnedOrFail(User $user, string $id): SupportTicket
    {
        /** @var SupportTicket|null $ticket */
        $ticket = SupportTicket::query()->with(['replies.author'])->find($id);

        if ($ticket === null) {
            throw new DomainException(ErrorCode::TICKET_NOT_FOUND);
        }

        if ($ticket->user_id !== $user->id) {
            throw new DomainException(ErrorCode::TICKET_FORBIDDEN);
        }

        return $ticket;
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketListPayload(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'category' => $ticket->category->value,
            'status' => $ticket->status->value,
            'priority' => $ticket->priority->value,
            'last_replied_at' => $ticket->last_replied_at?->toIso8601String(),
            'replies_count' => (int) ($ticket->replies_count ?? $ticket->replies()->count()),
            'created_at' => $ticket->created_at->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketPayload(SupportTicket $ticket): array
    {
        return array_merge($this->ticketListPayload($ticket), [
            'body' => $ticket->body,
            'email' => $ticket->email,
            'replies' => $ticket->replies
                ->sortBy('created_at')
                ->values()
                ->map(fn (SupportReply $r): array => $this->replyPayload($r))
                ->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function replyPayload(SupportReply $reply): array
    {
        return [
            'id' => $reply->id,
            'author' => [
                'id' => $reply->author->id,
                'name' => $reply->author->full_name,
                'is_staff' => $reply->is_staff,
            ],
            'body' => $reply->body,
            'created_at' => $reply->created_at->toIso8601String(),
        ];
    }
}
