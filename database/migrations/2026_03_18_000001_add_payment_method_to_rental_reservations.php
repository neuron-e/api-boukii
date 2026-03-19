<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rental_reservations')) {
            return;
        }

        Schema::table('rental_reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('rental_reservations', 'payment_method')) {
                $table->string('payment_method', 30)->nullable()->after('deposit_status');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('rental_reservations')) {
            return;
        }

        Schema::table('rental_reservations', function (Blueprint $table) {
            if (Schema::hasColumn('rental_reservations', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
        });
    }
};
