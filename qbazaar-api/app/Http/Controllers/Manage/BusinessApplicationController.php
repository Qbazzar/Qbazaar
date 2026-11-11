<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Enums\BusinessApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\BusinessApplications\ApproveBusinessApplicationRequest;
use App\Http\Requests\Manage\BusinessApplications\BusinessApplicationNoteRequest;
use App\Http\Requests\Manage\BusinessApplications\ListBusinessApplicationsRequest;
use App\Models\BusinessApplication;
use App\Models\User;
use App\Services\Admin\EntityTimeline;
use App\Services\Business\ApplicationDocumentResponse;
use App\Services\Business\BusinessApplicationQueue;
use App\Services\Business\BusinessApplicationReview;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The staff review queue of business applications: approve (the account
 * becomes a verified business), reject with a reason, or ask the applicant
 * for more information.
 *
 * Every staff member who sees companies sees the queue, but what an
 * applicant declared and wrote (legal name, CR number, notes, documents)
 * stays with the reviewers: until approved, none of it is public.
 */
class BusinessApplicationController extends Controller
{
    private const string REVIEW_PERMISSION = 'companies.verify';

    public function index(ListBusinessApplicationsRequest $request, #[CurrentUser] User $actor, BusinessApplicationQueue $queue): View
    {
        $canReview = $actor->can(self::REVIEW_PERMISSION);

        return view('admin.business-applications.index', [
            'applications' => $queue->page($request->status(), $request->search(), searchLegalDetails: $canReview),
            'status' => $request->status(),
            'statuses' => BusinessApplicationStatus::cases(),
            'search' => $request->search(),
            'canReview' => $canReview,
        ]);
    }

    public function show(#[CurrentUser] User $actor, BusinessApplication $application, EntityTimeline $timeline): View
    {
        $application->load(['user.avatarMedia', 'user.businessProfile', 'reviewer.avatarMedia', 'media']);
        $canReview = $actor->can(self::REVIEW_PERMISSION);

        return view('admin.business-applications.show', [
            'application' => $application,
            'applicant' => $application->user,
            'documents' => $application->documents(),
            'timeline' => $timeline->forSubject($application),
            'canDecide' => $canReview && $application->status->isOpen(),
            'canReview' => $canReview,
            'canViewUser' => $actor->can('users.view'),
        ]);
    }

    /**
     * One document, streamed from the private disk to reviewing staff only.
     */
    public function document(BusinessApplication $application, int $document, ApplicationDocumentResponse $response): StreamedResponse
    {
        return $response->stream($application->document($document) ?? abort(404));
    }

    public function approve(
        ApproveBusinessApplicationRequest $request,
        #[CurrentUser]
        User $actor,
        BusinessApplication $application,
        BusinessApplicationReview $review,
    ): RedirectResponse {
        $review->approve($actor, $application, $request->note());

        return back()->with('status', __('admin.business_applications.flash.approved'));
    }

    public function reject(
        BusinessApplicationNoteRequest $request,
        #[CurrentUser]
        User $actor,
        BusinessApplication $application,
        BusinessApplicationReview $review,
    ): RedirectResponse {
        $review->reject($actor, $application, $request->note());

        return back()->with('status', __('admin.business_applications.flash.rejected'));
    }

    public function requestInfo(
        BusinessApplicationNoteRequest $request,
        #[CurrentUser]
        User $actor,
        BusinessApplication $application,
        BusinessApplicationReview $review,
    ): RedirectResponse {
        $review->requestInfo($actor, $application, $request->note());

        return back()->with('status', __('admin.business_applications.flash.info_requested'));
    }
}
