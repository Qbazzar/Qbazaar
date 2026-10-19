<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\Moderation\BulkApproveAdsRequest;
use App\Http\Requests\Manage\Moderation\BulkSanctionAdsRequest;
use App\Models\User;
use App\Services\Admin\Moderation\ReviewQueue;
use App\Services\Admin\Moderation\SlaClock;
use App\Services\Moderation\BulkAdReview;
use App\Services\Moderation\BulkReviewOutcome;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The FIFO review queue: pending ads oldest first with their SLA standing,
 * approved or rejected in bulk.
 */
class ReviewQueueController extends Controller
{
    public function index(ReviewQueue $queue): View
    {
        $clock = SlaClock::fromConfig();

        return view('admin.moderation.queue', [
            'rows' => $queue->page($clock, (int) config('qbazaar.admin.review_queue_per_page')),
            'summary' => $queue->summary($clock),
        ]);
    }

    public function approve(BulkApproveAdsRequest $request, #[CurrentUser] User $staff, BulkAdReview $review): RedirectResponse
    {
        $outcome = $review->approve($staff, $request->adIds());

        return back()->with('status', $this->flash('approved', $outcome));
    }

    public function reject(BulkSanctionAdsRequest $request, #[CurrentUser] User $staff, BulkAdReview $review): RedirectResponse
    {
        $outcome = $review->reject($staff, $request->adIds(), $request->sanction());

        return back()->with('status', $this->flash('rejected', $outcome));
    }

    private function flash(string $key, BulkReviewOutcome $outcome): string
    {
        $message = (string) __('admin.review_queue.flash.' . $key, ['count' => $outcome->done]);

        return $outcome->skipped > 0
            ? $message . ' ' . __('admin.review_queue.flash.skipped', ['count' => $outcome->skipped])
            : $message;
    }
}
