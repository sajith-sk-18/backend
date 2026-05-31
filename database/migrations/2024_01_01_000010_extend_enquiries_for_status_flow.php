<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // --- 1. Add the new columns. SQLite can't ALTER an existing ENUM, so
        //        we use a plain string column for status and just rely on
        //        application-level validation. We also can't drop the existing
        //        enum-typed status column cleanly across drivers; instead we
        //        leave the old `status` column (already a string at the
        //        Eloquent layer) and widen the allowed values via app code.
        Schema::table('enquiries', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('id')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->after('customer_id')
                ->constrained('products')->nullOnDelete();
            $table->text('admin_reply')->nullable()->after('message');
            $table->timestamp('replied_at')->nullable()->after('resolved_at');
        });

        // --- 2. Migrate existing statuses to the new vocabulary.
        DB::table('enquiries')->where('status', 'pending')->update(['status'  => 'new']);
        DB::table('enquiries')->where('status', 'resolved')->update(['status' => 'closed']);

        // --- 3. Backfill customer_id from the email match (no-op if no users).
        // Use a sub-update so we don't depend on any specific driver's JOIN syntax.
        $users = DB::table('users')->select('id', 'email')->get();
        foreach ($users as $u) {
            DB::table('enquiries')
                ->whereNull('customer_id')
                ->where('email', $u->email)
                ->update(['customer_id' => $u->id]);
        }
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['product_id']);
            $table->dropColumn(['customer_id', 'product_id', 'admin_reply', 'replied_at']);
        });
        // Status values aren't reverted; new/in_progress/responded/closed are
        // accepted by the old enum's "out of range" handling (SQLite ignores
        // unknown enum values anyway).
    }
};
