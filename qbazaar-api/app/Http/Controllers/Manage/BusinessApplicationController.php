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
use App\Services\Business\BusinessApplicationQueue;
use App\Services\Business\BusinessApplicationReview;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The staff review queue of business applications: approve (the account
 * becomes a verified business), reject with a reason, or ask the applicant
 * for more information.
 */
class BusinessApplicationController extends Controller
{
    public function index(ListBusinessApplicationsRequest $request, BusinessApplicationQueue $queue): View
    {
        return view('admin.business-applications.index', [
            'applications' => $queue->page($request->status(), $request->search()),
            'status' => $request->status(),
            'statuses' => BusinessApplicationStatus::cases(),
            'search' => $request->search(),
        ]);
    }

    public function show(#[CurrentUser] User $actor, BusinessApplication $application, EntityTimeline $timeline): View
    {
        $application->load(['user.avatarMedia', 'user.businessProfile', 'reviewer.avatarMedia', 'media']);

        return view('admin.business-applications.show', [
            'application' => $application,
            'applicant' => $application->user,
            'documents' => $application->documents(),
            'timeline' => $timeline->forSubject($application),
            'canDecide' => $actor->can('companies.verify') && $application->status->isOpen(),
            'canOpenDocuments' => $actor->can('companies.verify'),
            'canViewUser' => $actor->can('users.view'),
        ]);
    }

    /**
     * One document, streamed from the private disk to reviewing staff only.
     * The sandbox policy keeps a crafted PDF or image from running script
     * on the admin origin.
     */
    public function document(BusinessApplication $application, int $document): StreamedResponse
    {
        $media = $application->document($document) ?? abort(404);

        return Storage::disk($media->disk)->response($media->getPathRelativeToRoot(), $media->file_name, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
        ]);
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
