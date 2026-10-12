@extends('admin.layout')

@section('title', 'حسابي')
@section('heading', 'حسابي')

@section('content')
    <x-admin.page title="حسابي" width="form" :form="[route('admin.profile.update'), 'PUT']" submit="حفظ التغييرات"
                  subtitle="بيانات دخولك إلى لوحة الإدارة. تُسجَّل التغييرات في سجل النشاط." side-label="ملخص الحساب">
        @if ($user->must_change_password)
            <x-slot:lead>
                <x-admin.alert tone="warning" :title="__('admin.auth.password_change_required')">
                    <p class="mt-1">لن تُفتح بقية صفحات اللوحة قبل حفظ كلمة مرور جديدة.</p>
                </x-admin.alert>
            </x-slot:lead>
        @endif

        <x-admin.form-section title="البيانات الأساسية" icon="users" cols="2" id="section-basics">
            <x-admin.input name="full_name" label="الاسم الكامل" :value="old('full_name', $user->full_name)" required autocomplete="name" />
            <x-admin.input name="email" type="email" label="البريد الإلكتروني" :value="old('email', $user->email)" required autocomplete="email" />
        </x-admin.form-section>

        <x-admin.form-section title="تغيير كلمة المرور" icon="key" cols="2" id="section-password"
                              :description="$user->must_change_password ? 'مطلوبة: اختر كلمة مرور جديدة تختلف عن الحالية.' : 'اتركها فارغة إن لم ترغب بتغييرها.'">
            <x-admin.input name="password" type="password" label="كلمة المرور الجديدة" autocomplete="new-password"
                           :hint="'الحد الأدنى ' . config('qbazaar.auth.password_min_length') . ' أحرف.'" />
            <x-admin.input name="password_confirmation" type="password" label="تأكيد كلمة المرور" autocomplete="new-password" />
        </x-admin.form-section>

        <x-slot:side>
            <x-admin.side-card title="الحساب" icon="users" id="side-identity">
                <div class="flex min-w-0 items-center gap-3">
                    <x-admin.user-avatar :user="$user" size="lg" decorative />
                    <div class="min-w-0">
                        <p class="truncate font-bold text-ink-900">{{ $user->full_name ?: '—' }}</p>
                        <p class="truncate text-end text-[13px] text-ink-500" dir="ltr">{{ $user->email }}</p>
                    </div>
                </div>
                @if ($user->roles->isNotEmpty())
                    <div class="mt-4 flex flex-wrap gap-2 border-t border-ink-200 pt-4">
                        @foreach ($user->roles as $role)
                            <x-admin.role-badge :role="$role" />
                        @endforeach
                    </div>
                @endif
            </x-admin.side-card>

            <x-admin.side-card title="النشاط" icon="clock" id="side-activity">
                <x-admin.meta-list>
                    <x-admin.meta-item label="آخر دخول" ltr>{{ optional($user->last_login_at)->format('Y-m-d H:i') ?? '—' }}</x-admin.meta-item>
                    <x-admin.meta-item label="عضو منذ" ltr>{{ optional($user->created_at)->format('Y-m-d') }}</x-admin.meta-item>
                    <x-admin.meta-item label="آخر تحديث" ltr>{{ optional($user->updated_at)->format('Y-m-d H:i') }}</x-admin.meta-item>
                </x-admin.meta-list>
            </x-admin.side-card>
        </x-slot:side>
    </x-admin.page>
@endsection
