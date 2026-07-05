@extends('manage.layout')

@section('title', 'الأدوار')
@section('heading', 'الأدوار')

@section('content')
    <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="border-b border-ink-200 text-xs font-semibold text-ink-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">الدور</th>
                        <th class="px-4 py-3 font-semibold">الحارس</th>
                        <th class="px-4 py-3 font-semibold">عدد الصلاحيات</th>
                        <th class="px-4 py-3 font-semibold">عدد المستخدمين</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-200">
                    @forelse ($roles as $role)
                        <tr class="hover:bg-cream-50">
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full bg-coral-soft px-3 py-1 text-xs font-semibold text-coral">{{ $role->name }}</span>
                            </td>
                            <td class="px-4 py-3 text-ink-500">{{ $role->guard_name }}</td>
                            <td class="px-4 py-3 text-ink-700">{{ number_format($role->permissions_count) }}</td>
                            <td class="px-4 py-3 text-ink-700">{{ number_format($role->users_count) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-12 text-center text-ink-500">لا توجد أدوار.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
