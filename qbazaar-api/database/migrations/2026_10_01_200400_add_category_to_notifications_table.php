<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CHUNK = 1000;

    /**
     * `category` is copied out of the JSON payload into its own column so
     * `GET /account/notifications?category=` filters through an index
     * instead of scanning every payload of the user.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->string('category', 64)->nullable()->after('type');
            $table->index(['notifiable_type', 'notifiable_id', 'category', 'created_at'], 'notifications_notifiable_category_idx');
        });

        DB::table('notifications')
            ->select(['id', 'data'])
            ->whereNull('category')
            ->chunkById(self::CHUNK, function (Collection $rows): void {
                // One UPDATE per distinct category in the chunk, not one per row.
                $idsByCategory = [];

                foreach ($rows as $row) {
                    $data = json_decode((string) $row->data, true);

                    if (is_array($data) && is_string($data['category'] ?? null)) {
                        $idsByCategory[mb_substr($data['category'], 0, 64)][] = $row->id;
                    }
                }

                foreach ($idsByCategory as $category => $ids) {
                    DB::table('notifications')->whereIn('id', $ids)->update(['category' => $category]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_notifiable_category_idx');
            $table->dropColumn('category');
        });
    }
};
