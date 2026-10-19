{{--
    A sanction behind one button: the button opens the reason fields in place
    (no script needed), and submitting asks for confirmation. After a failed
    submit only the form that was sent reopens with what was typed, because it
    posts its own `prefix` back as `_sanction_form`. Extra fields (the report
    page's choice of action) go in the slot, above the reason.
--}}
@props([
    'action',
    'label',
    'confirm',
    'prefix',
    'method' => 'POST',
    'icon' => 'ban',
    'submit' => null,
    'intro' => null,
])

<details {{ $attributes->class('group rounded-xl border border-ink-200 bg-cream-50 open:bg-cream-100') }} @if (old('_sanction_form') === $prefix) open @endif>
    <summary class="flex cursor-pointer list-none items-center justify-center gap-2 rounded-xl bg-danger-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-danger-700 group-open:rounded-b-none [&::-webkit-details-marker]:hidden">
        <x-admin.icon :name="$icon" class="size-[18px] shrink-0" />
        {{ $label }}
    </summary>

    <form method="POST" action="{{ $action }}" data-confirm="{{ $confirm }}" class="grid gap-3 p-3">
        @csrf
        @if (strtoupper($method) !== 'POST')
            @method($method)
        @endif
        <input type="hidden" name="_sanction_form" value="{{ $prefix }}">

        @if ($intro)
            <p class="text-xs leading-relaxed text-ink-500">{{ $intro }}</p>
        @endif

        {{ $slot }}

        <x-admin.sanction-fields :prefix="$prefix" :restore="old('_sanction_form') === $prefix" />

        <x-admin.button variant="danger" :icon="$icon" block>{{ $submit ?? $label }}</x-admin.button>
    </form>
</details>
