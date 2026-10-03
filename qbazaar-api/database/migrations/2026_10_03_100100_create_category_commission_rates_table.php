<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional per-category commission percent. A category without a row
     * inherits its nearest ancestor's rate, then the general platform rate.
     */
    public function up(): void
    {
        Schema::create('category_commission_rates', function (Blueprint $table): void {
            $table->foreignUlid('category_id')->primary()->constrained('categories')->cascadeOnDelete();
            $table->decimal('rate', 5, 2);
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_commission_rates');
    }
};
