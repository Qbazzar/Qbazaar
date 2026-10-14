<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Enums\ReportTarget;
use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Report;
use App\Services\Messaging\AdminTranscriptBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConversationController extends Controller
{
    private const SIDE_LIST_LIMIT = 5;

    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();
        $adId = $request->string('ad')->toString();

        $conversations = Conversation::query()
            ->with(['ad:id,title', 'buyer:id,full_name,email', 'seller:id,full_name,email', 'buyer.avatarMedia', 'seller.avatarMedia'])
            ->withCount('messages')
            // Served by conversations_ad_last_msg_idx, which also gives the newest-first order.
            ->when($adId !== '', fn ($query) => $query->where('ad_id', $adId))
            ->when(
                $search !== '',
                fn ($query) => $query->where(function ($inner) use ($search): void {
                    $inner->whereHas('ad', fn ($ad) => $ad->where('title', 'like', "%{$search}%"))
                        ->orWhereHas('buyer', fn ($user) => $user->where('full_name', 'like', "%{$search}%"))
                        ->orWhereHas('seller', fn ($user) => $user->where('full_name', 'like', "%{$search}%"));
                }),
            )
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.conversations.index', [
            'conversations' => $conversations,
            'search' => $search,
            'adFilter' => $adId === '' ? null : Ad::withTrashed()->find($adId, ['id', 'title']),
        ]);
    }

    public function show(Request $request, Conversation $conversation, AdminTranscriptBuilder $transcript): View
    {
        $conversation->load([
            'ad:id,title,price,currency,status',
            'ad.primaryImage',
            'buyer:id,full_name,email,status',
            'seller:id,full_name,email,status',
            'buyer.avatarMedia',
            'seller.avatarMedia',
        ]);

        // Long threads open on their newest page; older pages stay one click away.
        $perPage = 100;
        $lastPage = max(1, (int) ceil($conversation->messages()->count() / $perPage));
        $page = $request->integer('page') ?: $lastPage;

        $messages = $conversation->messages()
            ->with('sender:id,full_name,email')
            ->with('sender.avatarMedia')
            // The relation sorts newest first; the transcript reads oldest to newest.
            ->reorder('created_at')
            ->orderBy('id')
            ->paginate($perPage, page: $page);

        return view('admin.conversations.show', [
            'conversation' => $conversation,
            'messages' => $messages,
            'rows' => $transcript->build($messages->getCollection(), app()->getLocale()),
            'reports' => $request->user()?->can('reports.view')
                ? rescue(fn () => $this->reportsOn($conversation))
                : null,
        ]);
    }

    /**
     * The latest reports filed against the conversation itself, read through
     * reports_target_status_idx.
     *
     * @return Collection<int, Report>
     */
    private function reportsOn(Conversation $conversation): Collection
    {
        return Report::query()
            ->where('target_type', ReportTarget::CONVERSATION->value)
            ->where('target_id', $conversation->id)
            ->latest()
            ->limit(self::SIDE_LIST_LIMIT)
            ->get(['id', 'category', 'status', 'created_at']);
    }
}
