<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Models\SupportReply;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $priority = $request->string('priority')->toString();
        $search = $request->string('q')->toString();

        $tickets = SupportTicket::query()
            ->with(['user', 'assignee'])
            ->withCount('replies')
            ->when(
                in_array($status, array_column(SupportTicketStatus::cases(), 'value'), true),
                fn ($query) => $query->where('status', $status),
            )
            ->when(
                in_array($priority, array_column(SupportTicketPriority::cases(), 'value'), true),
                fn ($query) => $query->where('priority', $priority),
            )
            ->when(
                $search !== '',
                fn ($query) => $query->where(function ($inner) use ($search): void {
                    $inner->where('subject', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u->where('full_name', 'like', "%{$search}%"));
                }),
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('manage.support.index', [
            'tickets' => $tickets,
            'status' => $status,
            'priority' => $priority,
            'search' => $search,
            'statuses' => SupportTicketStatus::cases(),
            'priorities' => SupportTicketPriority::cases(),
        ]);
    }

    public function show(SupportTicket $ticket): View
    {
        $ticket->load([
            'user',
            'assignee',
            'replies' => fn ($query) => $query->with('author')->oldest(),
        ]);

        return view('manage.support.show', [
            'ticket' => $ticket,
            'statuses' => SupportTicketStatus::cases(),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        SupportReply::create([
            'ticket_id' => $ticket->id,
            'author_id' => (string) auth()->id(),
            'is_staff' => true,
            'body' => $data['body'],
        ]);

        $patch = ['last_replied_at' => Carbon::now()];
        if ($ticket->status === SupportTicketStatus::OPEN) {
            $patch['status'] = SupportTicketStatus::IN_PROGRESS->value;
        } elseif ($ticket->status === SupportTicketStatus::IN_PROGRESS) {
            $patch['status'] = SupportTicketStatus::WAITING_USER->value;
        }
        $ticket->forceFill($patch)->save();

        return back()->with('status', 'تم إرسال الرد');
    }

    public function updateStatus(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', array_column(SupportTicketStatus::cases(), 'value'))],
        ]);

        $ticket->forceFill(['status' => $data['status']])->save();

        return back()->with('status', 'تم تحديث حالة التذكرة');
    }

    /** Assign the ticket to the current staff member. */
    public function assignToMe(SupportTicket $ticket): RedirectResponse
    {
        $ticket->forceFill(['assigned_to' => auth()->id()])->save();

        return back()->with('status', 'تم إسناد التذكرة إليك');
    }
}
