{{-- A refused delete of a category or location: what still uses it, and deactivating as the way out. --}}
@if ($notice)
    <x-admin.alert tone="warning" :title="$notice->title" class="mb-6" data-qb-delete-blocked>
        <ul class="mt-1.5 grid list-disc gap-1 ps-5">
            @foreach ($notice->reasons as $reason)
                <li>{{ $reason }}</li>
            @endforeach
        </ul>
        @if ($notice->deactivateUrl)
            <p class="mt-2">{{ __('admin.catalog.delete.deactivate_hint') }}</p>
            <div class="mt-3">
                <x-admin.form-button :action="$notice->deactivateUrl" method="PATCH" :fields="['is_active' => '0']" icon="ban" size="sm">
                    {{ __('admin.catalog.delete.deactivate_instead') }}
                </x-admin.form-button>
            </div>
        @endif
    </x-admin.alert>
@endif
