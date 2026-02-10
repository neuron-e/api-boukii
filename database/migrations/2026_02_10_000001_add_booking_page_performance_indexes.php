<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_users', function (Blueprint $table) {
            $table->index(['school_id', 'date', 'status'], 'booking_users_school_date_status_idx');
            $table->index(['monitor_id', 'date'], 'booking_users_monitor_date_idx');
        });

        Schema::table('course_dates', function (Blueprint $table) {
            $table->index(['date'], 'course_dates_date_idx');
        });

        Schema::table('course_subgroups', function (Blueprint $table) {
            $table->index(['monitor_id'], 'course_subgroups_monitor_idx');
        });
    }

    public function down(): void
    {
        Schema::table('booking_users', function (Blueprint $table) {
            $table->dropIndex('booking_users_school_date_status_idx');
            $table->dropIndex('booking_users_monitor_date_idx');
        });

        Schema::table('course_dates', function (Blueprint $table) {
            $table->dropIndex('course_dates_date_idx');
        });

        Schema::table('course_subgroups', function (Blueprint $table) {
            $table->dropIndex('course_subgroups_monitor_idx');
        });
    }
};
