@extends('admin.layout')

@section('title', 'حسابي')
@section('heading', 'حسابي')

@section('content')
    <form method="POST" action="{{ route('admin.profile.update') }}" class="mx-auto max-w-2xl space-y-6">
        @csrf
        @method('PUT')

        <x-admin.card title="البيانات الأساسية">
            <div class="space-y-4">
                <x-admin.input name="full_name" label="الاسم الكامل" :value="old('full_name', $user->full_name)" required autocomplete="name" />
                <x-admin.input name="email" type="email" label="البريد الإلكتروني" :value="old('email', $user->email)" required autocomplete="email" />
            </div>
        </x-admin.card>

        <x-admin.card title="تغيير كلمة المرور" description="اتركها فارغة إن لم ترغب بتغييرها.">
            <div class="space-y-4">
                <x-admin.input name="password" type="password" label="كلمة المرور الجديدة" autocomplete="new-password" />
                <x-admin.input name="password_confirmation" type="password" label="تأكيد كلمة المرور" autocomplete="new-password" />
            </div>
        </x-admin.card>

        <x-admin.button icon="check">حفظ التغييرات</x-admin.button>
    </form>
@endsection
