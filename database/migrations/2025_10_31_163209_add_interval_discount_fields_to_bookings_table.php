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
            // Tipo de descuento aplicado: 'interval' | 'promo_code' | null
            $table->string('discount_type', 50)->nullable()->after('discount_code_value');

            // ID del descuento de intervalo aplicado (para auditoría)
            $table->unsignedBigInteger('interval_discount_id')->nullable()->after('discount_type');

            // Precio original antes de descuentos (para transparencia)
            $table->decimal('original_price', 10, 2)->nullable()->after('interval_discount_id');

            // Monto del descuento aplicado (positivo)
            $table->decimal('discount_amount', 10, 2)->nullable()->after('original_price');

            // Precio final después del descuento
            $table->decimal('final_price', 10, 2)->nullable()->after('discount_amount');

            // Índice para mejorar consultas por tipo de descuento
            $table->index('discount_type', 'idx_bookings_discount_type');

            // Índice para consultas por descuento de intervalo
            $table->index('interval_discount_id', 'idx_bookings_interval_discount');

            // Foreign key para garantizar integridad referencial (opcional, puede ser null)
            $table->foreign('interval_discount_id', 'fk_bookings_interval_discount')
                ->references('id')
                ->on('course_interval_discounts')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Eliminar foreign key primero
            $table->dropForeign('fk_bookings_interval_discount');

            // Eliminar índices
            $table->dropIndex('idx_bookings_discount_type');
            $table->dropIndex('idx_bookings_interval_discount');

            // Eliminar columnas
            $table->dropColumn([
                'discount_type',
                'interval_discount_id',
                'original_price',
                'discount_amount',
                'final_price'
            ]);
        });
    }
};
