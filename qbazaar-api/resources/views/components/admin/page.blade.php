@props([
    'title',
    'subtitle' => null,
    'width' => 'full',
    'crumbs' => [],
    'back' => null,
    'form' => null,
    'submit' => null,
    'cancel' => null,
    'stickySide' => true,
    'dirtyGuard' => false,
    'sideLabel' => null,
])

@php
    $widths = ['full' => 'max-w-[96rem]', 'form' => 'max-w-6xl', 'reading' => 'max-w-3xl'];

    // form: [action, method?] or ['action' => ..., 'method' => ..., 'enctype' => ..., 'id' => ...].
    // The form wraps header and grid, so the sticky Save submits it and side-column fields post too.
    $formSpec = null;
    if (is_array($form)) {
        $method = strtoupper((string) ($form['method'] ?? $form[1] ?? 'POST'));
        $formSpec = [
            'action' => $form['action'] ?? $form[0],
            'method' => $method,
            'verb' => $method === 'GET' ? 'GET' : 'POST',
            'enctype' => $form['enctype'] ?? null,
            'id' => $form['id'] ?? 'page-form',
        ];
    }

    // submit: the Save label; false hides the built-in Save/Cancel pair for pages with their own buttons.
    $submitLabel = $formSpec && $submit !== false ? ($submit ?? __('admin.shell.save')) : null;
    $hasSide = isset($side) && $side->isNotEmpty();
    $errorBag = $errors ?? null;
@endphp

{{-- Tells the layout that this page owns the h1, so its top bar shows the section as plain text. --}}
@pushOnce('admin-page-shell')
    shell
@endPushOnce

@if ($formSpec)
<form data-qb-page id="{{ $formSpec['id'] }}" method="{{ $formSpec['verb'] }}" action="{{ $formSpec['action'] }}"
      @if ($formSpec['enctype']) enctype="{{ $formSpec['enctype'] }}" @endif
      @if ($dirtyGuard) data-dirty-guard @endif
      {{ $attributes->class(['mx-auto w-full', $widths[$width] ?? $widths['full']]) }}>
    @if ($formSpec['verb'] === 'POST')
        @csrf
    @endif
    @if (! in_array($formSpec['method'], ['GET', 'POST'], true))
        @method($formSpec['method'])
    @endif
@else
<div data-qb-page {{ $attributes->class(['mx-auto w-full', $widths[$width] ?? $widths['full']]) }}>
@endif

    <x-admin.page-header :title="$title" :subtitle="$subtitle" :crumbs="$crumbs" :back="$back">
        @isset($badges)
            <x-slot:badges>{{ $badges }}</x-slot:badges>
        @endisset
        <x-slot:actions>
            {{ $actions ?? '' }}
            @if ($submitLabel && $cancel)
                <x-admin.button variant="secondary" :href="$cancel" class="max-sm:hidden">{{ __('admin.shell.cancel') }}</x-admin.button>
            @endif
            @if ($submitLabel)
                <x-admin.button icon="check" class="max-sm:hidden">{{ $submitLabel }}</x-admin.button>
            @endif
        </x-slot:actions>
        @isset($menu)
            <x-slot:menu>{{ $menu }}</x-slot:menu>
        @endisset
    </x-admin.page-header>

    @if ($formSpec && $errorBag?->any())
        <x-admin.alert tone="danger" :title="__('admin.shell.error_summary')" :summary="$errorBag" autofocus class="mb-6" />
    @endif

    @isset($lead)
        <div class="mb-6">{{ $lead }}</div>
    @endisset

    <div @class(['grid items-start gap-6', 'xl:grid-cols-[minmax(0,2fr)_minmax(20rem,1fr)]' => $hasSide])>
        <div class="grid min-w-0 gap-6">
            {{ $slot }}
        </div>

        @if ($hasSide)
            {{-- Below xl the side column follows the main one; at xl it stays in view while it fits the screen. --}}
            <aside aria-label="{{ $sideLabel ?? __('admin.shell.side_label') }}" @if ($stickySide) data-qb-side @endif
                   class="grid min-w-0 gap-4 xl:data-sticky:sticky xl:data-sticky:top-[calc(var(--qb-page-header,7.5rem)+1rem)]">
                {{ $side }}
            </aside>
        @endif
    </div>

    @if ($submitLabel)
        {{-- Phones get the pair at the end of the form as well, where the thumb finishes. --}}
        <div class="mt-6 grid gap-2 sm:hidden">
            <x-admin.button icon="check" block>{{ $submitLabel }}</x-admin.button>
            @if ($cancel)
                <x-admin.button variant="secondary" :href="$cancel" block>{{ __('admin.shell.cancel') }}</x-admin.button>
            @endif
        </div>
    @endif

@if ($formSpec)
</form>
@else
</div>
@endif

@pushOnce('admin-scripts')
    <script>
        (function () {
            var page = document.querySelector('[data-qb-page]');
            if (!page) return;
            var header = page.querySelector('[data-qb-page-header]');
            var sentinel = page.querySelector('[data-qb-header-sentinel]');
            var side = page.querySelector('[data-qb-side]');

            // The hairline and shadow appear only once the header is actually stuck.
            if (header && sentinel && 'IntersectionObserver' in window) {
                new IntersectionObserver(function (entries) {
                    header.toggleAttribute('data-stuck', !entries[0].isIntersecting);
                }).observe(sentinel);
            }

            // Anchors and focused fields land below the sticky header; the side column only
            // sticks when it fits under it, otherwise its lower cards would be unreachable.
            function measure() {
                var offset = header ? header.getBoundingClientRect().height : 0;
                page.style.setProperty('--qb-page-header', offset + 'px');
                document.documentElement.style.scrollPaddingTop = offset ? (offset + 16) + 'px' : '';
                if (side) side.toggleAttribute('data-sticky', side.offsetHeight + offset + 32 <= window.innerHeight);
            }
            measure();
            window.addEventListener('resize', measure);
            if ('ResizeObserver' in window) {
                var observer = new ResizeObserver(measure);
                if (header) observer.observe(header);
                if (side) observer.observe(side);
            }

            var summary = page.querySelector('[data-qb-autofocus]');
            if (summary) summary.focus();

            // Error links focus their field, opening the x-admin.tabs panel that holds it first.
            page.addEventListener('click', function (event) {
                var link = event.target.closest('[data-qb-field-link]');
                var field = link && document.getElementById(link.getAttribute('href').slice(1));
                if (!field) return;
                event.preventDefault();
                var panel = field.closest('[role="tabpanel"][hidden]');
                var tab = panel && document.querySelector('[role="tab"][aria-controls="' + panel.id + '"]');
                if (tab) tab.click();
                var target = field;
                while (target && target.getClientRects().length === 0) target = target.parentElement;
                if (target === field) field.focus();
                else if (target) target.scrollIntoView({ block: 'center' });
            });

            if (page.hasAttribute('data-dirty-guard')) {
                var dirty = false;
                page.addEventListener('input', function () { dirty = true; });
                window.addEventListener('submit', function (event) {
                    if (event.target === page && !event.defaultPrevented) dirty = false;
                });
                window.addEventListener('beforeunload', function (event) {
                    if (!dirty) return;
                    event.preventDefault();
                    event.returnValue = '';
                });
            }
        })();
    </script>
@endPushOnce
