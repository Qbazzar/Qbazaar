<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تسجيل الدخول · QBazaar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-cream-50 px-4 text-ink-900 antialiased">
    <main class="w-full max-w-sm">
        <div class="mb-8 text-center">
            <div class="text-3xl font-extrabold text-coral">QBazaar</div>
            <h1 class="mt-1 text-sm text-ink-500">لوحة الإدارة</h1>
        </div>

        <form method="POST" action="{{ route('admin.login.attempt') }}"
              class="space-y-4 rounded-2xl border border-ink-200 bg-cream-100 p-6 shadow-sm">
            @csrf

            <x-admin.input name="email" type="email" label="البريد الإلكتروني" :value="old('email')" required autofocus autocomplete="username" />
            <x-admin.input name="password" type="password" label="كلمة المرور" required autocomplete="current-password" />
            <x-admin.checkbox name="remember" label="تذكّرني" />

            <x-admin.button block>دخول</x-admin.button>
        </form>
    </main>
</body>
</html>
