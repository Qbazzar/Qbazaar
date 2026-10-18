@extends('admin.layout')

@section('title', $application->business_name)
@section('heading', __('admin.business_applications.title'))

@section('content')
    <x-admin.page :title="$application->business_name"
                  :subtitle="$application->legal_name"
                  :crumbs="[[__('admin.business_applications.title'), route('admin.business-applications.index')], $application->business_name]"
                  :back="route('admin.business-applications.index')"
                  :side-label="__('admin.business_applications.side_label')">
        <x-slot:badges>
            <x-admin.badge :status="$application->status" />
        </x-slot:badges>

        <x-admin.form-section :title="__('admin.business_applications.details.title')" icon="document" id="application-details">
            <x-admin.meta-list>
                <x-admin.meta-item :label="__('admin.business_applications.details.business_name')">{{ $application->business_name }}</x-admin.meta-item>
                <x-admin.meta-item :label="__('admin.business_applications.details.legal_name')">{{ $application->legal_name }}</x-admin.meta-item>
                <x-admin.meta-item :label="__('admin.business_applications.details.registration')" ltr>{{ $application->commercial_registration_number }}</x-admin.meta-item>
                <x-admin.meta-item :label="__('admin.business_applications.submitted_at')" ltr>{{ $application->submitted_at->format('Y-m-d H:i') }}</x-admin.meta-item>
            </x-admin.meta-list>
            @if ($application->applicant_note)
                <div>
                    <p class="text-sm font-semibold text-ink-500">{{ __('admin.business_applications.details.applicant_note') }}</p>
                    <p class="mt-1 text-sm whitespace-pre-line text-ink-900">{{ $application->applicant_note }}</p>
                </div>
            @endif
        </x-admin.form-section>

        <x-admin.form-section :title="__('admin.business_applications.documents.title')" icon="photo" id="application-documents"
                              :description="$canOpenDocuments ? __('admin.business_applications.documents.private_hint') : __('admin.business_applications.documents.reviewers_only')">
            @forelse ($documents as $document)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-ink-200 bg-cream-50 px-4 py-3">
                    <span class="min-w-0">
                        <span class="block truncate font-semibold text-ink-900" dir="auto">{{ $document->name }}</span>
                        <span class="block text-xs text-ink-500" dir="ltr">{{ $document->mime_type }} · {{ $document->human_readable_size }}</span>
                    </span>
                    @if ($canOpenDocuments)
                        <x-admin.button variant="secondary" size="sm" icon="external" target="_blank" rel="noopener"
                                        :href="route('admin.business-applications.document', [$application, $document->id])">{{ __('admin.business_applications.documents.open') }}</x-admin.button>
                    @endif
                </div>
            @empty
                <x-admin.empty-state compact icon="photo" :message="__('admin.business_applications.documents.empty')" />
            @endforelse
        </x-admin.form-section>

        @if ($application->reviewed_at)
            <x-admin.form-section :title="__('admin.business_applications.review.title')" icon="check" id="application-review">
                <x-admin.meta-list>
                    <x-admin.meta-item :label="__('admin.business_applications.review.reviewer')">
                        <x-admin.user :user="$application->reviewer" size="xs" :fallback="__('admin.business_applications.deleted_account')" />
                    </x-admin.meta-item>
                    <x-admin.meta-item :label="__('admin.business_applications.review.reviewed_at')" ltr>{{ $application->reviewed_at->format('Y-m-d H:i') }}</x-admin.meta-item>
                </x-admin.meta-list>
                @if ($application->review_note)
                    <div>
                        <p class="text-sm font-semibold text-ink-500">{{ __('admin.business_applications.review.note') }}</p>
                        <p class="mt-1 text-sm whitespace-pre-line text-ink-900">{{ $application->review_note }}</p>
                    </div>
                @endif
            </x-admin.form-section>
        @endif

        @if ($canDecide)
            <x-admin.form-section :title="__('admin.business_applications.decide.title')" icon="shield" id="application-decision"
                                  :description="__('admin.business_applications.decide.hint')" cols="3">
                <form method="POST" action="{{ route('admin.business-applications.approve', $application) }}" class="grid content-start gap-3"
                      data-confirm="{{ __('admin.business_applications.decide.approve_confirm') }}" data-confirm-tone="primary">
                    @csrf
                    <x-admin.textarea name="note" id="approve-note" :label="__('admin.business_applications.decide.approve_note')" rows="3" maxlength="1000" />
                    <x-admin.button variant="success" icon="check" block>{{ __('admin.business_applications.decide.approve') }}</x-admin.button>
                </form>
                <form method="POST" action="{{ route('admin.business-applications.request-info', $application) }}" class="grid content-start gap-3">
                    @csrf
                    <x-admin.textarea name="note" id="request-info-note" :label="__('admin.business_applications.decide.request_info_note')" rows="3" required minlength="5" maxlength="1000" />
                    <x-admin.button variant="info" icon="info" block>{{ __('admin.business_applications.decide.request_info') }}</x-admin.button>
                </form>
                <form method="POST" action="{{ route('admin.business-applications.reject', $application) }}" class="grid content-start gap-3"
                      data-confirm="{{ __('admin.business_applications.decide.reject_confirm') }}">
                    @csrf
                    <x-admin.textarea name="note" id="reject-note" :label="__('admin.business_applications.decide.reject_note')" rows="3" required minlength="5" maxlength="1000" />
                    <x-admin.button variant="danger" icon="x-circle" block>{{ __('admin.business_applications.decide.reject') }}</x-admin.button>
                </form>
            </x-admin.form-section>
        @endif

        <x-slot:side>
            <x-admin.side-card id="application-applicant" :title="__('admin.business_applications.applicant.title')" icon="users"
                               :link="$applicant && $canViewUser ? [__('admin.business_applications.applicant.open'), route('admin.users.show', $applicant)] : null">
                @if ($applicant)
                    <div class="flex items-center gap-3">
                        <x-admin.user-avatar :user="$applicant" size="md" decorative />
                        <div class="min-w-0">
                            <p class="truncate font-bold text-ink-900">{{ $applicant->full_name ?: $applicant->email }}</p>
                            @if ($applicant->phone)
                                <x-admin.phone :phone="$applicant->phone" class="text-[13px] text-ink-500" />
                            @endif
                        </div>
                    </div>
                    <x-admin.meta-list class="mt-4">
                        <x-admin.meta-item :label="__('admin.business_applications.applicant.email')" ltr>{{ $applicant->email }}</x-admin.meta-item>
                        <x-admin.meta-item :label="__('admin.business_applications.applicant.account_type')">{{ __('admin.home.account_type.' . $applicant->account_type->value) }}</x-admin.meta-item>
                        <x-admin.meta-item :label="__('admin.business_applications.applicant.status')"><x-admin.badge :status="$applicant->status" /></x-admin.meta-item>
                        <x-admin.meta-item :label="__('admin.business_applications.applicant.verified')">
                            {{ $applicant->isVerifiedBusiness() ? __('admin.companies.verification.verified') : __('admin.companies.verification.unverified') }}
                        </x-admin.meta-item>
                    </x-admin.meta-list>
                    @if ($applicant->isBusiness())
                        <a href="{{ route('admin.companies.show', $applicant) }}" class="mt-4 inline-flex text-xs font-semibold text-coral-700 hover:underline">{{ __('admin.business_applications.applicant.company_page') }}</a>
                    @endif
                @else
                    <p class="text-ink-500">{{ __('admin.business_applications.deleted_account') }}</p>
                @endif
            </x-admin.side-card>

            <x-admin.side-card id="application-timeline" :title="__('admin.business_applications.timeline')" icon="clock">
                <x-admin.timeline :entries="$timeline" />
            </x-admin.side-card>
        </x-slot:side>
    </x-admin.page>
@endsection
