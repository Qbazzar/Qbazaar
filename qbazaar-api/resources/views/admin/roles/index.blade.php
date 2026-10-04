@extends('admin.layout')

@section('title', 'الأدوار')
@section('heading', 'الأدوار')

@section('content')
    <x-admin.page-toolbar>
        <x-admin.filter-bar :reset-url="route('admin.roles.index')" :search="$search" placeholder="بحث بالاسم…" />
    </x-admin.page-toolbar>

    <x-admin.table
        caption="الأدوار"
        :columns="['الدور', 'الحارس', 'عدد الصلاحيات', 'عدد المستخدمين']"
        :empty="$roles->isEmpty()"
        empty-icon="key"
        empty-message="لا توجد أدوار."
    >
        @foreach ($roles as $role)
            <x-admin.table.row>
                <x-admin.table.cell primary><x-admin.badge tone="brand">{{ $role->name }}</x-admin.badge></x-admin.table.cell>
                <x-admin.table.cell label="الحارس" class="text-ink-500">{{ $role->guard_name }}</x-admin.table.cell>
                <x-admin.table.cell label="عدد الصلاحيات" class="text-ink-700">{{ number_format($role->permissions_count) }}</x-admin.table.cell>
                <x-admin.table.cell label="عدد المستخدمين" class="text-ink-700">{{ number_format($role->users_count) }}</x-admin.table.cell>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>
@endsection
