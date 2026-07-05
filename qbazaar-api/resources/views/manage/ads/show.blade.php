@extends('manage.layout')

@section('title', 'إعلان #' . $ad->id)
@section('heading', 'مراجعة إعلان')

@section('content')
    <a href="{{ url()->previous() }}" class="mb-4 inline-block text-sm font-semibold text-ink-500 hover:text-coral">→ رجوع</a>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Details --}}
        <div class="space-y-6 lg:col-span-2">
            <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
                <div class="flex items-start justify-between gap-4">
                    <h2 class="text-xl font-bold">{{ $ad->title }}</h2>
                    @include('manage.partials.status-badge', ['status' => $ad->status])
                </div>

                <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
                    <div><span class="text-ink-500">السعر:</span> {{ $ad->price !== null ? number_format((float) $ad->price, 2) . ' ر.ق' : '—' }}</div>
                    <div><span class="text-ink-500">التصنيف:</span> {{ $ad->category?->getLocalizedName(app()->getLocale()) ?? '—' }}</div>
                    <div><span class="text-ink-500">الموقع:</span> {{ $ad->location?->getLocalizedName(app()->getLocale()) ?? '—' }}</div>
                    <div><span class="text-ink-500">المشاهدات:</span> {{ number_format($ad->views_count) }}</div>
                    <div><span class="text-ink-500">مميّز:</span> {{ $ad->featured ? 'نعم' : 'لا' }}</div>
                    <div><span class="text-ink-500">تاريخ الإنشاء:</span> {{ optional($ad->created_at)->format('Y-m-d H:i') }}</div>
                </div>

                <div class="mt-5">
                    <div class="mb-1.5 text-sm font-semibold text-ink-500">الوصف</div>
                    <p class="whitespace-pre-line text-sm leading-relaxed text-ink-700">{{ $ad->description }}</p>
                </div>
            </div>

            @php($images = $ad->getMedia('images'))
            @if ($images->isNotEmpty())
                <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
                    <div class="mb-3 text-sm font-semibold text-ink-500">الصور ({{ $images->count() }})</div>
                    <div class="grid grid-cols-3 gap-3 sm:grid-cols-4">
                        @foreach ($images as $image)
                            <a href="{{ $image->getUrl() }}" target="_blank"
                               class="block aspect-square overflow-hidden rounded-xl border border-ink-200">
                                <img src="{{ $image->getUrl() }}" alt="" class="h-full w-full object-cover">
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Actions --}}
        <div class="space-y-6">
            <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
                <div class="mb-3 text-sm font-semibold text-ink-500">البائع</div>
                <div class="font-semibold">{{ $ad->user?->full_name ?? '—' }}</div>
                <div class="text-sm text-ink-500">{{ $ad->user?->email }}</div>
            </div>

            <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
                <div class="mb-4 text-sm font-semibold text-ink-500">إجراءات الإشراف</div>
                <div class="space-y-3">
                    @if ($ad->status === \App\Enums\AdStatus::PENDING)
                        <form method="POST" action="{{ route('manage.ads.approve', $ad) }}">
                            @csrf
                            <button class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:brightness-95">اعتماد الإعلان</button>
                        </form>

                        <form method="POST" action="{{ route('manage.ads.reject', $ad) }}" class="space-y-2">
                            @csrf
                            <textarea name="admin_notes" rows="3" required placeholder="سبب الرفض (يظهر للبائع)…"
                                      class="w-full rounded-xl border border-ink-200 bg-cream-50 px-3 py-2 text-sm outline-none focus:border-coral">{{ old('admin_notes') }}</textarea>
                            @error('admin_notes')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                            <button class="w-full rounded-xl bg-red-600 px-4 py-2.5 text-sm font-bold text-white hover:brightness-95">رفض الإعلان</button>
                        </form>
                    @endif

                    @if ($ad->status === \App\Enums\AdStatus::ACTIVE)
                        <form method="POST" action="{{ route('manage.ads.suspend', $ad) }}">
                            @csrf
                            <button class="w-full rounded-xl bg-red-600 px-4 py-2.5 text-sm font-bold text-white hover:brightness-95">إيقاف الإعلان</button>
                        </form>
                    @endif

                    @if ($ad->status === \App\Enums\AdStatus::BLOCKED)
                        <form method="POST" action="{{ route('manage.ads.unsuspend', $ad) }}">
                            @csrf
                            <button class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:brightness-95">رفع الإيقاف</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('manage.ads.feature', $ad) }}">
                        @csrf
                        <button class="w-full rounded-xl border border-ink-200 px-4 py-2.5 text-sm font-semibold text-ink-700 hover:bg-cream-200">
                            {{ $ad->featured ? 'إلغاء التمييز' : 'تمييز الإعلان' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
