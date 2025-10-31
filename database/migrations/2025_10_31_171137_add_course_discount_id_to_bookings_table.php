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
            // Agregar campo para descuento global del curso
            $table->unsignedBigInteger('course_discount_id')->nullable()->after('interval_discount_id');

            // Índice para búsquedas
            $table->index('course_discount_id', 'idx_bookings_course_discount');

            // Foreign key
            $table->foreign('course_discount_id')->references('id')->on('course_discounts')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['course_discount_id']);
            $table->dropIndex('idx_bookings_course_discount');
            $table->dropColumn('course_discount_id');
        });
    }
};
