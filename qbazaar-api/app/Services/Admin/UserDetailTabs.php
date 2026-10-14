<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Ad;
use App\Models\Order;
use App\Models\User;
use App\Services\Ledger\WalletService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Pagination\Paginator as PageResolver;

/**
 * The tab strip on the admin user page. Only the selected panel is queried:
 * the page renders it inline and the others load on demand from their own
 * endpoint, so a user with thousands of ads or orders costs the same to open.
 */
final class UserDetailTabs
{
    public const PER_PAGE = 10;

    /** Tab key => permission needed to see it (null = anyone who can open the user). */
    private const TABS = [
        'profile' => null,
        'ads' => 'ads.view',
        'orders' => 'finance.view',
        'wallet' => 'finance.view',
    ];

    public function __construct(private readonly WalletService $wallets) {}

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::TABS);
    }

    /**
     * @return list<string>
     */
    public function visibleTo(User $actor): array
    {
        return array_keys(array_filter(
            self::TABS,
            fn (?string $permission): bool => $permission === null || $actor->can($permission),
        ));
    }

    public function resolve(User $actor, string $requested): string
    {
        $visible = $this->visibleTo($actor);

        return in_array($requested, $visible, true) ? $requested : $visible[0];
    }

    /**
     * The partial that renders one panel, inline on the page or on its own.
     *
     * @return view-string
     */
    public function view(string $tab): string
    {
        return match ($tab) {
            'ads' => 'admin.users.tabs.ads',
            'orders' => 'admin.users.tabs.orders',
            'wallet' => 'admin.users.tabs.wallet',
            default => 'admin.users.tabs.profile',
        };
    }

    /**
     * View data for one panel. Paginators link back to the full user page so
     * paging works the same whether the panel was rendered inline or fetched.
     *
     * @return array<string, mixed>
     */
    public function panel(string $tab, User $user): array
    {
        return match ($tab) {
            'ads' => ['ads' => $this->ads($user)],
            'orders' => ['orders' => $this->orders($user)],
            'wallet' => ['summary' => $this->wallets->summary($user->id)],
            default => ['business' => $user->isBusiness() ? $user->businessProfile()->first() : null],
        };
    }

    /**
     * @return LengthAwarePaginator<int, Ad>
     */
    private function ads(User $user): LengthAwarePaginator
    {
        return $user->ads()
            ->with('primaryImage')
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withPath(route('admin.users.show', $user))
            ->appends('tab', 'ads');
    }

    /**
     * Orders where the user is buyer or seller. A single "buyer_id = ? OR seller_id = ?" would merge two
     * indexes and sort the result; instead each side reads its own (user, created_at) index in order, only
     * as many rows as the requested page can need, and the two short lists are merged here.
     *
     * @return LengthAwarePaginator<int, Order>
     */
    private function orders(User $user): LengthAwarePaginator
    {
        $page = max(1, PageResolver::resolveCurrentPage());
        $window = $page * self::PER_PAGE;
        $asBuyer = Order::query()->where('buyer_id', $user->id);
        // An order the user is on both sides of is already counted on the buyer side.
        $asSeller = Order::query()->where('seller_id', $user->id)
            ->where(fn (Builder $query) => $query->whereNull('buyer_id')->orWhere('buyer_id', '<>', $user->id));

        $ids = $this->newest($asBuyer, $window)->concat($this->newest($asSeller, $window))
            ->sort(fn (Order $a, Order $b): int => [$b->created_at, $b->id] <=> [$a->created_at, $a->id])
            ->slice(($page - 1) * self::PER_PAGE, self::PER_PAGE)
            ->pluck('id')
            ->all();

        $position = array_flip($ids);
        $orders = Order::query()
            ->whereKey($ids)
            ->with(['buyer:id,full_name,email', 'seller:id,full_name,email', 'buyer.avatarMedia', 'seller.avatarMedia'])
            ->get()
            ->sortBy(fn (Order $order): int => $position[$order->id])
            ->values();

        return (new Paginator($orders, $asBuyer->count() + $asSeller->count(), self::PER_PAGE, $page))
            ->withPath(route('admin.users.show', $user))
            ->appends('tab', 'orders');
    }

    /**
     * @param Builder<Order> $query
     * @return Collection<int, Order>
     */
    private function newest(Builder $query, int $limit): Collection
    {
        return (clone $query)->select(['id', 'created_at'])->orderByDesc('created_at')->orderByDesc('id')->limit($limit)->get();
    }
}
