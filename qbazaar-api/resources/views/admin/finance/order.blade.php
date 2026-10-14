@extends('admin.layout')

@php
    $staff = auth()->user();
    $code = strtoupper(substr($order->id, -8));
    $canViewUsers = $staff->can('users.view');
    $canViewAds = $staff->can('ads.view') && $order->ad !== null;
    $parties = [
        ['order-buyer', __('admin.deal.order.buyer'), $order->buyer],
        ['order-seller', __('admin.deal.order.seller'), $order->seller],
    ];
@endphp

@section('title', __('admin.deal.order.page_title', ['id' => $code]))
@section('heading', __('admin.deal.order.page_heading'))

@section('content')
    <x-admin.page :title="__('admin.deal.order.page_title', ['id' => $code])"
                  :crumbs="[__('admin.deal.order.page_heading'), '#' . $code]"
                  :subtitle="$order->ad_title"
                  :side-label="__('admin.deal.order.side_label')">
        <x-slot:badges>
            <x-admin.badge :tone="['created' => 'neutral', 'awaiting_handover' => 'info', 'completed' => 'success', 'cancelled' => 'danger', 'disputed' => 'warning'][$order->status->value]">{{ __('admin.deal.order_status.' . $order->status->value) }}</x-admin.badge>
        </x-slot:badges>

        @include('admin.ads.partials.deal-order', ['deal' => $deal])
        @include('admin.ads.partials.deal-payment', ['deal' => $deal])
        @include('admin.ads.partials.deal-timeline', ['deal' => $deal])

        <x-slot:side>
            @foreach ($parties as [$id, $title, $party])
                <x-admin.side-card :id="$id" :title="$title" icon="users"
                                   :link="$party && $canViewUsers ? [__('admin.deal.buyer.open'), route('admin.users.show', $party)] : null">
                    @if ($party)
                        <div class="flex items-center gap-3">
                            <x-admin.user-avatar :user="$party" size="md" decorative />
                            <div class="min-w-0">
                                <p class="truncate font-bold text-ink-900" title="{{ $party->full_name }}">{{ $party->full_name ?: $party->email }}</p>
                                @if ($party->phone)
                                    <x-admin.phone :phone="$party->phone" class="text-[13px] text-ink-500" />
                                @endif
                            </div>
                        </div>
                        <x-admin.meta-list class="mt-4">
                            <x-admin.meta-item :label="__('admin.deal.buyer.email')" ltr>{{ $party->email }}</x-admin.meta-item>
                            <x-admin.meta-item :label="__('admin.deal.buyer.status')"><x-admin.badge :status="$party->status" /></x-admin.meta-item>
                        </x-admin.meta-list>
                    @else
                        <p class="text-ink-500">{{ __('admin.deal.order.account_deleted') }}</p>
                    @endif
                </x-admin.side-card>
            @endforeach

            <x-admin.side-card id="order-ad" :title="__('admin.deal.order.ad')" icon="tag"
                               :link="$canViewAds ? [__('admin.deal.order.open_ad'), route('admin.ads.show', $order->ad)] : null">
                <p class="font-bold break-words text-ink-900">{{ $order->ad_title }}</p>
                @if ($order->ad)
                    <p class="mt-2"><x-admin.badge :status="$order->ad->status" /></p>
                @endif
            </x-admin.side-card>
        </x-slot:side>
    </x-admin.page>
@endsection
