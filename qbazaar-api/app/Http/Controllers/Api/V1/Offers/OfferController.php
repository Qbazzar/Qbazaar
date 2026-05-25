<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Offers;

use App\Actions\Offers\AcceptOfferAction;
use App\Actions\Offers\MakeOfferAction;
use App\Actions\Offers\RejectOfferAction;
use App\Actions\Offers\WithdrawOfferAction;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Offers\MakeOfferRequest;
use App\Http\Resources\Api\V1\Offers\OfferResource;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\User;
use App\Policies\OfferPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Offer endpoints — make / list (per-conversation) + accept / reject /
 * withdraw (per-offer).
 *
 * The controller stays thin: every endpoint resolves the persisted row,
 * authorises via {@see OfferPolicy}, then delegates to a
 * single-purpose action. Domain rules (active-offer guard, lifecycle
 * status checks, broadcasting) all live in the actions so this class
 * can be unit-tested with action mocks.
 *
 * Non-participants on a private resource land in a 404 envelope (not
 * 403) to avoid leaking conversation / offer existence to strangers —
 * same precaution as the messaging endpoints.
 *
 * @group Offers
 */
class OfferController extends Controller
{
    public function __construct(
        private readonly MakeOfferAction $makeAction,
        private readonly AcceptOfferAction $acceptAction,
        private readonly RejectOfferAction $rejectAction,
        private readonly WithdrawOfferAction $withdrawAction,
    ) {}

    /**
     * POST /api/v1/conversations/{id}/offers
     *
     * @authenticated
     */
    public function store(MakeOfferRequest $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $conversation = $this->findConversationOrFail($id, $user);

        /** @var array{amount: float|int|string, note?: string|null} $validated */
        $validated = $request->validated();

        $offer = ($this->makeAction)(
            $user,
            $conversation,
            (float) $validated['amount'],
            $validated['note'] ?? null,
        );

        return response()
            ->json((new OfferResource($offer))->toArray($request))
            ->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }

    /**
     * GET /api/v1/conversations/{id}/offers — newest first.
     *
     * @authenticated
     */
    public function index(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $conversation = $this->findConversationOrFail($id, $user);

        $offers = Offer::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $rows = $offers
            ->map(fn (Offer $o): array => (new OfferResource($o))->toArray($request))
            ->all();

        // Returning `{data, meta}` matches the ApiResponseWrapper's
        // resource-collection branch, which preserves our shape instead
        // of nesting it inside another `data` key.
        return response()->json([
            'data' => $rows,
            'meta' => [],
        ]);
    }

    /**
     * POST /api/v1/offers/{id}/accept (seller).
     *
     * @authenticated
     */
    public function accept(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $offer = $this->findOfferOrFail($id, $user);
        $this->authorize('accept', $offer);

        $offer = ($this->acceptAction)($user, $offer);

        return response()->json((new OfferResource($offer))->toArray($request));
    }

    /**
     * POST /api/v1/offers/{id}/reject (seller).
     *
     * @authenticated
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $offer = $this->findOfferOrFail($id, $user);
        $this->authorize('reject', $offer);

        $offer = ($this->rejectAction)($user, $offer);

        return response()->json((new OfferResource($offer))->toArray($request));
    }

    /**
     * POST /api/v1/offers/{id}/withdraw (buyer).
     *
     * @authenticated
     */
    public function withdraw(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $offer = $this->findOfferOrFail($id, $user);
        $this->authorize('withdraw', $offer);

        $offer = ($this->withdrawAction)($user, $offer);

        return response()->json((new OfferResource($offer))->toArray($request));
    }

    /**
     * @throws DomainException
     */
    private function findConversationOrFail(string $id, User $user): Conversation
    {
        /** @var Conversation|null $conversation */
        $conversation = Conversation::query()->find($id);

        if ($conversation === null || ! $conversation->isParticipant($user)) {
            // Same 404-not-403 reasoning as MessageController::index —
            // avoid leaking conversation existence to non-participants.
            throw new DomainException(ErrorCode::MSG_CONVERSATION_NOT_FOUND);
        }

        return $conversation;
    }

    /**
     * @throws DomainException
     */
    private function findOfferOrFail(string $id, User $user): Offer
    {
        /** @var Offer|null $offer */
        $offer = Offer::query()->find($id);

        if ($offer === null) {
            throw new DomainException(ErrorCode::OFFER_NOT_FOUND);
        }

        if ($user->id !== $offer->buyer_id && $user->id !== $offer->seller_id) {
            // Non-participants get the 404 envelope to keep offer ids
            // non-enumerable through a side channel.
            throw new DomainException(ErrorCode::OFFER_NOT_FOUND);
        }

        return $offer;
    }
}
