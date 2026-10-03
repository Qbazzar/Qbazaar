@extends('admin.layout')

@section('title', 'طلبات السحب')
@section('heading', 'طلبات السحب')

@php
    $statusLabels = ['pending' => 'بانتظار التحويل', 'paid' => 'محوّلة', 'rejected' => 'مرفوضة'];
@endphp

@section('content')
    <nav class="mb-6 flex gap-3 text-sm">
        @foreach ($statusLabels as $value => $label)
            <a href="{{ route('admin.finance.withdrawals.index', ['status' => $value]) }}"
               class="rounded-xl px-4 py-2 font-semibold {{ $status->value === $value ? 'bg-coral text-white' : 'bg-cream-100 text-ink-500' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <div class="space-y-4">
        @forelse ($withdrawals as $withdrawal)
            <article class="rounded-2xl border border-ink-200 bg-cream-100 p-5 text-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <p class="font-bold">
                            {{ $withdrawal->user?->full_name ?? 'حساب محذوف' }}
                            @if ($withdrawal->user)
                                <a href="{{ route('admin.finance.statements.show', $withdrawal->user) }}" class="mr-2 text-xs font-semibold text-coral hover:underline">كشف الحساب</a>
                            @endif
                        </p>
                        <p class="text-ink-500" dir="ltr">{{ $withdrawal->created_at->format('Y-m-d H:i') }}</p>
                        <p>صاحب الحساب: <span class="font-semibold">{{ $withdrawal->holder_name }}</span></p>
                        <p>الآيبان: <span class="font-semibold" dir="ltr">{{ $canManage ? $withdrawal->iban : $withdrawal->maskedIban() }}</span></p>
                        @if ($withdrawal->transfer_reference)
                            <p>مرجع التحويل: <span class="font-semibold" dir="ltr">{{ $withdrawal->transfer_reference }}</span></p>
                        @endif
                        @if ($withdrawal->rejection_reason)
                            <p class="text-red-700">سبب الرفض: {{ $withdrawal->rejection_reason }}</p>
                        @endif
                        @if ($withdrawal->reviewer)
                            <p class="text-ink-500">راجعه: {{ $withdrawal->reviewer->full_name }}</p>
                        @endif
                    </div>
                    <p class="text-lg font-bold" dir="ltr">{{ $withdrawal->amount }} QAR</p>
                </div>

                @if ($canManage && $withdrawal->status->value === 'pending')
                    <div class="mt-4 grid gap-3 border-t border-ink-200 pt-4 md:grid-cols-2">
                        <form method="POST" action="{{ route('admin.finance.withdrawals.pay', $withdrawal) }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            <input type="text" name="transfer_reference" required maxlength="100" placeholder="مرجع التحويل البنكي"
                                   class="min-w-48 flex-1 rounded-xl border border-ink-200 bg-cream-50 px-4 py-2 outline-none focus:border-coral">
                            <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 font-bold text-white">تم التحويل</button>
                        </form>
                        <form method="POST" action="{{ route('admin.finance.withdrawals.reject', $withdrawal) }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            <input type="text" name="reason" required minlength="5" maxlength="500" placeholder="سبب الرفض (يظهر للبائع)"
                                   class="min-w-48 flex-1 rounded-xl border border-ink-200 bg-cream-50 px-4 py-2 outline-none focus:border-coral">
                            <button type="submit" class="rounded-xl bg-red-600 px-4 py-2 font-bold text-white">رفض وإرجاع المبلغ</button>
                        </form>
                    </div>
                @endif
            </article>
        @empty
            <p class="rounded-2xl border border-ink-200 bg-cream-100 p-6 text-center text-ink-500">لا توجد طلبات سحب.</p>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $withdrawals->links() }}
    </div>
@endsection
