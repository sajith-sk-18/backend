<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();

            // The product this offer is attached to (the main item the buyer is purchasing)
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // 'percent' | 'flat' | 'bundle'
            $table->string('type', 20);

            // For 'percent' it's a % (0-100). For 'flat' it's an amount off in USD.
            $table->decimal('discount_value', 10, 2)->nullable();

            // For 'bundle': the accessory/companion product
            $table->foreignId('bundle_product_id')->nullable()->constrained('products')->nullOnDelete();
            // For 'bundle': % off the accessory (100 = free)
            $table->decimal('bundle_discount', 5, 2)->nullable();

            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['product_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
