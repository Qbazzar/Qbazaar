{{-- System health (settings.manage only): one row of status pills. --}}
@isset($stats['system'])
    @php
        $system = $stats['system'];
        $pillTone = [
            'success' => ['pill' => 'border-success-200 bg-success-50 text-success-800', 'dot' => 'bg-success-600'],
            'warning' => ['pill' => 'border-warning-200 bg-warning-50 text-warning-900', 'dot' => 'bg-warning-500'],
            'danger' => ['pill' => 'border-danger-200 bg-danger-50 text-danger-800', 'dot' => 'bg-danger-600'],
            'neutral' => ['pill' => 'border-ink-200 bg-cream-200 text-ink-700', 'dot' => 'bg-ink-300'],
        ];
        $statusTone = ['ok' => 'success', 'unavailable' => 'danger', 'stale' => 'danger', 'disabled' => 'neutral', 'unknown' => 'warning'];
        $queueDetail = collect($system['queues']['sizes'])->map(fn (int $size, string $queue): string => $queue . ' ' . number_format($size))->implode(' · ');
        $pills = [
            ['label' => __('admin.home.system.queues'), 'tone' => $statusTone[$system['queues']['status']], 'status' => __('admin.home.system.status.' . $system['queues']['status']), 'detail' => $queueDetail],
            ['label' => __('admin.home.system.failed_jobs'), 'tone' => $system['failed_jobs'] > 0 ? 'danger' : 'success', 'status' => number_format($system['failed_jobs']), 'detail' => null],
            ['label' => __('admin.home.system.search'), 'tone' => $statusTone[$system['search']['status']], 'status' => __('admin.home.system.status.' . $system['search']['status']),
                'detail' => $system['search']['documents'] !== null ? __('admin.home.system.documents', ['count' => number_format($system['search']['documents'])]) : null],
            ['label' => __('admin.home.system.scheduler'), 'tone' => $statusTone[$system['scheduler']['status']], 'status' => __('admin.home.system.status.' . $system['scheduler']['status']),
                'detail' => $system['scheduler']['last_run'] ? __('admin.home.system.scheduler_last', ['time' => $ago($system['scheduler']['last_run'])]) : null],
            ['label' => __('admin.home.system.storage'), 'tone' => $statusTone[$system['storage']['status']], 'status' => __('admin.home.system.status.' . $system['storage']['status']), 'detail' => $system['storage']['disk']],
        ];
    @endphp

    <section aria-labelledby="dashboard-health" class="flex flex-wrap items-center gap-x-4 gap-y-3 rounded-2xl border border-ink-200 bg-cream-100 px-4 py-3 sm:px-6">
        <h2 id="dashboard-health" class="flex items-center gap-2 text-sm font-bold text-ink-700">
            <x-admin.icon name="shield" class="size-[18px] text-coral-600" /> {{ __('admin.home.system.title') }}
        </h2>
        <ul class="flex flex-1 flex-wrap gap-2">
            @foreach ($pills as $pill)
                <li class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-sm {{ $pillTone[$pill['tone']]['pill'] }}" @if ($pill['detail']) title="{{ $pill['detail'] }}" @endif>
                    <span aria-hidden="true" class="size-2 rounded-full {{ $pillTone[$pill['tone']]['dot'] }}"></span>
                    <span class="font-semibold">{{ $pill['label'] }}</span>
                    <span class="text-xs" dir="auto">{{ $pill['status'] }}</span>
                    @if ($pill['detail'])<span class="sr-only">{{ $pill['detail'] }}</span>@endif
                </li>
            @endforeach
        </ul>
        <p class="text-xs text-ink-500">{{ __('admin.home.system.checked') }}: {{ $ago($system['checked_at']) }}</p>
    </section>
@endisset
