<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBookingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->bigInteger('id')->primary();
            $table->bigInteger('school_id');
            $table->bigInteger('client_main_id')->nullable();
            $table->decimal('price_total', 8, 2);
            $table->boolean('has_cancellation_insurance')->default(0);
            $table->decimal('price_cancellation_insurance', 8, 2)->default(0.00);
            $table->string('currency', 3)->default('CHF');
            $table->bigInteger('payment_method_id')->nullable()->index('fk_bookings_payment_idx');
            $table->decimal('paid_total', 8, 2)->default(0.00);
            $table->boolean('paid')->default(0);
            $table->text('payrexx_reference')->nullable();
            $table->text('payrexx_transaction')->nullable();
            $table->text('payyo_reference')->nullable();
            $table->text('payyo_transaction')->nullable();
            $table->boolean('attendance')->default(1);
            $table->boolean('payrexx_refund')->default(0);
            $table->string('notes', 500)->default('');
            $table->integer('paxes')->default(0);
            $table->string('color', 45)->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
            
            $table->foreign('school_id', 'bookings_ibfk_1')->references('id')->on('schools');
            $table->foreign('client_main_id', 'bookings_ibfk_2')->references('id')->on('clients');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bookings');
    }
}
