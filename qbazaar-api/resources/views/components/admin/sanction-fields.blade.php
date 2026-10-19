{{--
    The reason every suspension, removal, bulk rejection and cancelled offer
    needs: a preset (told to the affected user, in their language) and an
    internal note, required with "other". `prefix` keeps ids unique when a page
    shows several of these forms; `form` joins the fields to a form elsewhere
    on the page (the bulk bar); `restore` refills them after a failed submit.
    `required` is off where only some buttons need a reason (approve and reject
    share the queue's bulk bar); the server still enforces it.
--}}
@props(['prefix' => 'sanction', 'form' => null, 'required' => true, 'restore' => true, 'hint' => null])

<div {{ $attributes->class('grid gap-3') }}>
    <x-admin.select name="reason" :id="$prefix . '-reason'" :label="__('admin.moderation.fields.reason')" :required="$required" :form="$form">
        <option value="">{{ __('admin.moderation.fields.reason_placeholder') }}</option>
        @foreach (\App\Enums\SanctionReason::cases() as $reason)
            <option value="{{ $reason->value }}" @selected($restore && old('reason') === $reason->value)>{{ $reason->adminLabel() }}</option>
        @endforeach
    </x-admin.select>

    <x-admin.textarea name="reason_note" :id="$prefix . '-note'" :label="__('admin.moderation.fields.note')" :value="$restore ? old('reason_note') : null" rows="2"
                      :maxlength="\App\Http\Requests\Manage\Moderation\SanctionRequest::NOTE_MAX_LENGTH" :form="$form"
                      :placeholder="__('admin.moderation.fields.note_placeholder')"
                      :hint="$hint ?? __('admin.moderation.fields.note_hint')" />
</div>
