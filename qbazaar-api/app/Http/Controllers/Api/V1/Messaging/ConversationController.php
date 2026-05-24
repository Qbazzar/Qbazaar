<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Messaging;

use App\Actions\Messaging\MarkConversationReadAction;
use App\Actions\Messaging\StartConversationAction;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Messaging\StartConversationRequest;
use App\Http\Resources\Api\V1\Messaging\ConversationListResource;
use App\Http\Resources\Api\V1\Messaging\ConversationResource;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Conversation-scoped endpoints (inbox + show + start + mark-read +
 * unread-count). Message append/list lives on {@see MessageController}.
 *
 * @group Messaging
 */
class ConversationController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly StartConversationAction $startAction,
        private readonly MarkConversationReadAction $markReadAction,
    ) {}

    /**
     * POST /api/v1/conversations — start (or resolve) a conversation.
     *
     * Returns 201 the first time the (ad, buyer) pair is seen, 200 on
     * every subsequent call. Mirrors the find-or-create semantics that
     * `StartConversationAction` enforces.
     *
     * @authenticated
     */
    public function store(StartConversationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{ad_id: string} $validated */
        $validated = $request->validated();

        /** @var Ad|null $ad */
        $ad = Ad::query()->find($validated['ad_id']);
        if ($ad === null) {
            throw new DomainException(ErrorCode::AD_NOT_FOUND);
        }

        $result = $this->startAction->execute($user, $ad);

        return response()
            ->json((new ConversationResource($result['conversation']))->toArray($request))
            ->setStatusCode($result['created']
                ? SymfonyResponse::HTTP_CREATED
                : SymfonyResponse::HTTP_OK);
    }

    /**
     * GET /api/v1/conversations — paginated inbox, most recent first.
     *
     * @authenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $paginator = Conversation::query()
            ->forUser($user)
            ->orderedForInbox()
            ->with(['ad.media', 'buyer', 'seller'])
            ->paginate(self::PER_PAGE);

        return ConversationListResource::collection($paginator);
    }

    /**
     * GET /api/v1/conversations/{id} — single conversation with full ad +
     * both participants.
     *
     * @authenticated
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $conversation = $this->findOrFail($id);
        $this->authorize('view', $conversation);

        $conversation->load(['ad.user', 'ad.category', 'ad.location', 'ad.media', 'buyer', 'seller']);

        return response()->json((new ConversationResource($conversation))->toArray($request));
    }

    /**
     * POST /api/v1/conversations/{id}/read — mark every message NOT
     * sent by the caller as read.
     *
     * Returns the number of rows updated so the client can update its
     * local store without a refetch.
     *
     * @authenticated
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $conversation = $this->findOrFail($id);
        $this->authorize('markRead', $conversation);

        $marked = $this->markReadAction->execute($user, $conversation);

        return response()->json(['marked' => $marked]);
    }

    /**
     * GET /api/v1/conversations/unread-count — sum of unread messages
     * across every conversation the caller participates in.
     *
     * Drives the header badge so the count must be cheap; we run a single
     * aggregated query against `messages` joined on the caller's
     * conversations to avoid N inbox lookups.
     *
     * @authenticated
     */
    public function unreadCount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $total = Message::query()
            ->whereIn(
                'conversation_id',
                Conversation::query()->forUser($user)->select('id'),
            )
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['total' => $total]);
    }

    /**
     * Centralised find-or-throw — every conversation-scoped endpoint maps
     * a missing row to the same stable `MSG_CONVERSATION_NOT_FOUND` code.
     *
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
