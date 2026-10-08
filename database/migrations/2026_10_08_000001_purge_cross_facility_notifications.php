<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Notifications addressed to staff of a different facility than the event
        DB::statement("
            DELETE FROM notifications
            WHERE facility_id IS NOT NULL
            AND EXISTS (
                SELECT 1 FROM users
                WHERE users.id = notifications.user_id
                AND users.role != 'super_admin'
                AND (users.facility_id IS NULL OR users.facility_id != notifications.facility_id)
            )
        ");

        // Facility notifications sent to platform super admins (they only keep platform alerts)
        DB::statement("
            DELETE FROM notifications
            WHERE type != 'facility_created'
            AND EXISTS (
                SELECT 1 FROM users
                WHERE users.id = notifications.user_id
                AND users.role = 'super_admin'
            )
        ");
    }

    public function down(): void
    {
        // No rollback — data-only migration
    }
};
