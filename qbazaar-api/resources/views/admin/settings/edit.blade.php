@extends('admin.layout')

@section('title', __('admin.settings.title'))
@section('heading', __('admin.settings.title'))

@section('content')
    <form method="POST" action="{{ route('admin.settings.update') }}" class="mx-auto max-w-2xl space-y-6">
        @csrf
        @method('PUT')

        <p class="text-sm text-ink-500">{{ __('admin.settings.intro') }}</p>

        @foreach ($groups as $group => $settings)
            <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
                <h2 class="mb-4 font-bold">{{ __("admin.settings.groups.{$group}") }}</h2>
                <div class="space-y-5">
                    @foreach ($settings as $setting)
                        @php($key = $setting->value)
                        @php($definition = $setting->definition())
                        <div>
                            <label for="{{ $key }}" class="mb-1.5 block text-sm font-semibold">{{ __("admin.settings.fields.{$key}.label") }}</label>
                            <div class="flex items-center gap-2">
                                <input id="{{ $key }}" name="{{ $key }}" type="number" required dir="ltr"
                                       step="{{ $definition->step() }}" min="{{ $definition->min }}" max="{{ $definition->max }}"
                                       value="{{ old($key, $values[$key]) }}"
                                       aria-describedby="{{ $key }}_help"
                                       class="w-full max-w-xs rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                                <span class="text-sm font-semibold text-ink-500">{{ __("admin.settings.fields.{$key}.unit") }}</span>
                            </div>
                            <p id="{{ $key }}_help" class="mt-1 text-xs text-ink-500">{{ __("admin.settings.fields.{$key}.help") }}</p>
                            @error($key)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-coral px-5 py-2.5 text-sm font-bold text-white transition hover:brightness-95">
            <x-admin.icon name="check" class="size-[18px]" /> {{ __('admin.settings.save') }}
        </button>
    </form>
@endsection
