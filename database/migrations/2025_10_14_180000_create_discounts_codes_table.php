<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Sistema de códigos de descuento promocionales.
     * Permite crear códigos que los clientes pueden usar en el booking flow
     * para obtener descuentos en sus reservas.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('discounts_codes', function (Blueprint $table) {
            $table->id();

            // Información básica del código
            $table->string('code', 50)->unique()
                ->comment('Código promocional único (ej: VERANO2025, WELCOME10)');

            $table->string('description', 255)->nullable()
                ->comment('Descripción interna del código');

            // Tipo y valor del descuento
            $table->enum('discount_type', ['percentage', 'fixed_amount'])->default('percentage')
                ->comment('Tipo: porcentaje o monto fijo');

            $table->decimal('discount_value', 10, 2)
                ->comment('Valor del descuento (% o monto fijo según tipo)');

            $table->decimal('quantity', 10, 2)->nullable()
                ->comment('DEPRECATED: Usar discount_value en su lugar');

            $table->decimal('percentage', 10, 2)->nullable()
                ->comment('DEPRECATED: Usar discount_value en su lugar');

            // Límites de uso
            $table->integer('total')->nullable()
                ->comment('Total de usos permitidos (null = ilimitado)');

            $table->integer('remaining')->nullable()
                ->comment('Usos restantes');

            $table->integer('max_uses_per_user')->default(1)
                ->comment('Máximo de veces que un usuario puede usar este código');

            // Vigencia
            $table->dateTime('valid_from')->nullable()
                ->comment('Fecha/hora desde la cual el código es válido');

            $table->dateTime('valid_to')->nullable()
                ->comment('Fecha/hora hasta la cual el código es válido');

            // Restricciones por entidad
            $table->bigInteger('school_id')->nullable()
                ->comment('Escuela específica (null = todas las escuelas)');

            $table->json('sport_ids')->nullable()
                ->comment('IDs de deportes aplicables (null = todos)');

            $table->json('course_ids')->nullable()
                ->comment('IDs de cursos específicos (null = todos)');

            $table->json('degree_ids')->nullable()
                ->comment('IDs de niveles/grados (null = todos)');

            // Restricciones de monto
            $table->decimal('min_purchase_amount', 10, 2)->nullable()
                ->comment('Monto mínimo de compra para aplicar el descuento');

            $table->decimal('max_discount_amount', 10, 2)->nullable()
                ->comment('Descuento máximo aplicable (útil para % altos)');

            // Estado
            $table->boolean('active')->default(true)
                ->comment('Si el código está activo y puede ser usado');

            // Metadatos
            $table->string('created_by')->nullable()
                ->comment('Usuario/admin que creó el código');

            $table->text('notes')->nullable()
                ->comment('Notas internas sobre el código');

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index(['active', 'valid_from', 'valid_to'], 'idx_active_valid');
            $table->index('school_id', 'idx_school');
            $table->index(['code', 'active'], 'idx_code_active');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('discounts_codes');
    }
};
