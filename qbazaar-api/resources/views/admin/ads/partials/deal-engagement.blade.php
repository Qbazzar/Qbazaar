{{-- Main column: the ad's newest conversations and offers, for any ad that has them. Needs $deal (AdTransaction|null), $ad. --}}
@php
    $limit = \App\Services\Admin\Ads\AdTransactionSummary::LIST_LIMIT;
    $money = fn (string $amount): string => \App\Support\Money::format($amount);
@endphp

@if ($deal === null)
    <x-admin.card><x-admin.error-state :retry-url="request()->fullUrl()" /></x-admin.card>
@else
    @if ($deal->access->conversations && $deal->conversationsTotal > 0)
        <section id="ad-conversations" aria-labelledby="ad-conversations-title" class="grid gap-3">
            <div class="flex flex-wrap items-end justify-between gap-3 px-1">
                <div class="min-w-0">
                    <h2 id="ad-conversations-title" class="text-[15px] font-bold text-ink-900">{{ __('admin.deal.conversations.title') }}</h2>
                    <p class="mt-1 text-[13px] text-ink-500">{{ __('admin.deal.conversations.count', ['shown' => $deal->conversations->count(), 'total' => number_format($deal->conversationsTotal)]) }}</p>
                </div>
                @if ($deal->conversationsTotal > $limit)
                    <a href="{{ route('admin.conversations.index', ['ad' => $ad->id]) }}"
                       class="shrink-0 rounded-full bg-cream-200 px-2.5 py-0.5 text-xs font-semibold text-ink-700 transition hover:bg-coral-soft hover:text-coral-700">{{ __('admin.deal.view_all', ['count' => number_format($deal->conversationsTotal)]) }}</a>
                @endif
            </div>

            <x-admin.table :caption="__('admin.deal.conversations.title')"
                           :columns="[__('admin.deal.conversations.participants'), __('admin.deal.conversations.last_message'), __('admin.deal.conversations.state'), ['label' => __('admin.deal.conversations.last_activity'), 'secondary' => true]]">
                @foreach ($deal->conversations as $row)
                    <x-admin.table.row>
                        <x-admin.table.cell primary>
                            <a href="{{ route('admin.conversations.show', $row->conversation) }}" class="grid gap-1.5 hover:text-coral-700">
                                <span class="flex min-w-0 items-center gap-2">
                                    <x-admin.user-avatar :user="$row->conversation->buyer" size="xs" decorative />
                                    <span class="truncate font-bold">{{ $row->conversation->buyer?->full_name ?: $row->conversation->buyer?->email ?: '—' }}</span>
                                    <span class="text-xs text-ink-500">{{ __('admin.deal.conversations.buyer') }}</span>
                                </span>
                                <span class="flex min-w-0 items-center gap-2">
                                    <x-admin.user-avatar :user="$row->conversation->seller" size="xs" decorative />
                                    <span class="truncate font-semibold text-ink-700">{{ $row->conversation->seller?->full_name ?: $row->conversation->seller?->email ?: '—' }}</span>
                                    <span class="text-xs text-ink-500">{{ __('admin.deal.conversations.seller') }}</span>
                                </span>
                            </a>
                        </x-admin.table.cell>
                        <x-admin.table.cell :label="__('admin.deal.conversations.last_message')" class="text-ink-700">
                            @if ($row->privateExcerpt)
                                <span class="text-ink-500">{{ __('admin.deal.conversations.private_message') }}</span>
                            @else
                                <span class="line-clamp-2 break-words">{{ $row->excerpt ?? __('admin.deal.conversations.no_messages') }}</span>
                            @endif
                        </x-admin.table.cell>
                        <x-admin.table.cell :label="__('admin.deal.conversations.state')">
                            <span class="flex flex-wrap justify-end gap-1.5 md:justify-start">
                                @if ($row->unread > 0)
                                    <x-admin.badge tone="brand">{{ __('admin.deal.conversations.unread', ['count' => $row->unread]) }}</x-admin.badge>
                                @endif
                                @if ($row->hiddenByBuyer)
                                    <x-admin.badge>{{ __('admin.deal.conversations.hidden_buyer') }}</x-admin.badge>
                                @endif
                                @if ($row->hiddenBySeller)
                                    <x-admin.badge>{{ __('admin.deal.conversations.hidden_seller') }}</x-admin.badge>
                                @endif
                                @if ($row->unread === 0 && ! $row->hiddenByBuyer && ! $row->hiddenBySeller)
                                    <span class="text-ink-500">—</span>
                                @endif
                            </span>
                        </x-admin.table.cell>
                        <x-admin.table.cell :label="__('admin.deal.conversations.last_activity')" secondary class="text-ink-500">
                            @if ($row->conversation->last_message_at)
                                <time dir="ltr" datetime="{{ $row->conversation->last_message_at->toIso8601String() }}" title="{{ $row->conversation->last_message_at->format('Y-m-d H:i') }}">{{ $row->conversation->last_message_at->diffForHumans() }}</time>
                            @else
                                —
                            @endif
                        </x-admin.table.cell>
                    </x-admin.table.row>
                @endforeach
            </x-admin.table>
        </section>
    @endif

    @if ($deal->access->offers && $deal->offersTotal > 0)
        <section id="ad-offers" aria-labelledby="ad-offers-title" class="grid gap-3">
            <div class="flex flex-wrap items-end justify-between gap-3 px-1">
                <div class="min-w-0">
                    <h2 id="ad-offers-title" class="text-[15px] font-bold text-ink-900">{{ __('admin.deal.offers.title') }}</h2>
                    <p class="mt-1 text-[13px] text-ink-500">{{ __('admin.deal.offers.count', ['shown' => $deal->offers->count(), 'total' => number_format($deal->offersTotal)]) }}</p>
                </div>
                @if ($deal->offersTotal > $limit)
                    <a href="{{ route('admin.offers.index', ['ad' => $ad->id]) }}"
                       class="shrink-0 rounded-full bg-cream-200 px-2.5 py-0.5 text-xs font-semibold text-ink-700 transition hover:bg-coral-soft hover:text-coral-700">{{ __('admin.deal.view_all', ['count' => number_format($deal->offersTotal)]) }}</a>
                @endif
            </div>

            <x-admin.table :caption="__('admin.deal.offers.title')"
                           :columns="[__('admin.deal.offers.buyer'), __('admin.deal.offers.amount'), __('admin.deal.offers.status'), ['label' => __('admin.deal.offers.time'), 'secondary' => true], ['label' => __('admin.deal.offers.actions'), 'srOnly' => true]]">
                @foreach ($deal->offers as $offer)
                    <x-admin.table.row>
                        <x-admin.table.cell primary><x-admin.user :user="$offer->buyer" /></x-admin.table.cell>
                        <x-admin.table.cell :label="__('admin.deal.offers.amount')"><span dir="ltr" class="font-semibold">{{ $money($offer->amount) }} {{ $offer->currency }}</span></x-admin.table.cell>
                        <x-admin.table.cell :label="__('admin.deal.offers.status')"><x-admin.badge :status="$offer->status" /></x-admin.table.cell>
                        <x-admin.table.cell :label="__('admin.deal.offers.time')" secondary class="text-ink-500">
                            <time dir="ltr" datetime="{{ $offer->created_at->toIso8601String() }}" title="{{ $offer->created_at->format('Y-m-d H:i') }}">{{ $offer->created_at->diffForHumans() }}</time>
                        </x-admin.table.cell>
                        <x-admin.table.actions>
                            {{-- The offers section only shows to staff with offers.view, which also opens the offer page. --}}
                            <x-admin.icon-button :href="route('admin.offers.show', $offer)" icon="eye" :label="__('admin.offer_admin.open')" />
                            @if ($deal->access->conversations)
                                <x-admin.icon-button :href="route('admin.conversations.show', $offer->conversation_id)" icon="chat" :label="__('admin.deal.offers.open_chat')" />
                            @endif
                        </x-admin.table.actions>
                    </x-admin.table.row>
                @endforeach
            </x-admin.table>
        </section>
    @endif
@endif
