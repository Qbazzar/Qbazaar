<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\AccountType;
use App\Enums\AdStatus;
use App\Enums\UserStatus;
use Illuminate\Support\Facades\DB;

/**
 * Row counts per entity after a run, one grouped query per table.
 */
final class DemoSummary
{
    /**
     * @return list<array{string, int}>
     */
    public function rows(): array
    {
        return [
            ['Staff accounts', $this->count('model_has_roles')],
            ...$this->grouped('users', 'account_type', 'Accounts', [], [AccountType::BUSINESS->value => 'business', AccountType::PRIVATE_INDIVIDUAL->value => 'private']),
            ['Accounts: suspended', $this->count('users', ['status' => UserStatus::SUSPENDED->value])],
            ['Business profiles', $this->count('business_profiles')],
            ...$this->grouped('ads', 'status', 'Ads'),
            ['Ads: reserved (open order)', DB::table('ads')->where('status', AdStatus::ACTIVE->value)->whereNotNull('reserved_at')->count()],
            ['Ads: featured', $this->count('ads', ['featured' => true])],
            ...$this->grouped('media', 'collection_name', 'Media'),
            ['Follows', $this->count('follows')],
            ['Favorites', $this->count('favorites')],
            ['Recently viewed', $this->count('recently_viewed')],
            ['Saved searches', $this->count('saved_searches')],
            ['Blocked pairs', $this->count('user_blocks')],
            ['Conversations', $this->count('conversations')],
            ...$this->grouped('messages', 'type', 'Messages'),
            ['Unread messages', (int) DB::table('conversation_participants')->sum('unread_count')],
            ...$this->grouped('offers', 'status', 'Offers'),
            ...$this->grouped('orders', 'status', 'Orders'),
            ...$this->grouped('ledger_transactions', 'type', 'Ledger'),
            ['Reviews', $this->count('reviews')],
            ...$this->grouped('reports', 'status', 'Reports'),
            ...$this->grouped('support_tickets', 'status', 'Tickets'),
            ['Ticket replies', $this->count('support_replies')],
            ['Notifications', $this->count('notifications')],
            ['Activity log entries', $this->count('activity_log')],
        ];
    }

    /**
     * @param array<string, mixed> $where
     */
    private function count(string $table, array $where = []): int
    {
        return DB::table($table)->where($where)->count();
    }

    /**
     * @param array<string, mixed> $where
     * @param array<string, string> $labels
     * @return list<array{string, int}>
     */
    private function grouped(string $table, string $column, string $prefix, array $where = [], array $labels = []): array
    {
        return DB::table($table)
            ->where($where)
            ->groupBy($column)
            ->orderBy($column)
            ->selectRaw("{$column} as bucket, COUNT(*) as total")
            ->get()
            ->map(fn (object $row): array => ["{$prefix}: " . ($labels[$row->bucket] ?? $row->bucket), (int) $row->total])
            ->values()
            ->all();
    }
}
