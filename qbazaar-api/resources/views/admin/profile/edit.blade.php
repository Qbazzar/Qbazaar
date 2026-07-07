@extends('admin.layout')

@section('title', 'حسابي')
@section('heading', 'حسابي')

@section('content')
    <form method="POST" action="{{ route('admin.profile.update') }}" class="mx-auto max-w-2xl space-y-6">
        @csrf
        @method('PUT')

        <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
            <h2 class="mb-4 font-bold">البيانات الأساسية</h2>
            <div class="space-y-4">
                <div>
                    <label for="full_name" class="mb-1.5 block text-sm font-semibold">الاسم الكامل</label>
                    <input id="full_name" name="full_name" type="text" required value="{{ old('full_name', $user->full_name) }}"
                           class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                    @error('full_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-semibold">البريد الإلكتروني</label>
                    <input id="email" name="email" type="email" required value="{{ old('email', $user->email) }}"
                           class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                    @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
            <h2 class="mb-1 font-bold">تغيير كلمة المرور</h2>
            <p class="mb-4 text-xs text-ink-500">اتركها فارغة إن لم ترغب بتغييرها.</p>
            <div class="space-y-4">
                <div>
                    <label for="password" class="mb-1.5 block text-sm font-semibold">كلمة المرور الجديدة</label>
                    <input id="password" name="password" type="password" autocomplete="new-password"
                           class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                    @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password_confirmation" class="mb-1.5 block text-sm font-semibold">تأكيد كلمة المرور</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                           class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                </div>
            </div>
        </div>

        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-coral px-5 py-2.5 text-sm font-bold text-white transition hover:brightness-95">
            <x-admin.icon name="check" class="size-[18px]" /> حفظ التغييرات
        </button>
    </form>
@endsection
