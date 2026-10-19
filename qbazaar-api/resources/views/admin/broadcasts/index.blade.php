@extends('admin.layout')

@section('title', __('admin.broadcasts.title'))
@section('heading', __('admin.broadcasts.title'))

@section('content')
    <x-admin.page :title="__('admin.broadcasts.title')" :subtitle="__('admin.broadcasts.subtitle')" :side-label="__('admin.broadcasts.side_label')">
        @error('limit')
            <x-slot:lead>
                <x-admin.alert tone="warning" :title="$message" />
            </x-slot:lead>
        @enderror

        <x-admin.form-section :title="__('admin.broadcasts.compose_title')" icon="bell" id="section-compose" :description="__('admin.broadcasts.compose_hint')">
            <form method="POST" action="{{ route('admin.broadcasts.store') }}" class="grid gap-4"
                  data-confirm="{{ __('admin.broadcasts.confirm') }}" data-confirm-tone="primary">
                @csrf

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-admin.select name="segment" :label="__('admin.broadcasts.fields.segment')" required>
                        @foreach ($segments as $segment)
                            <option value="{{ $segment->value }}" @selected(old('segment', 'all') === $segment->value)>{{ $segment->label() }}</option>
                        @endforeach
                    </x-admin.select>

                    <x-admin.select name="location_id" :label="__('admin.broadcasts.fields.city')" :hint="__('admin.broadcasts.city_hint')">
                        <option value="">{{ __('admin.broadcasts.no_city') }}</option>
                        @foreach ($cities as $city)
                            <option value="{{ $city->id }}" @selected(old('location_id') === $city->id)>{{ $city->getLocalizedName($locale) }}</option>
                        @endforeach
                    </x-admin.select>
                </div>

                <x-admin.input name="title_ar" :label="__('admin.broadcasts.fields.title_ar')" :value="old('title_ar')" required dir="rtl"
                               maxlength="{{ config('qbazaar.admin.broadcasts.title_max_length') }}" />
                <x-admin.textarea name="body_ar" :label="__('admin.broadcasts.fields.body_ar')" :value="old('body_ar')" rows="4" required dir="rtl"
                                  maxlength="{{ config('qbazaar.admin.broadcasts.body_max_length') }}" />

                <x-admin.input name="title_en" :label="__('admin.broadcasts.fields.title_en')" :value="old('title_en')" dir="ltr"
                               :hint="__('admin.broadcasts.english_hint')" maxlength="{{ config('qbazaar.admin.broadcasts.title_max_length') }}" />
                <x-admin.textarea name="body_en" :label="__('admin.broadcasts.fields.body_en')" :value="old('body_en')" rows="4" dir="ltr"
                                  maxlength="{{ config('qbazaar.admin.broadcasts.body_max_length') }}" />

                <x-admin.checkbox name="send_email" :label="__('admin.broadcasts.fields.send_email')" :checked="(bool) old('send_email')" />

                <div>
                    <x-admin.button icon="bell" :disabled="$remaining['hour'] === 0 || $remaining['day'] === 0">{{ __('admin.broadcasts.submit') }}</x-admin.button>
                </div>
            </form>
        </x-admin.form-section>

        <section aria-labelledby="broadcast-history" class="grid gap-3">
            <h2 id="broadcast-history" class="text-base font-bold">{{ __('admin.broadcasts.history_title') }}</h2>

            <x-admin.table
                :caption="__('admin.broadcasts.history_title')"
                :columns="[
                    __('admin.broadcasts.columns.message'),
                    __('admin.broadcasts.columns.audience'),
                    __('admin.broadcasts.columns.status'),
                    __('admin.broadcasts.columns.delivered'),
                    ['label' => __('admin.broadcasts.columns.sender'), 'secondary' => true],
                    ['label' => __('admin.broadcasts.columns.date'), 'secondary' => true],
                ]"
                :empty="$broadcasts->isEmpty()"
                empty-icon="bell"
                :empty-message="__('admin.broadcasts.empty')"
            >
                @foreach ($broadcasts as $broadcast)
                    <x-admin.table.row>
                        <x-admin.table.cell primary>
                            <span class="block font-semibold break-words">{{ $broadcast->title_ar }}</span>
                            @if ($broadcast->title_en)
                                <span class="block text-xs break-words text-ink-500" dir="ltr">{{ $broadcast->title_en }}</span>
                            @endif
                            @if ($broadcast->send_email)
                                <span class="block text-xs text-ink-500">{{ __('admin.broadcasts.with_email') }}</span>
                            @endif
                        </x-admin.table.cell>
                        <x-admin.table.cell :label="__('admin.broadcasts.columns.audience')" class="text-ink-700">
                            {{ $broadcast->segment->label() }}@if ($broadcast->location) · {{ $broadcast->location->getLocalizedName($locale) }}@endif
                        </x-admin.table.cell>
                        <x-admin.table.cell :label="__('admin.broadcasts.columns.status')">
                            <x-admin.badge :tone="$broadcast->status->tone()">{{ $broadcast->status->label() }}</x-admin.badge>
                        </x-admin.table.cell>
                        <x-admin.table.cell :label="__('admin.broadcasts.columns.delivered')" class="text-ink-700">
                            <span dir="ltr">{{ number_format($broadcast->delivered_count) }} / {{ number_format($broadcast->recipients_count) }}</span>
                        </x-admin.table.cell>
                        <x-admin.table.cell :label="__('admin.broadcasts.columns.sender')" secondary>
                            <x-admin.user :user="$broadcast->sender" size="xs" :fallback="__('admin.conversation_access.deleted_account')" />
                        </x-admin.table.cell>
                        <x-admin.table.cell :label="__('admin.broadcasts.columns.date')" secondary class="text-ink-500">
                            <span dir="ltr">{{ $broadcast->created_at->format('Y-m-d H:i') }}</span>
                        </x-admin.table.cell>
                    </x-admin.table.row>
                @endforeach
            </x-admin.table>

            <x-admin.pagination :paginator="$broadcasts" />
        </section>

        <x-slot:side>
            <x-admin.side-card :title="__('admin.broadcasts.limits_title')" icon="clock" id="side-limits">
                <x-admin.meta-list>
                    <x-admin.meta-item :label="__('admin.broadcasts.remaining_hour')">{{ number_format($remaining['hour']) }} / {{ number_format(config('qbazaar.admin.broadcasts.max_per_hour')) }}</x-admin.meta-item>
                    <x-admin.meta-item :label="__('admin.broadcasts.remaining_day')">{{ number_format($remaining['day']) }} / {{ number_format(config('qbazaar.admin.broadcasts.max_per_day')) }}</x-admin.meta-item>
                </x-admin.meta-list>
            </x-admin.side-card>

            <x-admin.side-card :title="__('admin.broadcasts.how_title')" icon="info" id="side-how">
                <ul class="grid list-disc gap-2 ps-5 text-[13px] text-ink-700">
                    <li>{{ __('admin.broadcasts.how_queue') }}</li>
                    <li>{{ __('admin.broadcasts.how_language') }}</li>
                    <li>{{ __('admin.broadcasts.how_preferences') }}</li>
                </ul>
            </x-admin.side-card>
        </x-slot:side>
    </x-admin.page>
@endsection
