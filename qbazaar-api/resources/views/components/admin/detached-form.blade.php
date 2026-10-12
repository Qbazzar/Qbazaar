@props(['id', 'action', 'method' => 'POST', 'fields' => [], 'confirm' => null, 'confirmTone' => null])

{{-- Forms cannot nest, so an action button inside an <x-admin.page form> points at this form with
     form="id"; the layout prints the form after </main>. Identical actions share one form. --}}
@pushOnce('admin-detached-forms', $id)
    <form id="{{ $id }}" method="POST" action="{{ $action }}" class="hidden"
          @if ($confirm) data-confirm="{{ $confirm }}" @endif
          @if ($confirmTone) data-confirm-tone="{{ $confirmTone }}" @endif>
        @csrf
        @if (strtoupper($method) !== 'POST')
            @method(strtoupper($method))
        @endif
        @foreach ($fields as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
    </form>
@endPushOnce
