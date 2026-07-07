@extends('admin.layout')

@section('title', 'الأدوار')
@section('heading', 'الأدوار')

@section('content')
    {{-- Filters --}}
    <form method="GET" class="mb-6 flex flex-wrap items-center gap-3">
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-ink-300">
                <x-admin.icon name="search" class="size-[18px]" />
            </span>
            <input type="text" name="q" value="{{ $search }}" placeholder="بحث بالاسم…"
                   class="w-64 rounded-xl border border-ink-200 bg-cream-100 py-2.5 pr-10 pl-4 text-sm outline-none focus:border-coral">
        </div>

        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white transition hover:brightness-95">
            <x-admin.icon name="filter" class="size-[18px]" /> تصفية
        </button>
        @if ($search !== '')
            <a href="{{ route('admin.roles.index') }}" class="text-sm font-semibold text-ink-500 hover:text-coral">مسح</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="border-b border-ink-200 bg-cream-50 text-xs font-semibold text-ink-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">الدور</th>
                        <th class="px-4 py-3 font-semibold">الحارس</th>
                        <th class="px-4 py-3 font-semibold">عدد الصلاحيات</th>
                        <th class="px-4 py-3 font-semibold">عدد المستخدمين</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-200">
                    @forelse ($roles as $role)
                        <tr class="transition hover:bg-cream-50">
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full bg-coral-soft px-3 py-1 text-xs font-semibold text-coral">{{ $role->name }}</span>
                            </td>
                            <td class="px-4 py-3 text-ink-500">{{ $role->guard_name }}</td>
                            <td class="px-4 py-3 text-ink-700">{{ number_format($role->permissions_count) }}</td>
                            <td class="px-4 py-3 text-ink-700">{{ number_format($role->users_count) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-16 text-center">
                                <span class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-cream-200 text-ink-300">
                                    <x-admin.icon name="key" class="size-6" />
                                </span>
                                <p class="text-sm font-semibold text-ink-500">لا توجد أدوار.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
