<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds deposit_payment_id to rental_reservations so the deposit charge
 * is tracked as a real payment record (separate from the main rental payment).
 *
 * Also adds payment_type to payments to distinguish:
 *   'rental'  — main rental payment
 *   'deposit' — deposit hold/charge
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. payments — add payment_type discriminator
        if (Schema::hasTable('payments') && !Schema::hasColumn('payments', 'payment_type')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('payment_type', 20)->default('rental')->after('payment_method');
            });
        }

        // 2. rental_reservations — add FK to the deposit payment record
        if (Schema::hasTable('rental_reservations') && !Schema::hasColumn('rental_reservations', 'deposit_payment_id')) {
            Schema::table('rental_reservations', function (Blueprint $table) {
                $table->unsignedBigInteger('deposit_payment_id')->nullable()->after('payment_id');
            });

            $this->tryAddForeignKey(
                'rental_reservations',
                'deposit_payment_id',
                'payments',
                'id',
                'set null',
                'rental_reservations_deposit_payment_id_foreign'
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rental_reservations') && Schema::hasColumn('rental_reservations', 'deposit_payment_id')) {
            $this->dropForeignKeyIfExists(
                'rental_reservations',
                'deposit_payment_id',
                'rental_reservations_deposit_payment_id_foreign'
            );

            Schema::table('rental_reservations', function (Blueprint $table) {
                $table->dropColumn('deposit_payment_id');
            });
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'payment_type')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('payment_type');
            });
        }
    }

    private function tryAddForeignKey(
        string $table,
        string $column,
        string $referencedTable,
        string $referencedColumn,
        string $onDelete,
        string $constraintName
    ): void {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column) || !Schema::hasTable($referencedTable)) {
            return;
        }

        $database = DB::getDatabaseName();
        $exists = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->where('CONSTRAINT_NAME', $constraintName)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();

        if ($exists) {
            return;
        }

        try {
            DB::statement(sprintf(
                'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`) ON DELETE %s',
                $table,
                $constraintName,
                $column,
                $referencedTable,
                $referencedColumn,
                strtoupper($onDelete)
            ));
        } catch (\Throwable $e) {
            // Legacy production schemas may not accept the FK cleanly. Keep the column and continue safely.
        }
    }

    private function dropForeignKeyIfExists(string $table, string $column, string $constraintName): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        $database = DB::getDatabaseName();
        $exists = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->where('CONSTRAINT_NAME', $constraintName)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();

        if (!$exists) {
            return;
        }

        try {
            DB::statement(sprintf(
                'ALTER TABLE `%s` DROP FOREIGN KEY `%s`',
                $table,
                $constraintName
            ));
        } catch (\Throwable $e) {
            // Ignore if already absent or engine-specific state differs.
        }
    }
};
