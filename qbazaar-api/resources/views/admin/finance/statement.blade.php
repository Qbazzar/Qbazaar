@extends('admin.layout')

@section('title', 'كشف حساب ' . $user->full_name)
@section('heading', 'كشف حساب ' . $user->full_name)

@section('content')
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-admin.stat label="الرصيد المتاح" icon="banknotes"><span dir="ltr">{{ $summary->available }} QAR</span></x-admin.stat>
        <x-admin.stat label="قابل للسحب" icon="check"><span dir="ltr">{{ $summary->withdrawable }} QAR</span></x-admin.stat>
        <x-admin.stat label="عمولة مستحقة" icon="clock"><span dir="ltr">{{ $summary->commissionDebt }} QAR</span></x-admin.stat>
        <x-admin.stat label="سقف الدين" icon="shield">
            <span dir="ltr">{{ $summary->debtCeiling }} QAR</span>
            <span @class(['mt-1 block text-xs font-semibold', 'text-emerald-700' => $summary->canAcceptOrders, 'text-red-700' => ! $summary->canAcceptOrders])>{{ $summary->canAcceptOrders ? 'يستقبل طلبات جديدة' : 'موقوف عن الطلبات الجديدة حتى يسدد' }}</span>
        </x-admin.stat>
    </div>

    <x-admin.table
        caption="حركات الحساب"
        :columns="['التاريخ', 'الحركة', 'الحساب', 'مدين', 'دائن', 'الرصيد بعدها']"
        :empty="$entries->isEmpty()"
        empty-icon="inbox"
        empty-message="لا توجد حركات."
    >
        @foreach ($entries as $entry)
            <x-admin.table.row>
                <x-admin.table.cell primary><span dir="ltr">{{ $entry->created_at->format('Y-m-d H:i') }}</span></x-admin.table.cell>
                <x-admin.table.cell label="الحركة">{{ __($entry->transaction->type->descriptionKey(), [], 'ar') }}</x-admin.table.cell>
                <x-admin.table.cell label="الحساب">{{ $entry->account->type->value === 'wallet' ? 'المحفظة' : 'العمولة المستحقة' }}</x-admin.table.cell>
                <x-admin.table.cell label="مدين"><span dir="ltr">{{ $entry->debit }}</span></x-admin.table.cell>
                <x-admin.table.cell label="دائن"><span dir="ltr">{{ $entry->credit }}</span></x-admin.table.cell>
                <x-admin.table.cell label="الرصيد بعدها" class="font-semibold"><span dir="ltr">{{ $entry->balance_after }}</span></x-admin.table.cell>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$entries" />
@endsection
