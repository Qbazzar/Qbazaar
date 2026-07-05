@extends('manage.layout')

@php($typeLabels = ['city' => 'مدينة', 'district' => 'منطقة', 'area' => 'حي'])

@section('title', 'المواقع')
@section('heading', 'المواقع')

@section('content')
    {{-- Toolbar --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <input type="text" name="q" value="{{ $search }}" placeholder="بحث بالاسم أو المعرّف…"
                   class="w-64 rounded-xl border border-ink-200 bg-cream-100 px-4 py-2.5 text-sm outline-none focus:border-coral">

            <select name="type" class="rounded-xl border border-ink-200 bg-cream-100 px-4 py-2.5 text-sm outline-none focus:border-coral">
                <option value="">كل الأنواع</option>
                @foreach ($types as $case)
                    <option value="{{ $case->value }}" @selected($type === $case->value)>{{ $typeLabels[$case->value] ?? $case->value }}</option>
                @endforeach
            </select>

            <button type="submit" class="rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white hover:brightness-95">تصفية</button>
            @if ($search !== '' || $type !== '')
                <a href="{{ route('manage.locations.index') }}" class="text-sm font-semibold text-ink-500 hover:text-coral">مسح</a>
            @endif
        </form>

        <a href="{{ route('manage.locations.create') }}" class="rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white hover:brightness-95">
            + موقع جديد
        </a>
    </div>

    <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="border-b border-ink-200 text-xs font-semibold text-ink-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">الاسم</th>
                        <th class="px-4 py-3 font-semibold">المعرّف</th>
                        <th class="px-4 py-3 font-semibold">النوع</th>
                        <th class="px-4 py-3 font-semibold">الموقع الأب</th>
                        <th class="px-4 py-3 font-semibold">الترتيب</th>
                        <th class="px-4 py-3 font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-200">
                    @forelse ($locations as $location)
                        <tr class="hover:bg-cream-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('manage.locations.edit', $location) }}" class="font-semibold hover:text-coral">
                                    {{ $location->getLocalizedName(app()->getLocale()) }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-ink-500">{{ $location->slug }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-cream-200 px-2.5 py-1 text-xs font-semibold text-ink-700">{{ $typeLabels[$location->type->value] ?? $location->type->value }}</span>
                            </td>
                            <td class="px-4 py-3 text-ink-700">{{ $location->parent?->getLocalizedName(app()->getLocale()) ?? '—' }}</td>
                            <td class="px-4 py-3 text-ink-500">{{ $location->order }}</td>
                            <td class="px-4 py-3 text-left">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('manage.locations.edit', $location) }}" class="rounded-lg bg-cream-200 px-3 py-1.5 text-xs font-semibold hover:bg-ink-200">تعديل</a>
                                    <form method="POST" action="{{ route('manage.locations.destroy', $location) }}"
                                          onsubmit="return confirm('هل أنت متأكد من حذف هذا الموقع؟');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-100">حذف</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-ink-500">لا توجد مواقع مطابقة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $locations->links() }}
    </div>
@endsection
