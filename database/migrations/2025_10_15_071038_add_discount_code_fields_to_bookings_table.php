<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->bigInteger('discount_code_id')->nullable()->after('price_reduction')
                ->comment('ID del código de descuento aplicado');

            $table->decimal('discount_code_value', 10, 2)->nullable()->after('discount_code_id')
                ->comment('Valor del descuento aplicado del código');

            // Foreign key comentada temporalmente
            // $table->foreign('discount_code_id')
            //     ->references('id')
            //     ->on('discounts_codes')
            //     ->onDelete('set null');

            $table->index('discount_code_id', 'idx_booking_discount_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('idx_booking_discount_code');
            // $table->dropForeign(['discount_code_id']);
            $table->dropColumn(['discount_code_id', 'discount_code_value']);
        });
    }
};
