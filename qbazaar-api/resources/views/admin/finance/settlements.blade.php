@extends('admin.layout')

@section('title', 'تسويات العمولة')
@section('heading', 'تسويات العمولة')

@php
    $statusLabels = ['pending' => 'بانتظار المراجعة', 'approved' => 'مقبولة', 'rejected' => 'مرفوضة'];
    $methodLabels = ['bank_transfer' => 'تحويل بنكي', 'wallet' => 'من المحفظة'];
    $canManage = auth()->user()?->can('finance.manage') ?? false;
@endphp

@section('content')
    <nav class="mb-6 flex gap-3 text-sm">
        @foreach ($statusLabels as $value => $label)
            <a href="{{ route('admin.finance.settlements.index', ['status' => $value]) }}"
               class="rounded-xl px-4 py-2 font-semibold {{ $status->value === $value ? 'bg-coral text-white' : 'bg-cream-100 text-ink-500' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <div class="space-y-4">
        @forelse ($settlements as $settlement)
            <article class="rounded-2xl border border-ink-200 bg-cream-100 p-5 text-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <p class="font-bold">
                            {{ $settlement->user?->full_name ?? 'حساب محذوف' }}
                            @if ($settlement->user)
                                <a href="{{ route('admin.finance.statements.show', $settlement->user) }}" class="mr-2 text-xs font-semibold text-coral hover:underline">كشف الحساب</a>
                            @endif
                        </p>
                        <p class="text-ink-500">{{ $methodLabels[$settlement->method->value] ?? $settlement->method->value }} · <span dir="ltr">{{ $settlement->created_at->format('Y-m-d H:i') }}</span></p>
                        @if ($settlement->bank_reference)
                            <p>مرجع التحويل: <span class="font-semibold" dir="ltr">{{ $settlement->bank_reference }}</span></p>
                        @endif
                        @if ($settlement->rejection_reason)
                            <p class="text-red-700">سبب الرفض: {{ $settlement->rejection_reason }}</p>
                        @endif
                        @if ($settlement->reviewer)
                            <p class="text-ink-500">راجعها: {{ $settlement->reviewer->full_name }}</p>
                        @endif
                    </div>
                    <div class="text-left">
                        <p class="text-lg font-bold" dir="ltr">{{ $settlement->amount }} QAR</p>
                        @if ($settlement->proof())
                            <a href="{{ route('admin.finance.settlements.proof', $settlement) }}" target="_blank" rel="noopener" class="text-xs font-semibold text-coral hover:underline">عرض إيصال التحويل</a>
                        @endif
                    </div>
                </div>

                @if ($canManage && $settlement->status->value === 'pending')
                    <div class="mt-4 flex flex-wrap items-end gap-3 border-t border-ink-200 pt-4">
                        <form method="POST" action="{{ route('admin.finance.settlements.approve', $settlement) }}">
                            @csrf
                            <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 font-bold text-white">قبول التسوية</button>
                        </form>
                        <form method="POST" action="{{ route('admin.finance.settlements.reject', $settlement) }}" class="flex flex-1 flex-wrap items-end gap-2">
                            @csrf
                            <input type="text" name="reason" required minlength="5" maxlength="500" placeholder="سبب الرفض (يظهر للبائع)"
                                   class="min-w-56 flex-1 rounded-xl border border-ink-200 bg-cream-50 px-4 py-2 outline-none focus:border-coral">
                            <button type="submit" class="rounded-xl bg-red-600 px-4 py-2 font-bold text-white">رفض</button>
                        </form>
                    </div>
                @endif
            </article>
        @empty
            <p class="rounded-2xl border border-ink-200 bg-cream-100 p-6 text-center text-ink-500">لا توجد تسويات.</p>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $settlements->links() }}
    </div>
@endsection
