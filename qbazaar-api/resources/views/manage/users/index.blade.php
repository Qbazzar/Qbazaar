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
        <input type="text" name="q" value="{{ $search }}" placeholder="بحث بالاسم أو البريد أو الهاتف…"
               class="w-64 rounded-xl border border-ink-200 bg-cream-100 px-4 py-2.5 text-sm outline-none focus:border-coral">

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

        <button type="submit" class="rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white hover:brightness-95">تصفية</button>
        @if ($search !== '' || $status !== '' || $role !== '')
            <a href="{{ route('manage.users.index') }}" class="text-sm font-semibold text-ink-500 hover:text-coral">مسح</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="border-b border-ink-200 text-xs font-semibold text-ink-500">
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
                        <tr class="hover:bg-cream-50">
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
                                <a href="{{ route('manage.users.show', $user) }}" class="rounded-lg bg-cream-200 px-3 py-1.5 text-xs font-semibold hover:bg-ink-200">عرض</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-12 text-center text-ink-500">لا يوجد مستخدمون مطابقون.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $users->links() }}
    </div>
@endsection
