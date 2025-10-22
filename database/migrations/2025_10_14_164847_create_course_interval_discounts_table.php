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
        Schema::create('course_interval_discounts', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('course_id'); // Signed - matches courses.id
            $table->unsignedBigInteger('course_interval_id'); // Unsigned - matches course_intervals.id

            // Información del descuento
            $table->string('name', 100);
            $table->text('description')->nullable();

            // Tipo y valor del descuento
            $table->enum('discount_type', ['percentage', 'fixed_amount'])->default('percentage');
            $table->decimal('discount_value', 10, 2)
                  ->comment('Porcentaje (0-100) o cantidad fija según discount_type');

            // Condiciones de aplicación
            $table->integer('min_participants')->nullable()
                  ->comment('Mínimo de participantes para aplicar descuento');
            $table->integer('min_days')->nullable()
                  ->comment('Mínimo de días de reserva para aplicar descuento');

            // Validez temporal (opcional, adicional al intervalo)
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();

            // Prioridad (para múltiples descuentos aplicables)
            $table->integer('priority')->default(0)
                  ->comment('Mayor número = mayor prioridad');

            // Estado
            $table->boolean('active')->default(true);

            $table->timestamps();

            // Índices
            $table->index(['course_id', 'course_interval_id'], 'idx_course_interval_discount');
            $table->index(['active', 'priority'], 'idx_active_priority');

            // Foreign keys
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('course_interval_id')->references('id')->on('course_intervals')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_interval_discounts');
    }
};
