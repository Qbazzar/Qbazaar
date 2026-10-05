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
    <a href="#main" class="sr-only z-50 rounded-lg bg-coral-600 px-4 py-2 text-sm font-bold text-white focus:not-sr-only focus:fixed focus:start-4 focus:top-4">تخطٍّ إلى المحتوى</a>

    <div class="flex min-h-screen">
        <div id="admin-nav-backdrop" data-nav-close class="fixed inset-0 z-30 hidden bg-black/40 md:hidden"></div>

        {{-- A slide-in drawer below md; a sticky full-height column from md up. --}}
        <aside id="admin-nav"
               class="fixed inset-y-0 start-0 z-40 flex w-64 shrink-0 flex-col border-e border-ink-200 bg-cream-100 transition-[translate,visibility] duration-200 max-md:invisible max-md:translate-x-full data-open:visible data-open:translate-x-0 md:sticky md:top-0 md:h-screen ltr:max-md:-translate-x-full">
            <div class="flex h-16 items-center justify-between gap-2 border-b border-ink-200 px-6">
                <div class="flex items-center gap-2">
                    <span class="text-xl font-extrabold text-coral">QBazaar</span>
                    <span class="text-sm font-semibold text-ink-500">الإدارة</span>
                </div>
                <button type="button" data-nav-close class="rounded-lg text-ink-500 hover:text-ink-900 md:hidden" aria-label="إغلاق القائمة">
                    <x-admin.icon name="x-circle" class="size-6" />
                </button>
            </div>
            <nav aria-label="القائمة الرئيسية" class="flex-1 space-y-4 overflow-y-auto p-4">
                @php($pendingReviewCount = auth()->user()->can(\App\Listeners\Ads\NotifyAdminsOfPendingAd::REVIEW_PERMISSION) ? app(\App\Services\Ads\PendingReviewCounter::class)->count() : 0)
                @php($groups = [
                    'عام' => [
                        ['route' => 'admin.dashboard', 'label' => 'لوحة القيادة', 'match' => 'admin.dashboard', 'icon' => 'dashboard'],
                    ],
                    'الإشراف' => [
                        ['route' => 'admin.ads.index', 'label' => 'الإعلانات', 'match' => 'admin.ads.*', 'icon' => 'tag', 'permission' => 'ads.view', 'badge' => $pendingReviewCount, 'badge_label' => __('admin.ad_review.pending_badge')],
                        ['route' => 'admin.reports.index', 'label' => 'البلاغات', 'match' => 'admin.reports.*', 'icon' => 'flag', 'permission' => 'reports.view'],
                        ['route' => 'admin.moderation-rules.index', 'label' => 'قواعد الإشراف', 'match' => 'admin.moderation-rules.*', 'icon' => 'shield', 'permission' => 'moderation-rules.manage'],
                    ],
                    'المستخدمون' => [
                        ['route' => 'admin.users.index', 'label' => 'المستخدمون', 'match' => 'admin.users.*', 'icon' => 'users', 'permission' => 'users.view'],
                        ['route' => 'admin.roles.index', 'label' => 'الأدوار', 'match' => 'admin.roles.*', 'icon' => 'key', 'permission' => 'roles.manage'],
                    ],
                    'المالية' => [
                        ['route' => 'admin.finance.settlements.index', 'label' => 'تسويات العمولة', 'match' => 'admin.finance.settlements.*', 'icon' => 'banknotes', 'permission' => 'finance.manage'],
                        ['route' => 'admin.finance.withdrawals.index', 'label' => 'طلبات السحب', 'match' => 'admin.finance.withdrawals.*', 'icon' => 'banknotes', 'permission' => 'finance.manage'],
                        ['route' => 'admin.finance.disputes.index', 'label' => 'نزاعات الطلبات', 'match' => 'admin.finance.disputes.*', 'icon' => 'flag', 'permission' => 'finance.manage'],
                        ['route' => 'admin.finance.promotions.index', 'label' => 'تحويلات الترويج', 'match' => 'admin.finance.promotions.*', 'icon' => 'banknotes', 'permission' => 'finance.manage'],
                    ],
                    'التواصل' => [
                        ['route' => 'admin.conversations.index', 'label' => 'المحادثات', 'match' => 'admin.conversations.*', 'icon' => 'chat', 'permission' => 'conversations.view'],
                        ['route' => 'admin.support.index', 'label' => 'الدعم الفني', 'match' => 'admin.support.*', 'icon' => 'lifebuoy', 'permission' => 'support.view'],
                        ['route' => 'admin.offers.index', 'label' => 'العروض', 'match' => 'admin.offers.*', 'icon' => 'banknotes', 'permission' => 'offers.view'],
                    ],
                    'المحتوى' => [
                        ['route' => 'admin.categories.index', 'label' => 'التصنيفات', 'match' => 'admin.categories.*', 'icon' => 'folder', 'permission' => 'categories.manage'],
                        ['route' => 'admin.locations.index', 'label' => 'المواقع', 'match' => 'admin.locations.*', 'icon' => 'map-pin', 'permission' => 'locations.manage'],
                        ['route' => 'admin.pages.index', 'label' => 'الصفحات', 'match' => 'admin.pages.*', 'icon' => 'document', 'permission' => 'pages.manage'],
                        ['route' => 'admin.help-categories.index', 'label' => 'أقسام المساعدة', 'match' => 'admin.help-categories.*', 'icon' => 'help', 'permission' => 'articles.manage'],
                        ['route' => 'admin.help-articles.index', 'label' => 'مقالات المساعدة', 'match' => 'admin.help-articles.*', 'icon' => 'document', 'permission' => 'articles.manage'],
                    ],
                    'المراقبة' => [
                        ['route' => 'admin.saved-searches.index', 'label' => 'عمليات البحث المحفوظة', 'match' => 'admin.saved-searches.*', 'icon' => 'bookmark', 'permission' => 'users.view'],
                        ['route' => 'admin.notifications.index', 'label' => 'الإشعارات', 'match' => 'admin.notifications.*', 'icon' => 'bell', 'permission' => 'users.view'],
                        ['route' => 'admin.activity.index', 'label' => 'سجل النشاط', 'match' => 'admin.activity.*', 'icon' => 'activity', 'permission' => 'activity.view'],
                    ],
                    __('admin.navigation_groups.system') => [
                        ['route' => 'admin.settings.edit', 'label' => __('admin.navigation.settings'), 'match' => 'admin.settings.*', 'icon' => 'adjustments', 'permission' => 'settings.manage'],
                    ],
                ])
                @foreach ($groups as $groupLabel => $items)
                    @php($items = array_filter($items, fn (array $item): bool => ! isset($item['permission']) || auth()->user()->can($item['permission'])))
                    @continue($items === [])
                    <div>
                        <p id="nav-group-{{ $loop->index }}" class="px-4 pb-1 text-[11px] font-bold uppercase tracking-wide text-ink-500">{{ $groupLabel }}</p>
                        <ul aria-labelledby="nav-group-{{ $loop->index }}">
                            @foreach ($items as $item)
                                @php($active = request()->routeIs($item['match']))
                                <li>
                                    <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif
                                       @class([
                                           'flex items-center gap-3 rounded-xl px-4 py-2 text-sm font-semibold transition',
                                           'bg-coral-soft text-coral-700' => $active,
                                           'text-ink-700 hover:bg-cream-200' => ! $active,
                                       ])>
                                        <x-admin.icon :name="$item['icon']" class="size-[18px] shrink-0 {{ $active ? 'text-coral-700' : 'text-ink-500' }}" />
                                        {{ $item['label'] }}
                                        @if (($item['badge'] ?? 0) > 0)
                                            <span class="ms-auto rounded-full bg-coral-600 px-2 py-0.5 text-[11px] font-bold text-white" title="{{ $item['badge_label'] }}">
                                                {{ $item['badge'] > 99 ? '99+' : $item['badge'] }}<span class="sr-only"> {{ $item['badge_label'] }}</span>
                                            </span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex h-16 items-center justify-between gap-3 border-b border-ink-200 bg-cream-100 px-4 sm:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    <button type="button" id="admin-nav-toggle" aria-controls="admin-nav" aria-expanded="false"
                            class="rounded-lg text-ink-500 hover:text-ink-900 md:hidden" aria-label="فتح القائمة">
                        <x-admin.icon name="menu" class="size-6" />
                    </button>
                    <h1 class="truncate text-base font-bold sm:text-lg">@yield('heading', '')</h1>
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    @php($me = auth()->user())
                    @php($meName = $me?->full_name ?? $me?->email ?? '')
                    <a href="{{ route('admin.profile.edit') }}" class="flex items-center gap-2.5 rounded-lg px-1.5 py-1 transition hover:bg-cream-200" title="حسابي">
                        <span class="flex size-8 items-center justify-center rounded-full bg-coral-soft text-sm font-bold text-coral-700" aria-hidden="true">
                            {{ mb_strtoupper(mb_substr($meName, 0, 1)) ?: 'Q' }}
                        </span>
                        <span class="hidden text-sm font-semibold text-ink-700 sm:block">{{ $meName }}</span>
                        <span class="sr-only sm:hidden">حسابي</span>
                    </a>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" aria-label="خروج" class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold text-ink-500 transition hover:bg-cream-200 hover:text-ink-900">
                            <x-admin.icon name="logout" class="size-[18px]" />
                            <span class="hidden sm:block">خروج</span>
                        </button>
                    </form>
                </div>
            </header>

            @include('admin.partials.toast')

            <main id="main" tabindex="-1" class="flex-1 p-4 outline-hidden sm:p-6">
                @yield('content')
            </main>
        </div>
    </div>

    <x-admin.confirm-dialog />

    <script>
        (function () {
            var drawer = document.getElementById('admin-nav');
            var backdrop = document.getElementById('admin-nav-backdrop');
            var toggle = document.getElementById('admin-nav-toggle');
            var desktop = window.matchMedia('(min-width: 48rem)');

            function setOpen(open) {
                drawer.toggleAttribute('data-open', open);
                backdrop.classList.toggle('hidden', !open);
                toggle.setAttribute('aria-expanded', String(open));
                document.body.classList.toggle('overflow-hidden', open);
                if (open) {
                    var firstLink = drawer.querySelector('nav a');
                    if (firstLink) firstLink.focus();
                } else if (!desktop.matches) {
                    toggle.focus();
                }
            }

            toggle.addEventListener('click', function () { setOpen(true); });
            document.querySelectorAll('[data-nav-close]').forEach(function (element) {
                element.addEventListener('click', function () { setOpen(false); });
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && drawer.hasAttribute('data-open')) setOpen(false);
            });
            desktop.addEventListener('change', function () {
                if (drawer.hasAttribute('data-open')) setOpen(false);
            });
        })();
    </script>
</body>
</html>
