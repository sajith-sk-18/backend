<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upcoming_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->date('expected_at')->nullable();
            $table->string('teaser', 160)->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->decimal('expected_price', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upcoming_products');
    }
};
