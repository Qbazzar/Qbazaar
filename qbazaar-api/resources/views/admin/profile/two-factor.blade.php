@extends('admin.layout')

@section('title', __('admin.two_factor.page.title'))
@section('heading', __('admin.two_factor.page.title'))

@section('content')
    <x-admin.page :title="__('admin.two_factor.page.title')" width="form" :subtitle="__('admin.two_factor.page.subtitle')"
                  :crumbs="[[__('admin.two_factor.page.profile'), route('admin.profile.edit')], __('admin.two_factor.page.title')]"
                  :side-label="__('admin.two_factor.page.side_label')">
        @if ($required && ! $enabled)
            <x-slot:lead>
                <x-admin.alert tone="warning" :title="__('admin.two_factor.enrolment_required')">
                    <p class="mt-1">{{ __('admin.two_factor.page.required_hint') }}</p>
                </x-admin.alert>
            </x-slot:lead>
        @endif

        @if ($recoveryCodes)
            <x-admin.alert tone="success" :title="__('admin.two_factor.recovery.title')" id="recovery-codes">
                <p class="mt-1">{{ __('admin.two_factor.recovery.save_hint') }}</p>
                <ul class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm font-bold text-ink-900" dir="ltr">
                    @foreach ($recoveryCodes as $recoveryCode)
                        <li class="rounded-lg bg-cream-50 px-3 py-1.5 text-center select-all">{{ $recoveryCode }}</li>
                    @endforeach
                </ul>
            </x-admin.alert>
        @endif

        @if ($enabled)
            <x-admin.form-section :title="__('admin.two_factor.recovery.regenerate_title')" icon="key" id="section-recovery"
                                  :description="__('admin.two_factor.recovery.regenerate_hint')">
                <form method="POST" action="{{ route('admin.profile.two-factor.recovery-codes') }}" class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end"
                      data-confirm="{{ __('admin.two_factor.recovery.regenerate_confirm') }}" data-confirm-tone="primary">
                    @csrf
                    <x-admin.input name="current_password" id="regenerate-password" type="password" :label="__('admin.two_factor.fields.current_password')" required autocomplete="current-password" />
                    <x-admin.button variant="secondary" icon="refresh">{{ __('admin.two_factor.recovery.regenerate') }}</x-admin.button>
                </form>
            </x-admin.form-section>

            <x-admin.form-section :title="__('admin.two_factor.disable.title')" icon="shield" id="section-disable"
                                  :description="$required ? __('admin.two_factor.disable.required_hint') : __('admin.two_factor.disable.hint')">
                <form method="POST" action="{{ route('admin.profile.two-factor.destroy') }}" class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end"
                      data-confirm="{{ __('admin.two_factor.disable.confirm') }}">
                    @csrf
                    @method('DELETE')
                    <x-admin.input name="current_password" id="disable-password" type="password" :label="__('admin.two_factor.fields.current_password')" required autocomplete="current-password" />
                    <x-admin.button variant="danger" icon="x-circle">{{ __('admin.two_factor.disable.submit') }}</x-admin.button>
                </form>
            </x-admin.form-section>
        @elseif ($secret !== null)
            <x-admin.form-section :title="__('admin.two_factor.setup.step_key')" icon="key" id="section-key"
                                  :description="__('admin.two_factor.setup.step_key_hint')">
                <div class="grid gap-3">
                    <p class="rounded-xl border border-ink-200 bg-cream-50 px-4 py-3 text-center font-mono text-base font-bold tracking-wider text-ink-900 select-all" dir="ltr">{{ $secretGroups }}</p>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-[13px] text-ink-500">
                        <a href="{{ $provisioningUri }}" class="font-semibold text-coral-700 hover:underline">{{ __('admin.two_factor.setup.open_in_app') }}</a>
                        <span>{{ __('admin.two_factor.setup.key_details') }}</span>
                    </div>
                </div>
            </x-admin.form-section>

            <x-admin.form-section :title="__('admin.two_factor.setup.step_confirm')" icon="check" id="section-confirm"
                                  :description="__('admin.two_factor.setup.step_confirm_hint')">
                <form method="POST" action="{{ route('admin.profile.two-factor.confirm') }}" class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                    @csrf
                    <x-admin.input name="code" :label="__('admin.two_factor.fields.code')" required autofocus inputmode="numeric"
                                   pattern="[0-9 ]*" maxlength="16" autocomplete="one-time-code" dir="ltr" />
                    <x-admin.button icon="check">{{ __('admin.two_factor.setup.confirm') }}</x-admin.button>
                </form>
            </x-admin.form-section>

            <form method="POST" action="{{ route('admin.profile.two-factor.store') }}" class="text-sm">
                @csrf
                <button type="submit" class="font-semibold text-ink-500 hover:text-ink-900">{{ __('admin.two_factor.setup.restart') }}</button>
            </form>
        @else
            <x-admin.form-section :title="__('admin.two_factor.setup.title')" icon="shield" id="section-start"
                                  :description="__('admin.two_factor.setup.intro')">
                <form method="POST" action="{{ route('admin.profile.two-factor.store') }}">
                    @csrf
                    <x-admin.button icon="shield">{{ __('admin.two_factor.setup.start') }}</x-admin.button>
                </form>
            </x-admin.form-section>
        @endif

        <x-slot:side>
            <x-admin.side-card :title="__('admin.two_factor.page.status')" icon="shield" id="side-status">
                <x-admin.meta-list>
                    <x-admin.meta-item :label="__('admin.two_factor.page.state')">
                        <x-admin.badge :tone="$enabled ? 'success' : ($secret !== null ? 'warning' : 'neutral')">
                            {{ $enabled ? __('admin.two_factor.states.enabled') : ($secret !== null ? __('admin.two_factor.states.pending') : __('admin.two_factor.states.disabled')) }}
                        </x-admin.badge>
                    </x-admin.meta-item>
                    <x-admin.meta-item :label="__('admin.two_factor.page.policy')">
                        {{ $required ? __('admin.two_factor.page.policy_required') : __('admin.two_factor.page.policy_optional') }}
                    </x-admin.meta-item>
                    @if ($enabled)
                        <x-admin.meta-item :label="__('admin.two_factor.page.enabled_at')" ltr>{{ $credential->confirmed_at->format('Y-m-d H:i') }}</x-admin.meta-item>
                        <x-admin.meta-item :label="__('admin.two_factor.page.recovery_left')">{{ number_format($credential->remainingRecoveryCodes()) }}</x-admin.meta-item>
                    @endif
                </x-admin.meta-list>
            </x-admin.side-card>

            <x-admin.side-card :title="__('admin.two_factor.page.how_title')" icon="info" id="side-how">
                <ul class="grid list-disc gap-2 ps-5 text-[13px] text-ink-700">
                    <li>{{ __('admin.two_factor.page.how_apps') }}</li>
                    <li>{{ __('admin.two_factor.page.how_trust', ['days' => $trustedDays]) }}</li>
                    <li>{{ __('admin.two_factor.page.how_lost') }}</li>
                </ul>
            </x-admin.side-card>
        </x-slot:side>
    </x-admin.page>
@endsection
