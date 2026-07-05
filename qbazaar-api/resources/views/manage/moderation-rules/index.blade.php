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
            <input type="text" name="q" value="{{ $search }}" placeholder="بحث بالقيمة…"
                   class="w-56 rounded-xl border border-ink-200 bg-cream-100 px-4 py-2.5 text-sm outline-none focus:border-coral">

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

            <button type="submit" class="rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white hover:brightness-95">تصفية</button>
            @if ($search !== '' || $type !== '' || $language !== '')
                <a href="{{ route('manage.moderation-rules.index') }}" class="text-sm font-semibold text-ink-500 hover:text-coral">مسح</a>
            @endif
        </form>

        <a href="{{ route('manage.moderation-rules.create') }}" class="rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white hover:brightness-95">إضافة قاعدة</a>
    </div>

    <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="border-b border-ink-200 text-xs font-semibold text-ink-500">
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
                        <tr class="hover:bg-cream-50">
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
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('manage.moderation-rules.edit', $rule) }}" class="rounded-lg bg-cream-200 px-3 py-1.5 text-xs font-semibold hover:bg-ink-200">تعديل</a>
                                    <form method="POST" action="{{ route('manage.moderation-rules.destroy', $rule) }}"
                                          onsubmit="return confirm('حذف هذه القاعدة؟');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100">حذف</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-ink-500">لا توجد قواعد مطابقة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $rules->links() }}
    </div>
@endsection
