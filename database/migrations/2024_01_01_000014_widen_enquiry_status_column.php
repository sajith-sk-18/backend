<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix: enquiries.status was created as enum('pending','resolved') in the
 * original migration, and 000010 only *renamed the data* (pending→new,
 * resolved→closed) without changing the column TYPE. On SQLite the enum is
 * ignored so the app's real vocabulary (new / in_progress / responded /
 * closed — see Enquiry::STATUSES) worked silently. On MySQL the strict enum
 * rejects those values, breaking enquiry create/update.
 *
 * This widens status to a plain string, matching the model's app-level
 * validation. MySQL-only ALTER; on SQLite the column already behaves as a
 * string so nothing is needed.
 */
return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `enquiries` MODIFY `status` VARCHAR(20) NOT NULL DEFAULT 'new'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `enquiries` MODIFY `status` ENUM('pending','resolved') NOT NULL DEFAULT 'pending'");
        }
    }
};
