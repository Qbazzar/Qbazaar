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
        {{-- Mobile backdrop --}}
        <div id="mnav-bd" onclick="qbNav(false)"
             class="fixed inset-0 z-30 hidden bg-black/40 md:hidden"></div>

        {{-- Sidebar (static on desktop, slide-in drawer on mobile / RTL from the right) --}}
        <aside id="mnav"
               class="fixed inset-y-0 right-0 z-40 flex w-64 shrink-0 translate-x-full flex-col border-l border-ink-200 bg-cream-100 transition-transform duration-200 md:static md:translate-x-0">
            <div class="flex h-16 items-center justify-between gap-2 border-b border-ink-200 px-6">
                <div class="flex items-center gap-2">
                    <span class="text-xl font-extrabold text-coral">QBazaar</span>
                    <span class="text-sm font-semibold text-ink-500">الإدارة</span>
                </div>
                <button type="button" onclick="qbNav(false)" class="text-ink-400 hover:text-ink-700 md:hidden" aria-label="إغلاق">
                    <x-manage.icon name="x-circle" class="size-5" />
                </button>
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
            </nav>
        </aside>

        {{-- Main --}}
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex h-16 items-center justify-between border-b border-ink-200 bg-cream-100 px-4 sm:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    <button type="button" onclick="qbNav(true)" class="text-ink-500 hover:text-ink-900 md:hidden" aria-label="القائمة">
                        <x-manage.icon name="menu" class="size-6" />
                    </button>
                    <h1 class="truncate text-base font-bold sm:text-lg">@yield('heading', '')</h1>
                </div>
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

            <main class="flex-1 p-4 sm:p-6">
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        // Mobile nav drawer toggle (no deps). Desktop keeps the sidebar static.
        function qbNav(open) {
            var a = document.getElementById('mnav'), b = document.getElementById('mnav-bd');
            if (!a || !b) return;
            a.classList.toggle('translate-x-full', !open);
            a.classList.toggle('translate-x-0', open);
            b.classList.toggle('hidden', !open);
        }

        // Bulk-selection: one selectable table per page. Row checkboxes carry
        // class `qb-bulk-cb`; the select-all is `#qb-bulk-all`; the action bar is
        // `#qb-bulk-form` (hidden until ≥1 row is checked).
        function qbBulkAll(cb) {
            document.querySelectorAll('.qb-bulk-cb').forEach(function (c) { c.checked = cb.checked; });
            qbBulkSync();
        }
        function qbBulkSync() {
            var n = document.querySelectorAll('.qb-bulk-cb:checked').length;
            var bar = document.getElementById('qb-bulk-form');
            var cnt = document.getElementById('qb-bulk-count');
            if (cnt) cnt.textContent = n;
            if (bar) bar.classList.toggle('hidden', n === 0);
            if (bar) bar.classList.toggle('flex', n > 0);
        }
    </script>
</body>
</html>
