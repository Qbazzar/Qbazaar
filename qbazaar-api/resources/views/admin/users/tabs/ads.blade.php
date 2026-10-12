<section aria-labelledby="user-ads-title" class="grid gap-4">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="user-ads-title" class="text-[15px] font-bold text-ink-900">إعلانات المستخدم</h2>
        <p class="text-[13px] text-ink-500">{{ number_format($ads->total()) }} إعلان، الأحدث أولاً</p>
    </div>

    <x-admin.table
        caption="إعلانات المستخدم"
        :columns="['الإعلان', 'السعر', 'الحالة', ['label' => 'التاريخ', 'secondary' => true], ['label' => 'إجراءات', 'srOnly' => true]]"
        :empty="$ads->isEmpty()"
        empty-icon="tag"
        empty-message="لا توجد إعلانات لهذا المستخدم."
    >
        @foreach ($ads as $ad)
            <x-admin.table.row>
                <x-admin.table.cell primary>
                    <div class="flex min-w-0 items-center gap-3">
                        <x-admin.ad-thumb :ad="$ad" />
                        <a href="{{ route('admin.ads.show', $ad) }}" title="{{ $ad->title }}" class="block min-w-0 max-w-xs truncate font-semibold hover:text-coral-700">{{ $ad->title }}</a>
                    </div>
                </x-admin.table.cell>
                <x-admin.table.cell label="السعر" class="text-ink-700">
                    @if ($ad->price !== null)
                        <span dir="ltr">{{ $ad->price }} {{ $ad->currency }}</span>
                    @else
                        —
                    @endif
                </x-admin.table.cell>
                <x-admin.table.cell label="الحالة"><x-admin.badge :status="$ad->status" /></x-admin.table.cell>
                <x-admin.table.cell label="التاريخ" secondary class="text-ink-500"><span dir="ltr">{{ optional($ad->created_at)->format('Y-m-d') }}</span></x-admin.table.cell>
                <x-admin.table.actions>
                    <x-admin.icon-button :href="route('admin.ads.show', $ad)" icon="eye" label="عرض" />
                </x-admin.table.actions>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$ads" class="!mt-0" />
</section>
