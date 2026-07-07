{{--
    Reusable bulk-action bar. Row checkboxes elsewhere on the page associate with
    this form via the HTML5 `form="qb-bulk-form"` attribute, so they can live
    inside table cells (even alongside per-row forms) without illegal nesting.

    Params: $action (POST url), $label, $confirm, optional $icon (default trash),
    $tone (default 'danger'). Bulk actions are plain POST endpoints (no verb
    spoofing) so they never collide with the RESTful `{id}` DELETE routes.
--}}
@php($tone = $tone ?? 'danger')
@php($btn = $tone === 'danger' ? 'bg-red-600' : 'bg-coral')
<form id="qb-bulk-form" method="POST" action="{{ $action }}"
      onsubmit="return confirm('{{ $confirm }}')"
      class="mb-4 hidden items-center justify-between gap-3 rounded-xl border border-coral bg-coral-soft px-4 py-2.5">
    @csrf
    <span class="text-sm font-semibold text-ink-700">العناصر المحددة: <span id="qb-bulk-count">0</span></span>
    <button type="submit" class="inline-flex items-center gap-2 rounded-lg {{ $btn }} px-4 py-2 text-sm font-bold text-white transition hover:brightness-95">
        <x-admin.icon :name="$icon ?? 'trash'" class="size-[18px]" /> {{ $label }}
    </button>
</form>
