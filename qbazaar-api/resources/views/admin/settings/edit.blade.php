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

    <section class="mx-auto mt-8 max-w-2xl rounded-2xl border border-ink-200 bg-cream-100 p-6">
        <h2 class="mb-1 font-bold">{{ __('admin.commission_rates.title') }}</h2>
        <p class="mb-4 text-xs text-ink-500">{{ __('admin.commission_rates.intro') }}</p>

        @if ($commissionRates->isEmpty())
            <p class="mb-4 text-sm text-ink-500">{{ __('admin.commission_rates.empty') }}</p>
        @else
            <ul class="mb-5 divide-y divide-ink-200">
                @foreach ($commissionRates as $override)
                    <li class="flex items-center justify-between gap-3 py-2 text-sm">
                        <span>{{ $override->category->getLocalizedName(app()->getLocale()) }}</span>
                        <span class="flex items-center gap-3">
                            <span class="font-semibold" dir="ltr">{{ $override->rate }}%</span>
                            <form method="POST" action="{{ route('admin.settings.commission-rates.destroy', $override->category_id) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">{{ __('admin.commission_rates.remove') }}</button>
                            </form>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('admin.settings.commission-rates.store') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="min-w-48 flex-1">
                <label for="commission_category_id" class="mb-1.5 block text-sm font-semibold">{{ __('admin.commission_rates.category') }}</label>
                <select id="commission_category_id" name="category_id" required
                        class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id') === $category->id)>{{ $category->getLocalizedName(app()->getLocale()) }}</option>
                    @endforeach
                </select>
                @error('category_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="commission_rate_value" class="mb-1.5 block text-sm font-semibold">{{ __('admin.commission_rates.rate') }}</label>
                <input id="commission_rate_value" name="rate" type="number" required dir="ltr" step="0.01" min="0" max="100"
                       value="{{ old('rate') }}"
                       class="w-32 rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                @error('rate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="rounded-xl bg-coral px-5 py-2.5 text-sm font-bold text-white transition hover:brightness-95">
                {{ __('admin.commission_rates.add') }}
            </button>
        </form>
    </section>
@endsection
