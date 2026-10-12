{{-- Its forms sit outside the settings form (HTML forbids nesting); controls join them with the form attribute. --}}
<x-admin.card :title="__('admin.commission_rates.title')" :description="__('admin.commission_rates.intro')" class="mt-6">
    @if ($commissionRates->isEmpty())
        <p class="mb-4 text-sm text-ink-500">{{ __('admin.commission_rates.empty') }}</p>
    @else
        <ul class="mb-5 divide-y divide-ink-200">
            @foreach ($commissionRates as $override)
                <li class="flex items-center justify-between gap-3 py-2 text-sm">
                    <span class="min-w-0 break-words">{{ $override->category->getLocalizedName(app()->getLocale()) }}</span>
                    <span class="flex shrink-0 items-center gap-3">
                        <span class="font-semibold" dir="ltr">{{ $override->rate }}%</span>
                        <x-admin.button variant="danger-ghost" size="sm" form="commission-rate-remove-{{ $override->category_id }}">{{ __('admin.commission_rates.remove') }}</x-admin.button>
                    </span>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="flex flex-wrap items-end gap-3">
        <x-admin.select name="category_id" id="commission_category_id" form="commission-rate-form" :label="__('admin.commission_rates.category')" required class="min-w-48 flex-1">
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(old('category_id') === $category->id)>{{ $category->getLocalizedName(app()->getLocale()) }}</option>
            @endforeach
        </x-admin.select>
        <x-admin.input name="rate" id="commission_rate_value" form="commission-rate-form" type="number" :label="__('admin.commission_rates.rate')" :value="old('rate')"
                       required step="0.01" min="0" max="100" class="w-32" />
        <x-admin.button icon="plus" form="commission-rate-form">{{ __('admin.commission_rates.add') }}</x-admin.button>
    </div>
</x-admin.card>
