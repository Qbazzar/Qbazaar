<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function index(): View
    {
        $conversations = Conversation::query()
            ->with(['ad:id,title', 'buyer:id,full_name,email', 'seller:id,full_name,email'])
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('manage.conversations.index', ['conversations' => $conversations]);
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

        return view('manage.conversations.show', [
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }
}
