@props(['route', 'statuses', 'active', 'label' => 'تصفية حسب الحالة'])

<nav {{ $attributes->class('flex flex-wrap gap-2') }} aria-label="{{ $label }}">
    @foreach ($statuses as $case)
        <x-admin.button
            :href="route($route, ['status' => $case->value])"
            :variant="$active === $case ? 'primary' : 'secondary'"
            size="sm"
            :aria-current="$active === $case ? 'page' : null"
        >{{ $case->label()['ar'] }}</x-admin.button>
    @endforeach
</nav>
