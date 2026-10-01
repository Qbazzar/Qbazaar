<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Support;

use App\Actions\Support\ReplyToTicketAsOwnerAction;
use App\Actions\Support\SubmitSupportTicketAction;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Support\MakeSupportTicketRequest;
use App\Http\Requests\Api\V1\Support\ReplySupportTicketRequest;
use App\Http\Resources\Api\V1\Support\SupportReplyResource;
use App\Http\Resources\Api\V1\Support\SupportTicketResource;
use App\Http\Resources\Api\V1\Support\SupportTicketSummaryResource;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupportController extends Controller
{
    private const PER_PAGE = 20;

    /**
     * POST /api/v1/support/tickets — anyone can submit; auth users get their tickets attached.
     */
    public function store(MakeSupportTicketRequest $request, SubmitSupportTicketAction $submitTicket): JsonResponse
    {
        /** @var array{subject:string,category:string,body:string,email?:string} $payload */
        $payload = $request->validated();

        $ticket = $submitTicket($request->submitter(), $payload);

        $fresh = $ticket->fresh(['replies.author']) ?? $ticket;

        return response()->json((new SupportTicketResource($fresh))->resolve($request), 201);
    }

    /**
     * GET /api/v1/account/support/tickets — paginated list of caller's tickets.
     */
    public function myTickets(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $tickets = SupportTicket::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->withCount('replies')
            ->paginate(self::PER_PAGE);

        return SupportTicketSummaryResource::collection($tickets);
    }

    /**
     * GET /api/v1/account/support/tickets/{id} — full ticket + replies.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $ticket = $this->findOwnedOrFail($user, $id, ['replies.author']);

        return response()->json((new SupportTicketResource($ticket))->resolve($request));
    }

    /**
     * POST /api/v1/account/support/tickets/{id}/reply — user posts a reply.
     */
    public function reply(ReplySupportTicketRequest $request, string $id, ReplyToTicketAsOwnerAction $replyAsOwner): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $ticket = $this->findOwnedOrFail($user, $id);

        $reply = $replyAsOwner($ticket, $user, (string) $request->validated('body'));

        return response()->json((new SupportReplyResource($reply))->resolve($request), 201);
    }

    /**
     * @param list<string> $relations
     *
     * @throws DomainException
     */
    private function findOwnedOrFail(User $user, string $id, array $relations = []): SupportTicket
    {
        /** @var SupportTicket|null $ticket */
        $ticket = SupportTicket::query()->with($relations)->find($id);

        if ($ticket === null) {
            throw new DomainException(ErrorCode::TICKET_NOT_FOUND);
        }

        if ($ticket->user_id !== $user->id) {
            throw new DomainException(ErrorCode::TICKET_FORBIDDEN);
        }

        return $ticket;
    }
}
