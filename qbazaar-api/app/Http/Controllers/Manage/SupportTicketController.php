<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Actions\Support\ChangeTicketStatusAction;
use App\Actions\Support\ReplyToTicketAsStaffAction;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Admin\EntityTimeline;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    private const SIDE_LIST_LIMIT = 5;

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $priority = $request->string('priority')->toString();
        $search = $request->string('q')->toString();

        $tickets = SupportTicket::query()
            ->with(['user.avatarMedia', 'assignee.avatarMedia'])
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

        return view('admin.support.index', [
            'tickets' => $tickets,
            'status' => $status,
            'priority' => $priority,
            'search' => $search,
            'statuses' => SupportTicketStatus::cases(),
            'priorities' => SupportTicketPriority::cases(),
        ]);
    }

    public function show(Request $request, SupportTicket $ticket, EntityTimeline $timeline): View
    {
        $ticket->load([
            'user.avatarMedia',
            'assignee.avatarMedia',
            'replies' => fn ($query) => $query->with('author.avatarMedia')->oldest(),
        ]);

        return view('admin.support.show', [
            'ticket' => $ticket,
            'statuses' => SupportTicketStatus::cases(),
            'otherTickets' => rescue(fn () => $this->otherTicketsOf($ticket)),
            'timeline' => $request->user()?->can('activity.view')
                ? rescue(fn () => $timeline->forSubject($ticket))
                : null,
        ]);
    }

    /**
     * The requester's latest other tickets for the side column, read through
     * the (user_id, created_at) index.
     *
     * @return Collection<int, SupportTicket>
     */
    private function otherTicketsOf(SupportTicket $ticket): Collection
    {
        if ($ticket->user_id === null) {
            return new Collection;
        }

        return SupportTicket::query()
            ->where('user_id', $ticket->user_id)
            ->whereKeyNot($ticket->getKey())
            ->latest()
            ->limit(self::SIDE_LIST_LIMIT)
            ->get(['id', 'subject', 'status', 'created_at']);
    }

    public function reply(Request $request, SupportTicket $ticket, ReplyToTicketAsStaffAction $replyAsStaff): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:' . (int) config('qbazaar.support.body_max_length')],
        ]);

        /** @var User $staff */
        $staff = $request->user();

        $replyAsStaff($ticket, $staff, $data['body']);

        return back()->with('status', __('admin.flash.support_replied'));
    }

    public function updateStatus(Request $request, SupportTicket $ticket, ChangeTicketStatusAction $changeStatus): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', array_column(SupportTicketStatus::cases(), 'value'))],
        ]);

        $changeStatus($ticket, SupportTicketStatus::from($data['status']));

        return back()->with('status', __('admin.flash.support_status_updated'));
    }

    /** Assign the ticket to the current staff member. */
    public function assignToMe(SupportTicket $ticket): RedirectResponse
    {
        $ticket->forceFill(['assigned_to' => auth()->id()])->save();

        return back()->with('status', __('admin.flash.support_assigned'));
    }
}
