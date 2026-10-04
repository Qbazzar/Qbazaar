@extends('admin.layout')

@section('title', __('admin.settings.title'))
@section('heading', __('admin.settings.title'))

@section('content')
    <form method="POST" action="{{ route('admin.settings.update') }}" class="mx-auto max-w-2xl space-y-6">
        @csrf
        @method('PUT')

        <p class="text-sm text-ink-500">{{ __('admin.settings.intro') }}</p>

        @foreach ($groups as $group => $settings)
            <x-admin.card :title="__('admin.settings.groups.' . $group)">
                <div class="space-y-5">
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
                            dir="ltr"
                            step="{{ $definition->step() }}"
                            min="{{ $definition->min }}"
                            max="{{ $definition->max }}"
                            input-class="max-w-xs"
                        />
                    @endforeach
                </div>
            </x-admin.card>
        @endforeach

        <x-admin.button icon="check">{{ __('admin.settings.save') }}</x-admin.button>
    </form>

    <x-admin.card :title="__('admin.commission_rates.title')" :description="__('admin.commission_rates.intro')" class="mx-auto mt-8 max-w-2xl">
        @if ($commissionRates->isEmpty())
            <p class="mb-4 text-sm text-ink-500">{{ __('admin.commission_rates.empty') }}</p>
        @else
            <ul class="mb-5 divide-y divide-ink-200">
                @foreach ($commissionRates as $override)
                    <li class="flex items-center justify-between gap-3 py-2 text-sm">
                        <span>{{ $override->category->getLocalizedName(app()->getLocale()) }}</span>
                        <span class="flex items-center gap-3">
                            <span class="font-semibold" dir="ltr">{{ $override->rate }}%</span>
                            <form method="POST" action="{{ route('admin.settings.commission-rates.destroy', $override->category_id) }}"
                                  data-confirm="حذف نسبة العمولة الخاصة بتصنيف «{{ $override->category->getLocalizedName(app()->getLocale()) }}»؟">
                                @csrf
                                @method('DELETE')
                                <x-admin.button variant="danger-ghost" size="sm">{{ __('admin.commission_rates.remove') }}</x-admin.button>
                            </form>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('admin.settings.commission-rates.store') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <x-admin.select name="category_id" id="commission_category_id" :label="__('admin.commission_rates.category')" required class="min-w-48 flex-1">
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('category_id') === $category->id)>{{ $category->getLocalizedName(app()->getLocale()) }}</option>
                @endforeach
            </x-admin.select>
            <x-admin.input name="rate" id="commission_rate_value" type="number" :label="__('admin.commission_rates.rate')" :value="old('rate')"
                           required dir="ltr" step="0.01" min="0" max="100" class="w-32" />
            <x-admin.button icon="plus">{{ __('admin.commission_rates.add') }}</x-admin.button>
        </form>
    </x-admin.card>
@endsection
