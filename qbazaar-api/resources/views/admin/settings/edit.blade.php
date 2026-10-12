@extends('admin.layout')

@section('title', __('admin.settings.title'))
@section('heading', __('admin.settings.title'))

@section('content')
    <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6 [&_:is(input,select,textarea,button)]:scroll-mb-24">
        @csrf
        <input type="hidden" name="_tab" value="">
        @method('PUT')

        <p class="text-sm text-ink-500">{{ __('admin.settings.intro') }}</p>

        @php
            $tabs = collect($groups)->mapWithKeys(fn ($settings, $group) => [
                $group => [
                    'label' => __('admin.settings.groups.' . $group),
                    'error' => $errors->hasAny([
                        ...collect($settings)->map->value->all(),
                        ...($group === \App\Enums\SettingGroup::COMMISSION->value ? ['category_id', 'rate'] : []),
                    ]),
                ],
            ])->all();

            foreach ($integrationGroups as $integrationGroup) {
                $tabs[$integrationGroup->value] = [
                    'label' => __('admin.integrations.groups.' . $integrationGroup->value),
                    'error' => $errors->hasAny(
                        collect(\App\Enums\IntegrationSetting::forGroup($integrationGroup))
                            ->map(fn ($setting) => 'integrations.' . $setting->value)
                            ->all()
                    ) || ($integrationGroup === \App\Enums\IntegrationGroup::EMAIL && $errors->has('test_email'))
                        || ($integrationGroup === \App\Enums\IntegrationGroup::SMS && $errors->has('test_phone')),
                ];
            }
        @endphp

        <x-admin.tabs id="settings" :tabs="$tabs" :label="__('admin.settings.title')">
            @foreach ($groups as $group => $settings)
                <x-admin.tab-panel tabs="settings" :id="$group">
                    <x-admin.card :title="$tabs[$group]['label']">
                        <div class="grid gap-x-6 gap-y-5 lg:grid-cols-2 2xl:grid-cols-3">
                            @foreach ($settings as $setting)
                                @php($key = $setting->value)
                                @php($definition = $setting->definition())
                                <x-admin.input
                                    :name="$key"
                                    type="number"
                                    :label="__('admin.settings.fields.' . $key . '.label')"
                                    :value="old($key, $values[$key])"
                                    :hint="__('admin.settings.fields.' . $key . '.help')"
                                    :suffix="__('admin.settings.fields.' . $key . '.unit')"
                                    required
                                    step="{{ $definition->step() }}"
                                    min="{{ $definition->min }}"
                                    max="{{ $definition->max }}"
                                />
                            @endforeach
                        </div>
                    </x-admin.card>

                    @if ($group === \App\Enums\SettingGroup::COMMISSION->value)
                        @include('admin.settings._commission-rates')
                    @endif
                </x-admin.tab-panel>
            @endforeach
            @foreach ($integrationGroups as $integrationGroup)
                <x-admin.tab-panel tabs="settings" :id="$integrationGroup->value">
                    @include('admin.settings._integration-panel', ['group' => $integrationGroup])
                </x-admin.tab-panel>
            @endforeach
        </x-admin.tabs>

        <div class="sticky bottom-0 z-10 -mx-4 border-t border-ink-200 bg-cream-50/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur sm:-mx-6 sm:px-6">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                <x-admin.button icon="check">{{ __('admin.settings.save') }}</x-admin.button>
                <p class="text-xs text-ink-500">{{ __('admin.settings.save_scope') }}</p>
            </div>
        </div>
    </form>

    <form id="commission-rate-form" method="POST" action="{{ route('admin.settings.commission-rates.store') }}">@csrf</form>
    @foreach ($commissionRates as $override)
        <form id="commission-rate-remove-{{ $override->category_id }}" method="POST" action="{{ route('admin.settings.commission-rates.destroy', $override->category_id) }}"
              data-confirm="حذف نسبة العمولة الخاصة بتصنيف «{{ $override->category->getLocalizedName(app()->getLocale()) }}»؟">
            @csrf
            @method('DELETE')
        </form>
    @endforeach

    @if (collect($integrationGroups)->contains(\App\Enums\IntegrationGroup::EMAIL))
        <form id="test-email-form" method="POST" action="{{ route('admin.settings.test-email') }}">@csrf</form>
        <form id="test-sms-form" method="POST" action="{{ route('admin.settings.test-sms') }}">@csrf</form>
    @endif
@endsection
