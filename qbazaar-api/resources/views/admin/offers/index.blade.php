@extends('admin.layout')

@section('title', 'العروض')
@section('heading', 'العروض')

@section('content')
    @include('admin.partials.ad-filter-chip', ['adId' => $adId, 'adFilter' => $adFilter, 'clearUrl' => route('admin.offers.index')])

    <x-admin.page-toolbar>
        <x-admin.filter-bar
            :reset-url="route('admin.offers.index')"
            :search="$search"
            placeholder="بحث بالإعلان أو المشتري…"
            :carry="$adId !== '' ? ['ad' => $adId] : []"
            :filters="[
                ['name' => 'status', 'label' => 'الحالة', 'placeholder' => 'كل الحالات', 'value' => $status,
                    'options' => collect($statuses)->mapWithKeys(fn ($case) => [$case->value => $case->label()['ar']])],
            ]"
        />
    </x-admin.page-toolbar>

    <x-admin.table
        caption="العروض"
        :columns="['الإعلان', 'من (مشتري)', 'إلى (بائع)', 'المبلغ', 'الحالة', ['label' => 'التاريخ', 'secondary' => true]]"
        :empty="$offers->isEmpty()"
        empty-icon="banknotes"
        empty-message="لا توجد عروض مطابقة."
    >
        @foreach ($offers as $offer)
            <x-admin.table.row>
                <x-admin.table.cell primary>
                    <a href="{{ route('admin.offers.show', $offer) }}" class="font-semibold hover:text-coral-700" title="{{ __('admin.offer_admin.open') }}">{{ \Illuminate\Support\Str::limit($offer->ad?->title ?? '—', 40) }}</a>
                </x-admin.table.cell>
                <x-admin.table.cell label="من (مشتري)" class="text-ink-700"><x-admin.user :user="$offer->buyer" /></x-admin.table.cell>
                <x-admin.table.cell label="إلى (بائع)" class="text-ink-700"><x-admin.user :user="$offer->seller" /></x-admin.table.cell>
                <x-admin.table.cell label="المبلغ" class="font-semibold tabular-nums">{{ number_format((float) $offer->amount, 2) }} {{ $offer->currency ?: 'QAR' }}</x-admin.table.cell>
                <x-admin.table.cell label="الحالة"><x-admin.badge :status="$offer->status" /></x-admin.table.cell>
                <x-admin.table.cell label="التاريخ" secondary class="text-ink-500">{{ optional($offer->created_at)->format('Y-m-d') }}</x-admin.table.cell>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$offers" />
@endsection
