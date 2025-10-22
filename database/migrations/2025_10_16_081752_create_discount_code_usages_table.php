<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabla para trackear el uso de códigos de descuento por usuario.
     * Permite implementar límites de uso por usuario (max_uses_per_user).
     */
    public function up(): void
    {
        Schema::create('discount_code_usages', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->unsignedBigInteger('discount_code_id')->comment('ID del código de descuento');
            $table->unsignedBigInteger('user_id')->comment('ID del usuario que usó el código');
            $table->unsignedBigInteger('booking_id')->nullable()->comment('ID de la reserva asociada');

            // Información del uso
            $table->decimal('discount_amount', 10, 2)->comment('Monto del descuento aplicado');
            $table->timestamp('used_at')->useCurrent()->comment('Fecha y hora de uso');

            $table->timestamps();

            // Índices para optimizar consultas
            $table->index(['discount_code_id', 'user_id'], 'idx_code_user');
            $table->index('booking_id', 'idx_booking');
            $table->index('used_at', 'idx_used_at');

            // Especificar engine, charset y collation para compatibilidad con tablas existentes
            $table->engine = 'InnoDB';
            $table->charset = 'latin1';
            $table->collation = 'latin1_swedish_ci';
        });

        // NOTA: Foreign keys comentadas temporalmente debido a problemas de compatibilidad
        // con la tabla discounts_codes existente (diferente estructura en DB vs migración)
        // Las relaciones funcionarán a nivel de aplicación mediante Eloquent

        // Schema::table('discount_code_usages', function (Blueprint $table) {
        //     $table->foreign('discount_code_id')
        //           ->references('id')
        //           ->on('discounts_codes')
        //           ->onDelete('cascade');

        //     $table->foreign('user_id')
        //           ->references('id')
        //           ->on('users')
        //           ->onDelete('cascade');

        //     $table->foreign('booking_id')
        //           ->references('id')
        //           ->on('bookings')
        //           ->onDelete('set null');
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discount_code_usages');
    }
};
