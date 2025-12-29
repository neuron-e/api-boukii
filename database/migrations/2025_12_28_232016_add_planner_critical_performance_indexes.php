<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * CRITICAL PERFORMANCE FIX: Añadir índices compuestos específicos para getAgenda/getPlanner
     *
     * Impacto esperado:
     * - Reducción de 50-70% en tiempo de queries
     * - Mejora de 10-23s a 3-7s en rangos de 1 mes
     *
     * @see app/Http/Controllers/Teach/HomeController.php:getAgenda
     * @see app/Http/Controllers/Admin/PlannerController.php:performPlannerQuery
     */
    public function up(): void
    {
        Schema::table('booking_users', function (Blueprint $table) {
            // Índice para query principal de getAgenda/getPlanner
            // WHERE school_id = ? AND status = 1 AND accepted = 1 AND date BETWEEN ? AND ?
            if (!$this->indexExists('booking_users', 'idx_bu_planner_main')) {
                $table->index(['school_id', 'status', 'accepted', 'date'], 'idx_bu_planner_main');
            }

            // Índice para filtro por monitor en getAgenda
            // WHERE monitor_id = ? AND date BETWEEN ? AND ? AND status = 1
            if (!$this->indexExists('booking_users', 'idx_bu_monitor_agenda')) {
                $table->index(['monitor_id', 'date', 'status'], 'idx_bu_monitor_agenda');
            }

            // Índice para relación con subgrupos
            // WHERE course_subgroup_id = ? AND status = 1 AND accepted = 1
            if (!$this->indexExists('booking_users', 'idx_bu_subgroup_active')) {
                $table->index(['course_subgroup_id', 'status', 'accepted'], 'idx_bu_subgroup_active');
            }
        });

        Schema::table('course_subgroups', function (Blueprint $table) {
            // Índice para whereHas en getPlanner
            // WHERE course_date_id = ? AND monitor_id = ?
            if (!$this->indexExists('course_subgroups', 'idx_cs_planner')) {
                $table->index(['course_date_id', 'monitor_id', 'degree_id'], 'idx_cs_planner');
            }
        });

        Schema::table('monitor_nwd', function (Blueprint $table) {
            // Índice para rangos de fechas en getPlanner
            // WHERE monitor_id = ? AND school_id = ? AND start_date <= ? AND end_date >= ?
            if (!$this->indexExists('monitor_nwd', 'idx_mnwd_planner')) {
                $table->index(['monitor_id', 'school_id', 'start_date', 'end_date'], 'idx_mnwd_planner');
            }

            // Índice para Full Day NWDs
            // WHERE school_id = ? AND monitor_id IN (...) AND full_day = 1 AND user_nwd_subtype_id = 1
            if (!$this->indexExists('monitor_nwd', 'idx_mnwd_fullday')) {
                $table->index(['school_id', 'monitor_id', 'full_day', 'user_nwd_subtype_id', 'start_date'], 'idx_mnwd_fullday');
            }
        });

        Schema::table('monitors_schools', function (Blueprint $table) {
            // Índice para monitores activos por escuela
            // WHERE school_id = ? AND active_school = 1
            if (!$this->indexExists('monitors_schools', 'idx_ms_active')) {
                $table->index(['school_id', 'active_school', 'monitor_id'], 'idx_ms_active');
            }
        });

        Schema::table('course_dates', function (Blueprint $table) {
            // Índice para filtros de fecha en courseDates
            // WHERE course_id = ? AND date BETWEEN ? AND ? AND active = 1
            if (!$this->indexExists('course_dates', 'idx_cd_course_date_range')) {
                $table->index(['course_id', 'date', 'active'], 'idx_cd_course_date_range');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_users', function (Blueprint $table) {
            $table->dropIndex('idx_bu_planner_main');
            $table->dropIndex('idx_bu_monitor_agenda');
            $table->dropIndex('idx_bu_subgroup_active');
        });

        Schema::table('course_subgroups', function (Blueprint $table) {
            $table->dropIndex('idx_cs_planner');
        });

        Schema::table('monitor_nwd', function (Blueprint $table) {
            $table->dropIndex('idx_mnwd_planner');
            $table->dropIndex('idx_mnwd_fullday');
        });

        Schema::table('monitors_schools', function (Blueprint $table) {
            $table->dropIndex('idx_ms_active');
        });

        Schema::table('course_dates', function (Blueprint $table) {
            $table->dropIndex('idx_cd_course_date_range');
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
