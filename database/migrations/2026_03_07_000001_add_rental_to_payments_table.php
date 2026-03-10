<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 payment integration:
 * - Makes payments.booking_id nullable so payments can belong to a rental reservation instead
 * - Adds payments.rental_reservation_id (nullable FK)
 * - Adds rental_reservations.booking_id (nullable FK for "cobro conjunto" scenario)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. payments table — make booking_id nullable and add rental_reservation_id
        if (Schema::hasTable('payments')) {
            $this->dropBookingForeignKeys();

            if (Schema::hasColumn('payments', 'booking_id')) {
                $this->makeBookingIdNullablePreservingType();
            }

            Schema::table('payments', function (Blueprint $table) {
                // Re-add FK with SET NULL on delete
                try {
                    $table->foreign('booking_id')
                        ->references('id')->on('bookings')
                        ->onDelete('set null');
                } catch (\Throwable $e) {
                    // FK may already exist — ignore
                }

                // Add rental_reservation_id column
                if (!Schema::hasColumn('payments', 'rental_reservation_id')) {
                    $table->unsignedBigInteger('rental_reservation_id')
                        ->nullable()
                        ->after('booking_id');
                }

                // Add method column for tracking payment source
                if (!Schema::hasColumn('payments', 'payment_method')) {
                    $table->string('payment_method', 30)
                        ->nullable()
                        ->default(null)
                        ->after('rental_reservation_id')
                        ->comment('cash|card|payrexx_link|payrexx_invoice|invoice');
                }
            });

            // Add FK for rental_reservation_id (separate call for safety)
            if (Schema::hasTable('rental_reservations')) {
                try {
                    Schema::table('payments', function (Blueprint $table) {
                        $table->foreign('rental_reservation_id')
                            ->references('id')->on('rental_reservations')
                            ->onDelete('set null');
                    });
                } catch (\Throwable $e) {
                    // FK may already exist — ignore
                }
            }
        }

        // 2. rental_reservations — add booking_id for "cobro conjunto" (optional link to a booking)
        if (Schema::hasTable('rental_reservations') && !Schema::hasColumn('rental_reservations', 'booking_id')) {
            Schema::table('rental_reservations', function (Blueprint $table) {
                $table->unsignedBigInteger('booking_id')
                    ->nullable()
                    ->after('school_id')
                    ->comment('Optional link to a booking for joint billing (cobro conjunto)');
            });
        }

        // 3. Index for fast rental payment lookup
        try {
            Schema::table('payments', function (Blueprint $table) {
                $table->index('rental_reservation_id', 'payments_rental_res_id_idx');
            });
        } catch (\Throwable $e) {
            // Index may already exist — ignore
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payments')) {
            $this->dropBookingForeignKeys();

            Schema::table('payments', function (Blueprint $table) {
                try { $table->dropForeign(['rental_reservation_id']); } catch (\Throwable $e) {}
                try { $table->dropIndex('payments_rental_res_id_idx'); } catch (\Throwable $e) {}
                if (Schema::hasColumn('payments', 'rental_reservation_id')) {
                    $table->dropColumn('rental_reservation_id');
                }
                if (Schema::hasColumn('payments', 'payment_method')) {
                    $table->dropColumn('payment_method');
                }
            });
        }

        if (Schema::hasTable('rental_reservations') && Schema::hasColumn('rental_reservations', 'booking_id')) {
            Schema::table('rental_reservations', function (Blueprint $table) {
                $table->dropColumn('booking_id');
            });
        }
    }

    private function dropBookingForeignKeys(): void
    {
        if (!Schema::hasTable('payments') || !Schema::hasColumn('payments', 'booking_id')) {
            return;
        }

        $database = DB::getDatabaseName();
        $foreignKeys = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->select('CONSTRAINT_NAME')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'payments')
            ->where('COLUMN_NAME', 'booking_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->pluck('CONSTRAINT_NAME');

        foreach ($foreignKeys as $constraint) {
            try {
                DB::statement(sprintf(
                    'ALTER TABLE `payments` DROP FOREIGN KEY `%s`',
                    str_replace('`', '``', $constraint)
                ));
            } catch (\Throwable $e) {
                // Ignore if already removed or managed under a different engine-specific state
            }
        }
    }

    private function makeBookingIdNullablePreservingType(): void
    {
        $database = DB::getDatabaseName();
        $column = DB::table('information_schema.COLUMNS')
            ->select('COLUMN_TYPE')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'payments')
            ->where('COLUMN_NAME', 'booking_id')
            ->first();

        $columnType = $column->COLUMN_TYPE ?? null;
        if (!$columnType) {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE `payments` MODIFY `booking_id` %s NULL',
            $columnType
        ));
    }
};
