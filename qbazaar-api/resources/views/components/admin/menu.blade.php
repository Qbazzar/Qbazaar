@props(['label' => null, 'icon' => 'dots'])

{{-- A disclosure (not an ARIA menu): items stay in the Tab order, Escape and outside clicks close it. --}}
<details data-qb-menu {{ $attributes->class('relative') }}>
    <summary title="{{ $label ?? __('admin.shell.more_actions') }}"
             class="flex size-10 cursor-pointer list-none items-center justify-center rounded-xl border border-ink-200 bg-cream-100 text-ink-700 transition select-none hover:bg-cream-200 [&::-webkit-details-marker]:hidden">
        <x-admin.icon :name="$icon" class="size-5" />
        <span class="sr-only">{{ $label ?? __('admin.shell.more_actions') }}</span>
    </summary>
    <div class="absolute end-0 top-full z-30 mt-1.5 grid w-max max-w-[calc(100vw-2rem)] min-w-56 gap-0.5 rounded-2xl border border-ink-200 bg-cream-100 p-1.5 shadow-[0_12px_28px_-10px_rgb(0_0_0/0.2)]">
        {{ $slot }}
    </div>
</details>

@pushOnce('admin-scripts')
    <script>
        (function () {
            function close(menu, restoreFocus) {
                menu.removeAttribute('open');
                if (restoreFocus) menu.querySelector('summary').focus();
            }
            function openMenus() {
                return document.querySelectorAll('details[data-qb-menu][open]');
            }

            document.addEventListener('click', function (event) {
                openMenus().forEach(function (menu) { if (!menu.contains(event.target)) close(menu, false); });
            });
            document.addEventListener('focusin', function (event) {
                openMenus().forEach(function (menu) { if (!menu.contains(event.target)) close(menu, false); });
            });
            document.addEventListener('keydown', function (event) {
                var menu = event.target.closest && event.target.closest('details[data-qb-menu][open]');
                if (!menu) return;
                if (event.key === 'Escape') {
                    event.preventDefault();
                    close(menu, true);
                    return;
                }
                if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
                var items = Array.prototype.slice.call(menu.querySelectorAll('[data-qb-menu-item]:not([disabled])'));
                if (items.length === 0) return;
                event.preventDefault();
                var step = event.key === 'ArrowDown' ? 1 : -1;
                var index = items.indexOf(document.activeElement);
                items[index === -1 ? (step > 0 ? 0 : items.length - 1) : (index + step + items.length) % items.length].focus();
            });
        })();
    </script>
@endPushOnce
