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
            <nav class="flex-1 space-y-4 overflow-y-auto p-4">
                @php($isSuperAdmin = (bool) auth()->user()?->hasRole('super_admin'))
                @php($groups = [
                    'عام' => [
                        ['route' => 'manage.dashboard', 'label' => 'لوحة القيادة', 'match' => 'manage.dashboard', 'icon' => 'dashboard'],
                    ],
                    'الإشراف' => [
                        ['route' => 'manage.ads.index', 'label' => 'الإعلانات', 'match' => 'manage.ads.*', 'icon' => 'tag'],
                        ['route' => 'manage.reports.index', 'label' => 'البلاغات', 'match' => 'manage.reports.*', 'icon' => 'flag'],
                        ['route' => 'manage.moderation-rules.index', 'label' => 'قواعد الإشراف', 'match' => 'manage.moderation-rules.*', 'icon' => 'shield'],
                    ],
                    'المستخدمون' => [
                        ['route' => 'manage.users.index', 'label' => 'المستخدمون', 'match' => 'manage.users.*', 'icon' => 'users'],
                        ['route' => 'manage.roles.index', 'label' => 'الأدوار', 'match' => 'manage.roles.*', 'icon' => 'key', 'super' => true],
                    ],
                    'التواصل' => [
                        ['route' => 'manage.conversations.index', 'label' => 'المحادثات', 'match' => 'manage.conversations.*', 'icon' => 'chat'],
                        ['route' => 'manage.support.index', 'label' => 'الدعم الفني', 'match' => 'manage.support.*', 'icon' => 'lifebuoy'],
                        ['route' => 'manage.offers.index', 'label' => 'العروض', 'match' => 'manage.offers.*', 'icon' => 'banknotes'],
                    ],
                    'المحتوى' => [
                        ['route' => 'manage.categories.index', 'label' => 'التصنيفات', 'match' => 'manage.categories.*', 'icon' => 'folder'],
                        ['route' => 'manage.locations.index', 'label' => 'المواقع', 'match' => 'manage.locations.*', 'icon' => 'map-pin'],
                        ['route' => 'manage.pages.index', 'label' => 'الصفحات', 'match' => 'manage.pages.*', 'icon' => 'document'],
                        ['route' => 'manage.help-categories.index', 'label' => 'أقسام المساعدة', 'match' => 'manage.help-categories.*', 'icon' => 'help'],
                        ['route' => 'manage.help-articles.index', 'label' => 'مقالات المساعدة', 'match' => 'manage.help-articles.*', 'icon' => 'document'],
                    ],
                    'المراقبة' => [
                        ['route' => 'manage.saved-searches.index', 'label' => 'عمليات البحث المحفوظة', 'match' => 'manage.saved-searches.*', 'icon' => 'bookmark'],
                        ['route' => 'manage.notifications.index', 'label' => 'الإشعارات', 'match' => 'manage.notifications.*', 'icon' => 'bell'],
                        ['route' => 'manage.activity.index', 'label' => 'سجل النشاط', 'match' => 'manage.activity.*', 'icon' => 'activity'],
                    ],
                ])
                @foreach ($groups as $groupLabel => $items)
                    <div>
                        <div class="px-4 pb-1 text-[11px] font-bold uppercase tracking-wide text-ink-300">{{ $groupLabel }}</div>
                        @foreach ($items as $item)
                            @if (($item['super'] ?? false) && ! $isSuperAdmin)
                                @continue
                            @endif
                            @php($active = request()->routeIs($item['match']))
                            <a href="{{ route($item['route']) }}"
                               class="flex items-center gap-3 rounded-xl px-4 py-2 text-sm font-semibold transition
                                      {{ $active ? 'bg-coral-soft text-coral' : 'text-ink-700 hover:bg-cream-200' }}">
                                <x-manage.icon :name="$item['icon']" class="size-[18px] shrink-0 {{ $active ? 'text-coral' : 'text-ink-500' }}" />
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                @endforeach
                <a href="/admin" class="flex items-center gap-3 rounded-xl px-4 py-2 text-sm font-medium text-ink-500 hover:bg-cream-200">
                    <x-manage.icon name="external" class="size-[18px] shrink-0" />
                    اللوحة القديمة (Filament)
                </a>
            </nav>
        </aside>

        {{-- Main --}}
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex h-16 items-center justify-between border-b border-ink-200 bg-cream-100 px-6">
                <h1 class="text-lg font-bold">@yield('heading', '')</h1>
                <div class="flex items-center gap-3">
                    @php($me = auth()->user())
                    @php($meName = $me?->full_name ?? $me?->email ?? '')
                    <div class="flex items-center gap-2.5">
                        <span class="flex size-8 items-center justify-center rounded-full bg-coral-soft text-sm font-bold text-coral">
                            {{ mb_strtoupper(mb_substr($meName, 0, 1)) ?: 'Q' }}
                        </span>
                        <span class="hidden text-sm font-semibold text-ink-700 sm:block">{{ $meName }}</span>
                    </div>
                    <form method="POST" action="{{ route('manage.logout') }}">
                        @csrf
                        <button type="submit" class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold text-ink-500 transition hover:bg-cream-200 hover:text-ink-900">
                            <x-manage.icon name="logout" class="size-[18px]" />
                            <span class="hidden sm:block">خروج</span>
                        </button>
                    </form>
                </div>
            </header>

            @include('manage.partials.toast')

            <main class="flex-1 p-6">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
