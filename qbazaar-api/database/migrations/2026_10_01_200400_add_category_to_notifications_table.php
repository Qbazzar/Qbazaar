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
                foreach ($rows as $row) {
                    $data = json_decode((string) $row->data, true);
                    $category = is_array($data) && is_string($data['category'] ?? null) ? $data['category'] : null;

                    if ($category !== null) {
                        DB::table('notifications')->where('id', $row->id)->update(['category' => mb_substr($category, 0, 64)]);
                    }
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
