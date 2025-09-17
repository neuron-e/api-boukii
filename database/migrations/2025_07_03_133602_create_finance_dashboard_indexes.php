<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // 1. Índice principal para bookings
      /*  Schema::table('bookings', function (Blueprint $table) {
            //$table->index(['school_id', 'created_at', 'deleted_at'], 'idx_bookings_school_created');
         $table->index(['school_id', 'status', 'price_expected', 'source'], 'idx_bookings_financial');
        });

        // 2. Índice para booking_users
        Schema::table('booking_users', function (Blueprint $table) {
            $table->index(['booking_id', 'date', 'course_id'], 'idx_booking_users_booking_date');
        });

        // 3. Índice para clients
        Schema::table('clients', function (Blueprint $table) {
            $table->index(['school_id', 'email', 'name'], 'idx_clients_test_detection');
        });

        // 4. Índice para payments
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['booking_id', 'type', 'amount', 'deleted_at'], 'idx_payments_booking_type');
        });

        // 5. Índice para seasons
        Schema::table('seasons', function (Blueprint $table) {
            $table->index(['school_id', 'start_date', 'end_date'], 'idx_seasons_school_dates');
        });*/
    }

    public function down()
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('idx_bookings_school_created');
/*            $table->dropIndex('idx_bookings_financial');*/
        });

        Schema::table('booking_users', function (Blueprint $table) {
            $table->dropIndex('idx_booking_users_booking_date');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('idx_clients_test_detection');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_booking_type');
        });

        Schema::table('seasons', function (Blueprint $table) {
            $table->dropIndex('idx_seasons_school_dates');
        });
    }
};
