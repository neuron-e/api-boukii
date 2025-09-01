<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('seasons')) {
            return;
        }
        Schema::table('seasons', function (Blueprint $table) {
            if (!Schema::hasColumn('seasons', 'is_current')) {
                $table->boolean('is_current')->default(false)->after('is_active');
            }
            
            if (!Schema::hasColumn('seasons', 'is_historical')) {
                $table->boolean('is_historical')->default(false)->after('is_current');
            }
        });

        // Agregar índices para mejorar performance
        if (Schema::hasTable('seasons') && !$this->indexExists('seasons', 'idx_seasons_current_active')) {
            Schema::table('seasons', function (Blueprint $table) {
                $table->index(['school_id', 'is_current', 'is_active'], 'idx_seasons_current_active');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('seasons')) {
            return;
        }
        Schema::table('seasons', function (Blueprint $table) {
            if ($this->indexExists('seasons', 'idx_seasons_current_active')) {
                $table->dropIndex('idx_seasons_current_active');
            }
            
            if (Schema::hasColumn('seasons', 'is_historical')) {
                $table->dropColumn('is_historical');
            }
            
            if (Schema::hasColumn('seasons', 'is_current')) {
                $table->dropColumn('is_current');
            }
        });
    }

    /**
     * Check if an index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        try {
            $connection = Schema::getConnection();
            $doctrineSchemaManager = $connection->getDoctrineSchemaManager();
            $doctrineTable = $doctrineSchemaManager->listTableDetails($table);
            
            return $doctrineTable->hasIndex($index);
        } catch (\Exception $e) {
            return false;
        }
    }
};
