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
    <div class="w-full max-w-sm">
        <div class="mb-8 text-center">
            <div class="text-3xl font-extrabold text-coral">QBazaar</div>
            <p class="mt-1 text-sm text-ink-500">لوحة الإدارة</p>
        </div>

        <form method="POST" action="{{ route('admin.login.attempt') }}"
              class="space-y-4 rounded-2xl border border-ink-200 bg-cream-100 p-6 shadow-sm">
            @csrf

            @if ($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <div>
                <label for="email" class="mb-1.5 block text-sm font-semibold">البريد الإلكتروني</label>
                <input id="email" name="email" type="email" required autofocus
                       value="{{ old('email') }}"
                       class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
            </div>

            <div>
                <label for="password" class="mb-1.5 block text-sm font-semibold">كلمة المرور</label>
                <input id="password" name="password" type="password" required
                       class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
            </div>

            <label class="flex items-center gap-2 text-sm text-ink-700">
                <input type="checkbox" name="remember" value="1" class="rounded border-ink-300 text-coral">
                تذكّرني
            </label>

            <button type="submit"
                    class="w-full rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white transition hover:brightness-95">
                دخول
            </button>
        </form>
    </div>
</body>
</html>
