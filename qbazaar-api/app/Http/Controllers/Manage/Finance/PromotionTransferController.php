<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage\Finance;

use App\Actions\Promotions\ReviewPromotionTransferAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\Finance\RejectPromotionTransferRequest;
use App\Models\AdPromotion;
use App\Models\User;
use App\Services\Promotions\PromotionQueries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Promotions paid by bank transfer, waiting for an admin to see the money
 * arrive. Listing needs finance.view; confirming or rejecting finance.manage.
 */
class PromotionTransferController extends Controller
{
    public function index(PromotionQueries $promotions): View
    {
        return view('admin.finance.promotions.index', [
            'promotions' => $promotions->awaitingTransferConfirmation((int) config('qbazaar.promotions.per_page')),
        ]);
    }

    public function confirm(Request $request, AdPromotion $promotion, ReviewPromotionTransferAction $review): RedirectResponse
    {
        $review->confirm($promotion, $this->admin($request));

        return redirect()->route('admin.finance.promotions.index')->with('status', __('admin.promotion_transfers.confirmed'));
    }

    public function reject(RejectPromotionTransferRequest $request, AdPromotion $promotion, ReviewPromotionTransferAction $review): RedirectResponse
    {
        $review->reject($promotion, $this->admin($request), $request->reason());

        return redirect()->route('admin.finance.promotions.index')->with('status', __('admin.promotion_transfers.rejected'));
    }

    private function admin(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
