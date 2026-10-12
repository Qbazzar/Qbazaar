<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\Messaging\AdminTranscriptBuilder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();

        $conversations = Conversation::query()
            ->with(['ad:id,title', 'buyer:id,full_name,email', 'seller:id,full_name,email', 'buyer.media', 'seller.media'])
            ->withCount('messages')
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
        ]);
    }

    public function show(Conversation $conversation, AdminTranscriptBuilder $transcript): View
    {
        $conversation->load([
            'ad:id,title',
            'ad.primaryImage',
            'buyer:id,full_name,email',
            'seller:id,full_name,email',
            'buyer.media',
            'seller.media',
        ]);

        $messages = $conversation->messages()
            ->with('sender:id,full_name,email')
            ->with('sender.media')
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(100);

        return view('admin.conversations.show', [
            'conversation' => $conversation,
            'messages' => $messages,
            'rows' => $transcript->build($messages->getCollection(), app()->getLocale()),
        ]);
    }
}
