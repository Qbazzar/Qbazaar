@php
    $statusTones = [
        'created' => 'info',
        'awaiting_handover' => 'warning',
        'completed' => 'success',
        'cancelled' => 'neutral',
        'disputed' => 'danger',
    ];
@endphp

<section aria-labelledby="user-orders-title" class="grid gap-4">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="user-orders-title" class="text-[15px] font-bold text-ink-900">طلبات المستخدم</h2>
        <p class="text-[13px] text-ink-500">{{ number_format($orders->total()) }} طلب، شراءً وبيعاً</p>
    </div>

    <x-admin.table
        caption="طلبات المستخدم"
        :columns="['الطلب', 'الدور', 'الطرف الآخر', 'المبلغ', 'الحالة']"
        :empty="$orders->isEmpty()"
        empty-icon="inbox"
        empty-message="لا توجد طلبات لهذا المستخدم."
    >
        @foreach ($orders as $order)
            @php($asSeller = $order->seller_id === $user->id)
            <x-admin.table.row>
                {{-- The date rides under the title so the table fits the main column beside the side cards. --}}
                <x-admin.table.cell primary>
                    <span class="block max-w-64 truncate font-semibold" title="{{ $order->ad_title }}">{{ $order->ad_title }}</span>
                    <span class="mt-0.5 block text-xs font-normal text-ink-500"><bdi dir="ltr">{{ optional($order->created_at)->format('Y-m-d') }}</bdi></span>
                </x-admin.table.cell>
                <x-admin.table.cell label="الدور">
                    <x-admin.badge :tone="$asSeller ? 'brand' : 'info'">{{ $asSeller ? 'بائع' : 'مشترٍ' }}</x-admin.badge>
                </x-admin.table.cell>
                <x-admin.table.cell label="الطرف الآخر" class="text-ink-700 md:max-w-48">
                    <x-admin.user :user="$asSeller ? $order->buyer : $order->seller" fallback="حساب محذوف" />
                </x-admin.table.cell>
                <x-admin.table.cell label="المبلغ" class="font-semibold whitespace-nowrap"><span dir="ltr">{{ $order->total }} {{ $order->currency }}</span></x-admin.table.cell>
                <x-admin.table.cell label="الحالة">
                    <x-admin.badge :tone="$statusTones[$order->status->value] ?? 'neutral'">{{ __('admin.home.order_status.' . $order->status->value) }}</x-admin.badge>
                </x-admin.table.cell>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$orders" class="!mt-0" />
</section>
