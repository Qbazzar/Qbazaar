@extends('admin.layout')

@section('title', 'عمليات البحث المحفوظة')
@section('heading', 'عمليات البحث المحفوظة')

@section('content')
    <x-admin.page-toolbar>
        <x-admin.filter-bar :reset-url="route('admin.saved-searches.index')" :search="$search" placeholder="بحث بالاسم أو المستخدم…" />
    </x-admin.page-toolbar>

    <x-admin.table
        caption="عمليات البحث المحفوظة"
        :columns="['الاسم', 'المستخدم', 'معايير البحث', ['label' => 'التاريخ', 'secondary' => true]]"
        :empty="$savedSearches->isEmpty()"
        empty-icon="bookmark"
        empty-message="لا توجد عمليات بحث محفوظة."
    >
        @foreach ($savedSearches as $savedSearch)
            <x-admin.table.row>
                <x-admin.table.cell primary class="font-semibold">{{ $savedSearch->name ?? '—' }}</x-admin.table.cell>
                <x-admin.table.cell label="المستخدم" class="text-ink-700">{{ $savedSearch->user?->full_name ?? '—' }}</x-admin.table.cell>
                <x-admin.table.cell label="معايير البحث" class="font-mono text-xs text-ink-500">
                    <span dir="ltr" class="break-all">{{ \Illuminate\Support\Str::limit(is_array($savedSearch->query_params) ? (string) json_encode($savedSearch->query_params, JSON_UNESCAPED_UNICODE) : (string) $savedSearch->query_params, 70) }}</span>
                </x-admin.table.cell>
                <x-admin.table.cell label="التاريخ" secondary class="text-ink-500">{{ optional($savedSearch->created_at)->format('Y-m-d') }}</x-admin.table.cell>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$savedSearches" />
@endsection
