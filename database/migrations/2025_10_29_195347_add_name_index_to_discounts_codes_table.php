<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Añade índice en el campo 'name' para optimizar búsquedas
     * por nombre de código de descuento en el admin panel.
     */
    public function up(): void
    {
        Schema::table('discounts_codes', function (Blueprint $table) {
            // Verificar que el índice no exista antes de crearlo
            if (!$this->indexExists('discounts_codes', 'idx_name')) {
                $table->index('name', 'idx_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discounts_codes', function (Blueprint $table) {
            if ($this->indexExists('discounts_codes', 'idx_name')) {
                $table->dropIndex('idx_name');
            }
        });
    }

    /**
     * Verifica si un índice existe en la tabla
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = \DB::select("SHOW INDEXES FROM {$table} WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
};
