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
@endsection
