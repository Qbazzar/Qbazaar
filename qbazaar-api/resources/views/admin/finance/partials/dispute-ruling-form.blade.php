{{-- An admin's ruling on a disputed order, posted to the finance dispute flow (ResolveOrderDisputeAction) wherever it is shown. --}}
<form method="POST" action="{{ route('admin.finance.disputes.resolve', $order) }}" data-confirm="{{ __('admin.finance.disputes.confirm') }}" data-confirm-tone="primary" class="{{ $class ?? 'w-full space-y-2 md:w-64' }}">
    @csrf
    <x-admin.textarea name="note" :id="'note-' . $order->id" :label="__('admin.finance.disputes.note_label')" label-hidden :placeholder="__('admin.finance.disputes.note_placeholder')" :rows="2" required minlength="5" maxlength="1000" />
    <div class="flex flex-wrap gap-2">
        <x-admin.button variant="success" size="sm" name="resolution" value="completed">{{ __('admin.finance.disputes.rule_completed') }}</x-admin.button>
        <x-admin.button variant="danger" size="sm" name="resolution" value="cancelled">{{ __('admin.finance.disputes.rule_cancelled') }}</x-admin.button>
    </div>
</form>
