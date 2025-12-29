<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureCourseIndexes();
        $this->ensureCourseDateIndexes();
        $this->ensureCourseGroupIndexes();
        $this->ensureCourseSubgroupIndexes();
        $this->ensureBookingIndexes();
        $this->ensureBookingUserIndexes();
    }

    public function down(): void
    {
        // Intentionally left empty to avoid dropping pre-existing production indexes.
    }

    private function ensureCourseIndexes(): void
    {
        $table = 'courses';
        $this->addPrimaryIfMissing($table, 'id');

        $this->addIndexIfMissing($table, 'fk_courses2_type_idx', ['course_type']);
        $this->addIndexIfMissing($table, 'fk_courses2_sport_idx', ['sport_id']);
        $this->addIndexIfMissing($table, 'fk_courses2_school_idx', ['school_id']);
        $this->addIndexIfMissing($table, 'fk_courses_station', ['station_id']);
        $this->addIndexIfMissing($table, 'courses_ibfk_4_idx', ['user_id']);
        $this->addIndexIfMissing($table, 'idx_courses_school_active_type', ['school_id', 'active', 'course_type']);
        $this->addIndexIfMissing($table, 'idx_courses_sport_school', ['sport_id', 'school_id', 'active']);
        $this->addIndexIfMissing($table, 'idx_courses_date_range', ['date_start', 'date_end', 'active']);
        $this->addIndexIfMissing($table, 'idx_courses_sport_online_type', ['sport_id', 'online', 'course_type']);
        $this->addIndexIfMissing($table, 'idx_courses_school_sport', ['school_id', 'sport_id']);
        $this->addIndexIfMissing($table, 'idx_courses_age_range', ['age_min', 'age_max']);
        $this->addIndexIfMissing($table, 'idx_school_type_sport', ['school_id', 'course_type', 'sport_id']);
        $this->addIndexIfMissing($table, 'idx_active_dates', ['active', 'date_start', 'date_end']);
        $this->addIndexIfMissing($table, 'courses_archived_at_index', ['archived_at']);
    }

    private function ensureCourseDateIndexes(): void
    {
        $table = 'course_dates';
        $this->addPrimaryIfMissing($table, 'id');

        $this->addIndexIfMissing($table, 'fk_cd_course2_idx', ['course_id']);
        $this->addIndexIfMissing($table, 'idx_course_dates_date_course', ['date', 'course_id']);
        $this->addIndexIfMissing($table, 'idx_course_dates_course_date', ['course_id', 'date']);
        $this->addIndexIfMissing($table, 'idx_course_date', ['course_id', 'date']);
        $this->addIndexIfMissing($table, 'idx_date_course', ['date', 'course_id']);
        $this->addIndexIfMissing($table, 'course_dates_course_interval_id_index', ['course_interval_id']);
    }

    private function ensureCourseGroupIndexes(): void
    {
        $table = 'course_groups';
        $this->addPrimaryIfMissing($table, 'id');

        $this->addIndexIfMissing($table, 'fk_cg_course_idx', ['course_id']);
        $this->addIndexIfMissing($table, 'fk_cg_course_date_idx', ['course_date_id']);
        $this->addIndexIfMissing($table, 'fk_cg_degree_idx', ['degree_id']);
        $this->addIndexIfMissing($table, 'fk_cg_teacher_degree_idx', ['teacher_min_degree']);
        $this->addIndexIfMissing($table, 'idx_course_groups_course_degree', ['course_id', 'degree_id']);
        $this->addIndexIfMissing($table, 'idx_course_groups_age_range', ['age_min', 'age_max']);
        $this->addIndexIfMissing($table, 'idx_course_degree', ['course_id', 'degree_id']);
        $this->addIndexIfMissing($table, 'idx_age_range', ['age_min', 'age_max']);
    }

    private function ensureCourseSubgroupIndexes(): void
    {
        $table = 'course_subgroups';
        $this->addPrimaryIfMissing($table, 'id');

        $this->addIndexIfMissing($table, 'fk_cgs_group_idx', ['course_group_id']);
        $this->addIndexIfMissing($table, 'fk_cgs_course_idx', ['course_id']);
        $this->addIndexIfMissing($table, 'fk_cgs_course_date_idx', ['course_date_id']);
        $this->addIndexIfMissing($table, 'fk_cgs_degree_idx', ['degree_id']);
        $this->addIndexIfMissing($table, 'fk_cgs_monitor_idx', ['monitor_id']);
        $this->addIndexIfMissing($table, 'idx_subgroups_group_date', ['course_group_id', 'course_date_id']);
        $this->addIndexIfMissing($table, 'idx_subgroups_max_participants', ['max_participants']);
        $this->addIndexIfMissing($table, 'idx_course_date_degree', ['course_date_id', 'degree_id']);
        $this->addIndexIfMissing($table, 'idx_max_participants', ['max_participants', 'created_at']);
        $this->addIndexIfMissing($table, 'idx_subgroup_dates_id', ['subgroup_dates_id']);
    }

    private function ensureBookingIndexes(): void
    {
        $table = 'bookings';
        $this->addPrimaryIfMissing($table, 'id');

        $this->addIndexIfMissing($table, 'fk_bookings_school_idx', ['school_id']);
        $this->addIndexIfMissing($table, 'fk_bookings_client_main_idx', ['client_main_id']);
        $this->addIndexIfMissing($table, 'fk_bookings_payment_idx', ['payment_method_id']);
        $this->addIndexIfMissing($table, 'bookings_ibfk_3_idx', ['user_id']);
        $this->addIndexIfMissing($table, 'idx_bookings_school_status_created', ['school_id', 'status', 'created_at']);
        $this->addIndexIfMissing($table, 'idx_bookings_payment_status', ['paid', 'status', 'school_id']);
        $this->addIndexIfMissing($table, 'idx_bookings_client_status', ['client_main_id', 'status']);
        $this->addIndexIfMissing($table, 'idx_bookings_analytics', ['created_at', 'school_id', 'price_total']);
        $this->addIndexIfMissing($table, 'idx_bookings_school_created', ['school_id', 'created_at', 'deleted_at']);
        $this->addIndexIfMissing($table, 'idx_school_client_status', ['school_id', 'client_main_id', 'status']);
        $this->addIndexIfMissing($table, 'idx_created_status', ['created_at', 'status']);
        $this->addIndexIfMissing($table, 'idx_payment_reports', ['payment_method_id', 'paid', 'created_at']);
        $this->addIndexIfMissing($table, 'idx_booking_discount_code', ['discount_code_id']);
        $this->addIndexIfMissing($table, 'idx_bookings_discount_type', ['discount_type']);
        $this->addIndexIfMissing($table, 'idx_bookings_interval_discount', ['interval_discount_id']);
        $this->addIndexIfMissing($table, 'idx_bookings_course_discount', ['course_discount_id']);
    }

    private function ensureBookingUserIndexes(): void
    {
        $table = 'booking_users';
        $this->addPrimaryIfMissing($table, 'id');

        $this->addIndexIfMissing($table, 'fk_bu_booking_idx', ['booking_id']);
        $this->addIndexIfMissing($table, 'fk_bu_user_main_idx', ['client_id']);
        $this->addIndexIfMissing($table, 'fk_bu2_subgroup_idx', ['course_subgroup_id']);
        $this->addIndexIfMissing($table, 'fk_bu_course_idx', ['course_id']);
        $this->addIndexIfMissing($table, 'fk_bu_monitor_idx', ['monitor_id']);
        $this->addIndexIfMissing($table, 'course_date_id', ['course_date_id']);
        $this->addIndexIfMissing($table, 'degree_id', ['degree_id']);
        $this->addIndexIfMissing($table, 'course_group_id', ['course_group_id']);
        $this->addIndexIfMissing($table, 'booking_users_ibfk_8_idx', ['school_id']);
        $this->addIndexIfMissing($table, 'idx_booking_users_client_date_status', ['client_id', 'date', 'status']);
        $this->addIndexIfMissing($table, 'idx_booking_users_course_monitor', ['course_id', 'monitor_id', 'status']);
        $this->addIndexIfMissing($table, 'idx_booking_users_date_range', ['date', 'hour_start', 'hour_end']);
        $this->addIndexIfMissing($table, 'idx_booking_users_group_status', ['group_id', 'status', 'deleted_at']);
        $this->addIndexIfMissing($table, 'idx_booking_users_school_date', ['school_id', 'date', 'status']);
        $this->addIndexIfMissing($table, 'idx_booking_users_booking_date', ['booking_id', 'date', 'course_id']);
        $this->addIndexIfMissing($table, 'idx_booking_users_subgroup_status', ['course_subgroup_id', 'status']);
        $this->addIndexIfMissing($table, 'idx_booking_users_client_status', ['client_id', 'status']);
        $this->addIndexIfMissing($table, 'idx_booking_users_date_status', ['course_date_id', 'status']);
        $this->addIndexIfMissing($table, 'idx_booking_subgroup_status', ['course_subgroup_id', 'status', 'booking_id']);
        $this->addIndexIfMissing($table, 'idx_client_status', ['client_id', 'status']);
    }

    private function addPrimaryIfMissing(string $table, string $column): void
    {
        if (!$this->columnsExist($table, [$column]) || $this->indexExists($table, 'PRIMARY')) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($column) {
            $table->primary($column);
        });
    }

    private function addIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if (!$this->columnsExist($table, $columns) || $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($columns, $indexName) {
            $table->index($columns, $indexName);
        });
    }

    private function columnsExist(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

        return !empty($result);
    }
};
