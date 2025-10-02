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
            // Nuevo campo: modo de configuración de intervalos
            // 'unified' = Configuración global para todos los intervalos (comportamiento actual V3)
            // 'independent' = Cada intervalo tiene su propia configuración (nueva funcionalidad V4)
            $table->enum('intervals_config_mode', ['unified', 'independent'])
                  ->default('unified')
                  ->after('is_flexible')
                  ->comment('Modo de configuración de intervalos: unified (global) o independent (por intervalo)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('intervals_config_mode');
        });
    }
};
