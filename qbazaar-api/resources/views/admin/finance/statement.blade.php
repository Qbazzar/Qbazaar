@extends('admin.layout')

@section('title', 'كشف حساب ' . $user->full_name)
@section('heading', 'كشف الحساب')

@php
    $canViewUsers = auth()->user()?->can('users.view') ?? false;
    $currency = config('qbazaar.default_currency', 'QAR');
    $displayName = $user->full_name ?: $user->email;
    $crumbs = $canViewUsers
        ? [['المستخدمون', route('admin.users.index')], [$displayName, route('admin.users.show', $user)], 'كشف الحساب']
        : [$displayName, 'كشف الحساب'];
@endphp

@section('content')
    <x-admin.page :title="'كشف حساب ' . $displayName" :crumbs="$crumbs" side-label="معلومات صاحب الحساب">
        <x-slot:lead>
            @include('admin.finance.partials.wallet-summary', ['summary' => $summary])
        </x-slot:lead>

        <section aria-labelledby="ledger-title" class="grid gap-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="ledger-title" class="text-[15px] font-bold text-ink-900">حركات الحساب</h2>
                <p class="text-[13px] text-ink-500">الأحدث أولاً</p>
            </div>

            <x-admin.table
                caption="حركات الحساب"
                :columns="['التاريخ', 'الحركة', 'الحساب', 'مدين', 'دائن', 'الرصيد بعدها']"
                :empty="$entries->isEmpty()"
                empty-icon="inbox"
                empty-message="لا توجد حركات."
            >
                @foreach ($entries as $entry)
                    <x-admin.table.row>
                        <x-admin.table.cell primary><span dir="ltr">{{ $entry->created_at->format('Y-m-d H:i') }}</span></x-admin.table.cell>
                        <x-admin.table.cell label="الحركة">{{ __($entry->transaction->type->descriptionKey(), [], 'ar') }}</x-admin.table.cell>
                        <x-admin.table.cell label="الحساب">{{ $entry->account->type->value === 'wallet' ? 'المحفظة' : 'العمولة المستحقة' }}</x-admin.table.cell>
                        <x-admin.table.cell label="مدين"><span dir="ltr">{{ $entry->debit }}</span></x-admin.table.cell>
                        <x-admin.table.cell label="دائن"><span dir="ltr">{{ $entry->credit }}</span></x-admin.table.cell>
                        <x-admin.table.cell label="الرصيد بعدها" class="font-semibold"><span dir="ltr">{{ $entry->balance_after }}</span></x-admin.table.cell>
                    </x-admin.table.row>
                @endforeach
            </x-admin.table>

            <x-admin.pagination :paginator="$entries" class="!mt-0" />
        </section>

        <x-slot:side>
            <x-admin.side-card title="صاحب الحساب" icon="users" id="side-owner"
                               :link="$canViewUsers ? ['فتح الملف', route('admin.users.show', $user)] : null">
                <div class="flex min-w-0 items-center gap-3">
                    <x-admin.user-avatar :user="$user" size="md" decorative />
                    <div class="min-w-0">
                        <p class="truncate font-bold text-ink-900">{{ $user->full_name ?: '—' }}</p>
                        <p class="truncate text-end text-[13px] text-ink-500" dir="ltr">{{ $user->email }}</p>
                    </div>
                </div>
                <x-admin.meta-list class="mt-4 border-t border-ink-200 pt-4">
                    <x-admin.meta-item label="الهاتف"><x-admin.phone :phone="$user->phone" /></x-admin.meta-item>
                    <x-admin.meta-item label="نوع الحساب">{{ __('admin.home.account_type.' . $user->account_type->value) }}</x-admin.meta-item>
                    <x-admin.meta-item label="الحالة"><x-admin.badge :status="$user->status" /></x-admin.meta-item>
                    <x-admin.meta-item label="عضو منذ" ltr>{{ optional($user->created_at)->format('Y-m-d') }}</x-admin.meta-item>
                </x-admin.meta-list>
            </x-admin.side-card>

            @foreach ([
                ['title' => 'آخر تسويات العمولة', 'icon' => 'banknotes', 'id' => 'side-settlements', 'rows' => $settlements,
                    'all' => ['كل تسويات هذا المستخدم', route('admin.finance.settlements.index', ['user' => $user->id])], 'empty' => 'لا توجد تسويات.'],
                ['title' => 'آخر طلبات السحب', 'icon' => 'arrow-right', 'id' => 'side-withdrawals', 'rows' => $withdrawals,
                    'all' => ['كل سحوبات هذا المستخدم', route('admin.finance.withdrawals.index', ['user' => $user->id])], 'empty' => 'لا توجد طلبات سحب.'],
            ] as $list)
                <x-admin.side-card :title="$list['title']" :icon="$list['icon']" :id="$list['id']" :link="$list['all']">
                    @if ($list['rows'] === null)
                        <x-admin.error-state :retry-url="route('admin.finance.statements.show', $user)" />
                    @elseif ($list['rows']->isEmpty())
                        <x-admin.empty-state compact icon="inbox" :message="$list['empty']" />
                    @else
                        <ul class="grid gap-3">
                            @foreach ($list['rows'] as $row)
                                <li class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <span class="block font-semibold text-ink-900"><bdi dir="ltr">{{ $row->amount }} {{ $currency }}</bdi></span>
                                        <span class="text-xs text-ink-500" dir="ltr">{{ optional($row->created_at)->format('Y-m-d') }}</span>
                                    </div>
                                    <x-admin.badge :status="$row->status" />
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-admin.side-card>
            @endforeach
        </x-slot:side>
    </x-admin.page>
@endsection
