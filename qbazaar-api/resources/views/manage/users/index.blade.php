@extends('manage.layout')

@section('title', 'المستخدمون')
@section('heading', 'المستخدمون')

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
@endphp

@section('content')
    {{-- Filters --}}
    <form method="GET" class="mb-6 flex flex-wrap items-center gap-3">
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-ink-300">
                <x-manage.icon name="search" class="size-[18px]" />
            </span>
            <input type="text" name="q" value="{{ $search }}" placeholder="بحث بالاسم أو البريد أو الهاتف…"
                   class="w-64 rounded-xl border border-ink-200 bg-cream-100 py-2.5 pr-10 pl-4 text-sm outline-none focus:border-coral">
        </div>

        <select name="status" class="rounded-xl border border-ink-200 bg-cream-100 px-4 py-2.5 text-sm outline-none focus:border-coral">
            <option value="">كل الحالات</option>
            @foreach ($statuses as $case)
                <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $statusLabels[$case->value] ?? $case->value }}</option>
            @endforeach
        </select>

        <select name="role" class="rounded-xl border border-ink-200 bg-cream-100 px-4 py-2.5 text-sm outline-none focus:border-coral">
            <option value="">كل الأدوار</option>
            @foreach ($roles as $roleName)
                <option value="{{ $roleName }}" @selected($role === $roleName)>{{ $roleName }}</option>
            @endforeach
        </select>

        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white transition hover:brightness-95">
            <x-manage.icon name="filter" class="size-[18px]" /> تصفية
        </button>
        @if ($search !== '' || $status !== '' || $role !== '')
            <a href="{{ route('manage.users.index') }}" class="text-sm font-semibold text-ink-500 hover:text-coral">مسح</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="border-b border-ink-200 bg-cream-50 text-xs font-semibold text-ink-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">الاسم</th>
                        <th class="px-4 py-3 font-semibold">البريد</th>
                        <th class="px-4 py-3 font-semibold">الهاتف</th>
                        <th class="px-4 py-3 font-semibold">الأدوار</th>
                        <th class="px-4 py-3 font-semibold">الحالة</th>
                        <th class="px-4 py-3 font-semibold">التاريخ</th>
                        <th class="px-4 py-3 font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-200">
                    @forelse ($users as $user)
                        <tr class="transition hover:bg-cream-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('manage.users.show', $user) }}" class="font-semibold hover:text-coral">{{ $user->full_name ?? '—' }}</a>
                            </td>
                            <td class="px-4 py-3 text-ink-700">{{ $user->email }}</td>
                            <td class="px-4 py-3 text-ink-500">{{ $user->phone ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @forelse ($user->roles as $userRole)
                                    <span class="mr-1 inline-flex items-center rounded-full bg-coral-soft px-2.5 py-0.5 text-xs font-semibold text-coral">{{ $userRole->name }}</span>
                                @empty
                                    <span class="text-ink-500">—</span>
                                @endforelse
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusStyles[$user->status->value] ?? 'bg-cream-200 text-ink-500' }}">
                                    {{ $statusLabels[$user->status->value] ?? $user->status->value }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-ink-500">{{ optional($user->created_at)->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-left">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('manage.users.show', $user) }}" title="عرض"
                                       class="inline-flex size-8 items-center justify-center rounded-lg bg-cream-200 text-ink-700 transition hover:bg-coral-soft hover:text-coral">
                                        <x-manage.icon name="eye" class="size-[18px]" />
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center">
                                <span class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-cream-200 text-ink-300">
                                    <x-manage.icon name="users" class="size-6" />
                                </span>
                                <p class="text-sm font-semibold text-ink-500">لا يوجد مستخدمون مطابقون.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $users->links() }}
    </div>
@endsection
