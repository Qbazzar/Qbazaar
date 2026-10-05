@extends('admin.layout')

@section('title', 'طلبات السحب')
@section('heading', 'طلبات السحب')

@section('content')
    <x-admin.page-toolbar>
        <x-admin.status-tabs route="admin.finance.withdrawals.index" :statuses="\App\Enums\WithdrawalStatus::cases()" :active="$status" />
    </x-admin.page-toolbar>

    <x-admin.table
        caption="طلبات السحب"
        :columns="['البائع', 'الحساب البنكي', 'المبلغ', 'الحالة', ['label' => 'التاريخ', 'secondary' => true], ['label' => 'إجراءات', 'srOnly' => true]]"
        :empty="$withdrawals->isEmpty()"
        empty-icon="banknotes"
        empty-message="لا توجد طلبات سحب."
    >
        @foreach ($withdrawals as $withdrawal)
            <x-admin.table.row>
                <x-admin.table.cell primary>
                    <span class="font-semibold">{{ $withdrawal->user?->full_name ?? 'حساب محذوف' }}</span>
                    @if ($withdrawal->user)
                        <a href="{{ route('admin.finance.statements.show', $withdrawal->user) }}" class="ms-2 text-xs font-semibold text-coral-700 hover:underline">كشف الحساب</a>
                    @endif
                    @if ($withdrawal->transfer_reference)
                        <span class="block text-xs text-ink-500">مرجع التحويل: <span dir="ltr">{{ $withdrawal->transfer_reference }}</span></span>
                    @endif
                    @if ($withdrawal->rejection_reason)
                        <span class="block text-xs text-red-700">سبب الرفض: {{ $withdrawal->rejection_reason }}</span>
                    @endif
                    @if ($withdrawal->reviewer)
                        <span class="block text-xs text-ink-500">راجعه: {{ $withdrawal->reviewer->full_name }}</span>
                    @endif
                </x-admin.table.cell>
                <x-admin.table.cell label="الحساب البنكي" class="text-ink-700">
                    {{ $withdrawal->holder_name }}
                    <span class="block text-xs text-ink-500" dir="ltr">{{ $canManage ? $withdrawal->iban : $withdrawal->maskedIban() }}</span>
                </x-admin.table.cell>
                <x-admin.table.cell label="المبلغ" class="font-bold"><span dir="ltr">{{ $withdrawal->amount }} QAR</span></x-admin.table.cell>
                <x-admin.table.cell label="الحالة"><x-admin.badge :status="$withdrawal->status" /></x-admin.table.cell>
                <x-admin.table.cell label="التاريخ" secondary class="text-ink-500"><span dir="ltr">{{ $withdrawal->created_at->format('Y-m-d H:i') }}</span></x-admin.table.cell>
                <x-admin.table.actions class="max-md:flex-wrap">
                    @if ($canManage && $withdrawal->status === \App\Enums\WithdrawalStatus::PENDING)
                        <form method="POST" action="{{ route('admin.finance.withdrawals.pay', $withdrawal) }}" data-confirm="تأكيد تحويل {{ $withdrawal->amount }} QAR إلى {{ $withdrawal->holder_name }}؟" data-confirm-tone="primary" class="flex items-end gap-2">
                            @csrf
                            <x-admin.input name="transfer_reference" :id="'pay-' . $withdrawal->id" label="مرجع التحويل البنكي" label-hidden placeholder="مرجع التحويل البنكي" required maxlength="100" class="min-w-44" />
                            <x-admin.button variant="success" size="sm" icon="check">تم التحويل</x-admin.button>
                        </form>
                        <form method="POST" action="{{ route('admin.finance.withdrawals.reject', $withdrawal) }}" data-confirm="رفض الطلب وإرجاع المبلغ إلى محفظة البائع؟" class="flex items-end gap-2">
                            @csrf
                            <x-admin.input name="reason" :id="'reject-' . $withdrawal->id" label="سبب رفض السحب (يظهر للبائع)" label-hidden placeholder="سبب الرفض (يظهر للبائع)" required minlength="5" maxlength="500" class="min-w-44" />
                            <x-admin.button variant="danger" size="sm" icon="x-circle">رفض وإرجاع المبلغ</x-admin.button>
                        </form>
                    @endif
                </x-admin.table.actions>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$withdrawals" />
@endsection
