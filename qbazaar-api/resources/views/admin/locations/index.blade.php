@extends('admin.layout')

@section('title', 'المواقع')
@section('heading', 'المواقع')

@section('content')
    <x-admin.page-toolbar>
        <x-admin.filter-bar
            :reset-url="route('admin.locations.index')"
            :search="$search"
            placeholder="بحث بالاسم أو المعرّف…"
            :filters="[
                ['name' => 'type', 'label' => 'النوع', 'placeholder' => 'كل الأنواع', 'value' => $type,
                    'options' => collect($types)->mapWithKeys(fn ($case) => [$case->value => $case->label()['ar']])],
            ]"
        />
        <x-slot:actions>
            <x-admin.button :href="route('admin.locations.create')" icon="plus">موقع جديد</x-admin.button>
        </x-slot:actions>
    </x-admin.page-toolbar>

    <x-admin.bulk-bar :action="route('admin.locations.bulk-destroy')" label="حذف المحدد" confirm="حذف العناصر المحددة نهائياً؟" />

    <x-admin.table
        caption="المواقع"
        selectable
        :columns="['الاسم', ['label' => 'المعرّف', 'secondary' => true], 'النوع', 'الموقع الأب', ['label' => 'الترتيب', 'secondary' => true], ['label' => 'إجراءات', 'srOnly' => true]]"
        :empty="$locations->isEmpty()"
        empty-icon="map-pin"
        empty-message="لا توجد مواقع مطابقة."
    >
        @foreach ($locations as $location)
            @php($locationName = $location->getLocalizedName(app()->getLocale()))
            <x-admin.table.row>
                <x-admin.table.select :value="$location->id" :label="'تحديد: ' . $locationName" />
                <x-admin.table.cell primary>
                    <a href="{{ route('admin.locations.edit', $location) }}" class="font-semibold hover:text-coral-700">{{ $locationName }}</a>
                </x-admin.table.cell>
                <x-admin.table.cell label="المعرّف" secondary class="text-ink-500">{{ $location->slug }}</x-admin.table.cell>
                <x-admin.table.cell label="النوع"><x-admin.badge :status="$location->type" /></x-admin.table.cell>
                <x-admin.table.cell label="الموقع الأب" class="text-ink-700">{{ $location->parent?->getLocalizedName(app()->getLocale()) ?? '—' }}</x-admin.table.cell>
                <x-admin.table.cell label="الترتيب" secondary class="text-ink-500">{{ $location->order }}</x-admin.table.cell>
                <x-admin.table.actions>
                    <x-admin.icon-button :href="route('admin.locations.edit', $location)" icon="pencil" label="تعديل" />
                    <form method="POST" action="{{ route('admin.locations.destroy', $location) }}" data-confirm="هل أنت متأكد من حذف هذا الموقع؟">
                        @csrf
                        @method('DELETE')
                        <x-admin.icon-button icon="trash" label="حذف" variant="danger" />
                    </form>
                </x-admin.table.actions>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$locations" />
@endsection
