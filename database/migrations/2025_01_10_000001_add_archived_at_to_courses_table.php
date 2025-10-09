<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Permite archivar cursos que tienen reservas (incluso anuladas)
     * sin perder trazabilidad. Un curso archivado:
     * - No aparece en búsquedas normales de cursos activos
     * - No permite nuevas reservas
     * - Mantiene todas las reservas existentes visibles
     * - Puede ser restaurado si es necesario
     *
     * Estados posibles:
     * - Activo: archived_at = null, deleted_at = null
     * - Archivado: archived_at != null, deleted_at = null (gris en UI)
     * - Eliminado: deleted_at != null (soft delete de Laravel)
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('deleted_at');
            $table->index('archived_at'); // Para queries rápidas
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};
