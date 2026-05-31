<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // NULL user_id = admin-scoped notification (the bell in AdminLayout).
            // Non-null = scoped to that customer's own notification feed.
            $table->foreignId('user_id')->nullable()->after('type')
                ->constrained('users')->nullOnDelete();
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id', 'read_at']);
            $table->dropColumn('user_id');
        });
    }
};
