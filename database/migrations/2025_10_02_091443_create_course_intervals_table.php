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
        Schema::create('course_intervals', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('course_id');

            // Información básica del intervalo
            $table->string('name'); // "Semana 1", "Semana 2", etc.
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('display_order')->default(0);

            // Configuración independiente
            $table->enum('config_mode', ['inherit', 'custom'])->default('inherit')
                  ->comment('inherit: hereda config del curso, custom: config propia');

            // Método de generación de fechas (solo si config_mode = 'custom')
            $table->enum('date_generation_method', ['consecutive', 'weekly', 'manual', 'first_day'])
                  ->nullable()
                  ->comment('Método de generación: consecutive, weekly, manual, first_day');

            $table->integer('consecutive_days_count')->nullable()
                  ->comment('Número de días consecutivos (si method = consecutive)');

            $table->json('weekly_pattern')->nullable()
                  ->comment('Patrón semanal: {"monday": true, "tuesday": false, ...}');

            // Configuración de reserva
            $table->enum('booking_mode', ['flexible', 'package'])->default('flexible')
                  ->comment('flexible: cliente elige fechas, package: debe reservar todas las fechas');

            $table->timestamps();

            // Índices
            $table->index(['course_id', 'display_order']);
            $table->index('start_date');
            $table->index('end_date');

            // Foreign key constraint
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_intervals');
    }
};
