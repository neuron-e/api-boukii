<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_users', function (Blueprint $table) {
            $table->index(['monitor_id', 'date', 'status'], 'idx_bu_monitor_date_status');
            $table->index(['school_id', 'date', 'status'], 'idx_bu_school_date_status');
            $table->index(['course_subgroup_id', 'course_date_id'], 'idx_bu_subgroup_course_date');
        });

        Schema::table('course_subgroups', function (Blueprint $table) {
            $table->index(['monitor_id', 'course_date_id'], 'idx_cs_monitor_course_date');
        });

        Schema::table('monitor_nwd', function (Blueprint $table) {
            $table->index(['monitor_id', 'start_date', 'end_date'], 'idx_nwd_monitor_dates');
            $table->index(['school_id', 'monitor_id', 'full_day', 'user_nwd_subtype_id'], 'idx_nwd_school_monitor_full_day');
        });

        Schema::table('monitors_schools', function (Blueprint $table) {
            $table->index(['school_id', 'active_school'], 'idx_ms_school_active');
        });

        Schema::table('course_dates', function (Blueprint $table) {
            $table->index(['course_id', 'date'], 'idx_course_dates_course_date');
        });
    }

    public function down(): void
    {
        Schema::table('booking_users', function (Blueprint $table) {
            $table->dropIndex('idx_bu_monitor_date_status');
            $table->dropIndex('idx_bu_school_date_status');
            $table->dropIndex('idx_bu_subgroup_course_date');
        });

        Schema::table('course_subgroups', function (Blueprint $table) {
            $table->dropIndex('idx_cs_monitor_course_date');
        });

        Schema::table('monitor_nwd', function (Blueprint $table) {
            $table->dropIndex('idx_nwd_monitor_dates');
            $table->dropIndex('idx_nwd_school_monitor_full_day');
        });

        Schema::table('monitors_schools', function (Blueprint $table) {
            $table->dropIndex('idx_ms_school_active');
        });

        Schema::table('course_dates', function (Blueprint $table) {
            $table->dropIndex('idx_course_dates_course_date');
        });
    }
};
