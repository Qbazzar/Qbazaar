@extends('admin.layout')

@section('title', __('admin.home_sections.title'))
@section('heading', __('admin.home_sections.title'))

@section('content')
    <x-admin.page :title="__('admin.home_sections.title')" :subtitle="__('admin.home_sections.subtitle')" width="form">
        @foreach ($panels as $panel)
            <x-admin.form-section :id="'section-' . $panel->section->value"
                                  :title="__('admin.home_sections.sections.' . $panel->section->value . '.title')"
                                  :description="__('admin.home_sections.sections.' . $panel->section->value . '.auto_rule')"
                                  icon="home">
                <x-slot:actions>
                    <x-admin.badge :status="$panel->mode" />
                </x-slot:actions>

                <form method="POST" action="{{ route('admin.home-sections.mode', $panel->section) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    @method('PUT')
                    <x-admin.select name="mode" :id="'mode-' . $panel->section->value" :label="__('admin.home_sections.mode_label')" class="min-w-56">
                        @foreach ($modes as $mode)
                            <option value="{{ $mode->value }}" @selected($panel->mode === $mode)>{{ __('admin.home_sections.modes.' . $mode->value) }}</option>
                        @endforeach
                    </x-admin.select>
                    <x-admin.button variant="secondary" icon="check">{{ __('admin.home_sections.save_mode') }}</x-admin.button>
                </form>

                @if ($panel->isManual() && $panel->liveCount() === 0)
                    <x-admin.alert tone="warning" :title="__('admin.home_sections.fallback_title')">{{ __('admin.home_sections.fallback_body') }}</x-admin.alert>
                @endif

                <div class="grid gap-3">
                    <p class="text-sm font-semibold text-ink-700">
                        {{ __('admin.home_sections.picks_title') }}
                        <span class="ms-1 text-xs font-normal text-ink-500 tabular-nums">{{ __('admin.home_sections.picks_count', ['count' => $panel->items->count(), 'max' => $panel->limit]) }}</span>
                    </p>

                    @if ($panel->items->isEmpty())
                        <x-admin.empty-state compact icon="tag" :message="__('admin.home_sections.picks_empty')" />
                    @else
                        <form method="POST" action="{{ route('admin.home-sections.order', $panel->section) }}" class="grid gap-3">
                            @csrf
                            @method('PUT')
                            <ol class="grid gap-2">
                                @foreach ($panel->items as $item)
                                    <li class="flex flex-wrap items-center gap-3 rounded-xl border border-ink-200 bg-cream-50 p-3">
                                        <x-admin.input type="number" :name="'positions[' . $item->id . ']'" :id="'position-' . $item->id"
                                                       :value="$item->position" :label="__('admin.home_sections.position_label', ['title' => $item->ad?->title ?? $item->ad_id])"
                                                       label-hidden min="1" max="999" class="w-20" input-class="text-center" />
                                        <x-admin.ad-thumb :ad="$item->ad" />
                                        <span class="min-w-0 flex-1">
                                            @if ($item->ad)
                                                <a href="{{ route('admin.ads.show', $item->ad) }}" class="block truncate font-semibold text-ink-900 hover:text-coral-700">{{ $item->ad->title }}</a>
                                                @unless ($item->ad->isPubliclyListed())
                                                    <span class="block text-xs font-semibold text-warning-800">{{ __('admin.home_sections.not_listed') }}</span>
                                                @endunless
                                            @else
                                                <span class="block truncate text-ink-500">{{ __('admin.home_sections.ad_deleted') }}</span>
                                            @endif
                                        </span>
                                        @if ($item->ad)
                                            <x-admin.badge :status="$item->ad->status" />
                                        @endif
                                        <x-admin.form-button :action="route('admin.home-sections.items.destroy', [$panel->section, $item->id])" method="DELETE"
                                                             variant="danger-ghost" size="sm" icon="trash"
                                                             :confirm="__('admin.home_sections.remove_confirm')">{{ __('admin.home_sections.remove') }}</x-admin.form-button>
                                    </li>
                                @endforeach
                            </ol>
                            <div>
                                <x-admin.button variant="secondary" icon="check">{{ __('admin.home_sections.save_order') }}</x-admin.button>
                            </div>
                        </form>
                    @endif

                    @unless ($panel->isFull())
                        <form method="POST" action="{{ route('admin.home-sections.items.store', $panel->section) }}" class="flex flex-wrap items-end gap-3">
                            @csrf
                            <x-admin.input name="ad" :id="'add-' . $panel->section->value" :label="__('admin.home_sections.add_label')"
                                           :hint="__('admin.home_sections.add_hint')" dir="ltr" required maxlength="300" class="min-w-0 flex-1" />
                            <x-admin.button icon="plus">{{ __('admin.home_sections.add') }}</x-admin.button>
                        </form>
                    @endunless
                </div>
            </x-admin.form-section>
        @endforeach
    </x-admin.page>
@endsection
