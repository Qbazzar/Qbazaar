@extends('admin.layout')

@section('title', __('admin.inbox.title'))
@section('heading', __('admin.inbox.title'))

@section('content')
    <x-admin.page-toolbar :description="__('admin.inbox.subtitle')">
        <nav class="flex flex-wrap gap-2" aria-label="{{ __('admin.inbox.filters_label') }}">
            <x-admin.button :href="route('admin.inbox.index')" size="sm" :variant="$unreadOnly ? 'secondary' : 'primary'" :aria-current="$unreadOnly ? null : 'page'">{{ __('admin.inbox.filters.all') }}</x-admin.button>
            <x-admin.button :href="route('admin.inbox.index', ['filter' => 'unread'])" size="sm" :variant="$unreadOnly ? 'primary' : 'secondary'" :aria-current="$unreadOnly ? 'page' : null">
                {{ __('admin.inbox.filters.unread') }}
                @if ($unreadCount > 0)
                    <span class="rounded-full bg-coral-soft px-1.5 text-[11px] font-bold text-coral-700 tabular-nums">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                @endif
            </x-admin.button>
        </nav>
        @if ($unreadCount > 0)
            <x-slot:actions>
                <form method="POST" action="{{ route('admin.inbox.read-all') }}">
                    @csrf
                    <x-admin.button variant="secondary" size="sm" icon="check">{{ __('admin.inbox.read_all') }}</x-admin.button>
                </form>
            </x-slot:actions>
        @endif
    </x-admin.page-toolbar>

    @if ($queues !== [])
        <section aria-labelledby="inbox-queues-title" class="mb-6">
            <h2 id="inbox-queues-title" class="mb-3 px-1 text-[15px] font-bold text-ink-900">{{ __('admin.inbox.queues.title') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($queues as $queue)
                    <x-admin.stat :label="$queue->label" :icon="$queue->icon" :href="$queue->href" data-queue="{{ $queue->key }}">
                        <span class="block tabular-nums">{{ number_format($queue->count) }}</span>
                        <span class="mt-2 flex flex-wrap items-center gap-1.5 text-xs font-semibold">
                            @if ($queue->count === 0)
                                <span class="text-ink-500">{{ __('admin.inbox.queues.clear') }}</span>
                            @else
                                @if ($queue->state)
                                    <x-admin.badge :tone="$queue->state->tone()">{{ $queue->state->label() }}</x-admin.badge>
                                @endif
                                @if ($queue->oldestWaited)
                                    <span class="text-ink-500">{{ __('admin.inbox.queues.oldest', ['age' => $queue->oldestWaited]) }}</span>
                                @endif
                                @if ($queue->overdue)
                                    <span class="text-danger-700">{{ __('admin.inbox.queues.overdue', ['count' => number_format($queue->overdue)]) }}</span>
                                @endif
                            @endif
                        </span>
                    </x-admin.stat>
                @endforeach
            </div>
        </section>
    @endif

    <section aria-labelledby="inbox-list-title">
        <h2 id="inbox-list-title" class="mb-3 px-1 text-[15px] font-bold text-ink-900">{{ __('admin.inbox.list_title') }}</h2>

        <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
            @if ($entries->isEmpty())
                <x-admin.empty-state icon="bell" :message="$unreadOnly ? __('admin.inbox.empty_unread') : __('admin.inbox.empty')" />
            @else
                <ul class="divide-y divide-ink-200">
                    @foreach ($entries as $entry)
                        <li @class(['flex items-start gap-3 px-4 py-3.5 sm:px-5', 'bg-coral-soft/40' => $entry->unread]) data-inbox-entry="{{ $entry->id }}">
                            <span aria-hidden="true" @class([
                                'mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full',
                                'bg-coral-soft text-coral-700' => $entry->unread,
                                'bg-cream-200 text-ink-500' => ! $entry->unread,
                            ])>
                                <x-admin.icon :name="$entry->icon" class="size-[18px]" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="flex flex-wrap items-center gap-2 text-sm font-bold text-ink-900">
                                    {{ $entry->title }}
                                    @if ($entry->unread)
                                        <x-admin.badge tone="brand">{{ __('admin.inbox.unread') }}</x-admin.badge>
                                    @endif
                                </p>
                                @if ($entry->body !== '')
                                    <p class="mt-0.5 text-[13px] leading-relaxed break-words text-ink-700">{{ $entry->body }}</p>
                                @endif
                                <time class="mt-1 block text-xs text-ink-500" datetime="{{ $entry->at->toIso8601String() }}" title="{{ $entry->at->format('Y-m-d H:i') }}">{{ $entry->at->diffForHumans() }}</time>
                            </div>
                            @if ($entry->hasLink || $entry->unread)
                                <form method="POST" action="{{ route('admin.inbox.open', $entry->id) }}" class="shrink-0">
                                    @csrf
                                    <x-admin.button variant="secondary" size="sm" :icon="$entry->hasLink ? 'arrow-right' : 'check'">{{ $entry->hasLink ? __('admin.inbox.open') : __('admin.inbox.mark_read') }}</x-admin.button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <x-admin.pagination :paginator="$entries" />
    </section>
@endsection
