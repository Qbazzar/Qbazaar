<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Notifications\NotificationResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;

/**
 * In-app notifications inbox endpoints.
 *
 * All endpoints scope to `$request->user()` — there's never a code path
 * that lets one user read or mutate another user's notifications. The
 * lookup helper returns a NOTIF_FORBIDDEN error if a notification exists
 * but belongs to a different notifiable, vs NOTIF_NOT_FOUND when no row
 * matches at all. This split exists so the FE can route the two cases
 * separately (an explicit "not yours" vs a missing row), even though both
 * effectively block the operation.
 *
 * @group Notifications
 */
class NotificationsController extends Controller
{
    private const PER_PAGE = 20;

    /**
     * GET /api/v1/account/notifications
     *
     * Paginated 20/page, newest first. `?unread=1` filters to unread only.
     *
     * @authenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $query = $user->notifications();

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $paginator = $query->paginate(self::PER_PAGE);

        return NotificationResource::collection($paginator);
    }

    /**
     * POST /api/v1/account/notifications/{id}/read — mark one as read.
     *
     * Idempotent: reading an already-read notification re-stamps `read_at`
     * to "now" intentionally so the FE's last-seen logic works even if the
     * same notification is opened from multiple tabs.
     *
     * @authenticated
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $notification = $this->findOwnedOrFail($user, $id);
        $notification->markAsRead();

        return response()->json((new NotificationResource($notification))->toArray($request));
    }

    /**
     * POST /api/v1/account/notifications/read-all — mark every unread
     * notification owned by the caller as read.
     *
     * @authenticated
     */
    public function markAllRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $count = $user->unreadNotifications()->count();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['marked' => $count]);
    }

    /**
     * GET /api/v1/account/notifications/unread-count — drives the bell
     * badge. Returns `{ total: int }` for symmetry with the messaging
     * unread-count endpoint.
     *
     * @authenticated
     */
    public function unreadCount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'total' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * DELETE /api/v1/account/notifications/{id} — remove a single
     * notification from the user's inbox.
     *
     * @authenticated
     */
    public function destroy(Request $request, string $id): Response
    {
        /** @var User $user */
        $user = $request->user();

        $notification = $this->findOwnedOrFail($user, $id);
        $notification->delete();

        return response()->noContent();
    }

    /**
     * Resolve `{id}` to a notification owned by `$user`.
     *
     *  - NOTIF_NOT_FOUND when no row matches — keeps the same 404 shape as
     *    the rest of the API.
     *  - NOTIF_FORBIDDEN when a row exists but belongs to someone else.
     *    This branch leaks the existence of an ID, which is acceptable
     *    here because notification IDs are server-generated UUIDs and
     *    enumeration would yield no useful information.
     *
     * @throws DomainException
     */
    private function findOwnedOrFail(User $user, string $id): DatabaseNotification
    {
        /** @var DatabaseNotification|null $notification */
        $notification = DatabaseNotification::query()->find($id);

        if ($notification === null) {
            throw new DomainException(ErrorCode::NOTIF_NOT_FOUND);
        }

        if (
            $notification->getAttribute('notifiable_type') !== $user->getMorphClass()
            || $notification->getAttribute('notifiable_id') !== $user->getKey()
        ) {
            throw new DomainException(ErrorCode::NOTIF_FORBIDDEN);
        }

        return $notification;
    }
}
