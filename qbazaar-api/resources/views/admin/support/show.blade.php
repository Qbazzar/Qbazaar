@extends('admin.layout')

@section('title', 'تذكرة: ' . $ticket->subject)
@section('heading', 'مراجعة تذكرة')

@php
    $me = auth()->user();
    $canReply = $me?->can('support.reply') ?? false;
    $assignedToMe = $ticket->assigned_to === $me?->id;
    $requester = $ticket->user;
@endphp

@section('content')
    <x-admin.page :title="$ticket->subject"
                  :crumbs="[['تذاكر الدعم', route('admin.support.index')], \Illuminate\Support\Str::limit($ticket->subject, 48)]"
                  side-label="معلومات التذكرة">
        <x-slot:badges>
            <x-admin.badge :status="$ticket->status" />
            <x-admin.badge :status="$ticket->priority" />
            <x-admin.badge>{{ $ticket->category->label()['ar'] }}</x-admin.badge>
        </x-slot:badges>

        <x-slot:actions>
            @if ($canReply && ! $assignedToMe)
                <x-admin.form-button :action="route('admin.support.assign', $ticket)" variant="primary" icon="check">إسناد التذكرة إليّ</x-admin.form-button>
            @endif
        </x-slot:actions>

        {{-- User messages sit at the inline end, staff replies at the start. --}}
        <x-admin.card title="المحادثة" icon="lifebuoy" :description="'عدد الردود: ' . number_format($ticket->replies->count())">
            <div class="space-y-4">
                <div class="flex justify-end">
                    <div class="max-w-[80%] rounded-2xl bg-cream-200 px-4 py-3">
                        <div class="mb-1 text-xs font-semibold text-ink-500"><x-admin.user :user="$requester" size="xs" :fallback="$ticket->email ?? 'المستخدم'" /></div>
                        <p class="whitespace-pre-line break-words text-sm leading-relaxed text-ink-900">{{ $ticket->body }}</p>
                        <div class="mt-1 text-[11px] text-ink-500">{{ optional($ticket->created_at)->format('Y-m-d H:i') }}</div>
                    </div>
                </div>

                @foreach ($ticket->replies as $reply)
                    <div class="flex {{ $reply->is_staff ? 'justify-start' : 'justify-end' }}">
                        <div class="max-w-[80%] rounded-2xl px-4 py-3 {{ $reply->is_staff ? 'bg-coral-soft' : 'bg-cream-200' }}">
                            <div class="mb-1 text-xs font-semibold {{ $reply->is_staff ? 'text-coral-700' : 'text-ink-500' }}">
                                <x-admin.user :user="$reply->author" size="xs" />{{ $reply->is_staff ? ' · فريق الدعم' : '' }}
                            </div>
                            <p class="whitespace-pre-line break-words text-sm leading-relaxed text-ink-900">{{ $reply->body }}</p>
                            <div class="mt-1 text-[11px] text-ink-500">{{ optional($reply->created_at)->format('Y-m-d H:i') }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-admin.card>

        @if ($canReply)
            <x-admin.card title="إرسال رد" icon="chat">
                <form method="POST" action="{{ route('admin.support.reply', $ticket) }}" class="space-y-3">
                    @csrf
                    <x-admin.textarea name="body" label="نص الرد" label-hidden :value="old('body')" rows="5" required placeholder="اكتب ردك هنا…" />
                    <div class="flex justify-end">
                        <x-admin.button icon="chat">إرسال الرد</x-admin.button>
                    </div>
                </form>
            </x-admin.card>
        @endif

        <x-slot:side>
            <x-admin.side-card title="مقدّم التذكرة" icon="users" id="side-requester"
                               :link="$requester && $me?->can('users.view') ? ['فتح الملف', route('admin.users.show', $requester)] : null">
                <div class="flex min-w-0 items-center gap-3">
                    <x-admin.user-avatar :user="$requester" size="md" decorative />
                    <div class="min-w-0">
                        <p class="truncate font-bold text-ink-900">{{ $requester?->full_name ?: ($ticket->email ?? '—') }}</p>
                        <p class="truncate text-end text-[13px] text-ink-500" dir="ltr">{{ $requester?->email ?? $ticket->email ?? '—' }}</p>
                    </div>
                </div>
                @if ($requester)
                    <x-admin.meta-list class="mt-4 border-t border-ink-200 pt-4">
                        <x-admin.meta-item label="الهاتف"><x-admin.phone :phone="$requester->phone" /></x-admin.meta-item>
                        <x-admin.meta-item label="حالة الحساب"><x-admin.badge :status="$requester->status" /></x-admin.meta-item>
                    </x-admin.meta-list>
                @endif
            </x-admin.side-card>

            @if ($canReply)
                <x-admin.side-card title="الحالة والإسناد" icon="check" id="side-status">
                    <form method="POST" action="{{ route('admin.support.status', $ticket) }}" class="grid gap-3">
                        @csrf
                        <x-admin.select name="status" label="حالة التذكرة" label-hidden>
                            @foreach ($statuses as $case)
                                <option value="{{ $case->value }}" @selected($ticket->status === $case)>{{ $case->label()['ar'] }}</option>
                            @endforeach
                        </x-admin.select>
                        <x-admin.button variant="secondary" icon="check" block>تحديث الحالة</x-admin.button>
                    </form>

                    <div class="mt-4 flex items-center justify-between gap-3 border-t border-ink-200 pt-4">
                        <span class="text-ink-500">المسؤول</span>
                        @if ($assignedToMe)
                            <span class="text-xs font-semibold text-emerald-700">التذكرة مُسنَدة إليك</span>
                        @else
                            <x-admin.user :user="$ticket->assignee" size="xs" fallback="غير مُسنَدة" />
                        @endif
                    </div>
                </x-admin.side-card>
            @endif

            <x-admin.side-card title="تفاصيل التذكرة" icon="info" id="side-details">
                <x-admin.meta-list>
                    <x-admin.meta-item label="التصنيف">{{ $ticket->category->label()['ar'] }}</x-admin.meta-item>
                    <x-admin.meta-item label="الأولوية">{{ $ticket->priority->label()['ar'] }}</x-admin.meta-item>
                    @unless ($canReply)
                        <x-admin.meta-item label="المسؤول"><x-admin.user :user="$ticket->assignee" size="xs" fallback="غير مُسنَدة" /></x-admin.meta-item>
                    @endunless
                    <x-admin.meta-item label="تاريخ الإنشاء" ltr>{{ optional($ticket->created_at)->format('Y-m-d H:i') }}</x-admin.meta-item>
                    <x-admin.meta-item label="آخر رد" ltr>{{ optional($ticket->last_replied_at)->format('Y-m-d H:i') ?? '—' }}</x-admin.meta-item>
                    <x-admin.meta-item label="المعرّف" ltr><span class="font-mono text-xs">{{ $ticket->id }}</span></x-admin.meta-item>
                </x-admin.meta-list>
            </x-admin.side-card>

            @if ($requester)
                <x-admin.side-card title="تذاكر أخرى للمستخدم" icon="inbox" id="side-other-tickets"
                                   :link="$requester->full_name ? ['عرض الكل', route('admin.support.index', ['q' => $requester->full_name])] : null">
                    @if ($otherTickets === null)
                        <x-admin.error-state :retry-url="route('admin.support.show', $ticket)" />
                    @elseif ($otherTickets->isEmpty())
                        <x-admin.empty-state compact icon="inbox" message="لا توجد تذاكر أخرى." />
                    @else
                        <ul class="grid gap-3">
                            @foreach ($otherTickets as $other)
                                <li class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.support.show', $other) }}" class="block truncate font-semibold text-ink-900 hover:text-coral-700" title="{{ $other->subject }}">{{ $other->subject }}</a>
                                        <span class="text-xs text-ink-500" dir="ltr">{{ optional($other->created_at)->format('Y-m-d') }}</span>
                                    </div>
                                    <x-admin.badge :status="$other->status" />
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-admin.side-card>
            @endif

            @can('activity.view')
                <x-admin.side-card title="سجل التذكرة" icon="activity" id="side-audit">
                    @if ($timeline === null)
                        <x-admin.error-state :retry-url="route('admin.support.show', $ticket)" />
                    @else
                        <x-admin.timeline :entries="$timeline" />
                    @endif
                </x-admin.side-card>
            @endcan
        </x-slot:side>
    </x-admin.page>
@endsection
