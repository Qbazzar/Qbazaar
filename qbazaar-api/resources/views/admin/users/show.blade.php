@extends('admin.layout')

@section('title', 'مستخدم · ' . ($user->full_name ?? $user->email))
@section('heading', 'تفاصيل المستخدم')

@php
    $assignedRoles = $user->roles->pluck('name')->all();
    $me = auth()->user();
    $displayName = $user->full_name ?: $user->email;
    $tabLabels = [
        'profile' => 'الملف الشخصي',
        'ads' => 'الإعلانات (' . number_format($user->ads_count) . ')',
        'orders' => 'الطلبات',
        'wallet' => 'المحفظة',
    ];
    $tabItems = collect($tabs)->mapWithKeys(fn (string $key): array => [$key => ['label' => $tabLabels[$key]]])->all();
@endphp

@section('content')
    <x-admin.page :title="$displayName" :crumbs="[['المستخدمون', route('admin.users.index')], $displayName]" side-label="معلومات المستخدم">
        <x-slot:badges>
            <x-admin.badge :status="$user->status" />
            @if ($user->isBusiness())
                <x-admin.badge tone="info">حساب أعمال</x-admin.badge>
            @endif
        </x-slot:badges>

        <x-slot:actions>
            @can('finance.view')
                <x-admin.button variant="secondary" icon="banknotes" :href="route('admin.finance.statements.show', $user)">كشف الحساب</x-admin.button>
            @endcan
        </x-slot:actions>

        <x-slot:menu>
            @can('reports.view')
                <x-admin.menu.item icon="flag" :href="route('admin.reports.index', ['q' => $user->id])">البلاغات عن المستخدم</x-admin.menu.item>
            @endcan
            {{-- Both lists search by name, so an account without one has nothing to filter on. --}}
            @if ($user->full_name)
                @can('support.view')
                    <x-admin.menu.item icon="lifebuoy" :href="route('admin.support.index', ['q' => $user->full_name])">تذاكر الدعم</x-admin.menu.item>
                @endcan
                @can('conversations.view')
                    <x-admin.menu.item icon="chat" :href="route('admin.conversations.index', ['q' => $user->full_name])">المحادثات</x-admin.menu.item>
                @endcan
            @endif
        </x-slot:menu>

        {{-- min-w-0: the scrolling tab strip must not widen the grid column on phones. --}}
        <x-admin.tabs :tabs="$tabItems" id="user-tabs" label="أقسام ملف المستخدم" :active="$activeTab" class="min-w-0">
            @foreach ($tabs as $key)
                @php($tabUrl = route('admin.users.show', [$user, 'tab' => $key]))
                <x-admin.tab-panel tabs="user-tabs" :id="$key" :data-qb-lazy="$key === $activeTab ? null : route('admin.users.tab', [$user, $key])">
                    @if ($key === $activeTab)
                        @include("admin.users.tabs.{$key}", ['user' => $user, ...$panel])
                    @else
                        {{-- Fetched when the tab opens; without scripts the link reloads the page on that tab. --}}
                        <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
                            <a href="{{ $tabUrl }}" data-qb-lazy-fallback class="text-sm font-semibold text-coral-700 hover:underline">عرض {{ $tabLabels[$key] }}</a>
                            <x-admin.skeleton lines="5" avatar hidden data-qb-lazy-skeleton />
                        </div>
                        <template data-qb-lazy-error>
                            <div class="rounded-2xl border border-ink-200 bg-cream-100">
                                <x-admin.error-state :retry-url="$tabUrl" />
                            </div>
                        </template>
                    @endif
                </x-admin.tab-panel>
            @endforeach
        </x-admin.tabs>

        <x-slot:side>
            <x-admin.side-card title="الحساب" icon="users" id="side-account">
                <div class="flex min-w-0 items-center gap-3">
                    <x-admin.user-avatar :user="$user" size="lg" decorative />
                    <div class="min-w-0">
                        <p class="truncate font-bold text-ink-900">{{ $user->full_name ?: '—' }}</p>
                        <p class="truncate text-end text-[13px] text-ink-500" dir="ltr">{{ $user->email }}</p>
                        <p class="text-[13px] text-ink-500"><x-admin.phone :phone="$user->phone" /></p>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-between gap-3 border-t border-ink-200 pt-4">
                    <span class="text-ink-500">الحالة</span>
                    <x-admin.badge :status="$user->status" />
                </div>

                @if ($canManageUser)
                    @can('users.ban')
                        <form method="POST" class="mt-4"
                              action="{{ $user->status === \App\Enums\UserStatus::SUSPENDED ? route('admin.users.activate', $user) : route('admin.users.suspend', $user) }}">
                            @csrf
                            @if ($user->status === \App\Enums\UserStatus::SUSPENDED)
                                <x-admin.button variant="success" icon="check" block>تفعيل المستخدم</x-admin.button>
                            @else
                                <x-admin.button variant="danger" icon="ban" block>إيقاف المستخدم</x-admin.button>
                            @endif
                        </form>
                    @endcan
                @endif
            </x-admin.side-card>

            <x-admin.side-card title="الأدوار" icon="key" id="side-roles">
                @if ($assignedRoles === [])
                    <p class="text-ink-500">لا توجد أدوار مُسندة.</p>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach ($assignedRoles as $roleName)
                            <x-admin.role-badge :role="$roleName" />
                        @endforeach
                    </div>
                @endif

                @if ($canManageRoles)
                    <form method="POST" action="{{ route('admin.users.roles', $user) }}" class="mt-4 border-t border-ink-200 pt-4">
                        @csrf
                        <fieldset>
                            <legend class="mb-3 text-[13px] font-semibold text-ink-700">تعديل الأدوار</legend>
                            <div class="grid gap-2">
                                @foreach ($roles as $role)
                                    <x-admin.checkbox
                                        name="roles[]"
                                        :id="'role-' . $role->name"
                                        :value="$role->name"
                                        :label="\Illuminate\Support\Facades\Lang::has('admin.role_names.' . $role->name) ? __('admin.role_names.' . $role->name) : $role->name"
                                        :checked="in_array($role->name, $assignedRoles, true)"
                                        class="rounded-xl border border-ink-200 bg-cream-50 px-3 py-2"
                                    />
                                @endforeach
                            </div>
                            @error('roles')
                                <p class="mt-2 text-xs font-semibold text-red-700">{{ $message }}</p>
                            @enderror
                        </fieldset>
                        <x-admin.button icon="key" block class="mt-4">حفظ الأدوار</x-admin.button>
                    </form>
                @endif
            </x-admin.side-card>

            @if ($canManageUser && ($me->can('users.update') || ($me->can('users.impersonate') && ! $user->isStaff())))
                <x-admin.side-card title="إجراءات الحساب" icon="shield" id="side-actions">
                    <div class="grid gap-4">
                        @can('users.update')
                            <form method="POST" action="{{ route('admin.users.reset-password', $user) }}"
                                  data-confirm="إرسال رابط إعادة تعيين كلمة المرور إلى بريد المستخدم؟" data-confirm-tone="primary">
                                @csrf
                                <x-admin.button variant="secondary" icon="key" block>إرسال رابط تعيين كلمة المرور</x-admin.button>
                            </form>
                        @endcan

                        @if ($me->can('users.impersonate') && ! $user->isStaff())
                            <form method="POST" action="{{ route('admin.users.impersonate', $user) }}" target="_blank" class="grid gap-2"
                                  data-confirm="فتح الموقع كأنك هذا المستخدم في تبويب جديد؟" data-confirm-tone="primary">
                                @csrf
                                <x-admin.textarea
                                    name="reason"
                                    id="impersonation-reason"
                                    :label="__('admin.impersonation.reason')"
                                    :value="old('reason')"
                                    rows="2"
                                    required
                                    :hint="__('admin.impersonation.reason_hint', ['minutes' => config('qbazaar.admin.impersonation_ttl_minutes')])"
                                    minlength="{{ config('qbazaar.admin.impersonation_reason_min_length') }}"
                                    maxlength="{{ config('qbazaar.admin.impersonation_reason_max_length') }}"
                                />
                                <x-admin.button variant="secondary" icon="external" block>{{ __('admin.impersonation.submit') }}</x-admin.button>
                            </form>
                        @endif
                    </div>
                </x-admin.side-card>
            @endif

            <x-admin.side-card title="التواريخ" icon="clock" id="side-dates">
                <x-admin.meta-list>
                    <x-admin.meta-item label="تاريخ الإنشاء" ltr>{{ optional($user->created_at)->format('Y-m-d H:i') ?? '—' }}</x-admin.meta-item>
                    <x-admin.meta-item label="آخر دخول" ltr>{{ optional($user->last_login_at)->format('Y-m-d H:i') ?? '—' }}</x-admin.meta-item>
                    <x-admin.meta-item label="آخر تحديث" ltr>{{ optional($user->updated_at)->format('Y-m-d H:i') ?? '—' }}</x-admin.meta-item>
                    @if ($user->deletion_requested_at)
                        <x-admin.meta-item label="طلب الحذف" ltr>{{ $user->deletion_requested_at->format('Y-m-d H:i') }}</x-admin.meta-item>
                    @endif
                </x-admin.meta-list>
            </x-admin.side-card>

            @can('activity.view')
                <x-admin.side-card title="سجل التدقيق" icon="activity" id="side-audit">
                    @if ($timeline === null)
                        <x-admin.error-state :retry-url="route('admin.users.show', [$user, 'tab' => $activeTab])" />
                    @else
                        <x-admin.timeline :entries="$timeline" />
                    @endif
                </x-admin.side-card>
            @endcan
        </x-slot:side>
    </x-admin.page>
@endsection

@push('admin-scripts')
    <script>
        (function () {
            var root = document.querySelector('[data-tabs="user-tabs"]');
            if (!root) return;

            function load(panel) {
                if (panel.dataset.loading) return;
                panel.dataset.loading = '1';
                panel.setAttribute('aria-busy', 'true');
                var fallback = panel.querySelector('[data-qb-lazy-fallback]');
                var skeleton = panel.querySelector('[data-qb-lazy-skeleton]');
                if (fallback) fallback.hidden = true;
                if (skeleton) skeleton.hidden = false;

                fetch(panel.getAttribute('data-qb-lazy'), { headers: { Accept: 'text/html' }, credentials: 'same-origin' })
                    .then(function (response) {
                        // A redirect means the session ended or a password change is due; the retry link reloads the page to show it.
                        if (!response.ok || response.redirected) throw new Error(String(response.status));
                        return response.text();
                    })
                    .then(function (html) {
                        panel.innerHTML = html;
                        panel.removeAttribute('data-qb-lazy');
                    })
                    .catch(function () {
                        var error = panel.querySelector('template[data-qb-lazy-error]');
                        if (error) panel.replaceChildren(error.content.cloneNode(true));
                    })
                    .finally(function () {
                        panel.removeAttribute('aria-busy');
                    });
            }

            // The tabs script flips `hidden` on the panels; a panel still waiting for its
            // content loads the first time it becomes visible, so closed tabs cost nothing.
            function loadVisible() {
                root.querySelectorAll('[role="tabpanel"][data-qb-lazy]').forEach(function (panel) {
                    if (!panel.hidden) load(panel);
                });
            }

            // Reloads and shared links open the same tab, rendered by the server.
            function rememberTab() {
                var selected = root.querySelector('[role="tab"][aria-selected="true"]');
                if (!selected) return;
                var url = new URL(location.href);
                if (url.searchParams.get('tab') === selected.dataset.tab && !url.hash) return;
                url.searchParams.set('tab', selected.dataset.tab);
                url.searchParams.delete('page');
                url.hash = '';
                history.replaceState(null, '', url);
            }

            loadVisible();
            var observer = new MutationObserver(function () {
                loadVisible();
                rememberTab();
            });
            root.querySelectorAll('[role="tabpanel"]').forEach(function (panel) {
                observer.observe(panel, { attributes: true, attributeFilter: ['hidden'] });
            });
        })();
    </script>
@endpush
