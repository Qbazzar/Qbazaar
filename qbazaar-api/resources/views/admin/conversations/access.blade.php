@extends('admin.layout')

@section('title', __('admin.conversation_access.title'))
@section('heading', __('admin.conversation_access.heading'))

@section('content')
    <x-admin.page :title="__('admin.conversation_access.title')" width="form"
                  :subtitle="__('admin.conversation_access.subtitle')"
                  :crumbs="[[__('admin.conversation_access.crumb'), route('admin.conversations.index')], __('admin.conversation_access.title')]"
                  :side-label="__('admin.conversation_access.side_label')">
        <x-admin.alert tone="warning" :title="__('admin.conversation_access.notice_title')">
            <p class="mt-1">{{ __('admin.conversation_access.notice', ['minutes' => config('qbazaar.admin.conversation_access.grant_minutes')]) }}</p>
        </x-admin.alert>

        <x-admin.form-section :title="__('admin.conversation_access.form_title')" icon="shield" id="section-reason">
            <form method="POST" action="{{ route('admin.conversations.access', $conversation) }}" class="grid gap-4">
                @csrf
                @if ($page)
                    <input type="hidden" name="page" value="{{ $page }}">
                @endif
                <x-admin.textarea
                    name="reason"
                    :label="__('admin.conversation_access.reason')"
                    :value="old('reason')"
                    rows="3"
                    required
                    autofocus
                    :hint="__('admin.conversation_access.reason_hint')"
                    minlength="{{ config('qbazaar.admin.conversation_access.reason_min_length') }}"
                    maxlength="{{ config('qbazaar.admin.conversation_access.reason_max_length') }}"
                />
                <div class="flex flex-wrap items-center gap-3">
                    <x-admin.button icon="eye">{{ __('admin.conversation_access.submit') }}</x-admin.button>
                    <x-admin.button variant="secondary" :href="route('admin.conversations.index')">{{ __('admin.shell.cancel') }}</x-admin.button>
                </div>
            </form>
        </x-admin.form-section>

        <x-slot:side>
            <x-admin.side-card :title="__('admin.conversation_access.participants')" icon="users" id="side-participants">
                <ul class="grid gap-4">
                    <li>
                        <p class="mb-1 text-xs text-ink-500">{{ __('admin.conversation_access.buyer') }}</p>
                        <x-admin.user :user="$conversation->buyer" size="md" :fallback="__('admin.conversation_access.deleted_account')" />
                    </li>
                    <li>
                        <p class="mb-1 text-xs text-ink-500">{{ __('admin.conversation_access.seller') }}</p>
                        <x-admin.user :user="$conversation->seller" size="md" :fallback="__('admin.conversation_access.deleted_account')" />
                    </li>
                </ul>
            </x-admin.side-card>

            <x-admin.side-card :title="__('admin.conversation_access.details')" icon="info" id="side-details">
                <x-admin.meta-list>
                    <x-admin.meta-item :label="__('admin.conversation_access.ad')">{{ $conversation->ad?->title ?? '—' }}</x-admin.meta-item>
                    <x-admin.meta-item :label="__('admin.conversation_access.started')" ltr>{{ optional($conversation->created_at)->format('Y-m-d H:i') }}</x-admin.meta-item>
                    <x-admin.meta-item :label="__('admin.conversation_access.last_message')" ltr>{{ optional($conversation->last_message_at)->format('Y-m-d H:i') ?? '—' }}</x-admin.meta-item>
                </x-admin.meta-list>
            </x-admin.side-card>
        </x-slot:side>
    </x-admin.page>
@endsection
