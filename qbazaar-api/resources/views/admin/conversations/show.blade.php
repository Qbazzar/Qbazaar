@extends('admin.layout')

@section('title', 'محادثة')
@section('heading', 'عرض المحادثة')

@php
    $ad = $conversation->ad;
    $canOpenAd = $ad !== null && auth()->user()?->can('ads.view');
    $title = $ad ? 'محادثة حول: ' . $ad->title : 'محادثة';
    $between = 'بين ' . ($conversation->buyer?->full_name ?: '—') . ' و' . ($conversation->seller?->full_name ?: '—');
@endphp

@section('content')
    <x-admin.page :title="$title" :subtitle="$between" :crumbs="[['المحادثات', route('admin.conversations.index')], 'محادثة']" side-label="معلومات المحادثة">
        <x-admin.card title="المحادثة" icon="chat" :description="'عدد الرسائل: ' . number_format($messages->total())">
            <div class="space-y-1">
                @forelse ($rows as $row)
                    @php
                        $message = $row['message'];
                        $fromSeller = $message->sender_id === $conversation->seller_id;
                        $time = optional($message->created_at)->format('Y-m-d H:i');
                    @endphp

                    @if ($row['isEvent'])
                        <div class="flex justify-center py-2">
                            <div class="max-w-lg rounded-2xl bg-cream-200 px-4 py-2 text-center text-xs font-medium text-ink-700">
                                <span class="sr-only">{{ __('admin.conversation.event') }}:</span>
                                <span class="break-words">{{ $row['text'] }}</span>
                                <span class="mt-0.5 block text-[11px] font-normal text-ink-500"><x-admin.user :user="$message->sender" size="xs" :link="false" class="me-1" /> <span dir="ltr">{{ $time }}</span></span>
                            </div>
                        </div>
                    @else
                        <div @class(['flex items-end gap-2', 'flex-row-reverse' => ! $fromSeller, 'mt-4' => $row['startsGroup'] && ! $loop->first])>
                            <div class="w-8 shrink-0">
                                @if ($row['startsGroup'])
                                    <x-admin.user-avatar :user="$message->sender" size="sm" />
                                @endif
                            </div>
                            <div class="min-w-0 max-w-lg">
                                @if ($row['startsGroup'])
                                    <div @class(['mb-1 flex items-center gap-2 text-xs text-ink-500', 'flex-row-reverse' => ! $fromSeller])>
                                        <span class="truncate font-semibold text-ink-700">{{ $message->sender?->full_name ?? '—' }}</span>
                                        <span dir="ltr">{{ $time }}</span>
                                    </div>
                                @endif
                                <div @class(['rounded-2xl px-4 py-2.5 text-sm leading-relaxed', 'bg-cream-200 text-ink-900' => $fromSeller, 'bg-coral-600 text-white' => ! $fromSeller])
                                     title="{{ $row['startsGroup'] ? '' : $time }}">
                                    @if ($message->type === \App\Enums\MessageType::OFFER)
                                        <span class="mb-1 block text-xs font-bold">{{ __('admin.conversation.offer_label') }}</span>
                                    @endif
                                    <p class="whitespace-pre-line break-words">{{ $row['text'] }}</p>
                                </div>
                            </div>
                        </div>
                    @endif
                @empty
                    <x-admin.empty-state compact icon="chat" message="لا توجد رسائل في هذه المحادثة." />
                @endforelse
            </div>
        </x-admin.card>

        <x-admin.pagination :paginator="$messages" class="!mt-0" />

        <x-slot:side>
            <x-admin.side-card title="الإعلان" icon="tag" id="side-ad" :link="$canOpenAd ? ['فتح الإعلان', route('admin.ads.show', $ad)] : null">
                @if ($ad)
                    <div class="flex min-w-0 items-center gap-3">
                        <x-admin.ad-thumb :ad="$ad" class="!size-14 !rounded-xl" />
                        <div class="min-w-0">
                            <p class="line-clamp-2 font-bold break-words text-ink-900" title="{{ $ad->title }}">{{ $ad->title }}</p>
                            <p class="mt-1 flex flex-wrap items-center gap-2 text-[13px] text-ink-500">
                                @if ($ad->price !== null)
                                    <span dir="ltr" class="font-semibold text-ink-700">{{ $ad->price }} {{ $ad->currency }}</span>
                                @endif
                                <x-admin.badge :status="$ad->status" />
                            </p>
                        </div>
                    </div>
                @else
                    <x-admin.empty-state compact icon="tag" message="الإعلان لم يعد متاحاً." />
                @endif
            </x-admin.side-card>

            <x-admin.side-card title="الأطراف" icon="users" id="side-participants">
                <ul class="grid gap-4">
                    @foreach (['المشتري' => $conversation->buyer, 'البائع' => $conversation->seller] as $role => $participant)
                        <li class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="mb-1 text-xs text-ink-500">{{ $role }}</p>
                                <x-admin.user :user="$participant" size="md" fallback="حساب محذوف" />
                            </div>
                            @if ($participant)
                                <x-admin.badge :status="$participant->status" />
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-admin.side-card>

            <x-admin.side-card title="تفاصيل المحادثة" icon="info" id="side-details">
                <x-admin.meta-list>
                    <x-admin.meta-item label="عدد الرسائل">{{ number_format($messages->total()) }}</x-admin.meta-item>
                    <x-admin.meta-item label="بدأت" ltr>{{ optional($conversation->created_at)->format('Y-m-d H:i') }}</x-admin.meta-item>
                    <x-admin.meta-item label="آخر رسالة" ltr>{{ optional($conversation->last_message_at)->format('Y-m-d H:i') ?? '—' }}</x-admin.meta-item>
                    <x-admin.meta-item label="المعرّف" ltr><span class="font-mono text-xs">{{ $conversation->id }}</span></x-admin.meta-item>
                </x-admin.meta-list>
            </x-admin.side-card>

            <x-admin.side-card :title="__('admin.conversation_access.log_title')" icon="shield" id="side-access-log">
                <p class="mb-3 text-xs text-ink-500">{{ __('admin.conversation_access.log_count', ['count' => number_format($access['total'])]) }}</p>
                <ul class="grid gap-3">
                    @foreach ($access['recent'] as $entry)
                        <li class="min-w-0">
                            <div class="flex items-center justify-between gap-3">
                                <x-admin.user :user="$entry->staff" size="xs" :fallback="__('admin.conversation_access.deleted_account')" />
                                <span class="shrink-0 text-xs text-ink-500" dir="ltr">{{ $entry->created_at->format('Y-m-d H:i') }}</span>
                            </div>
                            <p class="mt-1 text-[13px] break-words text-ink-700">{{ $entry->reason }}</p>
                        </li>
                    @endforeach
                </ul>
            </x-admin.side-card>

            @can('reports.view')
                <x-admin.side-card title="البلاغات على المحادثة" icon="flag" id="side-reports"
                                   :link="['عرض الكل', route('admin.reports.index', ['q' => $conversation->id])]">
                    @if ($reports === null)
                        <x-admin.error-state :retry-url="route('admin.conversations.show', $conversation)" />
                    @elseif ($reports->isEmpty())
                        <x-admin.empty-state compact icon="flag" message="لا توجد بلاغات على هذه المحادثة." />
                    @else
                        <ul class="grid gap-3">
                            @foreach ($reports as $report)
                                <li class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.reports.show', $report) }}" class="block truncate font-semibold text-ink-900 hover:text-coral-700">{{ $report->category->label()['ar'] }}</a>
                                        <span class="text-xs text-ink-500" dir="ltr">{{ optional($report->created_at)->format('Y-m-d') }}</span>
                                    </div>
                                    <x-admin.badge :status="$report->status" />
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-admin.side-card>
            @endcan
        </x-slot:side>
    </x-admin.page>
@endsection
