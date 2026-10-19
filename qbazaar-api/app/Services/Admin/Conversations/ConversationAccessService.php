<?php

declare(strict_types=1);

namespace App\Services\Admin\Conversations;

use App\Models\Conversation;
use App\Models\ConversationAccessLog;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Private conversations open in the admin panel only after the staff member
 * states why. Each opening is written to the access log (shown on the
 * transcript) and to the admin audit log, and grants this staff member, in
 * this session, a short window over every page of that one transcript, so
 * paging through a long thread does not ask again.
 */
class ConversationAccessService
{
    private const string SESSION_KEY = 'admin.conversation_access';

    public function __construct(private readonly AdminAuditLogger $audit) {}

    public function hasGrant(Request $request, User $staff, Conversation $conversation): bool
    {
        return ($this->grants($request)[$this->grantKey($staff, $conversation)] ?? 0) > Carbon::now()->getTimestamp();
    }

    public function grant(Request $request, User $staff, Conversation $conversation, string $reason): ConversationAccessLog
    {
        $log = new ConversationAccessLog;
        $log->forceFill([
            'conversation_id' => $conversation->id,
            'staff_id' => $staff->id,
            'reason' => $reason,
            'ip_address' => $request->ip(),
        ])->save();

        $this->audit->record($staff, 'admin.conversations.opened', $conversation, ['reason' => $reason]);

        $this->rememberGrant($request, $staff, $conversation);

        return $log;
    }

    /**
     * The latest openings of one conversation, newest first, and how many
     * there are in all. Both read conversation_access_conversation_idx, so
     * the cost stays the same however often the conversation was opened.
     *
     * @return array{recent: Collection<int, ConversationAccessLog>, total: int}
     */
    public function summaryFor(Conversation $conversation): array
    {
        $recent = ConversationAccessLog::query()
            ->where('conversation_id', $conversation->id)
            ->with(['staff:id,full_name,email', 'staff.avatarMedia'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit((int) config('qbazaar.admin.conversation_access.log_list_limit'))
            ->get();

        return [
            'recent' => $recent,
            'total' => ConversationAccessLog::query()->where('conversation_id', $conversation->id)->count(),
        ];
    }

    private function rememberGrant(Request $request, User $staff, Conversation $conversation): void
    {
        $now = Carbon::now()->getTimestamp();
        $grants = array_filter($this->grants($request), static fn (int $expiresAt): bool => $expiresAt > $now);
        $grants[$this->grantKey($staff, $conversation)] = $now + (int) config('qbazaar.admin.conversation_access.grant_minutes') * 60;

        // A bounded session: the oldest grants give way first.
        asort($grants);
        $grants = array_slice($grants, -(int) config('qbazaar.admin.conversation_access.max_grants_per_session'), null, true);

        $request->session()->put(self::SESSION_KEY, $grants);
    }

    /**
     * Keyed by staff member too, so a session that changes hands (sign-out
     * without expiry, impersonation tooling, tests) never carries a grant over.
     */
    private function grantKey(User $staff, Conversation $conversation): string
    {
        return $staff->id . '|' . $conversation->id;
    }

    /**
     * @return array<string, int> "staff id|conversation id" => expiry timestamp
     */
    private function grants(Request $request): array
    {
        $grants = $request->session()->get(self::SESSION_KEY);

        return is_array($grants) ? array_filter($grants, 'is_int') : [];
    }
}
