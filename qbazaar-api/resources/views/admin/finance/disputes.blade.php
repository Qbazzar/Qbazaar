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
                    <span class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-500">
                        <span class="inline-flex min-w-0 items-center gap-1">المشتري: <x-admin.user :user="$order->buyer" size="xs" fallback="حساب محذوف" /></span>
                        <span class="inline-flex min-w-0 items-center gap-1">البائع: <x-admin.user :user="$order->seller" size="xs" fallback="حساب محذوف" /></span>
                    </span>
                    <span class="block text-xs text-ink-500">طريقة الدفع: {{ $order->payment_method->value }}</span>
                </x-admin.table.cell>
                <x-admin.table.cell label="سبب الإبلاغ" class="text-ink-700">{{ $order->dispute_reason }}</x-admin.table.cell>
                <x-admin.table.cell label="المبلغ" class="font-bold"><span dir="ltr">{{ $order->total }} {{ $order->currency }}</span></x-admin.table.cell>
                <x-admin.table.cell label="فُتح في" secondary class="text-ink-500"><span dir="ltr">{{ $order->disputed_at?->format('Y-m-d H:i') }}</span></x-admin.table.cell>
                <x-admin.table.actions class="max-md:flex-wrap">
                    @if ($canManage)
                        @include('admin.finance.partials.dispute-ruling-form', ['order' => $order])
                    @endif
                </x-admin.table.actions>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$orders" />
@endsection
