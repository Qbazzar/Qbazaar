<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use JsonException;
use RuntimeException;

/**
 * Writes everything QBazaar holds about a user as one JSON document.
 *
 * Every list is streamed in key-ordered chunks, so memory stays flat however
 * much a user has. Secrets (password hash, token hashes, OTP codes) are
 * never exported.
 */
class UserDataExporter
{
    private const int CHUNK = 500;

    /**
     * @param resource $stream
     *
     * @throws JsonException
     */
    public function write($stream, User $user, string $exportId): void
    {
        $this->put($stream, '{');

        $first = true;
        foreach ($this->sections($user, $exportId) as $name => $content) {
            $this->put($stream, ($first ? '' : ',') . "\n" . $this->encode($name) . ':');
            $first = false;

            if ($content instanceof LazyCollection) {
                $this->writeRows($stream, $content);
            } else {
                $this->put($stream, $this->encode($content));
            }
        }

        $this->put($stream, "\n}\n");
    }

    /**
     * @return iterable<string, mixed> plain values, or lazy row collections written as lists
     */
    private function sections(User $user, string $exportId): iterable
    {
        yield 'generated_at' => Carbon::now()->toIso8601String();
        yield 'export_id' => $exportId;
        yield 'user' => [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'account_type' => $user->account_type->value,
            'status' => $user->status->value,
            'email_verified' => $user->email_verified,
            'phone_verified' => $user->phone_verified,
            'language' => $user->language->value,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at->toIso8601String(),
            'privacy_settings' => $user->privacySettings()->toArray(),
            'notification_preferences' => $user->notification_preferences->toArray(),
        ];

        yield 'addresses' => $this->rows(
            DB::table('user_addresses')->where('user_id', $user->id)
                ->select(['id', 'label', 'full_name', 'phone', 'street', 'house_number', 'supplement', 'city', 'postal_code', 'is_default', 'created_at']),
        );

        yield 'ads' => $this->rows(
            DB::table('ads')->where('user_id', $user->id)
                ->select([
                    'id', 'title', 'description', 'price', 'price_type', 'currency', 'condition', 'ad_type', 'shipping',
                    'postal_code', 'street', 'show_full_address', 'status', 'custom_fields', 'views_count', 'favorites_count',
                    'published_at', 'reserved_at', 'expires_at', 'created_at', 'updated_at', 'deleted_at',
                ]),
            json: ['custom_fields'],
        );

        yield 'messages_sent' => $this->rows(
            DB::table('messages')->where('sender_id', $user->id)
                ->select(['id', 'conversation_id', 'type', 'body', 'message_key', 'params', 'read_at', 'created_at']),
            json: ['params'],
        );

        yield 'hidden_conversations' => $this->rows(
            DB::table('conversations')
                ->where(fn (Builder $query) => $query
                    ->where(fn (Builder $buyer) => $buyer->where('buyer_id', $user->id)->whereNotNull('buyer_hidden_at'))
                    ->orWhere(fn (Builder $seller) => $seller->where('seller_id', $user->id)->whereNotNull('seller_hidden_at')))
                ->select(['id', 'ad_id'])
                ->selectRaw('case when buyer_id = ? then buyer_hidden_at else seller_hidden_at end as hidden_at', [$user->id]),
        );

        $offerColumns = ['id', 'ad_id', 'conversation_id', 'amount', 'currency', 'note', 'status', 'created_at', 'updated_at'];
        yield 'offers_made' => $this->rows(DB::table('offers')->where('buyer_id', $user->id)->select($offerColumns));
        yield 'offers_received' => $this->rows(DB::table('offers')->where('seller_id', $user->id)->select($offerColumns));

        yield 'favorites' => $this->rows(
            DB::table('favorites')->where('user_id', $user->id)->select(['id', 'ad_id', 'created_at']),
        );

        yield 'saved_searches' => $this->rows(
            DB::table('saved_searches')->where('user_id', $user->id)
                ->select(['id', 'name', 'query_params', 'alerts_enabled', 'category_id', 'location_id', 'condition', 'price_type', 'price_min', 'price_max', 'created_at']),
            json: ['query_params'],
        );

        yield 'business_profile' => $this->rows(
            DB::table('business_profiles')->where('user_id', $user->id)
                ->select([
                    'user_id', 'business_name', 'about', 'legal_name', 'commercial_registration_number', 'contact_phone',
                    'contact_email', 'website', 'address', 'opening_hours', 'created_at', 'updated_at',
                ]),
            key: 'user_id',
            json: ['opening_hours'],
        );

        yield 'following' => $this->rows(
            DB::table('follows')->where('follower_id', $user->id)->select(['id', 'followed_id', 'created_at']),
        );

        yield 'followers' => $this->rows(
            DB::table('follows')->where('followed_id', $user->id)->select(['id', 'follower_id', 'created_at']),
        );

        yield 'reviews_written' => $this->rows(
            DB::table('reviews')->where('reviewer_id', $user->id)
                ->select(['id', 'ad_id', 'seller_id', 'rating', 'comment', 'created_at']),
        );

        yield 'blocked_users' => $this->rows(
            DB::table('user_blocks')->where('blocker_id', $user->id)->select(['blocked_id', 'created_at']),
            key: 'blocked_id',
        );

        yield 'sessions' => $this->rows(
            DB::table('refresh_tokens')->where('user_id', $user->id)
                ->select(['id', 'device_fingerprint', 'expires_at', 'used_at', 'created_at']),
        );

        yield 'trusted_devices' => $this->rows(
            DB::table('trusted_devices')->where('user_id', $user->id)
                ->select(['id', 'label', 'last_ip', 'last_used_at', 'created_at']),
        );

        yield 'social_accounts' => $this->rows(
            DB::table('social_accounts')->where('user_id', $user->id)->select(['id', 'provider', 'email', 'created_at']),
        );

        // Codes are keyed by recipient rather than user, so this covers the
        // current phone and email; the code hashes stay out.
        yield 'one_time_codes' => $this->rows(
            DB::table('otp_codes')->whereIn('recipient', [$user->phone, $user->email])
                ->select(['id', 'purpose', 'expires_at', 'used_at', 'attempts', 'created_at']),
        );

        yield 'activity_log' => $this->rows(
            DB::table('activity_log')
                ->where('causer_type', $user->getMorphClass())
                ->where('causer_id', $user->id)
                ->select(['id', 'log_name', 'event', 'description', 'properties', 'created_at']),
            json: ['properties'],
        );
    }

    /**
     * Walks the query in key order, decoding JSON columns so they export as
     * nested values rather than strings.
     *
     * @param list<string> $json
     * @return LazyCollection<int, array<string, mixed>>
     */
    private function rows(Builder $query, string $key = 'id', array $json = []): LazyCollection
    {
        return $query->lazyById(self::CHUNK, $key)->map(fn (object $row): array => $this->decode($row, $json));
    }

    /**
     * @param list<string> $json
     * @return array<string, mixed>
     */
    private function decode(object $row, array $json): array
    {
        $values = get_object_vars($row);

        foreach ($json as $column) {
            if (is_string($values[$column] ?? null)) {
                $values[$column] = json_decode($values[$column], true);
            }
        }

        return $values;
    }

    /**
     * @param resource $stream
     * @param LazyCollection<int, array<string, mixed>> $rows
     *
     * @throws JsonException
     */
    private function writeRows($stream, LazyCollection $rows): void
    {
        $this->put($stream, '[');

        $first = true;
        foreach ($rows as $row) {
            $this->put($stream, ($first ? '' : ',') . "\n  " . $this->encode($row));
            $first = false;
        }

        $this->put($stream, ']');
    }

    /**
     * @throws JsonException
     */
    private function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param resource $stream
     */
    private function put($stream, string $chunk): void
    {
        if (fwrite($stream, $chunk) === false) {
            throw new RuntimeException('Could not write the data export.');
        }
    }
}
