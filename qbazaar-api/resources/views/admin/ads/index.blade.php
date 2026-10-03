@extends('admin.layout')

@section('title', 'الإعلانات')
@section('heading', 'الإعلانات')

@section('content')
    <x-admin.page-toolbar>
        <x-admin.filter-bar
            :reset-url="route('admin.ads.index')"
            :search="$search"
            placeholder="بحث بالعنوان أو الرقم…"
            :filters="[
                ['name' => 'status', 'label' => 'الحالة', 'placeholder' => 'كل الحالات', 'value' => $status,
                    'options' => collect($statuses)->mapWithKeys(fn ($case) => [$case->value => $case->label()['ar']])],
            ]"
        />
    </x-admin.page-toolbar>

    <x-admin.bulk-bar :action="route('admin.ads.bulk-destroy')" label="حذف المحدد" confirm="حذف الإعلانات المحددة نهائياً؟" />

    <x-admin.table
        caption="الإعلانات"
        selectable
        :columns="[['label' => '#', 'secondary' => true], 'العنوان', 'البائع', 'الحالة', ['label' => 'التاريخ', 'secondary' => true], ['label' => 'إجراءات', 'srOnly' => true]]"
        :empty="$ads->isEmpty()"
        empty-icon="tag"
        empty-message="لا توجد إعلانات مطابقة."
    >
        @foreach ($ads as $ad)
            <x-admin.table.row>
                <x-admin.table.select :value="$ad->id" :label="'تحديد: ' . $ad->title" />
                <x-admin.table.cell label="#" secondary class="text-ink-500">{{ $ad->id }}</x-admin.table.cell>
                <x-admin.table.cell primary>
                    <a href="{{ route('admin.ads.show', $ad) }}" class="font-semibold hover:text-coral-700">{{ \Illuminate\Support\Str::limit($ad->title, 50) }}</a>
                </x-admin.table.cell>
                <x-admin.table.cell label="البائع" class="text-ink-700">{{ $ad->user?->full_name ?? '—' }}</x-admin.table.cell>
                <x-admin.table.cell label="الحالة"><x-admin.badge :status="$ad->status" /></x-admin.table.cell>
                <x-admin.table.cell label="التاريخ" secondary class="text-ink-500">{{ optional($ad->created_at)->format('Y-m-d') }}</x-admin.table.cell>
                <x-admin.table.actions>
                    <x-admin.icon-button :href="route('admin.ads.show', $ad)" icon="eye" label="عرض" />
                    <x-admin.icon-button :href="route('admin.ads.edit', $ad)" icon="pencil" label="تعديل" />
                </x-admin.table.actions>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$ads" />
@endsection
