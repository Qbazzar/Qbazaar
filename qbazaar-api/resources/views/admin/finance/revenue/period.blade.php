{{-- Period presets as links, plus a custom range as a small GET form. --}}
<div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-4 rounded-2xl border border-ink-200 bg-cream-100 p-3">
    <nav aria-label="{{ __('admin.revenue.period_label') }}" class="flex flex-wrap gap-1">
        @foreach (\App\Services\Finance\Revenue\RevenueRange::cases() as $option)
            @continue($option === \App\Services\Finance\Revenue\RevenueRange::CUSTOM)
            <a href="{{ route('admin.finance.revenue.index', ['period' => $option->value]) }}"
               @if ($option === $range) aria-current="true" @endif
               @class([
                   'rounded-lg px-3 py-1.5 text-sm font-semibold transition',
                   'bg-coral-soft text-coral-700' => $option === $range,
                   'text-ink-700 hover:bg-cream-200' => $option !== $range,
               ])>{{ $option->label() }}</a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('admin.finance.revenue.index') }}" class="flex flex-wrap items-end gap-2" aria-label="{{ __('admin.revenue.periods.custom') }}">
        <input type="hidden" name="period" value="custom">
        <x-admin.input type="date" name="from" :label="__('admin.revenue.custom.from')" :value="request('from', $revenue->window()->start->toDateString())" required class="w-40" />
        <x-admin.input type="date" name="to" :label="__('admin.revenue.custom.to')" :value="request('to', $revenue->window()->end->subSecond()->toDateString())" required class="w-40" />
        <x-admin.button variant="{{ $range === \App\Services\Finance\Revenue\RevenueRange::CUSTOM ? 'primary' : 'secondary' }}">{{ __('admin.revenue.custom.apply') }}</x-admin.button>
    </form>
</div>
