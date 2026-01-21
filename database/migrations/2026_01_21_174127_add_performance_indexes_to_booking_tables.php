<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds performance indexes for optimized endpoints:
     * - season-dashboard query
     * - booking-users monitor query
     * - booking-users index query
     */
    public function up(): void
    {
        // Índice compuesto para booking_users - consultas de monitor
        Schema::table('booking_users', function (Blueprint $table) {
            // Verificar si el índice ya existe antes de crearlo
            $indexName = 'idx_booking_users_monitor_school_deleted';
            $indexes = DB::select("SHOW INDEX FROM booking_users WHERE Key_name = ?", [$indexName]);

            if (empty($indexes)) {
                $table->index(['monitor_id', 'school_id', 'deleted_at'], $indexName);
            }

            // Índice para filtros por fecha
            $indexName2 = 'idx_booking_users_date_deleted';
            $indexes2 = DB::select("SHOW INDEX FROM booking_users WHERE Key_name = ?", [$indexName2]);

            if (empty($indexes2)) {
                $table->index(['date', 'deleted_at'], $indexName2);
            }
        });

        // Índice compuesto para bookings - dashboard de temporada
        Schema::table('bookings', function (Blueprint $table) {
            $indexName = 'idx_bookings_school_created_deleted';
            $indexes = DB::select("SHOW INDEX FROM bookings WHERE Key_name = ?", [$indexName]);

            if (empty($indexes)) {
                $table->index(['school_id', 'created_at', 'deleted_at'], $indexName);
            }

            // Índice para status y paid (revenue queries)
            $indexName2 = 'idx_bookings_school_status_paid';
            $indexes2 = DB::select("SHOW INDEX FROM bookings WHERE Key_name = ?", [$indexName2]);

            if (empty($indexes2)) {
                $table->index(['school_id', 'status', 'paid'], $indexName2);
            }
        });

        // Índice para payments - dashboard de temporada
        Schema::table('payments', function (Blueprint $table) {
            $indexName = 'idx_payments_booking_status_created';
            $indexes = DB::select("SHOW INDEX FROM payments WHERE Key_name = ?", [$indexName]);

            if (empty($indexes)) {
                $table->index(['booking_id', 'status', 'created_at'], $indexName);
            }
        });

        // Índice para client_sports - si se usa en el futuro
        if (Schema::hasTable('client_sports')) {
            Schema::table('client_sports', function (Blueprint $table) {
                $indexName = 'idx_client_sports_client_sport_degree';
                $indexes = DB::select("SHOW INDEX FROM client_sports WHERE Key_name = ?", [$indexName]);

                if (empty($indexes)) {
                    $table->index(['client_id', 'sport_id', 'degree_id'], $indexName);
                }
            });
        }

        \Log::info('PERFORMANCE INDEXES CREATED', [
            'migration' => 'add_performance_indexes_to_booking_tables',
            'indexes_created' => [
                'booking_users' => ['idx_booking_users_monitor_school_deleted', 'idx_booking_users_date_deleted'],
                'bookings' => ['idx_bookings_school_created_deleted', 'idx_bookings_school_status_paid'],
                'payments' => ['idx_payments_booking_status_created'],
                'client_sports' => ['idx_client_sports_client_sport_degree']
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_users', function (Blueprint $table) {
            $table->dropIndex('idx_booking_users_monitor_school_deleted');
            $table->dropIndex('idx_booking_users_date_deleted');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('idx_bookings_school_created_deleted');
            $table->dropIndex('idx_bookings_school_status_paid');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_booking_status_created');
        });

        if (Schema::hasTable('client_sports')) {
            Schema::table('client_sports', function (Blueprint $table) {
                $table->dropIndex('idx_client_sports_client_sport_degree');
            });
        }
    }
};
