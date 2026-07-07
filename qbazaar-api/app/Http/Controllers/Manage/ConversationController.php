<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();

        $conversations = Conversation::query()
            ->with(['ad:id,title', 'buyer:id,full_name,email', 'seller:id,full_name,email'])
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

    public function show(Conversation $conversation): View
    {
        $conversation->load([
            'ad:id,title',
            'buyer:id,full_name,email',
            'seller:id,full_name,email',
        ]);

        $messages = $conversation->messages()
            ->with('sender:id,full_name')
            ->orderBy('created_at')
            ->get();

        return view('admin.conversations.show', [
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }
}
