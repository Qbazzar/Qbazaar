@php($definitions = \App\Enums\IntegrationSetting::forGroup($group))

<x-admin.card :title="__('admin.integrations.groups.' . $group->value)">
    <p class="mb-4 text-xs text-ink-500">{{ __('admin.integrations.note') }}</p>

    <div class="grid gap-x-6 gap-y-5 lg:grid-cols-2 2xl:grid-cols-3">
        @foreach ($definitions as $setting)
            @include('admin.settings._integration-field', ['setting' => $setting, 'field' => $setting->definition()])
        @endforeach
    </div>

    @if ($group === \App\Enums\IntegrationGroup::PAYMENT)
        <div class="mt-6 border-t border-ink-200 pt-5">
            <h3 class="text-sm font-bold text-ink-700">{{ __('admin.integrations.payment_urls_title') }}</h3>
            <p class="mt-1 mb-3 text-xs text-ink-500">{{ __('admin.integrations.payment_urls_note') }}</p>
            <div class="grid gap-x-6 gap-y-4 lg:grid-cols-3">
                @foreach (['success' => config('qbazaar.web_url') . '/payment/success', 'cancel' => config('qbazaar.web_url') . '/payment/cancel', 'webhook' => rtrim((string) config('app.url'), '/') . '/api/v1/payments/webhook'] as $name => $url)
                    <x-admin.input :name="'payment_' . $name . '_url_display'" :label="__('admin.integrations.payment_' . $name . '_url')" :value="$url" readonly dir="ltr" />
                @endforeach
            </div>
        </div>
    @endif
</x-admin.card>

@if ($group === \App\Enums\IntegrationGroup::EMAIL || $group === \App\Enums\IntegrationGroup::SMS)
    @php($isEmail = $group === \App\Enums\IntegrationGroup::EMAIL)
    @php($me = auth()->user())
    @php($canSms = $me->phone_verified && $me->phone !== '')
    <x-admin.card class="mt-4">
        <p class="mb-3 text-xs text-ink-500">{{ __('admin.integrations.save_first') }}</p>
        <div class="flex flex-wrap items-center gap-3">
            @if ($isEmail)
                <x-admin.button variant="secondary" form="test-email-form">{{ __('admin.integrations.test_email_button') }}</x-admin.button>
                <span class="text-sm text-ink-700">{{ __('admin.integrations.test_email_to', ['to' => $me->email]) }}</span>
            @else
                <x-admin.button variant="secondary" form="test-sms-form" :disabled="! $canSms">{{ __('admin.integrations.test_sms_button') }}</x-admin.button>
                <span class="text-sm text-ink-700" dir="auto">{{ $canSms ? __('admin.integrations.test_sms_to', ['to' => '••••' . mb_substr($me->phone, -4)]) : __('admin.integrations.test_sms_no_phone') }}</span>
            @endif
        </div>
        @php($testError = $errors->first($isEmail ? 'test_email' : 'test_phone'))
        @if ($testError !== '')
            <p class="mt-2 text-xs font-semibold text-red-700">{{ $testError }}</p>
        @endif
    </x-admin.card>
@endif
