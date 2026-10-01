<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STATUSES = ['pending', 'accepted', 'rejected', 'withdrawn', 'expired', 'countered'];

    private const LEGACY_STATUSES = ['pending', 'accepted', 'rejected', 'withdrawn', 'expired'];

    /**
     * `is_open` is TRUE while an offer is pending and NULL otherwise, so the
     * unique index below only ever compares open rows: NULLs never collide,
     * which gives "one open offer per buyer and ad" on MySQL and SQLite alike.
     */
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table): void {
            $table->enum('status', self::STATUSES)->default('pending')->change();
        });

        Schema::table('offers', function (Blueprint $table): void {
            $table->enum('proposed_by', ['buyer', 'seller'])->default('buyer')->after('seller_id');
            $table->foreignUlid('parent_offer_id')
                ->nullable()
                ->after('message_id')
                ->constrained('offers')
                ->nullOnDelete();
            $table->unsignedTinyInteger('counter_round')->default(0)->after('parent_offer_id');
            $table->boolean('is_open')->nullable()->after('status');
            $table->timestamp('countered_at')->nullable()->after('withdrawn_at');
        });

        $this->openPendingOffers();

        Schema::table('offers', function (Blueprint $table): void {
            $table->unique(['buyer_id', 'ad_id', 'is_open'], 'offers_one_open_per_buyer_ad');
        });
    }

    public function down(): void
    {
        DB::table('offers')->where('status', 'countered')->update(['status' => 'rejected']);

        Schema::table('offers', function (Blueprint $table): void {
            $table->dropUnique('offers_one_open_per_buyer_ad');
            $table->dropConstrainedForeignId('parent_offer_id');
            $table->dropColumn(['proposed_by', 'counter_round', 'is_open', 'countered_at']);
        });

        Schema::table('offers', function (Blueprint $table): void {
            $table->enum('status', self::LEGACY_STATUSES)->default('pending')->change();
        });
    }

    /**
     * Marks every pending offer open, withdrawing older duplicates first so
     * legacy rows cannot break the new unique index.
     */
    private function openPendingOffers(): void
    {
        $pending = DB::table('offers')
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'buyer_id', 'ad_id']);

        $newest = $pending->unique(fn (object $offer): string => $offer->buyer_id . '|' . $offer->ad_id);
        $duplicateIds = $pending->pluck('id')->diff($newest->pluck('id'));

        foreach ($duplicateIds->chunk(500) as $ids) {
            DB::table('offers')->whereIn('id', $ids->all())->update([
                'status' => 'withdrawn',
                'withdrawn_at' => now(),
            ]);
        }

        DB::table('offers')->where('status', 'pending')->update(['is_open' => true]);
    }
};
