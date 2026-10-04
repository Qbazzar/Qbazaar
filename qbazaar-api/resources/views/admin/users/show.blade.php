@extends('admin.layout')

@section('title', 'مستخدم · ' . ($user->full_name ?? $user->email))
@section('heading', 'تفاصيل المستخدم')

@php($assignedRoles = $user->roles->pluck('name')->all())

@section('content')
    <x-admin.back-link :href="route('admin.users.index')" class="mb-4">رجوع للمستخدمين</x-admin.back-link>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-admin.card>
                <div class="flex items-start justify-between gap-4">
                    <div class="flex min-w-0 items-center gap-4">
                        @if ($user->avatarThumbUrl())
                            <img src="{{ $user->avatarThumbUrl() }}" alt="" class="size-14 shrink-0 rounded-full border border-ink-200 object-cover">
                        @endif
                        <div class="min-w-0">
                            <h2 class="text-xl font-bold">{{ $user->full_name ?? '—' }}</h2>
                            <div class="break-all text-sm text-ink-500">{{ $user->email }}</div>
                        </div>
                    </div>
                    <x-admin.badge :status="$user->status" />
                </div>

                <x-admin.detail-list class="mt-5">
                    <x-admin.detail label="المعرّف"><span class="font-mono text-xs">{{ $user->id }}</span></x-admin.detail>
                    <x-admin.detail label="الهاتف">{{ $user->phone ?? '—' }}</x-admin.detail>
                    <x-admin.detail label="نوع الحساب">{{ $user->account_type->value }}</x-admin.detail>
                    <x-admin.detail label="اللغة">{{ $user->language->value }}</x-admin.detail>
                    <x-admin.detail label="البريد موثّق">{{ $user->email_verified ? 'نعم' : 'لا' }}</x-admin.detail>
                    <x-admin.detail label="الهاتف موثّق">{{ $user->phone_verified ? 'نعم' : 'لا' }}</x-admin.detail>
                    <x-admin.detail label="عدد الإعلانات">{{ number_format($user->ads_count) }}</x-admin.detail>
                    <x-admin.detail label="آخر دخول">{{ optional($user->last_login_at)->format('Y-m-d H:i') ?? '—' }}</x-admin.detail>
                    <x-admin.detail label="تاريخ الإنشاء">{{ optional($user->created_at)->format('Y-m-d H:i') }}</x-admin.detail>
                </x-admin.detail-list>
            </x-admin.card>

            <x-admin.card title="الأدوار">
                @if ($assignedRoles === [])
                    <p class="text-sm text-ink-500">لا توجد أدوار مُسندة.</p>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach ($assignedRoles as $roleName)
                            <x-admin.badge tone="brand">{{ $roleName }}</x-admin.badge>
                        @endforeach
                    </div>
                @endif

                @if ($canManageRoles)
                    <form method="POST" action="{{ route('admin.users.roles', $user) }}" class="mt-5 border-t border-ink-200 pt-5">
                        @csrf
                        <fieldset>
                            <legend class="mb-3 text-sm font-semibold text-ink-700">تعديل الأدوار</legend>
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach ($roles as $role)
                                    <x-admin.checkbox
                                        name="roles[]"
                                        :id="'role-' . $role->name"
                                        :value="$role->name"
                                        :label="$role->name"
                                        :checked="in_array($role->name, $assignedRoles, true)"
                                        class="rounded-xl border border-ink-200 bg-cream-50 px-3 py-2"
                                    />
                                @endforeach
                            </div>
                            @error('roles')
                                <p class="mt-2 text-xs font-semibold text-red-700">{{ $message }}</p>
                            @enderror
                        </fieldset>
                        <x-admin.button icon="key" block class="mt-4">حفظ الأدوار</x-admin.button>
                    </form>
                @endif
            </x-admin.card>
        </div>

        @if ($canManageUser)
            <div class="space-y-6">
                <x-admin.card title="إجراءات الإشراف">
                    <div class="space-y-3">
                        @can('users.ban')
                            @if ($user->status === \App\Enums\UserStatus::SUSPENDED)
                                <form method="POST" action="{{ route('admin.users.activate', $user) }}">
                                    @csrf
                                    <x-admin.button variant="success" icon="check" block>تفعيل المستخدم</x-admin.button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.users.suspend', $user) }}">
                                    @csrf
                                    <x-admin.button variant="danger" icon="ban" block>إيقاف المستخدم</x-admin.button>
                                </form>
                            @endif
                        @endcan

                        @can('users.update')
                            <form method="POST" action="{{ route('admin.users.reset-password', $user) }}"
                                  data-confirm="إرسال رابط إعادة تعيين كلمة المرور إلى بريد المستخدم؟" data-confirm-tone="primary">
                                @csrf
                                <x-admin.button variant="secondary" icon="key" block>إرسال رابط تعيين كلمة المرور</x-admin.button>
                            </form>
                        @endcan

                        @if (auth()->user()->can('users.impersonate') && ! $user->isStaff())
                            <form method="POST" action="{{ route('admin.users.impersonate', $user) }}" target="_blank" class="space-y-2"
                                  data-confirm="فتح الموقع كأنك هذا المستخدم في تبويب جديد؟" data-confirm-tone="primary">
                                @csrf
                                <x-admin.textarea
                                    name="reason"
                                    id="impersonation-reason"
                                    :label="__('admin.impersonation.reason')"
                                    :value="old('reason')"
                                    rows="2"
                                    required
                                    :hint="__('admin.impersonation.reason_hint', ['minutes' => config('qbazaar.admin.impersonation_ttl_minutes')])"
                                    minlength="{{ config('qbazaar.admin.impersonation_reason_min_length') }}"
                                    maxlength="{{ config('qbazaar.admin.impersonation_reason_max_length') }}"
                                />
                                <x-admin.button variant="secondary" icon="external" block>{{ __('admin.impersonation.submit') }}</x-admin.button>
                            </form>
                        @endif
                    </div>
                </x-admin.card>
            </div>
        @endif
    </div>
@endsection
