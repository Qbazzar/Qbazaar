@extends('admin.layout')

@section('title', 'المستخدمون')
@section('heading', 'المستخدمون')

@section('content')
    <x-admin.page-toolbar>
        <x-admin.filter-bar
            :reset-url="route('admin.users.index')"
            :search="$search"
            placeholder="بحث بالاسم أو البريد أو الهاتف…"
            :filters="[
                ['name' => 'status', 'label' => 'الحالة', 'placeholder' => 'كل الحالات', 'value' => $status,
                    'options' => collect($statuses)->mapWithKeys(fn ($case) => [$case->value => $case->label()['ar']])],
                ['name' => 'role', 'label' => 'الدور', 'placeholder' => 'كل الأدوار', 'value' => $role,
                    'options' => collect($roles)->mapWithKeys(fn ($roleName) => [$roleName => $roleName])],
            ]"
        />
    </x-admin.page-toolbar>

    <x-admin.table
        caption="المستخدمون"
        :columns="['الاسم', 'البريد', ['label' => 'الهاتف', 'secondary' => true], 'الأدوار', 'الحالة', ['label' => 'التاريخ', 'secondary' => true], ['label' => 'إجراءات', 'srOnly' => true]]"
        :empty="$users->isEmpty()"
        empty-icon="users"
        empty-message="لا يوجد مستخدمون مطابقون."
    >
        @foreach ($users as $user)
            <x-admin.table.row>
                <x-admin.table.cell primary>
                    <a href="{{ route('admin.users.show', $user) }}" class="font-semibold hover:text-coral-700">{{ $user->full_name ?? '—' }}</a>
                </x-admin.table.cell>
                <x-admin.table.cell label="البريد" class="text-ink-700">{{ $user->email }}</x-admin.table.cell>
                <x-admin.table.cell label="الهاتف" secondary class="text-ink-500">{{ $user->phone ?? '—' }}</x-admin.table.cell>
                <x-admin.table.cell label="الأدوار">
                    <div class="flex flex-wrap justify-end gap-1 md:justify-start">
                        @forelse ($user->roles as $userRole)
                            <x-admin.badge tone="brand">{{ $userRole->name }}</x-admin.badge>
                        @empty
                            <span class="text-ink-500">—</span>
                        @endforelse
                    </div>
                </x-admin.table.cell>
                <x-admin.table.cell label="الحالة"><x-admin.badge :status="$user->status" /></x-admin.table.cell>
                <x-admin.table.cell label="التاريخ" secondary class="text-ink-500">{{ optional($user->created_at)->format('Y-m-d') }}</x-admin.table.cell>
                <x-admin.table.actions>
                    <x-admin.icon-button :href="route('admin.users.show', $user)" icon="eye" label="عرض" />
                </x-admin.table.actions>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$users" />
@endsection
