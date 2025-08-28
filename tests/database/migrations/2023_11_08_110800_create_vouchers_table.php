<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVouchersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->bigInteger('id')->primary();
            $table->string('code');
            $table->double('quantity', 15, 2);
            $table->double('remaining_balance', 15, 2);
            $table->boolean('payed')->default(0);
            $table->boolean('is_gift')->default(0);
            $table->bigInteger('client_id');
            $table->bigInteger('school_id');
            $table->text('payrexx_reference')->nullable();
            $table->text('payrexx_transaction')->nullable();
            $table->text('payyo_reference')->nullable();
            $table->text('payyo_transaction')->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();

            $table->foreign('client_id', 'vouchers_ibfk_1')->references('id')->on('clients');
            $table->foreign('school_id', 'vouchers_ibfk_2')->references('id')->on('schools');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vouchers');
    }
}
