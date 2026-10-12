@extends('admin.layout')

@section('title', 'محادثة')
@section('heading', 'عرض المحادثة')

@section('content')
    @php
        $ad = $conversation->ad;
        $canOpenAd = $ad !== null && auth()->user()?->can('ads.view');
    @endphp

    <x-admin.back-link :href="route('admin.conversations.index')" class="mb-4">رجوع للمحادثات</x-admin.back-link>

    <x-admin.card class="mb-6">
        <x-admin.detail-list class="md:grid-cols-4">
            <x-admin.detail label="الإعلان">
                <span class="inline-flex max-w-full min-w-0 items-center gap-2 align-middle">
                    @if ($ad)
                        <x-admin.ad-thumb :ad="$ad" class="!size-8 !rounded-md" />
                    @endif
                    @if ($canOpenAd)
                        <a href="{{ route('admin.ads.show', $ad) }}" title="{{ $ad->title }}" class="min-w-0 truncate font-semibold hover:text-coral-700">{{ $ad->title }}</a>
                    @else
                        <span class="min-w-0 truncate font-semibold">{{ $ad?->title ?? '—' }}</span>
                    @endif
                </span>
            </x-admin.detail>
            <x-admin.detail label="المشتري"><x-admin.user :user="$conversation->buyer" /></x-admin.detail>
            <x-admin.detail label="البائع"><x-admin.user :user="$conversation->seller" /></x-admin.detail>
            <x-admin.detail label="عدد الرسائل">{{ number_format($messages->total()) }}</x-admin.detail>
        </x-admin.detail-list>
    </x-admin.card>

    <x-admin.card title="المحادثة" icon="chat">
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
                <p class="py-8 text-center text-ink-500">لا توجد رسائل في هذه المحادثة.</p>
            @endforelse
        </div>
    </x-admin.card>

    <x-admin.pagination :paginator="$messages" />
@endsection
