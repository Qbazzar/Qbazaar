{{--
    The header bell: the signed-in staff member's unread count, linking to
    their inbox. It refreshes by polling the cached summary while the tab is
    visible (the panel has no realtime client), backing off when requests fail.
--}}
@props(['unread' => 0, 'href', 'summaryUrl', 'pollSeconds' => 60])

<a href="{{ $href }}" data-staff-bell data-summary-url="{{ $summaryUrl }}" data-poll-seconds="{{ $pollSeconds }}"
   title="{{ __('admin.inbox.bell') }}"
   class="relative flex size-9 items-center justify-center rounded-lg text-ink-500 transition hover:bg-cream-200 hover:text-ink-900">
    <x-admin.icon name="bell" class="size-5" />
    <span class="sr-only">{{ __('admin.inbox.bell') }}</span>
    <span data-staff-bell-badge @class([
        'absolute -end-1 -top-1 min-w-[18px] rounded-full bg-coral-600 px-1 text-center text-[10px] leading-[18px] font-bold text-white tabular-nums',
        'hidden' => $unread === 0,
    ])>
        <span aria-hidden="true" data-staff-bell-number>{{ $unread > 99 ? '99+' : $unread }}</span>
        <span class="sr-only" data-staff-bell-label data-template="{{ __('admin.inbox.unread_count', ['count' => '__COUNT__']) }}">{{ __('admin.inbox.unread_count', ['count' => $unread]) }}</span>
    </span>
</a>

@once
    @push('admin-scripts')
        <script>
            (function () {
                var bell = document.querySelector('[data-staff-bell]');
                if (!bell || !window.fetch) return;

                var badge = bell.querySelector('[data-staff-bell-badge]');
                var number = bell.querySelector('[data-staff-bell-number]');
                var label = bell.querySelector('[data-staff-bell-label]');
                var every = Math.max(15, parseInt(bell.dataset.pollSeconds, 10) || 60) * 1000;
                var failures = 0;

                function render(count) {
                    badge.classList.toggle('hidden', count === 0);
                    number.textContent = count > 99 ? '99+' : String(count);
                    label.textContent = label.dataset.template.replace('__COUNT__', String(count));
                }

                function schedule() {
                    // Five failures in a row (signed out, server down) stop the polling until the next page load.
                    if (failures < 5) setTimeout(poll, every * Math.pow(2, failures));
                }

                function poll() {
                    if (document.visibilityState !== 'visible') { schedule(); return; }

                    fetch(bell.dataset.summaryUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                        .then(function (response) {
                            if (!response.ok) throw new Error(String(response.status));
                            return response.json();
                        })
                        .then(function (data) { failures = 0; render(Math.max(0, parseInt(data.unread, 10) || 0)); })
                        .catch(function () { failures++; })
                        .then(schedule);
                }

                schedule();
            })();
        </script>
    @endpush
@endonce
