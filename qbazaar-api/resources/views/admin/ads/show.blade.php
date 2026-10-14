@extends('admin.layout')

@section('title', 'إعلان #' . $ad->id)
@section('heading', 'مراجعة إعلان')

@php
    $staff = auth()->user();
    $seller = $ad->user;
    $locale = app()->getLocale();
    $media = app(\App\Services\Media\MediaStorage::class);
    $images = $ad->getMedia('images')->values();
    $gallery = $images->map(fn ($image, int $index): array => [
        'thumb' => $media->conversionUrl($image, 'medium'),
        'full' => $media->signedOriginalUrl($image),
        'alt' => 'صورة ' . ($index + 1) . ' من الإعلان',
    ])->all();
    $money = fn ($amount): string => $amount !== null ? number_format((float) $amount, 2) . ' ر.ق' : '—';
    $details = [
        ['السعر', $money($ad->price), true],
        ['نوع السعر', $ad->price_type->label()['ar'], false],
        ['الحالة (الجودة)', $ad->condition?->label()['ar'] ?? '—', false],
        ['التصنيف', $ad->category?->getLocalizedName($locale) ?? '—', false],
        ['الموقع', $ad->location?->getLocalizedName($locale) ?? '—', false],
        ['مميّز', $ad->featured ? 'نعم' : 'لا', false],
    ];
    $openReports = $reports?->open ?? 0;
    $publicUrl = $ad->isPubliclyListed() ? rtrim((string) config('qbazaar.web_url'), '/') . '/ads/' . $ad->id : null;
    $isPending = $ad->status === \App\Enums\AdStatus::PENDING;
    $isActive = $ad->status === \App\Enums\AdStatus::ACTIVE;
    // The moves that apply to the ad's status and that this staff member may make; the card hides without any.
    $moderation = [
        'approve' => $isPending && $staff->can('ads.approve'),
        'reject' => $isPending && $staff->can('ads.reject'),
        'suspend' => $isActive && $staff->can('ads.suspend'),
        'unsuspend' => $ad->status === \App\Enums\AdStatus::BLOCKED && $staff->can('ads.suspend'),
        'feature' => $staff->can('ads.feature'),
        'expire' => $isActive && $staff->can('ads.suspend'),
    ];
    $otherAdsTotal = max(0, ($seller?->ads_count ?? 0) - 1);
    $since = $ad->published_at ? 'نُشر في ' . $ad->published_at->format('Y-m-d') : 'أُنشئ في ' . $ad->created_at->format('Y-m-d');
@endphp

@section('content')
    <x-admin.page :title="$ad->title"
                  :crumbs="[['الإعلانات', route('admin.ads.index')], 'إعلان #' . $ad->id]"
                  :subtitle="collect([$since, $ad->category?->getLocalizedName($locale), $ad->location?->getLocalizedName($locale)])->filter()->implode(' · ')"
                  side-label="إشراف ومعلومات الإعلان">
        <x-slot:badges>
            <x-admin.badge :status="$ad->status" />
            @if ($ad->featured)
                <x-admin.badge tone="brand">مميّز</x-admin.badge>
            @endif
            @if ($openReports > 0)
                <x-admin.badge tone="warning">{{ $openReports }} {{ $openReports === 1 ? 'بلاغ مفتوح' : 'بلاغات مفتوحة' }}</x-admin.badge>
            @endif
        </x-slot:badges>

        <x-slot:actions>
            @can('ads.update')
                <x-admin.button variant="secondary" :href="route('admin.ads.edit', $ad)" icon="pencil">تعديل المحتوى</x-admin.button>
            @endcan
        </x-slot:actions>

        <x-slot:menu>
            @if ($publicUrl)
                <x-admin.menu.item :href="$publicUrl" icon="external" target="_blank" rel="noopener">فتح في الموقع</x-admin.menu.item>
            @endif
            @can('ads.delete')
                @if ($publicUrl)
                    <x-admin.menu.divider />
                @endif
                <x-admin.menu.item :action="route('admin.ads.destroy', $ad)" method="DELETE" icon="trash" danger
                                   confirm="حذف هذا الإعلان نهائياً؟ لا يمكن التراجع.">حذف الإعلان</x-admin.menu.item>
            @endcan
        </x-slot:menu>

        @if ($errors->any())
            <x-slot:lead>
                <x-admin.alert tone="danger" title="تعذّر تنفيذ الإجراء. راجع ما يلي:" :summary="$errors" autofocus />
            </x-slot:lead>
        @endif

        {{-- Main column --}}
        @include('admin.ads.partials.deal-main')

        <x-admin.form-section id="ad-photos" :title="'الصور (' . $images->count() . ')'" icon="photo">
            <x-admin.gallery :images="$gallery" empty="لم يرفع البائع صوراً لهذا الإعلان." />
        </x-admin.form-section>

        <x-admin.form-section id="ad-details" title="تفاصيل الإعلان">
            <dl class="grid gap-x-6 gap-y-4 @min-[34rem]:grid-cols-2 @min-[46rem]:grid-cols-3">
                @foreach ($details as [$label, $value, $ltr])
                    <div class="min-w-0">
                        <dt class="text-xs text-ink-500">{{ $label }}</dt>
                        <dd class="mt-0.5 text-sm font-semibold break-words text-ink-900">
                            <span @if ($ltr) dir="ltr" @endif>{{ $value }}</span>
                        </dd>
                    </div>
                @endforeach
            </dl>

            <div>
                <h3 class="mb-1.5 text-[13px] font-semibold text-ink-500">الوصف</h3>
                <p class="max-w-[70ch] text-sm leading-[1.9] whitespace-pre-line break-words text-ink-700">{{ $ad->description }}</p>
            </div>
        </x-admin.form-section>

        @include('admin.ads.partials.deal-engagement')

        @if ($ad->moderation_result !== null || $isPending)
            <x-admin.form-section id="ad-auto-check" :title="__('admin.ad_review.auto_check')" icon="shield">
                @if ($ad->moderation_result === null)
                    <p class="text-sm text-ink-500">{{ __('admin.ad_review.auto_check_pending') }}</p>
                @elseif ($ad->moderation_result->clean)
                    <p class="text-sm text-emerald-700">{{ __('admin.ad_review.auto_check_clean') }}</p>
                @else
                    <ul class="grid list-disc gap-1.5 ps-5 text-sm text-red-700">
                        @foreach ($ad->moderation_result->flags as $flag)
                            <li>{{ __('admin.ad_review.auto_check_flags.' . $flag) }}</li>
                        @endforeach
                    </ul>
                    @php($duplicateAdIds = $ad->moderation_result->details['duplicate_image']['duplicate_ad_ids'] ?? [])
                    @if ($duplicateAdIds !== [])
                        <p class="flex flex-wrap items-baseline gap-x-2 text-sm">
                            <span class="text-ink-500">{{ __('admin.ad_review.duplicate_of') }}</span>
                            @foreach ($duplicateAdIds as $duplicateAdId)
                                <a href="{{ route('admin.ads.show', $duplicateAdId) }}" dir="ltr" class="font-semibold text-coral-700 hover:underline">#{{ $duplicateAdId }}</a>
                            @endforeach
                        </p>
                    @endif
                @endif
                <x-slot:actions>
                    @if ($ad->moderation_result === null)
                        <x-admin.badge tone="warning">قيد التشغيل</x-admin.badge>
                    @elseif ($ad->moderation_result->clean)
                        <x-admin.badge tone="success">لا مخالفات</x-admin.badge>
                    @else
                        <x-admin.badge tone="danger">{{ count($ad->moderation_result->flags) }} ملاحظات</x-admin.badge>
                    @endif
                </x-slot:actions>
            </x-admin.form-section>
        @endif

        <section aria-labelledby="seller-ads-title" class="grid gap-3">
            <div class="flex flex-wrap items-end justify-between gap-3 px-1">
                <div class="min-w-0">
                    <h2 id="seller-ads-title" class="text-[15px] font-bold text-ink-900">إعلانات أخرى لهذا البائع</h2>
                    @if ($otherAdsTotal > 0)
                        <p class="mt-1 text-[13px] text-ink-500">الأحدث أولاً، {{ min($otherAdsTotal, \App\Services\Admin\Ads\AdDetailPanels::RELATED_LIMIT) }} من {{ number_format($otherAdsTotal) }}</p>
                    @endif
                </div>
                @if ($seller && $otherAdsTotal > \App\Services\Admin\Ads\AdDetailPanels::RELATED_LIMIT && $staff->can('users.view'))
                    <a href="{{ route('admin.users.show', ['user' => $seller, 'tab' => 'ads']) }}"
                       class="shrink-0 rounded-full bg-cream-200 px-2.5 py-0.5 text-xs font-semibold text-ink-700 transition hover:bg-coral-soft hover:text-coral-700">عرض الكل ({{ number_format($otherAdsTotal) }})</a>
                @endif
            </div>

            @if ($otherAds === null)
                <x-admin.card><x-admin.error-state :retry-url="request()->fullUrl()" /></x-admin.card>
            @else
                <x-admin.table caption="إعلانات أخرى لهذا البائع" :columns="['الإعلان', 'السعر', 'الحالة', 'التاريخ']"
                               :empty="$otherAds->isEmpty()" empty-icon="tag" empty-message="لا توجد إعلانات أخرى لهذا البائع.">
                    @foreach ($otherAds as $otherAd)
                        <x-admin.table.row>
                            <x-admin.table.cell primary>
                                <a href="{{ route('admin.ads.show', $otherAd) }}" class="font-bold break-words hover:text-coral-700">{{ $otherAd->title }}</a>
                            </x-admin.table.cell>
                            <x-admin.table.cell label="السعر"><span dir="ltr">{{ $money($otherAd->price) }}</span></x-admin.table.cell>
                            <x-admin.table.cell label="الحالة"><x-admin.badge :status="$otherAd->status" /></x-admin.table.cell>
                            <x-admin.table.cell label="التاريخ" class="text-ink-500"><span dir="ltr">{{ $otherAd->created_at->format('Y-m-d') }}</span></x-admin.table.cell>
                        </x-admin.table.row>
                    @endforeach
                </x-admin.table>
            @endif
        </section>

        {{-- Side column --}}
        <x-slot:side>
            @if (in_array(true, $moderation, true))
                <x-admin.side-card id="ad-moderation" title="إجراءات الإشراف" icon="shield">
                    <div class="grid gap-2.5">
                        @if ($moderation['approve'])
                            <form method="POST" action="{{ route('admin.ads.approve', $ad) }}">
                                @csrf
                                <x-admin.button variant="success" icon="check" block>اعتماد الإعلان</x-admin.button>
                            </form>
                        @endif

                        @if ($moderation['reject'])
                            <form method="POST" action="{{ route('admin.ads.reject', $ad) }}" class="grid gap-2">
                                @csrf
                                <x-admin.textarea name="admin_notes" label="سبب الرفض" label-hidden :value="old('admin_notes')" rows="3" required placeholder="سبب الرفض (يظهر للبائع)…" />
                                <x-admin.button variant="danger" icon="x-circle" block>رفض الإعلان</x-admin.button>
                            </form>
                        @endif

                        @if ($moderation['suspend'])
                            <form method="POST" action="{{ route('admin.ads.suspend', $ad) }}">
                                @csrf
                                <x-admin.button variant="danger" icon="ban" block>إيقاف الإعلان</x-admin.button>
                            </form>
                        @endif

                        @if ($moderation['unsuspend'])
                            <form method="POST" action="{{ route('admin.ads.unsuspend', $ad) }}">
                                @csrf
                                <x-admin.button variant="success" icon="check" block>رفع الإيقاف</x-admin.button>
                            </form>
                        @endif

                        @if ($moderation['feature'])
                            <form method="POST" action="{{ route('admin.ads.feature', $ad) }}">
                                @csrf
                                <x-admin.button variant="secondary" block>
                                    <x-admin.icon name="star" class="size-[18px] {{ $ad->featured ? 'text-coral-600' : '' }}" />
                                    {{ $ad->featured ? 'إلغاء التمييز' : 'تمييز الإعلان' }}
                                </x-admin.button>
                            </form>
                        @endif

                        @if ($moderation['expire'])
                            <form method="POST" action="{{ route('admin.ads.force-expire', $ad) }}" data-confirm="إنهاء صلاحية هذا الإعلان؟">
                                @csrf
                                <x-admin.button variant="secondary" icon="clock" block>إنهاء الصلاحية</x-admin.button>
                            </form>
                        @endif
                    </div>
                </x-admin.side-card>
            @endif

            <x-admin.side-card id="ad-seller" title="البائع" icon="users"
                               :link="$seller && $staff->can('users.view') ? ['فتح الملف', route('admin.users.show', $seller)] : null">
                @if ($seller)
                    <div class="flex items-center gap-3">
                        <x-admin.user-avatar :user="$seller" size="md" decorative />
                        <div class="min-w-0">
                            <p class="truncate font-bold text-ink-900" title="{{ $seller->full_name }}">{{ $seller->full_name ?: $seller->email }}</p>
                            @if ($seller->phone)
                                <x-admin.phone :phone="$seller->phone" class="text-[13px] text-ink-500" />
                            @endif
                        </div>
                    </div>
                    <x-admin.meta-list class="mt-4">
                        <x-admin.meta-item label="البريد" ltr>{{ $seller->email }}</x-admin.meta-item>
                        <x-admin.meta-item label="حالة الحساب"><x-admin.badge :status="$seller->status" /></x-admin.meta-item>
                        <x-admin.meta-item label="عضو منذ" ltr>{{ $seller->created_at->format('Y-m-d') }}</x-admin.meta-item>
                    </x-admin.meta-list>
                @else
                    <p class="text-ink-500">حساب البائع محذوف.</p>
                @endif
            </x-admin.side-card>

            @include('admin.ads.partials.deal-buyer')

            <x-admin.side-card id="ad-stats" title="الإحصاءات" icon="trend">
                <dl class="grid grid-cols-3 gap-2 text-center">
                    @foreach ([['مشاهدة', $ad->views_count], ['مفضلة', $ad->favorites_count], ['محادثة', $ad->conversations_count]] as [$label, $count])
                        {{-- The figure reads first on screen; the term still precedes it for screen readers. --}}
                        <div class="flex flex-col-reverse rounded-xl bg-cream-50 px-2 py-3">
                            <dt class="text-xs text-ink-500">{{ $label }}</dt>
                            <dd class="text-lg font-extrabold text-ink-900 tabular-nums">{{ number_format((int) $count) }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-admin.side-card>

            @can('reports.view')
                <x-admin.side-card id="ad-reports" title="البلاغات على الإعلان" icon="flag"
                                   :link="$reports?->hasMore() ? ['عرض الكل (' . number_format($reports->total) . ')', route('admin.reports.index', ['q' => $ad->id])] : null">
                    @if ($reports === null)
                        <x-admin.error-state :retry-url="request()->fullUrl()" />
                    @elseif ($reports->latest->isEmpty())
                        <x-admin.empty-state compact icon="flag" message="لا توجد بلاغات على هذا الإعلان." />
                    @else
                        <ul role="list" class="grid gap-3 text-[13px]">
                            @foreach ($reports->latest as $report)
                                <li class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.reports.show', $report) }}" class="font-bold text-ink-900 hover:text-coral-700">{{ $report->category->label()['ar'] }}</a>
                                        <p class="text-ink-500"><time datetime="{{ $report->created_at->toIso8601String() }}" title="{{ $report->created_at->format('Y-m-d H:i') }}">{{ $report->created_at->diffForHumans() }}</time></p>
                                    </div>
                                    <x-admin.badge :status="$report->status" />
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($openReports > 0)
                        <x-slot:footer>
                            <span class="font-semibold text-amber-800">{{ $openReports }} بانتظار المراجعة</span>
                        </x-slot:footer>
                    @endif
                </x-admin.side-card>
            @endcan

            <x-admin.side-card id="ad-promotions" title="الترويج" icon="banknotes">
                @if ($promotions === null)
                    <x-admin.error-state :retry-url="request()->fullUrl()" />
                @elseif ($promotions->isEmpty())
                    <x-admin.empty-state compact icon="banknotes" message="لم يُروَّج هذا الإعلان." />
                @else
                    <ul role="list" class="grid gap-3 text-[13px]">
                        @foreach ($promotions as $promotion)
                            <li class="grid gap-1 not-first:border-t not-first:border-ink-200 not-first:pt-3">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-bold text-ink-900">{{ __("admin.promotion_transfers.types.{$promotion->type->value}") }} ({{ $promotion->duration_days }} يوم)</span>
                                    <x-admin.badge :status="$promotion->status" />
                                </div>
                                <div class="flex flex-wrap items-center justify-between gap-x-3 text-ink-500">
                                    <span dir="ltr">{{ $promotion->price }} {{ $promotion->currency }}</span>
                                    @if ($promotion->starts_at)
                                        <span dir="ltr">{{ $promotion->starts_at->format('Y-m-d') }} → {{ $promotion->ends_at?->format('Y-m-d') ?? '…' }}</span>
                                    @else
                                        <span dir="ltr">{{ $promotion->created_at->format('Y-m-d') }}</span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    @if ($ad->promotions_count > $promotions->count())
                        <x-slot:footer>
                            <span class="text-ink-500">آخر {{ $promotions->count() }} من {{ number_format($ad->promotions_count) }}</span>
                        </x-slot:footer>
                    @endif
                @endif
            </x-admin.side-card>

            <x-admin.side-card id="ad-history" title="سجل الحالة" icon="activity">
                @if ($history === null)
                    <x-admin.error-state :retry-url="request()->fullUrl()" />
                @else
                    <x-admin.timeline :entries="$history" empty="لا يوجد تغيير مسجّل على حالة الإعلان." />
                @endif
            </x-admin.side-card>
        </x-slot:side>
    </x-admin.page>
@endsection
