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
        Schema::table('discounts_codes', function (Blueprint $table) {
            // Tipo y valor del descuento
            if (!Schema::hasColumn('discounts_codes', 'discount_type')) {
                $table->enum('discount_type', ['percentage', 'fixed_amount'])->default('percentage')
                    ->after('description')
                    ->comment('Tipo: porcentaje o monto fijo');
            }

            if (!Schema::hasColumn('discounts_codes', 'discount_value')) {
                $table->decimal('discount_value', 10, 2)->default(0)
                    ->after('discount_type')
                    ->comment('Valor del descuento (% o monto fijo según tipo)');
            }

            // Límites de uso
            if (!Schema::hasColumn('discounts_codes', 'max_uses_per_user')) {
                $table->integer('max_uses_per_user')->default(1)
                    ->after('remaining')
                    ->comment('Máximo de veces que un usuario puede usar este código');
            }

            // Vigencia
            if (!Schema::hasColumn('discounts_codes', 'valid_from')) {
                $table->dateTime('valid_from')->nullable()
                    ->after('max_uses_per_user')
                    ->comment('Fecha/hora desde la cual el código es válido');
            }

            if (!Schema::hasColumn('discounts_codes', 'valid_to')) {
                $table->dateTime('valid_to')->nullable()
                    ->after('valid_from')
                    ->comment('Fecha/hora hasta la cual el código es válido');
            }

            // Restricciones por entidad
            if (!Schema::hasColumn('discounts_codes', 'sport_ids')) {
                $table->json('sport_ids')->nullable()
                    ->after('school_id')
                    ->comment('IDs de deportes aplicables (null = todos)');
            }

            if (!Schema::hasColumn('discounts_codes', 'course_ids')) {
                $table->json('course_ids')->nullable()
                    ->after('sport_ids')
                    ->comment('IDs de cursos específicos (null = todos)');
            }

            if (!Schema::hasColumn('discounts_codes', 'degree_ids')) {
                $table->json('degree_ids')->nullable()
                    ->after('course_ids')
                    ->comment('IDs de niveles/grados (null = todos)');
            }

            // Restricciones de monto
            if (!Schema::hasColumn('discounts_codes', 'min_purchase_amount')) {
                $table->decimal('min_purchase_amount', 10, 2)->nullable()
                    ->after('valid_to')
                    ->comment('Monto mínimo de compra para aplicar el descuento');
            }

            if (!Schema::hasColumn('discounts_codes', 'max_discount_amount')) {
                $table->decimal('max_discount_amount', 10, 2)->nullable()
                    ->after('min_purchase_amount')
                    ->comment('Descuento máximo aplicable (útil para % altos)');
            }

            // Estado
            if (!Schema::hasColumn('discounts_codes', 'active')) {
                $table->boolean('active')->default(true)
                    ->after('max_discount_amount')
                    ->comment('Si el código está activo y puede ser usado');
            }

            // Metadatos
            if (!Schema::hasColumn('discounts_codes', 'created_by')) {
                $table->string('created_by')->nullable()
                    ->after('active')
                    ->comment('Usuario/admin que creó el código');
            }

            if (!Schema::hasColumn('discounts_codes', 'notes')) {
                $table->text('notes')->nullable()
                    ->after('created_by')
                    ->comment('Notas internas sobre el código');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discounts_codes', function (Blueprint $table) {
            $columns = ['discount_type', 'discount_value', 'max_uses_per_user', 'valid_from', 'valid_to',
                        'sport_ids', 'course_ids', 'degree_ids', 'min_purchase_amount', 'max_discount_amount',
                        'active', 'created_by', 'notes'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('discounts_codes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
