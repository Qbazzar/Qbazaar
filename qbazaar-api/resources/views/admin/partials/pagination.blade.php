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
        <li><x-admin.pagination.previous :paginator="$paginator" /></li>

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
                            <x-admin.pagination.link :url="$url" aria-label="الصفحة {{ $page }}">{{ $page }}</x-admin.pagination.link>
                        @endif
                    </li>
                @endforeach
            @endif
        @endforeach

        <li><x-admin.pagination.next :paginator="$paginator" /></li>
    </ul>
</nav>
