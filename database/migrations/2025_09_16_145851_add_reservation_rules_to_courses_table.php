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
        Schema::table('courses', function (Blueprint $table) {
            // Verificar si la columna settings no existe antes de añadirla
            if (!Schema::hasColumn('courses', 'settings')) {
                $table->json('settings')->nullable()->comment('Configuraciones del curso incluyendo reglas de reserva (mustBeConsecutive, mustStartFromFirst, etc.)');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // Solo eliminar si existe la columna
            if (Schema::hasColumn('courses', 'settings')) {
                $table->dropColumn('settings');
            }
        });
    }
};
