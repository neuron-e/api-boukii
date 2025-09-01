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
        // Verificar si la tabla user_season_roles existe, si no, crearla
        if (!Schema::hasTable('user_season_roles')) {
            Schema::create('user_season_roles', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('season_id');
                $table->string('role');
                $table->boolean('is_active')->default(true);
                $table->timestamp('assigned_at')->nullable();
                $table->unsignedBigInteger('assigned_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->primary(['user_id', 'season_id', 'role']);
                // Use indexes only to avoid FK issues across environments
                $table->index('user_id');
                $table->index('season_id');
                $table->index('role');
                $table->index(['user_id', 'is_active'], 'idx_user_season_active');
            });
        }

        // Agregar campos adicionales si no existen
        Schema::table('user_season_roles', function (Blueprint $table) {
            if (!Schema::hasColumn('user_season_roles', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('role');
            }

            if (!Schema::hasColumn('user_season_roles', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('is_active');
            }

            if (!Schema::hasColumn('user_season_roles', 'assigned_by')) {
                $table->unsignedBigInteger('assigned_by')->nullable()->after('assigned_at');
                $table->index('assigned_by');
            }

            if (!Schema::hasColumn('user_season_roles', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Agregar índices adicionales
        if (!$this->indexExists('user_season_roles', 'idx_user_season_active')) {
            Schema::table('user_season_roles', function (Blueprint $table) {
                $table->index(['user_id', 'is_active'], 'idx_user_season_active');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_season_roles', function (Blueprint $table) {
            if (Schema::hasColumn('user_season_roles', 'assigned_by')) {
                $table->dropForeign(['assigned_by']);
                $table->dropColumn('assigned_by');
            }
            
            if (Schema::hasColumn('user_season_roles', 'assigned_at')) {
                $table->dropColumn('assigned_at');
            }
            
            if (Schema::hasColumn('user_season_roles', 'is_active')) {
                $table->dropColumn('is_active');
            }
            
            if ($this->indexExists('user_season_roles', 'idx_user_season_active')) {
                $table->dropIndex('idx_user_season_active');
            }
        });
    }

    /**
     * Check if an index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $doctrineSchemaManager = $connection->getDoctrineSchemaManager();
        $doctrineTable = $doctrineSchemaManager->listTableDetails($table);
        
        return $doctrineTable->hasIndex($index);
    }
};
