@extends('admin.layout')

@section('title', 'إعلان #' . $ad->id)
@section('heading', 'مراجعة إعلان')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <x-admin.back-link :href="route('admin.ads.index')">رجوع للإعلانات</x-admin.back-link>
        <x-admin.button variant="secondary" :href="route('admin.ads.edit', $ad)" icon="pencil">تعديل المحتوى</x-admin.button>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-admin.card>
                <div class="flex items-start justify-between gap-4">
                    <h2 class="min-w-0 break-words text-xl font-bold">{{ $ad->title }}</h2>
                    <x-admin.badge :status="$ad->status" />
                </div>

                <x-admin.detail-list class="mt-4">
                    <x-admin.detail label="السعر">{{ $ad->price !== null ? number_format((float) $ad->price, 2) . ' ر.ق' : '—' }}</x-admin.detail>
                    <x-admin.detail label="التصنيف">{{ $ad->category?->getLocalizedName(app()->getLocale()) ?? '—' }}</x-admin.detail>
                    <x-admin.detail label="الموقع">{{ $ad->location?->getLocalizedName(app()->getLocale()) ?? '—' }}</x-admin.detail>
                    <x-admin.detail label="المشاهدات">{{ number_format($ad->views_count) }}</x-admin.detail>
                    <x-admin.detail label="مميّز">{{ $ad->featured ? 'نعم' : 'لا' }}</x-admin.detail>
                    <x-admin.detail label="تاريخ الإنشاء">{{ optional($ad->created_at)->format('Y-m-d H:i') }}</x-admin.detail>
                </x-admin.detail-list>

                <div class="mt-5">
                    <h3 class="mb-1.5 text-sm font-semibold text-ink-500">الوصف</h3>
                    <p class="whitespace-pre-line text-sm leading-relaxed text-ink-700">{{ $ad->description }}</p>
                </div>
            </x-admin.card>

            @if ($ad->moderation_result !== null)
                <x-admin.card :title="__('admin.ad_review.auto_check')">
                    @if ($ad->moderation_result->clean)
                        <p class="text-sm text-emerald-700">{{ __('admin.ad_review.auto_check_clean') }}</p>
                    @else
                        <ul class="space-y-1.5 text-sm text-red-700">
                            @foreach ($ad->moderation_result->flags as $flag)
                                <li>{{ __('admin.ad_review.auto_check_flags.' . $flag) }}</li>
                            @endforeach
                        </ul>
                        @php($duplicateAdIds = $ad->moderation_result->details['duplicate_image']['duplicate_ad_ids'] ?? [])
                        @if ($duplicateAdIds !== [])
                            <div class="mt-3 text-sm">
                                <span class="text-ink-500">{{ __('admin.ad_review.duplicate_of') }}</span>
                                @foreach ($duplicateAdIds as $duplicateAdId)
                                    <a href="{{ route('admin.ads.show', $duplicateAdId) }}" class="font-semibold text-coral-700 hover:underline">#{{ $duplicateAdId }}</a>
                                @endforeach
                            </div>
                        @endif
                    @endif
                </x-admin.card>
            @elseif ($ad->status === \App\Enums\AdStatus::PENDING)
                <x-admin.card :title="__('admin.ad_review.auto_check')">
                    <p class="text-sm text-ink-500">{{ __('admin.ad_review.auto_check_pending') }}</p>
                </x-admin.card>
            @endif

            @php($images = $ad->getMedia('images'))
            @php($mediaStorage = app(\App\Services\Media\MediaStorage::class))
            @if ($images->isNotEmpty())
                <x-admin.card :title="'الصور (' . $images->count() . ')'">
                    <div class="grid grid-cols-3 gap-3 sm:grid-cols-4">
                        @foreach ($images as $image)
                            <a href="{{ $mediaStorage->signedOriginalUrl($image) }}" target="_blank"
                               class="block aspect-square overflow-hidden rounded-xl border border-ink-200">
                                <img src="{{ $mediaStorage->conversionUrl($image, 'medium') }}" alt="صورة {{ $loop->iteration }} من الإعلان" class="size-full object-cover">
                            </a>
                        @endforeach
                    </div>
                </x-admin.card>
            @endif
        </div>

        <div class="space-y-6">
            <x-admin.card title="البائع">
                <div class="font-semibold">{{ $ad->user?->full_name ?? '—' }}</div>
                <div class="break-all text-sm text-ink-500">{{ $ad->user?->email }}</div>
            </x-admin.card>

            <x-admin.card title="إجراءات الإشراف">
                <div class="space-y-3">
                    @if ($ad->status === \App\Enums\AdStatus::PENDING)
                        <form method="POST" action="{{ route('admin.ads.approve', $ad) }}">
                            @csrf
                            <x-admin.button variant="success" icon="check" block>اعتماد الإعلان</x-admin.button>
                        </form>

                        <form method="POST" action="{{ route('admin.ads.reject', $ad) }}" class="space-y-2">
                            @csrf
                            <x-admin.textarea name="admin_notes" label="سبب الرفض" label-hidden :value="old('admin_notes')" rows="3" required placeholder="سبب الرفض (يظهر للبائع)…" />
                            <x-admin.button variant="danger" icon="x-circle" block>رفض الإعلان</x-admin.button>
                        </form>
                    @endif

                    @if ($ad->status === \App\Enums\AdStatus::ACTIVE)
                        <form method="POST" action="{{ route('admin.ads.suspend', $ad) }}">
                            @csrf
                            <x-admin.button variant="danger" icon="ban" block>إيقاف الإعلان</x-admin.button>
                        </form>
                    @endif

                    @if ($ad->status === \App\Enums\AdStatus::BLOCKED)
                        <form method="POST" action="{{ route('admin.ads.unsuspend', $ad) }}">
                            @csrf
                            <x-admin.button variant="success" icon="check" block>رفع الإيقاف</x-admin.button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('admin.ads.feature', $ad) }}">
                        @csrf
                        <x-admin.button variant="secondary" block>
                            <x-admin.icon name="star" class="size-[18px] {{ $ad->featured ? 'text-coral-600' : '' }}" />
                            {{ $ad->featured ? 'إلغاء التمييز' : 'تمييز الإعلان' }}
                        </x-admin.button>
                    </form>

                    @if ($ad->status === \App\Enums\AdStatus::ACTIVE)
                        <form method="POST" action="{{ route('admin.ads.force-expire', $ad) }}" data-confirm="إنهاء صلاحية هذا الإعلان؟">
                            @csrf
                            <x-admin.button variant="secondary" icon="clock" block>إنهاء الصلاحية</x-admin.button>
                        </form>
                    @endif
                </div>

                <div class="mt-6 border-t border-ink-200 pt-4">
                    <form method="POST" action="{{ route('admin.ads.destroy', $ad) }}" data-confirm="حذف هذا الإعلان نهائياً؟ لا يمكن التراجع.">
                        @csrf
                        @method('DELETE')
                        <x-admin.button variant="danger-ghost" icon="trash" block>حذف الإعلان</x-admin.button>
                    </form>
                </div>
            </x-admin.card>
        </div>
    </div>
@endsection
