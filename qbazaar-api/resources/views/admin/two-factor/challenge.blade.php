<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('admin.two_factor.challenge.title') }} · QBazaar</title>
    <link rel="preload" href="{{ Vite::asset('resources/fonts/noto-kufi-arabic-variable.woff2') }}" as="font" type="font/woff2" crossorigin>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-cream-50 px-4 text-ink-900 antialiased">
    <main class="w-full max-w-sm">
        <div class="mb-8 text-center">
            <div class="text-3xl font-extrabold text-coral">QBazaar</div>
            <h1 class="mt-1 text-sm text-ink-500">{{ __('admin.two_factor.challenge.title') }}</h1>
        </div>

        <form method="POST" action="{{ route('admin.two-factor.verify') }}"
              class="space-y-4 rounded-2xl border border-ink-200 bg-cream-100 p-6 shadow-sm">
            @csrf

            <p class="text-sm text-ink-700">{{ __('admin.two_factor.challenge.intro') }}</p>

            <x-admin.input name="code" :label="__('admin.two_factor.fields.code')" required autofocus
                           inputmode="numeric" pattern="[0-9 ]*" maxlength="16" autocomplete="one-time-code" dir="ltr" />
            <x-admin.checkbox name="trust_browser" :label="__('admin.two_factor.challenge.trust_browser', ['days' => $trustedDays])" />

            <x-admin.button block>{{ __('admin.two_factor.challenge.submit') }}</x-admin.button>
        </form>

        <details class="mt-4 rounded-2xl border border-ink-200 bg-cream-100 p-4 text-sm" @if ($errors->has('recovery_code')) open @endif>
            <summary class="cursor-pointer font-semibold text-coral-700">{{ __('admin.two_factor.challenge.use_recovery') }}</summary>

            <form method="POST" action="{{ route('admin.two-factor.verify') }}" class="mt-4 space-y-4">
                @csrf
                <p class="text-ink-700">{{ __('admin.two_factor.challenge.recovery_intro') }}</p>
                <x-admin.input name="recovery_code" :label="__('admin.two_factor.fields.recovery_code')" required
                               maxlength="32" autocomplete="off" autocapitalize="characters" spellcheck="false" dir="ltr" />
                <x-admin.button variant="secondary" block>{{ __('admin.two_factor.challenge.submit_recovery') }}</x-admin.button>
            </form>
        </details>

        <p class="mt-6 text-center text-sm">
            <a href="{{ route('admin.login') }}" class="font-semibold text-ink-500 hover:text-ink-900">{{ __('admin.two_factor.challenge.back_to_login') }}</a>
        </p>
    </main>
</body>
</html>
