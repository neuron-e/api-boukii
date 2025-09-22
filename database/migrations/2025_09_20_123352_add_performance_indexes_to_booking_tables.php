<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add indexes to courses table for better query performance
        Schema::table('courses', function (Blueprint $table) {
            $table->index(['sport_id', 'online', 'course_type'], 'idx_courses_sport_online_type');
            $table->index(['school_id', 'sport_id'], 'idx_courses_school_sport');
            $table->index(['age_min', 'age_max'], 'idx_courses_age_range');
        });

        // Add indexes to course_dates table
        Schema::table('course_dates', function (Blueprint $table) {
            $table->index(['date', 'course_id'], 'idx_course_dates_date_course');
            $table->index(['course_id', 'date'], 'idx_course_dates_course_date');
        });

        // Add indexes to course_groups table
        Schema::table('course_groups', function (Blueprint $table) {
            $table->index(['course_id', 'degree_id'], 'idx_course_groups_course_degree');
            $table->index(['age_min', 'age_max'], 'idx_course_groups_age_range');
        });

        // Add indexes to course_subgroups table
        Schema::table('course_subgroups', function (Blueprint $table) {
            $table->index(['course_group_id', 'course_date_id'], 'idx_subgroups_group_date');
            $table->index('max_participants', 'idx_subgroups_max_participants');
        });

        // Add indexes to booking_users table for better join performance
        Schema::table('booking_users', function (Blueprint $table) {
            $table->index(['course_subgroup_id', 'status'], 'idx_booking_users_subgroup_status');
            $table->index(['client_id', 'status'], 'idx_booking_users_client_status');
            $table->index(['course_date_id', 'status'], 'idx_booking_users_date_status');
        });

        // Add indexes to clients table
        Schema::table('clients', function (Blueprint $table) {
            $table->index('birth_date', 'idx_clients_birth_date');
        });

        // Add indexes to degrees table
        Schema::table('degrees', function (Blueprint $table) {
            $table->index(['school_id', 'sport_id', 'active'], 'idx_degrees_school_sport_active');
            $table->index('degree_order', 'idx_degrees_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex('idx_courses_sport_online_type');
            $table->dropIndex('idx_courses_school_sport');
            $table->dropIndex('idx_courses_age_range');
        });

        Schema::table('course_dates', function (Blueprint $table) {
            $table->dropIndex('idx_course_dates_date_course');
            $table->dropIndex('idx_course_dates_course_date');
        });

        Schema::table('course_groups', function (Blueprint $table) {
            $table->dropIndex('idx_course_groups_course_degree');
            $table->dropIndex('idx_course_groups_age_range');
        });

        Schema::table('course_subgroups', function (Blueprint $table) {
            $table->dropIndex('idx_subgroups_group_date');
            $table->dropIndex('idx_subgroups_max_participants');
        });

        Schema::table('booking_users', function (Blueprint $table) {
            $table->dropIndex('idx_booking_users_subgroup_status');
            $table->dropIndex('idx_booking_users_client_status');
            $table->dropIndex('idx_booking_users_date_status');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('idx_clients_birth_date');
        });

        Schema::table('degrees', function (Blueprint $table) {
            $table->dropIndex('idx_degrees_school_sport_active');
            $table->dropIndex('idx_degrees_order');
        });
    }
};
