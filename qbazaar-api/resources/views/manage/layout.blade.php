<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'الإدارة') · QBazaar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-cream-50 text-ink-900 antialiased">
    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <aside class="hidden w-64 shrink-0 flex-col border-l border-ink-200 bg-cream-100 md:flex">
            <div class="flex h-16 items-center gap-2 border-b border-ink-200 px-6">
                <span class="text-xl font-extrabold text-coral">QBazaar</span>
                <span class="text-sm font-semibold text-ink-500">الإدارة</span>
            </div>
            <nav class="flex-1 space-y-1 p-4">
                @php($nav = [
                    ['route' => 'manage.dashboard', 'label' => 'لوحة القيادة', 'match' => 'manage.dashboard'],
                    ['route' => 'manage.ads.index', 'label' => 'الإعلانات', 'match' => 'manage.ads.*'],
                ])
                @foreach ($nav as $item)
                    <a href="{{ route($item['route']) }}"
                       class="block rounded-xl px-4 py-2.5 text-sm font-semibold transition
                              {{ request()->routeIs($item['match'])
                                 ? 'bg-coral-soft text-coral'
                                 : 'text-ink-700 hover:bg-cream-200' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
                <a href="/admin" class="mt-4 block rounded-xl px-4 py-2.5 text-sm font-medium text-ink-500 hover:bg-cream-200">
                    اللوحة القديمة (Filament) ↗
                </a>
            </nav>
        </aside>

        {{-- Main --}}
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex h-16 items-center justify-between border-b border-ink-200 bg-cream-100 px-6">
                <h1 class="text-lg font-bold">@yield('heading', '')</h1>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-ink-500">{{ auth()->user()?->full_name ?? auth()->user()?->email }}</span>
                    <form method="POST" action="{{ route('manage.logout') }}">
                        @csrf
                        <button type="submit" class="rounded-lg px-3 py-1.5 text-sm font-semibold text-ink-700 hover:bg-cream-200">
                            خروج
                        </button>
                    </form>
                </div>
            </header>

            <main class="flex-1 p-6">
                @if (session('status'))
                    <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                        {{ session('status') }}
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
