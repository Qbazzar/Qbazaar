@props(['tabs', 'id' => 'tabs', 'label' => null])

{{-- Without JS the tablist stays hidden and every panel shows stacked; the
     script below reveals it and shows one panel at a time. --}}
<div data-tabs="{{ $id }}" {{ $attributes }}>
    <div role="tablist" aria-label="{{ $label }}" hidden
         class="-mx-4 mb-5 flex overflow-x-auto border-b border-ink-200 px-4 sm:mx-0 sm:px-0">
        @foreach ($tabs as $key => $tab)
            <button type="button" role="tab" id="{{ $id }}-tab-{{ $key }}" data-tab="{{ $key }}"
                    aria-controls="{{ $id }}-panel-{{ $key }}" aria-selected="false" tabindex="-1" @if ($tab['error'] ?? false) data-invalid @endif
                    class="-mb-px flex shrink-0 items-center gap-2 border-b-2 border-transparent px-4 py-2.5 text-sm font-semibold whitespace-nowrap text-ink-500 transition hover:text-ink-900 aria-selected:border-coral-600 aria-selected:text-coral-700">
                {{ $tab['label'] }}
                @if ($tab['error'] ?? false)
                    <span class="size-2 rounded-full bg-red-600" aria-hidden="true"></span>
                    <span class="sr-only">{{ __('admin.settings.has_errors') }}</span>
                @endif
            </button>
        @endforeach
    </div>

    {{ $slot }}
</div>

@once
    <script>
        (function () {
            function init(root) {
                var list = root.querySelector('[role="tablist"]');
                var tabs = Array.prototype.slice.call(list.querySelectorAll('[role="tab"]'));
                var panels = tabs.map(function (tab) { return document.getElementById(tab.getAttribute('aria-controls')); });

                var form = root.closest('form');
                var tabField = form && form.querySelector('input[name="_tab"]');

                function select(index, focus, updateUrl) {
                    if (tabField) tabField.value = tabs[index].dataset.tab;
                    tabs.forEach(function (tab, i) {
                        var on = i === index;
                        tab.setAttribute('aria-selected', String(on));
                        tab.tabIndex = on ? 0 : -1;
                        panels[i].hidden = !on;
                    });
                    if (focus) tabs[index].focus();
                    if (updateUrl) history.replaceState(null, '', '#' + tabs[index].dataset.tab);
                    tabs[index].scrollIntoView({ block: 'nearest', inline: 'nearest' });
                }

                function indexOfHash() {
                    return tabs.findIndex(function (tab) { return '#' + tab.dataset.tab === location.hash; });
                }

                function initial() {
                    var firstError = tabs.findIndex(function (tab) { return tab.dataset.invalid !== undefined; });
                    if (firstError >= 0) return firstError;
                    return Math.max(indexOfHash(), 0);
                }

                list.hidden = false;
                select(initial(), false, false);

                tabs.forEach(function (tab, i) {
                    tab.addEventListener('click', function () { select(i, false, true); });
                });

                list.addEventListener('keydown', function (event) {
                    // Leave Alt+Arrow (browser back/forward) and other shortcuts alone.
                    if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
                    var rtl = getComputedStyle(list).direction === 'rtl';
                    var current = tabs.indexOf(document.activeElement);
                    var step = { ArrowRight: rtl ? -1 : 1, ArrowLeft: rtl ? 1 : -1 }[event.key];
                    var next = current;
                    if (step) next = (current + step + tabs.length) % tabs.length;
                    else if (event.key === 'Home') next = 0;
                    else if (event.key === 'End') next = tabs.length - 1;
                    else return;
                    event.preventDefault();
                    select(next, true, true);
                });

                window.addEventListener('hashchange', function () {
                    var index = indexOfHash();
                    if (index >= 0) select(index, false, false);
                });

                // A native "required" failure inside a hidden panel would
                // otherwise block the submit silently.
                // Invalid events fire for every bad control in tree order; only the
                // first one per submit attempt may switch the tab.
                var handled = false;
                if (form) form.addEventListener('invalid', function (event) {
                    if (handled) return;
                    handled = true;
                    setTimeout(function () { handled = false; }, 0);
                    var index = panels.findIndex(function (panel) { return panel.contains(event.target); });
                    if (index >= 0 && panels[index].hidden) {
                        select(index, false, true);
                        event.target.focus();
                    }
                }, true);
            }
            document.querySelectorAll('[data-tabs]').forEach(init);
        })();
    </script>
@endonce
