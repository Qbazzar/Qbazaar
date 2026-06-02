<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Messaging;

use App\Actions\Messaging\SendMessageAction;
use App\Enums\MessageType;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Messaging\MessagesIndexRequest;
use App\Http\Requests\Api\V1\Messaging\SendMessageRequest;
use App\Http\Resources\Api\V1\Messaging\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Message-scoped endpoints: transcript list + append.
 *
 * Why split from ConversationController? Two reasons: (a) the two
 * controllers carry different action dependencies — keeping them small
 * eases unit-testing — and (b) the throttle middleware only needs to wrap
 * the `messages.store` route, which is cleaner on a dedicated controller.
 *
 * @group Messaging
 */
class MessageController extends Controller
{
    private const DEFAULT_LIMIT = 50;

    public function __construct(
        private readonly SendMessageAction $sendAction,
    ) {}

    /**
     * GET /api/v1/conversations/{id}/messages — cursor-paginated transcript.
     *
     * Newest first. `before={messageId}` returns the page strictly older than
     * the supplied message id, which lets clients implement "load more" by
     * passing the id of the oldest already-loaded bubble.
     *
     * Refusal for non-participants returns 404 (not 403) so the API doesn't
     * leak conversation existence to unrelated users — same precaution as
     * the unauthenticated ad-detail path.
     *
     * @authenticated
     */
    public function index(MessagesIndexRequest $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $conversation = $this->findOrFail($id);

        if (! $conversation->isParticipant($user)) {
            throw new DomainException(ErrorCode::MSG_CONVERSATION_NOT_FOUND);
        }

        /** @var array{before?: string|null, limit?: int|null} $validated */
        $validated = $request->validated();
        $limit = $validated['limit'] ?? self::DEFAULT_LIMIT;
        $before = $validated['before'] ?? null;

        $query = Message::query()
            ->where('conversation_id', $conversation->id)
            ->with('sender')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($before !== null && $before !== '') {
            /** @var Message|null $anchor */
            $anchor = Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('id', $before)
                ->first();

            if ($anchor === null) {
                throw new DomainException(ErrorCode::MSG_NOT_FOUND);
            }

            // Strictly older than the anchor. We compare on (created_at, id)
            // so messages produced in the same tick still paginate
            // deterministically — ULIDs are monotonic within ms.
            $query->where(function (Builder $q) use ($anchor): void {
                $q->where('created_at', '<', $anchor->created_at)
                    ->orWhere(function (Builder $q2) use ($anchor): void {
                        $q2->where('created_at', '=', $anchor->created_at)
                            ->where('id', '<', $anchor->id);
                    });
            });
        }

        $messages = $query->limit($limit)->get();

        // Materialise the resource collection into a plain array so the
        // wrapper middleware doesn't double-encode the auto-generated
        // {data, links, meta} envelope that a ResourceCollection emits.
        $rows = $messages
            ->map(fn (Message $m): array => (new MessageResource($m))->toArray($request))
            ->all();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'next_cursor' => $messages->count() === $limit
                    ? $messages->last()?->id
                    : null,
            ],
        ]);
    }

    /**
     * POST /api/v1/conversations/{id}/messages — append a message.
     *
     * @authenticated
     */
    public function store(SendMessageRequest $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $conversation = $this->findOrFail($id);

        // Non-participants get 404 (not 403) so the API never leaks the
        // existence of someone else's conversation — the same oracle-leak
        // precaution as index() above. The block check stays in
        // SendMessageAction so it can surface the stable MSG_BLOCKED code.
        if (! $conversation->isParticipant($user)) {
            throw new DomainException(ErrorCode::MSG_CONVERSATION_NOT_FOUND);
        }

        /** @var array{body: string, type?: string} $validated */
        $validated = $request->validated();
        $type = isset($validated['type'])
            ? MessageType::from($validated['type'])
            : MessageType::TEXT;

        $message = $this->sendAction->execute(
            $user,
            $conversation,
            $validated['body'],
            $type,
        );

        return response()
            ->json((new MessageResource($message))->toArray($request))
            ->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }

    /**
     * @throws DomainException
     */
    private function findOrFail(string $id): Conversation
    {
        /** @var Conversation|null $conversation */
        $conversation = Conversation::query()->find($id);

        if ($conversation === null) {
            throw new DomainException(ErrorCode::MSG_CONVERSATION_NOT_FOUND);
        }

        return $conversation;
    }
}
