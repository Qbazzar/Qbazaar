@php
    $pageLink = 'inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-ink-200 bg-cream-100 px-3 text-sm font-semibold text-ink-700 transition hover:border-coral-600 hover:text-coral-700';
    $pageDisabled = 'inline-flex h-9 min-w-9 cursor-not-allowed items-center justify-center rounded-lg border border-ink-200 bg-cream-200 px-3 text-sm font-semibold text-ink-500';
@endphp

<nav aria-label="التنقل بين الصفحات" class="flex flex-col items-center gap-3 sm:flex-row sm:justify-between">
    <p class="text-sm text-ink-500">
        @if ($paginator->firstItem())
            عرض <span class="font-semibold text-ink-700">{{ $paginator->firstItem() }}</span>–<span class="font-semibold text-ink-700">{{ $paginator->lastItem() }}</span>
            من <span class="font-semibold text-ink-700">{{ $paginator->total() }}</span>
        @else
            لا توجد نتائج
        @endif
    </p>

    <ul class="flex flex-wrap items-center justify-center gap-1">
        <li>
            @if ($paginator->onFirstPage())
                <span class="{{ $pageDisabled }}" aria-disabled="true">
                    <x-admin.icon name="chevron-right" class="size-4 ltr:rotate-180" /><span class="sr-only">الصفحة السابقة</span>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $pageLink }}">
                    <x-admin.icon name="chevron-right" class="size-4 ltr:rotate-180" /><span class="sr-only">الصفحة السابقة</span>
                </a>
            @endif
        </li>

        @foreach ($elements as $element)
            @if (is_string($element))
                <li class="max-sm:hidden"><span class="inline-flex h-9 min-w-9 items-center justify-center text-sm text-ink-500" aria-hidden="true">{{ $element }}</span></li>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    <li @class(['max-sm:hidden' => $page !== $paginator->currentPage()])>
                        @if ($page === $paginator->currentPage())
                            <span aria-current="page" class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg bg-coral-600 px-3 text-sm font-bold text-white">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="{{ $pageLink }}" aria-label="الصفحة {{ $page }}">{{ $page }}</a>
                        @endif
                    </li>
                @endforeach
            @endif
        @endforeach

        <li>
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $pageLink }}">
                    <x-admin.icon name="chevron-left" class="size-4 ltr:rotate-180" /><span class="sr-only">الصفحة التالية</span>
                </a>
            @else
                <span class="{{ $pageDisabled }}" aria-disabled="true">
                    <x-admin.icon name="chevron-left" class="size-4 ltr:rotate-180" /><span class="sr-only">الصفحة التالية</span>
                </span>
            @endif
        </li>
    </ul>
</nav>
