<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * INDICES CRÍTICOS PARA SEGURIDAD Y RENDIMIENTO
     *
     * Estos índices son esenciales para:
     * 1. Mejorar el rendimiento de consultas frecuentes
     * 2. Optimizar las búsquedas y filtros
     * 3. Acelerar las operaciones de JOIN
     * 4. Mejorar la seguridad mediante consultas más rápidas
     */
   public function up()
    {
        /* // BOOKING_USERS - Tabla más consultada del sistema
        Schema::table('booking_users', function (Blueprint $table) {
            // Índice para consultas por cliente, fecha y estado (usado en dashboard)
            $table->index(['client_id', 'date', 'status'], 'idx_booking_users_client_date_status');

            // Índice para consultas por curso, monitor y estado (usado en planificación)
            $table->index(['course_id', 'monitor_id', 'status'], 'idx_booking_users_course_monitor');

            // Índice para búsquedas por rango de fechas y horas
            $table->index(['date', 'hour_start', 'hour_end'], 'idx_booking_users_date_range');

            // Índice para consultas por grupo y estado (usado en actividades agrupadas)
            $table->index(['group_id', 'status', 'deleted_at'], 'idx_booking_users_group_status');

            // Índice para consultas de reservas por escuela y fecha
            $table->index(['school_id', 'date', 'status'], 'idx_booking_users_school_date');
        });

        // BOOKINGS - Tabla principal de reservas
        Schema::table('bookings', function (Blueprint $table) {
            // Índice para dashboard de escuela (consulta más frecuente)
            $table->index(['school_id', 'status', 'created_at'], 'idx_bookings_school_status_created');

            // Índice para consultas de pagos pendientes
            $table->index(['paid', 'status', 'school_id'], 'idx_bookings_payment_status');

            // Índice para consultas por cliente principal
            $table->index(['client_main_id', 'status'], 'idx_bookings_client_status');

            // Índice para analíticas financieras
            $table->index(['created_at', 'school_id', 'price_total'], 'idx_bookings_analytics');
        });

        // COURSES - Cursos disponibles
        Schema::table('courses', function (Blueprint $table) {
            // Índice para consultas de cursos activos por escuela y tipo
            $table->index(['school_id', 'active', 'course_type'], 'idx_courses_school_active_type');

            // Índice para consultas por deporte y escuela
            $table->index(['sport_id', 'school_id', 'active'], 'idx_courses_sport_school');

            // Índice para consultas por rango de fechas de disponibilidad
            $table->index(['date_start', 'date_end', 'active'], 'idx_courses_date_range');

            // Índice para búsquedas por nombre y escuela
            $table->index(['school_id', 'name'], 'idx_courses_school_name');
        });

        // COURSE_DATES - Fechas específicas de cursos
        Schema::table('course_dates', function (Blueprint $table) {
            // Índice para consultas por curso y fecha (evitar duplicados)
            $table->index(['course_id', 'date'], 'idx_course_dates_course_date');

            // Índice para consultas de disponibilidad por fecha y hora
            $table->index(['date', 'hour_start', 'hour_end'], 'idx_course_dates_availability');
        });

        // CLIENTS - Clientes del sistema
        Schema::table('clients', function (Blueprint $table) {
            // Índice para búsquedas por nombre completo y escuela
            $table->index(['first_name', 'last_name', 'school_id'], 'idx_clients_name_search');

            // Índice para consultas por email y escuela (evitar duplicados)
            $table->index(['email', 'school_id'], 'idx_clients_email_school');

            // Índice para consultas de clientes activos
            $table->index(['active', 'school_id'], 'idx_clients_active_school');
        });

        // MONITORS - Monitores/Instructores
        Schema::table('monitors', function (Blueprint $table) {
            // Índice para consultas de monitores activos por escuela
            $table->index(['active_school', 'active'], 'idx_monitors_school_active');

            // Índice para búsquedas por nombre
            $table->index(['first_name', 'last_name'], 'idx_monitors_name_search');
        });

        // MONITOR_NWDS - Días no laborables de monitores
        Schema::table('monitor_nwds', function (Blueprint $table) {
            // Índice para consultas de disponibilidad por monitor y fecha
            $table->index(['monitor_id', 'date'], 'idx_monitor_nwds_monitor_date');

            // Índice para consultas por rango de fechas
            $table->index(['date', 'monitor_id'], 'idx_monitor_nwds_date_monitor');
        });

        // PAYMENTS - Pagos del sistema
        Schema::table('payments', function (Blueprint $table) {
            // Índice para consultas por reserva y estado
            $table->index(['booking_id', 'status'], 'idx_payments_booking_status');

            // Índice para analíticas de pagos por fecha
            $table->index(['created_at', 'status', 'amount'], 'idx_payments_analytics');
        });

        // VOUCHERS - Sistema de cupones
        Schema::table('vouchers', function (Blueprint $table) {
            // Índice para búsquedas por código y escuela
            $table->index(['code', 'school_id'], 'idx_vouchers_code_school');

            // Índice para consultas de vouchers activos
            $table->index(['active', 'school_id', 'valid_until'], 'idx_vouchers_active');
        });

        // EMAIL_LOGS - Logs de emails enviados
        Schema::table('email_logs', function (Blueprint $table) {
            // Índice para consultas por reserva
            $table->index(['booking_id', 'status'], 'idx_email_logs_booking');

            // Índice para consultas por fecha de envío
            $table->index(['created_at', 'status'], 'idx_email_logs_sent');
        });*/
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // BOOKING_USERS
        Schema::table('booking_users', function (Blueprint $table) {
            $table->dropIndex('idx_booking_users_client_date_status');
            $table->dropIndex('idx_booking_users_course_monitor');
            $table->dropIndex('idx_booking_users_date_range');
            $table->dropIndex('idx_booking_users_group_status');
            $table->dropIndex('idx_booking_users_school_date');
        });

        // BOOKINGS
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('idx_bookings_school_status_created');
            $table->dropIndex('idx_bookings_payment_status');
            $table->dropIndex('idx_bookings_client_status');
            $table->dropIndex('idx_bookings_analytics');
        });

        // COURSES
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex('idx_courses_school_active_type');
            $table->dropIndex('idx_courses_sport_school');
            $table->dropIndex('idx_courses_date_range');
            $table->dropIndex('idx_courses_school_name');
        });

        // COURSE_DATES
        Schema::table('course_dates', function (Blueprint $table) {
            $table->dropIndex('idx_course_dates_course_date');
            $table->dropIndex('idx_course_dates_availability');
        });

        // CLIENTS
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('idx_clients_name_search');
            $table->dropIndex('idx_clients_email_school');
            $table->dropIndex('idx_clients_active_school');
        });

        // MONITORS
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropIndex('idx_monitors_school_active');
            $table->dropIndex('idx_monitors_name_search');
        });

        // MONITOR_NWDS
        Schema::table('monitor_nwds', function (Blueprint $table) {
            $table->dropIndex('idx_monitor_nwds_monitor_date');
            $table->dropIndex('idx_monitor_nwds_date_monitor');
        });

        // PAYMENTS
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_booking_status');
            $table->dropIndex('idx_payments_analytics');
        });

        // VOUCHERS
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex('idx_vouchers_code_school');
            $table->dropIndex('idx_vouchers_active');
        });

        // EMAIL_LOGS
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropIndex('idx_email_logs_booking');
            $table->dropIndex('idx_email_logs_sent');
        });
    }
};
