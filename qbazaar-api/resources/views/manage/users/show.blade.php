@extends('manage.layout')

@section('title', 'مستخدم · ' . ($user->full_name ?? $user->email))
@section('heading', 'تفاصيل المستخدم')

@php
    $statusStyles = [
        'active' => 'bg-emerald-50 text-emerald-700',
        'suspended' => 'bg-red-50 text-red-700',
        'deactivated' => 'bg-cream-200 text-ink-500',
        'pending_deletion' => 'bg-cream-200 text-ink-500',
    ];
    $statusLabels = [
        'active' => 'نشط',
        'suspended' => 'موقوف',
        'deactivated' => 'معطّل',
        'pending_deletion' => 'بانتظار الحذف',
    ];
    $assignedRoles = $user->roles->pluck('name')->all();
@endphp

@section('content')
    <a href="{{ url()->previous() }}" class="mb-4 inline-block text-sm font-semibold text-ink-500 hover:text-coral">→ رجوع</a>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Profile --}}
        <div class="space-y-6 lg:col-span-2">
            <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-4">
                        @if ($user->avatarThumbUrl())
                            <img src="{{ $user->avatarThumbUrl() }}" alt="" class="h-14 w-14 rounded-full border border-ink-200 object-cover">
                        @endif
                        <div>
                            <h2 class="text-xl font-bold">{{ $user->full_name ?? '—' }}</h2>
                            <div class="text-sm text-ink-500">{{ $user->email }}</div>
                        </div>
                    </div>
                    <span class="inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusStyles[$user->status->value] ?? 'bg-cream-200 text-ink-500' }}">
                        {{ $statusLabels[$user->status->value] ?? $user->status->value }}
                    </span>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-4 text-sm">
                    <div><span class="text-ink-500">المعرّف:</span> <span class="font-mono text-xs">{{ $user->id }}</span></div>
                    <div><span class="text-ink-500">الهاتف:</span> {{ $user->phone ?? '—' }}</div>
                    <div><span class="text-ink-500">نوع الحساب:</span> {{ $user->account_type->value }}</div>
                    <div><span class="text-ink-500">اللغة:</span> {{ $user->language->value }}</div>
                    <div><span class="text-ink-500">البريد موثّق:</span> {{ $user->email_verified ? 'نعم' : 'لا' }}</div>
                    <div><span class="text-ink-500">الهاتف موثّق:</span> {{ $user->phone_verified ? 'نعم' : 'لا' }}</div>
                    <div><span class="text-ink-500">عدد الإعلانات:</span> {{ number_format($user->ads_count) }}</div>
                    <div><span class="text-ink-500">آخر دخول:</span> {{ optional($user->last_login_at)->format('Y-m-d H:i') ?? '—' }}</div>
                    <div><span class="text-ink-500">تاريخ الإنشاء:</span> {{ optional($user->created_at)->format('Y-m-d H:i') }}</div>
                </div>
            </div>

            {{-- Roles --}}
            <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
                <div class="mb-3 text-sm font-semibold text-ink-500">الأدوار</div>
                @if ($assignedRoles === [])
                    <p class="text-sm text-ink-500">لا توجد أدوار مُسندة.</p>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach ($assignedRoles as $roleName)
                            <span class="inline-flex items-center rounded-full bg-coral-soft px-3 py-1 text-xs font-semibold text-coral">{{ $roleName }}</span>
                        @endforeach
                    </div>
                @endif

                @if ($canManageRoles)
                    <form method="POST" action="{{ route('manage.users.roles', $user) }}" class="mt-5 border-t border-ink-200 pt-5">
                        @csrf
                        <div class="mb-3 text-sm font-semibold text-ink-500">تعديل الأدوار</div>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($roles as $role)
                                <label class="flex items-center gap-2 rounded-xl border border-ink-200 bg-cream-50 px-3 py-2 text-sm">
                                    <input type="checkbox" name="roles[]" value="{{ $role->name }}"
                                           @checked(in_array($role->name, $assignedRoles, true))
                                           class="rounded border-ink-200 text-coral focus:ring-coral">
                                    <span class="font-semibold">{{ $role->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        <button class="mt-4 w-full rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white hover:brightness-95">حفظ الأدوار</button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Actions --}}
        <div class="space-y-6">
            <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
                <div class="mb-4 text-sm font-semibold text-ink-500">إجراءات الإشراف</div>
                <div class="space-y-3">
                    @if ($user->status === \App\Enums\UserStatus::SUSPENDED)
                        <form method="POST" action="{{ route('manage.users.activate', $user) }}">
                            @csrf
                            <button class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:brightness-95">تفعيل المستخدم</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('manage.users.suspend', $user) }}">
                            @csrf
                            <button class="w-full rounded-xl bg-red-600 px-4 py-2.5 text-sm font-bold text-white hover:brightness-95">إيقاف المستخدم</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
