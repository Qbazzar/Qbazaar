@extends('admin.layout')

@section('title', 'تسويات العمولة')
@section('heading', 'تسويات العمولة')

@php
    $methodLabels = ['bank_transfer' => 'تحويل بنكي', 'wallet' => 'من المحفظة'];
    $canManage = auth()->user()?->can('finance.manage') ?? false;
@endphp

@section('content')
    <x-admin.page-toolbar>
        <x-admin.status-tabs route="admin.finance.settlements.index" :statuses="\App\Enums\SettlementStatus::cases()" :active="$status" />
    </x-admin.page-toolbar>

    <x-admin.table
        caption="تسويات العمولة"
        :columns="['البائع', 'الطريقة', 'المبلغ', 'الحالة', ['label' => 'التاريخ', 'secondary' => true], ['label' => 'إجراءات', 'srOnly' => true]]"
        :empty="$settlements->isEmpty()"
        empty-icon="banknotes"
        empty-message="لا توجد تسويات."
    >
        @foreach ($settlements as $settlement)
            <x-admin.table.row>
                <x-admin.table.cell primary>
                    <span class="font-semibold">{{ $settlement->user?->full_name ?? 'حساب محذوف' }}</span>
                    @if ($settlement->user)
                        <a href="{{ route('admin.finance.statements.show', $settlement->user) }}" class="ms-2 text-xs font-semibold text-coral-700 hover:underline">كشف الحساب</a>
                    @endif
                    @if ($settlement->bank_reference)
                        <span class="block text-xs text-ink-500">مرجع التحويل: <span dir="ltr">{{ $settlement->bank_reference }}</span></span>
                    @endif
                    @if ($settlement->rejection_reason)
                        <span class="block text-xs text-red-700">سبب الرفض: {{ $settlement->rejection_reason }}</span>
                    @endif
                    @if ($settlement->reviewer)
                        <span class="block text-xs text-ink-500">راجعها: {{ $settlement->reviewer->full_name }}</span>
                    @endif
                </x-admin.table.cell>
                <x-admin.table.cell label="الطريقة" class="text-ink-700">
                    {{ $methodLabels[$settlement->method->value] ?? $settlement->method->value }}
                    @if ($settlement->proof())
                        <a href="{{ route('admin.finance.settlements.proof', $settlement) }}" target="_blank" rel="noopener" class="block text-xs font-semibold text-coral-700 hover:underline">عرض إيصال التحويل</a>
                    @endif
                </x-admin.table.cell>
                <x-admin.table.cell label="المبلغ" class="font-bold"><span dir="ltr">{{ $settlement->amount }} QAR</span></x-admin.table.cell>
                <x-admin.table.cell label="الحالة"><x-admin.badge :status="$settlement->status" /></x-admin.table.cell>
                <x-admin.table.cell label="التاريخ" secondary class="text-ink-500"><span dir="ltr">{{ $settlement->created_at->format('Y-m-d H:i') }}</span></x-admin.table.cell>
                <x-admin.table.actions class="max-md:flex-wrap">
                    @if ($canManage && $settlement->status === \App\Enums\SettlementStatus::PENDING)
                        <form method="POST" action="{{ route('admin.finance.settlements.approve', $settlement) }}" data-confirm="قبول تسوية {{ $settlement->amount }} QAR؟" data-confirm-tone="primary">
                            @csrf
                            <x-admin.button variant="success" size="sm" icon="check">قبول</x-admin.button>
                        </form>
                        <form method="POST" action="{{ route('admin.finance.settlements.reject', $settlement) }}" data-confirm="رفض هذه التسوية؟" class="flex items-end gap-2">
                            @csrf
                            <x-admin.input name="reason" :id="'reject-' . $settlement->id" label="سبب رفض التسوية (يظهر للبائع)" label-hidden placeholder="سبب الرفض (يظهر للبائع)" required minlength="5" maxlength="500" class="min-w-44" />
                            <x-admin.button variant="danger" size="sm" icon="x-circle">رفض</x-admin.button>
                        </form>
                    @endif
                </x-admin.table.actions>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$settlements" />
@endsection
