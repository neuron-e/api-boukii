<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->text('payyo_reference')->nullable()->after('payrexx_reference');
            $table->text('payyo_transaction')->nullable()->after('payrexx_transaction');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('payyo_reference')->nullable()->after('payrexx_reference');
            $table->string('payyo_transaction')->nullable()->after('payrexx_transaction');
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->text('payyo_reference')->nullable()->after('payrexx_reference');
            $table->text('payyo_transaction')->nullable()->after('payrexx_transaction');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['payyo_reference', 'payyo_transaction']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['payyo_reference', 'payyo_transaction']);
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn(['payyo_reference', 'payyo_transaction']);
        });
    }
};
