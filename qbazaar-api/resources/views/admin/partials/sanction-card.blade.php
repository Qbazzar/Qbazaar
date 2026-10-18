{{-- Why an ad or an account is suspended: the latest sanction on it, with the internal note only staff see. --}}
<x-admin.side-card :id="$id" :title="$title" icon="shield">
    <x-admin.meta-list>
        <x-admin.meta-item :label="__('admin.moderation.latest.reason')">{{ $sanction->reason->adminLabel() }}</x-admin.meta-item>
        @if ($sanction->note)
            <x-admin.meta-item :label="__('admin.moderation.latest.note')"><span class="whitespace-pre-line break-words">{{ $sanction->note }}</span></x-admin.meta-item>
        @endif
        <x-admin.meta-item :label="__('admin.moderation.latest.by')"><x-admin.user :user="$sanction->staff" size="xs" :fallback="__('admin.timeline.system')" /></x-admin.meta-item>
        <x-admin.meta-item :label="__('admin.moderation.latest.at')" ltr>{{ $sanction->created_at->format('Y-m-d H:i') }}</x-admin.meta-item>
        @if ($sanction->report_id && auth()->user()?->can('reports.view'))
            <x-admin.meta-item :label="__('admin.moderation.latest.report')">
                <a href="{{ route('admin.reports.show', $sanction->report_id) }}" class="font-semibold text-coral-700 hover:underline">{{ __('admin.moderation.latest.open_report') }}</a>
            </x-admin.meta-item>
        @endif
    </x-admin.meta-list>
</x-admin.side-card>
