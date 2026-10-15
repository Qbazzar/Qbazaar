{{-- The income statement. A line the ledger does not record yet says so instead of showing zero. --}}
<x-admin.card :title="__('admin.revenue.statement.title')" :description="__('admin.revenue.statement.basis')" icon="banknotes" flush>
    <div class="overflow-x-auto">
        <table class="w-full text-start text-sm">
            <caption class="sr-only">{{ __('admin.revenue.statement.title') }}</caption>
            <thead class="border-b border-ink-200 bg-cream-50 text-xs font-semibold text-ink-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-start">{{ __('admin.revenue.statement.line') }}</th>
                    <th scope="col" class="px-4 py-3 text-start">{{ __('admin.revenue.statement.current') }}</th>
                    <th scope="col" class="px-4 py-3 text-start max-sm:hidden">{{ __('admin.revenue.statement.previous') }}</th>
                    <th scope="col" class="px-4 py-3 text-start max-sm:hidden">{{ __('admin.revenue.statement.change') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($revenue->statement() as $row)
                    @if ($row['kind'] === 'heading')
                        <tr class="bg-cream-50"><th scope="colgroup" colspan="4" class="px-4 py-2 text-start text-xs font-bold text-ink-500">{{ $row['label'] }}</th></tr>
                        @continue
                    @endif
                    <tr @class(['border-t border-ink-200', 'font-bold bg-cream-50' => $row['kind'] !== 'line', 'text-base' => $row['kind'] === 'total'])>
                        <th scope="row" class="px-4 py-3 text-start font-[inherit]">
                            {{ $row['label'] }}
                            @if ($row['note'])
                                <span class="mt-0.5 block text-xs font-normal text-ink-500">{{ $row['note'] }}</span>
                            @endif
                        </th>
                        <td class="px-4 py-3 text-start">
                            @if ($row['value'] === null)
                                <span class="rounded-full bg-cream-200 px-2 py-0.5 text-xs font-semibold text-ink-700">{{ __('admin.revenue.not_recorded') }}</span>
                            @else
                                <span dir="ltr">{{ $row['value'] }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-start text-ink-500 max-sm:hidden"><span dir="ltr">{{ $row['previous'] ?? '—' }}</span></td>
                        <td class="px-4 py-3 text-start max-sm:hidden">
                            @if ($row['change'] === false)
                                —
                            @elseif ($row['change'] === null)
                                <span class="text-xs text-ink-500">{{ __('admin.home.no_change') }}</span>
                            @else
                                <span dir="ltr">{{ $row['change'] > 0 ? '+' : '' }}{{ $row['change'] }}%</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-admin.card>
