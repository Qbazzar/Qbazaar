@extends('admin.layout')

@section('title', $profile?->business_name ?: $company->full_name)
@section('heading', __('admin.companies.title'))

@section('content')
    <x-admin.page :title="$profile?->business_name ?: $company->full_name"
                  :subtitle="$company->full_name"
                  :crumbs="[[__('admin.companies.title'), route('admin.companies.index')], $profile?->business_name ?: $company->full_name]"
                  :back="route('admin.companies.index')"
                  :side-label="__('admin.companies.side_label')">
        <x-slot:badges>
            @if ($company->business_verified_at)
                <x-admin.badge tone="success">{{ __('admin.companies.verification.verified') }}</x-admin.badge>
            @else
                <x-admin.badge>{{ __('admin.companies.verification.unverified') }}</x-admin.badge>
            @endif
            @if ($profile?->featured_position)
                <x-admin.badge tone="brand">{{ __('admin.companies.featured.position', ['position' => $profile->featured_position]) }}</x-admin.badge>
            @endif
            <x-admin.badge :status="$company->status" />
        </x-slot:badges>

        <x-admin.form-section :title="__('admin.companies.profile.title')" icon="document" id="company-profile">
            @if ($profile)
                <x-admin.meta-list>
                    <x-admin.meta-item :label="__('admin.companies.profile.business_name')">{{ $profile->business_name ?: '—' }}</x-admin.meta-item>
                    <x-admin.meta-item :label="__('admin.companies.profile.legal_name')">{{ $profile->legal_name ?: '—' }}</x-admin.meta-item>
                    <x-admin.meta-item :label="__('admin.companies.profile.registration')" ltr>{{ $profile->commercial_registration_number ?: '—' }}</x-admin.meta-item>
                    <x-admin.meta-item :label="__('admin.companies.profile.contact_phone')" ltr>{{ $profile->contact_phone ?: '—' }}</x-admin.meta-item>
                    <x-admin.meta-item :label="__('admin.companies.profile.contact_email')" ltr>{{ $profile->contact_email ?: '—' }}</x-admin.meta-item>
                    <x-admin.meta-item :label="__('admin.companies.profile.website')" ltr>{{ $profile->website ?: '—' }}</x-admin.meta-item>
                    <x-admin.meta-item :label="__('admin.companies.profile.address')">{{ $profile->address ?: '—' }}</x-admin.meta-item>
                </x-admin.meta-list>
                @if ($profile->about)
                    <p class="mt-2 text-sm whitespace-pre-line text-ink-700">{{ $profile->about }}</p>
                @endif
            @else
                <x-admin.empty-state compact icon="document" :message="__('admin.companies.profile.empty')" />
            @endif
        </x-admin.form-section>

        <x-admin.form-section :title="__('admin.companies.applications.title')" icon="inbox" id="company-applications">
            @forelse ($applications as $application)
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-ink-200 pb-3 last:border-0 last:pb-0">
                    <div class="min-w-0">
                        <a href="{{ route('admin.business-applications.show', $application) }}" class="font-semibold text-ink-900 hover:text-coral-700">{{ $application->business_name }}</a>
                        <p class="text-xs text-ink-500">
                            {{ __('admin.business_applications.submitted_at') }} <span dir="ltr">{{ $application->submitted_at->format('Y-m-d H:i') }}</span>
                            @if ($application->reviewer)
                                · {{ __('admin.business_applications.reviewed_by', ['name' => $application->reviewer->full_name ?: $application->reviewer->email]) }}
                            @endif
                        </p>
                        @if ($application->review_note)
                            <p class="mt-1 text-xs text-ink-700">{{ $application->review_note }}</p>
                        @endif
                    </div>
                    <x-admin.badge :status="$application->status" />
                </div>
            @empty
                <x-admin.empty-state compact icon="inbox" :message="__('admin.companies.applications.empty')" />
            @endforelse
        </x-admin.form-section>

        <x-slot:side>
            <x-admin.side-card id="company-verification" :title="__('admin.companies.verification.title')" icon="shield">
                @if ($company->business_verified_at)
                    <x-admin.meta-list>
                        <x-admin.meta-item :label="__('admin.companies.verification.verified_at')" ltr>{{ $company->business_verified_at->format('Y-m-d H:i') }}</x-admin.meta-item>
                        <x-admin.meta-item :label="__('admin.companies.verification.verified_by')">
                            <x-admin.user :user="$company->businessVerifier" size="xs" :fallback="__('admin.companies.verification.unknown_staff')" />
                        </x-admin.meta-item>
                    </x-admin.meta-list>
                    @if ($canVerify)
                        <form method="POST" action="{{ route('admin.companies.revoke-verification', $company) }}" class="mt-4 grid gap-3"
                              data-confirm="{{ __('admin.companies.verification.revoke_confirm') }}">
                            @csrf
                            <x-admin.textarea name="reason" id="revoke-reason" :label="__('admin.companies.verification.revoke_reason')"
                                              :hint="__('admin.companies.verification.revoke_hint')" rows="3" required minlength="5" maxlength="500" />
                            <x-admin.button variant="danger" icon="x-circle" block>{{ __('admin.companies.verification.revoke') }}</x-admin.button>
                        </form>
                    @endif
                @else
                    <p class="text-ink-700">{{ __('admin.companies.verification.not_verified_hint') }}</p>
                    @if ($canVerify)
                        <form method="POST" action="{{ route('admin.companies.verify', $company) }}" class="mt-4"
                              data-confirm="{{ __('admin.companies.verification.verify_confirm') }}" data-confirm-tone="primary">
                            @csrf
                            <x-admin.button variant="success" icon="check" block>{{ __('admin.companies.verification.verify') }}</x-admin.button>
                        </form>
                    @endif
                @endif
            </x-admin.side-card>

            <x-admin.side-card id="company-featured" :title="__('admin.companies.featured.title')" icon="star"
                               :link="[__('admin.companies.featured.open_order'), route('admin.companies.featured.index')]">
                @if ($profile?->featured_position)
                    <p class="text-ink-700">{{ __('admin.companies.featured.is_featured', ['position' => $profile->featured_position]) }}</p>
                    @if ($canFeature)
                        <form method="POST" action="{{ route('admin.companies.unfeature', $company) }}" class="mt-4">
                            @csrf
                            <x-admin.button variant="secondary" icon="x-circle" block>{{ __('admin.companies.featured.remove') }}</x-admin.button>
                        </form>
                    @endif
                @else
                    <p class="text-ink-700">{{ __('admin.companies.featured.not_featured') }}</p>
                    @if ($canFeature)
                        <form method="POST" action="{{ route('admin.companies.feature', $company) }}" class="mt-4">
                            @csrf
                            <x-admin.button icon="star" block>{{ __('admin.companies.featured.add') }}</x-admin.button>
                        </form>
                    @endif
                @endif
            </x-admin.side-card>

            <x-admin.side-card id="company-account" :title="__('admin.companies.account.title')" icon="users"
                               :link="$canViewUser ? [__('admin.companies.account.open'), route('admin.users.show', $company)] : null">
                <div class="flex items-center gap-3">
                    <x-admin.user-avatar :user="$company" size="md" decorative />
                    <div class="min-w-0">
                        <p class="truncate font-bold text-ink-900">{{ $company->full_name }}</p>
                        <p class="truncate text-[13px] text-ink-500" dir="ltr">{{ $company->email }}</p>
                    </div>
                </div>
                <x-admin.meta-list class="mt-4">
                    <x-admin.meta-item :label="__('admin.companies.account.status')"><x-admin.badge :status="$company->status" /></x-admin.meta-item>
                    <x-admin.meta-item :label="__('admin.companies.columns.ads')">{{ $company->active_ads_count }}</x-admin.meta-item>
                    <x-admin.meta-item :label="__('admin.companies.columns.followers')">{{ $company->followers_count }}</x-admin.meta-item>
                    <x-admin.meta-item :label="__('admin.companies.account.joined')" ltr>{{ $company->created_at->format('Y-m-d') }}</x-admin.meta-item>
                </x-admin.meta-list>
            </x-admin.side-card>

            <x-admin.side-card id="company-timeline" :title="__('admin.companies.timeline')" icon="clock">
                <x-admin.timeline :entries="$timeline" />
            </x-admin.side-card>
        </x-slot:side>
    </x-admin.page>
@endsection
