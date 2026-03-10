<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
                $table->foreign('deposit_payment_id')
                      ->references('id')->on('payments')
                      ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rental_reservations') && Schema::hasColumn('rental_reservations', 'deposit_payment_id')) {
            Schema::table('rental_reservations', function (Blueprint $table) {
                $table->dropForeign(['deposit_payment_id']);
                $table->dropColumn('deposit_payment_id');
            });
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'payment_type')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('payment_type');
            });
        }
    }
};
