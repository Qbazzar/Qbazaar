@extends('admin.layout')

@section('title', 'تحويلات الترويج المدفوع')
@section('heading', 'تحويلات الترويج المدفوع')

@section('content')
    <p class="mb-6 text-sm text-ink-500">ترويجات دفعها البائعون بتحويل بنكي. أكّد بعد التحقق من وصول المبلغ إلى حساب المنصة، فيبدأ الترويج فوراً لمدته كاملة.</p>

    <x-admin.table
        caption="تحويلات الترويج بانتظار التأكيد"
        :columns="['الإعلان', 'البائع', 'النوع', 'المبلغ', 'مرجع التحويل', ['label' => 'تاريخ الطلب', 'secondary' => true], ['label' => 'إجراءات', 'srOnly' => true]]"
        :empty="$promotions->isEmpty()"
        empty-icon="banknotes"
        empty-message="لا توجد تحويلات بانتظار التأكيد."
    >
        @foreach ($promotions as $promotion)
            <x-admin.table.row>
                <x-admin.table.cell primary>
                    <span class="font-semibold">{{ \Illuminate\Support\Str::limit($promotion->ad?->title ?? '—', 40) }}</span>
                </x-admin.table.cell>
                <x-admin.table.cell label="البائع" class="text-ink-700">{{ $promotion->user?->full_name ?? 'حساب محذوف' }}</x-admin.table.cell>
                <x-admin.table.cell label="النوع">{{ __("admin.promotion_transfers.types.{$promotion->type->value}") }} ({{ $promotion->duration_days }} يوم)</x-admin.table.cell>
                <x-admin.table.cell label="المبلغ" class="font-bold"><span dir="ltr">{{ $promotion->price }} {{ $promotion->currency }}</span></x-admin.table.cell>
                <x-admin.table.cell label="مرجع التحويل" class="text-ink-700"><span dir="ltr">{{ $promotion->transfer_reference ?? '—' }}</span></x-admin.table.cell>
                <x-admin.table.cell label="تاريخ الطلب" secondary class="text-ink-500"><span dir="ltr">{{ $promotion->created_at->format('Y-m-d H:i') }}</span></x-admin.table.cell>
                <x-admin.table.actions class="max-md:flex-wrap">
                    @can('finance.manage')
                        <form method="POST" action="{{ route('admin.finance.promotions.confirm', $promotion) }}" data-confirm="تأكيد وصول {{ $promotion->price }} {{ $promotion->currency }} وبدء الترويج؟" data-confirm-tone="primary">
                            @csrf
                            <x-admin.button variant="success" size="sm" icon="check">تأكيد الاستلام</x-admin.button>
                        </form>
                        <form method="POST" action="{{ route('admin.finance.promotions.reject', $promotion) }}" data-confirm="رفض التحويل؟ لن يُسجَّل أي مبلغ." class="flex items-end gap-2">
                            @csrf
                            <x-admin.input name="reason" :id="'reject-' . $promotion->id" label="سبب الرفض (يظهر للبائع)" label-hidden placeholder="سبب الرفض (اختياري)" maxlength="500" class="min-w-44" />
                            <x-admin.button variant="danger" size="sm" icon="x-circle">رفض</x-admin.button>
                        </form>
                    @endcan
                </x-admin.table.actions>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$promotions" />
@endsection
