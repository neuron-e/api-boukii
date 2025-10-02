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
        Schema::table('course_dates', function (Blueprint $table) {
            // Vincular cada fecha con su intervalo (nullable para retrocompatibilidad)
            $table->unsignedBigInteger('course_interval_id')
                  ->nullable()
                  ->after('course_id')
                  ->comment('Intervalo al que pertenece esta fecha (null = curso sin intervalos)');

            // Índice para mejorar performance en consultas
            $table->index('course_interval_id');

            // Foreign key constraint
            $table->foreign('course_interval_id')
                  ->references('id')
                  ->on('course_intervals')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_dates', function (Blueprint $table) {
            $table->dropForeign(['course_interval_id']);
            $table->dropIndex(['course_interval_id']);
            $table->dropColumn('course_interval_id');
        });
    }
};
