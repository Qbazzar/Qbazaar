@extends('admin.layout')

@section('title', 'نزاعات الطلبات')
@section('heading', 'نزاعات الطلبات')

@php
    $canManage = auth()->user()?->can('finance.manage') ?? false;
@endphp

@section('content')
    <x-admin.page-toolbar description="طلبات أبلغ المشتري عن مشكلة فيها بعد التسليم. اكتب سبب القرار؛ يُحفظ مع الطلب وفي سجل النشاط." />

    <x-admin.table
        caption="نزاعات الطلبات"
        :columns="['الطلب', 'سبب الإبلاغ', 'المبلغ', ['label' => 'فُتح في', 'secondary' => true], ['label' => 'القرار', 'srOnly' => true]]"
        :empty="$orders->isEmpty()"
        empty-icon="flag"
        empty-message="لا توجد نزاعات مفتوحة."
    >
        @foreach ($orders as $order)
            <x-admin.table.row>
                <x-admin.table.cell primary>
                    <span class="font-semibold">{{ $order->ad_title }}</span>
                    <span class="block text-xs text-ink-500">المشتري: {{ $order->buyer?->full_name ?? 'حساب محذوف' }} · البائع: {{ $order->seller?->full_name ?? 'حساب محذوف' }}</span>
                    <span class="block text-xs text-ink-500">طريقة الدفع: {{ $order->payment_method->value }}</span>
                </x-admin.table.cell>
                <x-admin.table.cell label="سبب الإبلاغ" class="text-ink-700">{{ $order->dispute_reason }}</x-admin.table.cell>
                <x-admin.table.cell label="المبلغ" class="font-bold"><span dir="ltr">{{ $order->total }} {{ $order->currency }}</span></x-admin.table.cell>
                <x-admin.table.cell label="فُتح في" secondary class="text-ink-500"><span dir="ltr">{{ $order->disputed_at?->format('Y-m-d H:i') }}</span></x-admin.table.cell>
                <x-admin.table.actions class="max-md:flex-wrap">
                    @if ($canManage)
                        <form method="POST" action="{{ route('admin.finance.disputes.resolve', $order) }}" data-confirm="تثبيت قرارك في هذا النزاع؟ لا يمكن التراجع عنه." data-confirm-tone="primary" class="w-full space-y-2 md:w-64">
                            @csrf
                            <x-admin.textarea name="note" :id="'note-' . $order->id" label="سبب القرار (يُحفظ مع الطلب)" label-hidden placeholder="سبب القرار" :rows="2" required minlength="5" maxlength="1000" />
                            <div class="flex flex-wrap gap-2">
                                <x-admin.button variant="success" size="sm" name="resolution" value="completed">إتمام البيع لصالح البائع</x-admin.button>
                                <x-admin.button variant="danger" size="sm" name="resolution" value="cancelled">إلغاء البيع لصالح المشتري</x-admin.button>
                            </div>
                        </form>
                    @endif
                </x-admin.table.actions>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$orders" />
@endsection
