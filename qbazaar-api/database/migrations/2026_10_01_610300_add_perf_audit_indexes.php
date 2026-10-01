<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The remaining indexes from the 2026-10-01 performance audit (section 3).
 * Where a new index starts with every column of an old one, the old one is
 * dropped: it no longer serves a query and still costs every write.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            // A seller's public ads and profile pages, newest published first.
            $table->index(['user_id', 'status', 'published_at'], 'ads_user_status_published_idx');
            // Featured strip: featured, live, newest first.
            $table->index(['featured', 'status', 'published_at'], 'ads_featured_status_published_idx');
            $table->dropIndex('ads_featured_published_idx');
            // Admin ad list filtered by status, newest first.
            $table->index(['status', 'created_at'], 'ads_status_created_idx');
        });

        Schema::table('media', function (Blueprint $table): void {
            // primaryImage (lowest order_column), image counts and exists checks.
            $table->index(['model_type', 'model_id', 'collection_name', 'order_column'], 'media_model_collection_order_idx');
            $table->dropIndex(['model_type', 'model_id']);
        });

        Schema::table('messages', function (Blueprint $table): void {
            // Unread counts: `read_at IS NULL` is an equality and belongs before the `sender_id <>` range.
            $table->index(['conversation_id', 'read_at', 'sender_id'], 'messages_conv_read_sender_idx');
            $table->dropIndex('messages_conv_sender_read_idx');
        });

        Schema::table('offers', function (Blueprint $table): void {
            // Open-offer checks and expiry of the open offers on one ad.
            $table->index(['ad_id', 'status'], 'offers_ad_status_idx');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->index('created_at', 'users_created_at_idx');
        });

        Schema::table('refresh_tokens', function (Blueprint $table): void {
            $table->index(['user_id', 'device_fingerprint'], 'refresh_tokens_user_device_idx');
        });

        Schema::table('recently_viewed', function (Blueprint $table): void {
            $table->index('viewed_at', 'recently_viewed_viewed_at_idx');
        });

        Schema::table('activity_log', function (Blueprint $table): void {
            $table->index(['causer_type', 'causer_id', 'created_at'], 'activity_log_causer_created_idx');
            $table->dropIndex('causer');
        });

        Schema::table('password_reset_tokens', function (Blueprint $table): void {
            $table->index('created_at', 'password_reset_tokens_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            $table->index(['featured', 'published_at'], 'ads_featured_published_idx');
            $table->dropIndex('ads_featured_status_published_idx');
            $table->dropIndex('ads_user_status_published_idx');
            $table->dropIndex('ads_status_created_idx');
        });

        Schema::table('media', function (Blueprint $table): void {
            $table->index(['model_type', 'model_id']);
            $table->dropIndex('media_model_collection_order_idx');
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->index(['conversation_id', 'sender_id', 'read_at'], 'messages_conv_sender_read_idx');
            $table->dropIndex('messages_conv_read_sender_idx');
        });

        Schema::table('offers', function (Blueprint $table): void {
            // MySQL dropped the foreign key's own ad_id index once the composite covered it.
            $table->index('ad_id');
            $table->dropIndex('offers_ad_status_idx');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_created_at_idx');
        });

        Schema::table('refresh_tokens', function (Blueprint $table): void {
            $table->dropIndex('refresh_tokens_user_device_idx');
        });

        Schema::table('recently_viewed', function (Blueprint $table): void {
            $table->dropIndex('recently_viewed_viewed_at_idx');
        });

        Schema::table('activity_log', function (Blueprint $table): void {
            $table->index(['causer_type', 'causer_id'], 'causer');
            $table->dropIndex('activity_log_causer_created_idx');
        });

        Schema::table('password_reset_tokens', function (Blueprint $table): void {
            $table->dropIndex('password_reset_tokens_created_at_idx');
        });
    }
};
