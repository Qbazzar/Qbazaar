{{--
    Row checkboxes (<x-admin.table.select>) join this form through the HTML5
    form="qb-bulk-form" attribute, so they can sit in table cells next to
    per-row forms without illegal nesting. Bulk endpoints are plain POSTs so
    they never collide with the RESTful {id} DELETE routes.

    The default slot adds fields under the bar (a sanction reason); the
    `buttons` slot replaces the single button, e.g. approve and reject posting
    to two routes with formaction.
--}}
@props(['action', 'label' => null, 'confirm', 'icon' => 'trash', 'variant' => 'danger'])

@php($selectionError = $errors->first('ids') ?: $errors->first('ids.*'))
@php($fieldsHaveErrors = $slot->isNotEmpty() && ($errors->has('reason') || $errors->has('reason_note')))

<form id="qb-bulk-form" method="POST" action="{{ $action }}" data-confirm="{{ $confirm }}"
      @if ($variant !== 'danger') data-confirm-tone="primary" @endif
      @unless ($selectionError || $fieldsHaveErrors) hidden @endunless
      class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-coral bg-coral-soft px-4 py-2.5">
    @csrf
    <span class="text-sm font-semibold text-ink-700" aria-live="polite">العناصر المحددة: <span data-bulk-count>0</span></span>
    @isset($buttons)
        <div class="flex flex-wrap items-center gap-2">{{ $buttons }}</div>
    @else
        <x-admin.button :variant="$variant" :icon="$icon">{{ $label }}</x-admin.button>
    @endisset
    @if ($slot->isNotEmpty())
        <div class="w-full border-t border-coral/30 pt-3">{{ $slot }}</div>
    @endif
    @if ($selectionError)
        <p class="w-full text-xs font-semibold text-red-700" role="alert">{{ $selectionError }}</p>
    @endif
</form>

@once
    <script>
        (function () {
            var form = document.getElementById('qb-bulk-form');
            if (!form) return;

            function items() { return document.querySelectorAll('[data-bulk-item]'); }

            function sync() {
                var checked = document.querySelectorAll('[data-bulk-item]:checked').length;
                form.querySelector('[data-bulk-count]').textContent = checked;
                // A bar reopened by a validation error stays open until the staff member re-selects.
                form.hidden = checked === 0 && !form.hasAttribute('data-keep-open');
                var all = document.querySelector('[data-bulk-all]');
                if (all) {
                    all.checked = checked > 0 && checked === items().length;
                    all.indeterminate = checked > 0 && checked < items().length;
                }
            }

            if (!form.hidden) form.setAttribute('data-keep-open', '');

            document.addEventListener('change', function (event) {
                if (event.target.matches('[data-bulk-all]')) {
                    items().forEach(function (item) { item.checked = event.target.checked; });
                }
                if (event.target.matches('[data-bulk-all], [data-bulk-item]')) {
                    form.removeAttribute('data-keep-open');
                    sync();
                }
            });

            // Browsers restore checkbox state on back navigation without firing change.
            window.addEventListener('pageshow', sync);
        })();
    </script>
@endonce
