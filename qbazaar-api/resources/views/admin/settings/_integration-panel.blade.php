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
                @foreach (['success' => config('qbazaar.web_url') . '/payment/success', 'cancel' => config('qbazaar.web_url') . '/payment/cancel', 'webhook' => url('/api/v1/payments/webhook')] as $name => $url)
                    <x-admin.input :name="'payment_' . $name . '_url_display'" :label="__('admin.integrations.payment_' . $name . '_url')" :value="$url" readonly dir="ltr" />
                @endforeach
            </div>
        </div>
    @endif
</x-admin.card>

@if ($group === \App\Enums\IntegrationGroup::EMAIL || $group === \App\Enums\IntegrationGroup::SMS)
    <x-admin.card class="mt-4">
        @php($isEmail = $group === \App\Enums\IntegrationGroup::EMAIL)
        <p class="mb-3 text-xs text-ink-500">{{ __('admin.integrations.save_first') }}</p>
        <div class="flex flex-wrap items-end gap-3">
            <x-admin.input
                :name="$isEmail ? 'test_email' : 'test_phone'"
                :type="$isEmail ? 'email' : 'tel'"
                :label="__($isEmail ? 'admin.integrations.test_email_label' : 'admin.integrations.test_sms_label')"
                form="{{ $isEmail ? 'test-email-form' : 'test-sms-form' }}"
                dir="ltr"
                class="min-w-64 flex-1"
            />
            <x-admin.button variant="secondary" form="{{ $isEmail ? 'test-email-form' : 'test-sms-form' }}">
                {{ __($isEmail ? 'admin.integrations.test_email_button' : 'admin.integrations.test_sms_button') }}
            </x-admin.button>
        </div>
    </x-admin.card>
@endif
