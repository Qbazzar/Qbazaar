@props(['paginator'])

<x-admin.pagination.link :url="$paginator->hasMorePages() ? $paginator->nextPageUrl() : null" :rel="$paginator->hasMorePages() ? 'next' : null">
    <x-admin.icon name="chevron-left" class="size-4 ltr:rotate-180" /><span class="sr-only">الصفحة التالية</span>
</x-admin.pagination.link>
