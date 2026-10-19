@extends('admin.layout')

@section('title', __('admin.permission_matrix.title'))
@section('heading', __('admin.permission_matrix.title'))

@section('content')
    <x-admin.page :title="__('admin.permission_matrix.title')" :subtitle="__('admin.permission_matrix.subtitle')"
                  :crumbs="[[__('admin.permission_matrix.crumb'), route('admin.roles.index')], __('admin.permission_matrix.title')]"
                  :form="[route('admin.roles.permissions.update'), 'PUT']" :cancel="route('admin.roles.index')" dirty-guard
                  :side-label="__('admin.permission_matrix.side_label')">
        <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[36rem] text-start text-sm">
                    <caption class="sr-only">{{ __('admin.permission_matrix.title') }}</caption>
                    <thead class="border-b border-ink-200 bg-cream-50 text-xs font-semibold text-ink-500">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-start">{{ __('admin.permission_matrix.permission') }}</th>
                            <th scope="col" class="px-4 py-3 text-center">
                                {{ __('admin.role_names.super_admin') }}
                                <span class="block text-[11px] font-normal">{{ __('admin.permission_matrix.locked') }}</span>
                            </th>
                            @foreach ($roles as $role)
                                <th scope="col" class="px-4 py-3 text-center"><x-admin.role-badge :role="$role" /></th>
                            @endforeach
                        </tr>
                    </thead>
                    @foreach ($rows as $group)
                        <tbody class="border-b border-ink-200 last:border-b-0">
                            <tr class="bg-cream-50/60">
                                <th scope="rowgroup" colspan="{{ $roles->count() + 2 }}" class="px-4 py-2 text-start text-xs font-bold text-ink-700">
                                    {{ $group['label'] }}
                                </th>
                            </tr>
                            @foreach ($group['permissions'] as $permission)
                                <tr class="border-t border-ink-200/60">
                                    <th scope="row" class="px-4 py-2.5 text-start font-normal">
                                        <span class="block font-semibold text-ink-900">{{ $permission['label'] }}</span>
                                        <span class="block font-mono text-[11px] text-ink-500" dir="ltr">{{ $permission['name'] }}</span>
                                        @if ($permission['reserved'])
                                            <span class="block text-[11px] text-ink-500">{{ __('admin.permission_matrix.reserved') }}</span>
                                        @endif
                                    </th>
                                    <td class="px-4 py-2.5 text-center">
                                        <x-admin.icon name="check" class="mx-auto size-4 text-emerald-700" />
                                        <span class="sr-only">{{ __('admin.permission_matrix.always') }}</span>
                                    </td>
                                    @foreach ($roles as $role)
                                        <td class="px-4 py-2.5 text-center">
                                            <input type="checkbox" name="permissions[{{ $role->name }}][]" value="{{ $permission['name'] }}"
                                                   @checked($permission['granted'][$role->name] ?? false)
                                                   @disabled($permission['reserved'])
                                                   aria-label="{{ __('admin.permission_matrix.cell', ['permission' => $permission['label'], 'role' => $role->name]) }}"
                                                   class="size-4 rounded border-ink-300 accent-coral-600 disabled:opacity-40">
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>
        </div>

        <x-slot:side>
            <x-admin.side-card :title="__('admin.permission_matrix.rules_title')" icon="shield" id="side-rules">
                <ul class="grid list-disc gap-2 ps-5 text-[13px] text-ink-700">
                    <li>{{ __('admin.permission_matrix.rule_super_admin') }}</li>
                    <li>{{ __('admin.permission_matrix.rule_reserved') }}</li>
                    <li>{{ __('admin.permission_matrix.rule_effect') }}</li>
                    <li>{{ __('admin.permission_matrix.rule_audit') }}</li>
                </ul>
            </x-admin.side-card>
        </x-slot:side>
    </x-admin.page>
@endsection
