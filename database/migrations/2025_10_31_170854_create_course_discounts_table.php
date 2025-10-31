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
        Schema::create('course_discounts', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('course_id'); // Signed - matches courses.id

            // Información del descuento
            $table->string('name', 100)
                  ->comment('Nombre descriptivo del descuento (ej: "Descuento por reserva anticipada")');
            $table->text('description')->nullable();

            // Tipo y valor del descuento
            $table->enum('discount_type', ['percentage', 'fixed_amount'])->default('percentage');
            $table->decimal('discount_value', 10, 2)
                  ->comment('Porcentaje (0-100) o cantidad fija según discount_type');

            // Condiciones de aplicación
            $table->integer('min_days')->nullable()
                  ->comment('Mínimo de días de reserva para aplicar descuento global');

            // Validez temporal (opcional)
            $table->date('valid_from')->nullable()
                  ->comment('Fecha de inicio de validez del descuento');
            $table->date('valid_to')->nullable()
                  ->comment('Fecha de fin de validez del descuento');

            // Prioridad (para múltiples descuentos aplicables)
            $table->integer('priority')->default(0)
                  ->comment('Mayor número = mayor prioridad. Usado cuando varios descuentos aplican.');

            // Estado
            $table->boolean('active')->default(true);

            $table->timestamps();

            // Índices
            $table->index(['course_id', 'active'], 'idx_course_discount_active');
            $table->index(['active', 'priority'], 'idx_discount_active_priority');
            $table->index('min_days', 'idx_discount_min_days');

            // Foreign keys
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_discounts');
    }
};
