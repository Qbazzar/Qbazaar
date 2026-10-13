{{-- A user's balances (WalletSummary). Columns follow the space it is given:
     four across on the full-width statement, two in the user page's main column. --}}
<div class="@container">
    <div class="grid gap-4 @min-[28rem]:grid-cols-2 @min-[60rem]:grid-cols-4">
        <x-admin.stat label="الرصيد المتاح" icon="banknotes"><span dir="ltr">{{ $summary->available }} QAR</span></x-admin.stat>
        <x-admin.stat label="قابل للسحب" icon="check"><span dir="ltr">{{ $summary->withdrawable }} QAR</span></x-admin.stat>
        <x-admin.stat label="عمولة مستحقة" icon="clock"><span dir="ltr">{{ $summary->commissionDebt }} QAR</span></x-admin.stat>
        <x-admin.stat label="سقف الدين" icon="shield">
            <span dir="ltr">{{ $summary->debtCeiling }} QAR</span>
            <span @class(['mt-1 block text-xs font-semibold', 'text-emerald-700' => $summary->canAcceptOrders, 'text-red-700' => ! $summary->canAcceptOrders])>{{ $summary->canAcceptOrders ? 'يستقبل طلبات جديدة' : 'موقوف عن الطلبات الجديدة حتى يسدد' }}</span>
        </x-admin.stat>
    </div>
</div>
