@props(['paginator'])

<x-admin.pagination.link :url="$paginator->onFirstPage() ? null : $paginator->previousPageUrl()" :rel="$paginator->onFirstPage() ? null : 'prev'">
    <x-admin.icon name="chevron-right" class="size-4 ltr:rotate-180" /><span class="sr-only">الصفحة السابقة</span>
</x-admin.pagination.link>
