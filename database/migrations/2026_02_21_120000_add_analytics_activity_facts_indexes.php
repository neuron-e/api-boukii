<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('analytics_activity_facts')) {
            return;
        }

        Schema::table('analytics_activity_facts', function (Blueprint $table) {
            $activityIndex = 'aaf_school_activity_idx';
            $indexes = DB::select("SHOW INDEX FROM analytics_activity_facts WHERE Key_name = ?", [$activityIndex]);
            if (empty($indexes)) {
                $table->index(['school_id', 'activity_date'], $activityIndex);
            }

            $bookingIndex = 'aaf_school_booking_created_idx';
            $indexes = DB::select("SHOW INDEX FROM analytics_activity_facts WHERE Key_name = ?", [$bookingIndex]);
            if (empty($indexes)) {
                $table->index(['school_id', 'booking_created_at'], $bookingIndex);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('analytics_activity_facts')) {
            return;
        }

        Schema::table('analytics_activity_facts', function (Blueprint $table) {
            $table->dropIndex('aaf_school_activity_idx');
            $table->dropIndex('aaf_school_booking_created_idx');
        });
    }
};
