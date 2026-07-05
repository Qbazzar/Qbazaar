@extends('manage.layout')

@section('title', 'قواعد الإشراف')
@section('heading', 'قواعد الإشراف')

@php
    $typeLabels = [
        'banned_word' => 'كلمة محظورة',
        'blocked_domain' => 'نطاق محظور',
    ];
    $languageLabels = [
        'any' => 'الكل',
        'ar' => 'العربية',
        'en' => 'الإنجليزية',
    ];
@endphp

@section('content')
    {{-- Filters + create --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-ink-300">
                    <x-manage.icon name="search" class="size-[18px]" />
                </span>
                <input type="text" name="q" value="{{ $search }}" placeholder="بحث بالقيمة…"
                       class="w-56 rounded-xl border border-ink-200 bg-cream-100 py-2.5 pr-10 pl-4 text-sm outline-none focus:border-coral">
            </div>

            <select name="type" class="rounded-xl border border-ink-200 bg-cream-100 px-4 py-2.5 text-sm outline-none focus:border-coral">
                <option value="">كل الأنواع</option>
                @foreach ($types as $case)
                    <option value="{{ $case->value }}" @selected($type === $case->value)>{{ $typeLabels[$case->value] ?? $case->value }}</option>
                @endforeach
            </select>

            <select name="language" class="rounded-xl border border-ink-200 bg-cream-100 px-4 py-2.5 text-sm outline-none focus:border-coral">
                <option value="">كل اللغات</option>
                @foreach ($languages as $case)
                    <option value="{{ $case->value }}" @selected($language === $case->value)>{{ $languageLabels[$case->value] ?? $case->value }}</option>
                @endforeach
            </select>

            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white transition hover:brightness-95">
                <x-manage.icon name="filter" class="size-[18px]" /> تصفية
            </button>
            @if ($search !== '' || $type !== '' || $language !== '')
                <a href="{{ route('manage.moderation-rules.index') }}" class="text-sm font-semibold text-ink-500 hover:text-coral">مسح</a>
            @endif
        </form>

        <a href="{{ route('manage.moderation-rules.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white transition hover:brightness-95">
            <x-manage.icon name="plus" class="size-[18px]" /> إضافة قاعدة
        </a>
    </div>

    <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="border-b border-ink-200 bg-cream-50 text-xs font-semibold text-ink-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">النوع</th>
                        <th class="px-4 py-3 font-semibold">القيمة</th>
                        <th class="px-4 py-3 font-semibold">اللغة</th>
                        <th class="px-4 py-3 font-semibold">مفعّلة</th>
                        <th class="px-4 py-3 font-semibold">آخر تحديث</th>
                        <th class="px-4 py-3 font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-200">
                    @forelse ($rules as $rule)
                        <tr class="transition hover:bg-cream-50">
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                                    {{ $typeLabels[$rule->type->value] ?? $rule->type->value }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-mono text-ink-900">{{ $rule->value }}</td>
                            <td class="px-4 py-3 text-ink-700">{{ $languageLabels[$rule->language->value] ?? $rule->language->value }}</td>
                            <td class="px-4 py-3">
                                @if ($rule->is_active)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">نعم</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-cream-200 px-2.5 py-0.5 text-xs font-semibold text-ink-500">لا</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-ink-500">{{ optional($rule->updated_at)->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-left">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('manage.moderation-rules.edit', $rule) }}" title="تعديل"
                                       class="inline-flex size-8 items-center justify-center rounded-lg bg-cream-200 text-ink-700 transition hover:bg-coral-soft hover:text-coral">
                                        <x-manage.icon name="pencil" class="size-[18px]" />
                                    </a>
                                    <form method="POST" action="{{ route('manage.moderation-rules.destroy', $rule) }}"
                                          onsubmit="return confirm('حذف هذه القاعدة؟');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="حذف"
                                                class="inline-flex size-8 items-center justify-center rounded-lg bg-red-50 text-red-700 transition hover:bg-red-100">
                                            <x-manage.icon name="trash" class="size-[18px]" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-16 text-center">
                                <span class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-cream-200 text-ink-300">
                                    <x-manage.icon name="shield" class="size-6" />
                                </span>
                                <p class="text-sm font-semibold text-ink-500">لا توجد قواعد مطابقة.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $rules->links() }}
    </div>
@endsection
