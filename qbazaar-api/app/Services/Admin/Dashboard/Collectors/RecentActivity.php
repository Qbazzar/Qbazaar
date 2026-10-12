<?php

declare(strict_types=1);

namespace App\Services\Admin\Dashboard\Collectors;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Order;
use App\Models\Report;
use App\Models\SupportTicket;
use App\Models\User;

/**
 * The newest five rows of each queue. Every list loads its related names with
 * one eager query and only the columns it shows, so five rows cost two
 * queries however big the tables are.
 */
final class RecentActivity
{
    private const int LIMIT = 5;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pendingAds(): array
    {
        return Ad::query()
            ->select(['id', 'user_id', 'category_id', 'title', 'created_at'])
            ->where('status', AdStatus::PENDING->value)
            ->with(['user:id,full_name', 'user.avatarMedia', 'category:id,name,slug'])
            ->latest()
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Ad $ad): array => [
                'title' => $ad->title,
                'meta' => $ad->user->full_name,
                'person' => $this->person($ad->user),
                'badge' => $ad->category->name,
                'at' => $ad->created_at->toIso8601String(),
                'href' => route('admin.ads.show', $ad, false),
            ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function reports(): array
    {
        return Report::query()
            ->select(['id', 'reporter_id', 'target_type', 'category', 'status', 'created_at'])
            ->with(['reporter:id,full_name', 'reporter.avatarMedia'])
            ->latest()
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Report $report): array => [
                'title' => $report->category->label(),
                'meta' => $report->reporter->full_name ?? null,
                'person' => $this->person($report->reporter),
                'badge' => $report->status->label(),
                'at' => $report->created_at->toIso8601String(),
                'href' => route('admin.reports.show', $report, false),
            ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tickets(): array
    {
        return SupportTicket::query()
            ->select(['id', 'user_id', 'email', 'subject', 'status', 'created_at'])
            ->with(['user:id,full_name', 'user.avatarMedia'])
            ->latest()
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (SupportTicket $ticket): array => [
                'title' => $ticket->subject,
                'meta' => $ticket->user->full_name ?? $ticket->email,
                'person' => $this->person($ticket->user, $ticket->email),
                'badge' => $ticket->status->label(),
                'at' => $ticket->created_at->toIso8601String(),
                'href' => route('admin.support.show', $ticket, false),
            ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function signups(): array
    {
        return User::query()
            ->select(['id', 'full_name', 'account_type', 'created_at'])
            ->latest()
            ->with('avatarMedia')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (User $user): array => [
                'title' => $user->full_name,
                'meta' => null,
                'person' => $this->person($user),
                'badge' => ['key' => 'admin.home.account_type.' . $user->account_type->value],
                'at' => $user->created_at->toIso8601String(),
                'href' => route('admin.users.show', $user, false),
            ])->all();
    }

    /**
     * Linked to the seller's statement, the only admin page that shows an
     * order's money.
     *
     * @return array<int, array<string, mixed>>
     */
    public function orders(): array
    {
        return Order::query()
            ->select(['id', 'buyer_id', 'seller_id', 'ad_title', 'total', 'status', 'created_at'])
            ->with(['buyer:id,full_name', 'buyer.avatarMedia', 'seller:id,full_name'])
            ->latest()
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Order $order): array => [
                'title' => $order->ad_title,
                'meta' => trim(($order->buyer->full_name ?? '—') . ' → ' . ($order->seller->full_name ?? '—')),
                'person' => $this->person($order->buyer),
                'badge' => ['key' => 'admin.home.order_status.' . $order->status->value],
                'amount' => (string) $order->total,
                'at' => $order->created_at->toIso8601String(),
                'href' => $order->seller_id ? route('admin.finance.statements.show', $order->seller_id, false) : null,
            ])->all();
    }

    /**
     * The face shown beside a row: plain data, so the cached payload holds no models.
     *
     * @return array{id: int|string, name: string, avatar: string|null}|array{id: null, name: string, avatar: null}|null
     */
    private function person(?User $user, ?string $fallbackName = null): ?array
    {
        if ($user === null) {
            return $fallbackName === null ? null : ['id' => null, 'name' => $fallbackName, 'avatar' => null];
        }

        return ['id' => $user->getKey(), 'name' => (string) $user->full_name, 'avatar' => $user->avatarThumbUrl()];
    }
}
