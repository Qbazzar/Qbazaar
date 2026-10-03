@extends('admin.layout')

@section('title', 'كشف حساب ' . $user->full_name)
@section('heading', 'كشف حساب ' . $user->full_name)

@section('content')
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-ink-200 bg-cream-100 p-5">
            <p class="text-xs text-ink-500">الرصيد المتاح</p>
            <p class="text-xl font-bold" dir="ltr">{{ $summary->available }} QAR</p>
        </div>
        <div class="rounded-2xl border border-ink-200 bg-cream-100 p-5">
            <p class="text-xs text-ink-500">عمولة مستحقة</p>
            <p class="text-xl font-bold" dir="ltr">{{ $summary->commissionDebt }} QAR</p>
        </div>
        <div class="rounded-2xl border border-ink-200 bg-cream-100 p-5">
            <p class="text-xs text-ink-500">سقف الدين</p>
            <p class="text-xl font-bold" dir="ltr">{{ $summary->debtCeiling }} QAR</p>
            <p class="text-xs {{ $summary->canAcceptOrders ? 'text-emerald-700' : 'text-red-700' }}">{{ $summary->canAcceptOrders ? 'يستقبل طلبات جديدة' : 'موقوف عن الطلبات الجديدة حتى يسدد' }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="border-b border-ink-200 bg-cream-50 text-xs font-semibold text-ink-500">
                    <tr>
                        <th class="px-4 py-3">التاريخ</th>
                        <th class="px-4 py-3">الحركة</th>
                        <th class="px-4 py-3">الحساب</th>
                        <th class="px-4 py-3">مدين</th>
                        <th class="px-4 py-3">دائن</th>
                        <th class="px-4 py-3">الرصيد بعدها</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-200">
                    @forelse ($entries as $entry)
                        <tr>
                            <td class="px-4 py-3" dir="ltr">{{ $entry->created_at->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3">{{ __($entry->transaction->type->descriptionKey(), [], 'ar') }}</td>
                            <td class="px-4 py-3">{{ $entry->account->type->value === 'wallet' ? 'المحفظة' : 'العمولة المستحقة' }}</td>
                            <td class="px-4 py-3" dir="ltr">{{ $entry->debit }}</td>
                            <td class="px-4 py-3" dir="ltr">{{ $entry->credit }}</td>
                            <td class="px-4 py-3 font-semibold" dir="ltr">{{ $entry->balance_after }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-ink-500">لا توجد حركات.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $entries->links() }}
    </div>
@endsection
