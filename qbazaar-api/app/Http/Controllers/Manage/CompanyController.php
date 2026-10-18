<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Enums\CompanyVerificationFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\Companies\ListCompaniesRequest;
use App\Http\Requests\Manage\Companies\RevokeCompanyVerificationRequest;
use App\Models\User;
use App\Services\Admin\EntityTimeline;
use App\Services\Business\BusinessApplicationQueue;
use App\Services\Business\CompanyAdminDirectory;
use App\Services\Business\CompanyVerification;
use App\Services\Business\FeaturedCompanyCurator;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Business accounts: the verified badge (who granted it and when, revoked
 * with a reason) and whether the company is featured.
 */
class CompanyController extends Controller
{
    /** Audit events about a company, shown on its page. */
    private const array TIMELINE_EVENTS = [
        'admin.companies.verify',
        'admin.companies.revoke-verification',
        'admin.companies.feature',
        'admin.companies.unfeature',
    ];

    public function index(ListCompaniesRequest $request, CompanyAdminDirectory $directory, BusinessApplicationQueue $applications): View
    {
        return view('admin.companies.index', [
            'companies' => $directory->page($request->verification(), $request->search()),
            'search' => $request->search(),
            'verification' => $request->verification(),
            'verificationOptions' => CompanyVerificationFilter::options(),
            'pendingApplications' => $applications->pendingCount(),
        ]);
    }

    public function show(
        #[CurrentUser]
        User $actor,
        User $company,
        BusinessApplicationQueue $applications,
        EntityTimeline $timeline,
    ): View {
        abort_unless($company->isBusiness(), 404);

        $company->load(['businessProfile', 'avatarMedia', 'businessVerifier.avatarMedia']);

        return view('admin.companies.show', [
            'company' => $company,
            'profile' => $company->businessProfile,
            'applications' => $applications->historyOf($company),
            'timeline' => $timeline->forSubject($company, EntityTimeline::DEFAULT_LIMIT, self::TIMELINE_EVENTS),
            'canVerify' => $actor->can('companies.verify'),
            'canFeature' => $actor->can('companies.feature'),
            'canViewUser' => $actor->can('users.view'),
        ]);
    }

    public function verify(#[CurrentUser] User $actor, User $company, CompanyVerification $verification): RedirectResponse
    {
        $verification->verify($actor, $company);

        return back()->with('status', __('admin.companies.flash.verified'));
    }

    public function revokeVerification(
        RevokeCompanyVerificationRequest $request,
        #[CurrentUser]
        User $actor,
        User $company,
        CompanyVerification $verification,
    ): RedirectResponse {
        $verification->revoke($actor, $company, $request->reason());

        return back()->with('status', __('admin.companies.flash.revoked'));
    }

    public function feature(#[CurrentUser] User $actor, User $company, FeaturedCompanyCurator $curator): RedirectResponse
    {
        $curator->feature($actor, $company);

        return back()->with('status', __('admin.companies.flash.featured'));
    }

    public function unfeature(#[CurrentUser] User $actor, User $company, FeaturedCompanyCurator $curator): RedirectResponse
    {
        $curator->unfeature($actor, $company);

        return back()->with('status', __('admin.companies.flash.unfeatured'));
    }
}
