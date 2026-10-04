@extends('admin.layout')

@section('title', 'نزاعات الطلبات')
@section('heading', 'نزاعات الطلبات')

@php
    $canManage = auth()->user()?->can('finance.manage') ?? false;
@endphp

@section('content')
    <p class="mb-6 text-sm text-ink-500">طلبات أبلغ المشتري عن مشكلة فيها بعد التسليم. اكتب سبب القرار؛ يُحفظ مع الطلب وفي سجل النشاط.</p>

    <div class="space-y-4">
        @forelse ($orders as $order)
            <article class="rounded-2xl border border-ink-200 bg-cream-100 p-5 text-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <p class="font-bold">{{ $order->ad_title }}</p>
                        <p class="text-ink-500">المشتري: {{ $order->buyer?->full_name ?? 'حساب محذوف' }} · البائع: {{ $order->seller?->full_name ?? 'حساب محذوف' }}</p>
                        <p class="text-ink-500">طريقة الدفع: {{ $order->payment_method->value }} · فُتح النزاع: <span dir="ltr">{{ $order->disputed_at?->format('Y-m-d H:i') }}</span></p>
                        <p class="mt-2 rounded-xl bg-cream-50 p-3">{{ $order->dispute_reason }}</p>
                    </div>
                    <p class="text-lg font-bold" dir="ltr">{{ $order->total }} {{ $order->currency }}</p>
                </div>

                @if ($canManage)
                    <form method="POST" action="{{ route('admin.finance.disputes.resolve', $order) }}" class="mt-4 space-y-3 border-t border-ink-200 pt-4">
                        @csrf
                        <textarea name="note" required minlength="5" maxlength="1000" rows="2" placeholder="سبب القرار" aria-label="سبب القرار (يُحفظ مع الطلب)"
                                  class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2 outline-none focus:border-coral"></textarea>
                        <div class="flex flex-wrap gap-3">
                            <button type="submit" name="resolution" value="completed" class="rounded-xl bg-emerald-600 px-4 py-2 font-bold text-white">إتمام البيع لصالح البائع</button>
                            <button type="submit" name="resolution" value="cancelled" class="rounded-xl bg-red-600 px-4 py-2 font-bold text-white">إلغاء البيع لصالح المشتري</button>
                        </div>
                    </form>
                @endif
            </article>
        @empty
            <p class="rounded-2xl border border-ink-200 bg-cream-100 p-6 text-center text-ink-500">لا توجد نزاعات مفتوحة.</p>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $orders->links() }}
    </div>
@endsection
