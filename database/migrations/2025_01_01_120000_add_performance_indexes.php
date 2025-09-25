<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEJORA CRÍTICA: Migración para índices de performance optimizados
 *
 * Índices específicos para mejorar performance de queries críticas:
 * - course_subgroups: búsquedas por fecha y nivel
 * - booking_users: conteos de participantes por subgrupo
 * - bookings: filtros por escuela, cliente y estado
 */
class AddPerformanceIndexes extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('course_subgroups', function (Blueprint $table) {
            // Índice compuesto para búsquedas de disponibilidad
            $table->index(['course_date_id', 'degree_id'], 'idx_course_date_degree');

            // Índice para filtros de capacidad
            $table->index(['max_participants', 'created_at'], 'idx_max_participants');
        });

        Schema::table('booking_users', function (Blueprint $table) {
            // Índice crítico para conteos de participantes
            $table->index(['course_subgroup_id', 'status', 'booking_id'], 'idx_booking_subgroup_status');

            // Índice para búsquedas por cliente
            $table->index(['client_id', 'status'], 'idx_client_status');
        });

        Schema::table('bookings', function (Blueprint $table) {
            // Índice compuesto para filtros administrativos
            $table->index(['school_id', 'client_main_id', 'status'], 'idx_school_client_status');

            // Índice para búsquedas por fecha de creación
            $table->index(['created_at', 'status'], 'idx_created_status');

            // Índice para reportes financieros
            $table->index(['payment_method_id', 'paid', 'created_at'], 'idx_payment_reports');
        });

        Schema::table('course_dates', function (Blueprint $table) {
            // Índice para filtros por fecha y curso
            $table->index(['course_id', 'date'], 'idx_course_date');

            // Índice para búsquedas de fechas futuras
            $table->index(['date', 'course_id'], 'idx_date_course');
        });

        Schema::table('courses', function (Blueprint $table) {
            // Índice para filtros por escuela y tipo
            $table->index(['school_id', 'course_type', 'sport_id'], 'idx_school_type_sport');

            // Índice para cursos activos
            $table->index(['is_active', 'date_start', 'date_end'], 'idx_active_dates');
        });

        Schema::table('clients', function (Blueprint $table) {
            // Índice para búsquedas por escuela
            $table->index(['school_id', 'is_active'], 'idx_school_active');

            // Índice para búsquedas por nombre (performance en autocomplete)
            $table->index(['name', 'surname'], 'idx_name_surname');
        });

        // Índices adicionales para tablas relacionadas
        Schema::table('course_groups', function (Blueprint $table) {
            $table->index(['course_id', 'degree_id'], 'idx_course_degree');
            $table->index(['age_min', 'age_max'], 'idx_age_range');
        });

        Schema::table('monitors', function (Blueprint $table) {
            $table->index(['school_id', 'is_active'], 'idx_school_active_monitor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_subgroups', function (Blueprint $table) {
            $table->dropIndex('idx_course_date_degree');
            $table->dropIndex('idx_max_participants');
        });

        Schema::table('booking_users', function (Blueprint $table) {
            $table->dropIndex('idx_booking_subgroup_status');
            $table->dropIndex('idx_client_status');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('idx_school_client_status');
            $table->dropIndex('idx_created_status');
            $table->dropIndex('idx_payment_reports');
        });

        Schema::table('course_dates', function (Blueprint $table) {
            $table->dropIndex('idx_course_date');
            $table->dropIndex('idx_date_course');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex('idx_school_type_sport');
            $table->dropIndex('idx_active_dates');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('idx_school_active');
            $table->dropIndex('idx_name_surname');
        });

        Schema::table('course_groups', function (Blueprint $table) {
            $table->dropIndex('idx_course_degree');
            $table->dropIndex('idx_age_range');
        });

        Schema::table('monitors', function (Blueprint $table) {
            $table->dropIndex('idx_school_active_monitor');
        });
    }
}