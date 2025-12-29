<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_users', function (Blueprint $table) {
            // NOTA: idx_bu_monitor_date_status ya existe como idx_bu_monitor_agenda en la migración anterior
            // Solo añadir si no existe ninguna de las dos
            if (!$this->indexExists('booking_users', 'idx_bu_monitor_agenda') &&
                !$this->indexExists('booking_users', 'idx_bu_monitor_date_status')) {
                $table->index(['monitor_id', 'date', 'status'], 'idx_bu_monitor_date_status');
            }

            if (!$this->indexExists('booking_users', 'idx_bu_school_date_status')) {
                $table->index(['school_id', 'date', 'status'], 'idx_bu_school_date_status');
            }

            if (!$this->indexExists('booking_users', 'idx_bu_subgroup_course_date')) {
                $table->index(['course_subgroup_id', 'course_date_id'], 'idx_bu_subgroup_course_date');
            }
        });

        Schema::table('course_subgroups', function (Blueprint $table) {
            if (!$this->indexExists('course_subgroups', 'idx_cs_monitor_course_date')) {
                $table->index(['monitor_id', 'course_date_id'], 'idx_cs_monitor_course_date');
            }
        });

        Schema::table('monitor_nwd', function (Blueprint $table) {
            if (!$this->indexExists('monitor_nwd', 'idx_nwd_monitor_dates')) {
                $table->index(['monitor_id', 'start_date', 'end_date'], 'idx_nwd_monitor_dates');
            }

            // NOTA: idx_nwd_school_monitor_full_day ya existe como idx_mnwd_fullday en la migración anterior
            if (!$this->indexExists('monitor_nwd', 'idx_mnwd_fullday') &&
                !$this->indexExists('monitor_nwd', 'idx_nwd_school_monitor_full_day')) {
                $table->index(['school_id', 'monitor_id', 'full_day', 'user_nwd_subtype_id'], 'idx_nwd_school_monitor_full_day');
            }
        });

        Schema::table('monitors_schools', function (Blueprint $table) {
            // NOTA: idx_ms_school_active es similar a idx_ms_active en la migración anterior
            if (!$this->indexExists('monitors_schools', 'idx_ms_active') &&
                !$this->indexExists('monitors_schools', 'idx_ms_school_active')) {
                $table->index(['school_id', 'active_school'], 'idx_ms_school_active');
            }
        });

        Schema::table('course_dates', function (Blueprint $table) {
            // NOTA: idx_course_dates_course_date es similar a idx_cd_course_date_range en la migración anterior
            if (!$this->indexExists('course_dates', 'idx_cd_course_date_range') &&
                !$this->indexExists('course_dates', 'idx_course_dates_course_date')) {
                $table->index(['course_id', 'date'], 'idx_course_dates_course_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('booking_users', function (Blueprint $table) {
            if ($this->indexExists('booking_users', 'idx_bu_monitor_date_status')) {
                $table->dropIndex('idx_bu_monitor_date_status');
            }
            if ($this->indexExists('booking_users', 'idx_bu_school_date_status')) {
                $table->dropIndex('idx_bu_school_date_status');
            }
            if ($this->indexExists('booking_users', 'idx_bu_subgroup_course_date')) {
                $table->dropIndex('idx_bu_subgroup_course_date');
            }
        });

        Schema::table('course_subgroups', function (Blueprint $table) {
            if ($this->indexExists('course_subgroups', 'idx_cs_monitor_course_date')) {
                $table->dropIndex('idx_cs_monitor_course_date');
            }
        });

        Schema::table('monitor_nwd', function (Blueprint $table) {
            if ($this->indexExists('monitor_nwd', 'idx_nwd_monitor_dates')) {
                $table->dropIndex('idx_nwd_monitor_dates');
            }
            if ($this->indexExists('monitor_nwd', 'idx_nwd_school_monitor_full_day')) {
                $table->dropIndex('idx_nwd_school_monitor_full_day');
            }
        });

        Schema::table('monitors_schools', function (Blueprint $table) {
            if ($this->indexExists('monitors_schools', 'idx_ms_school_active')) {
                $table->dropIndex('idx_ms_school_active');
            }
        });

        Schema::table('course_dates', function (Blueprint $table) {
            if ($this->indexExists('course_dates', 'idx_course_dates_course_date')) {
                $table->dropIndex('idx_course_dates_course_date');
            }
        });
    }

    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $index): bool
    {
        $indexes = \DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]);
        return count($indexes) > 0;
    }
};
