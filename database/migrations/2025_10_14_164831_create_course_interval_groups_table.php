<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $courseIdUnsigned = $this->columnIsUnsigned('courses', 'id');
        $courseIntervalIdUnsigned = $this->columnIsUnsigned('course_intervals', 'id');
        $courseGroupIdUnsigned = $this->columnIsUnsigned('course_groups', 'id');

        if (!Schema::hasTable('course_interval_groups')) {
            Schema::create('course_interval_groups', function (Blueprint $table) use ($courseIdUnsigned, $courseIntervalIdUnsigned, $courseGroupIdUnsigned) {
                $table->id();
                $courseIdUnsigned
                    ? $table->unsignedBigInteger('course_id')
                    : $table->bigInteger('course_id');
                $courseIntervalIdUnsigned
                    ? $table->unsignedBigInteger('course_interval_id')
                    : $table->bigInteger('course_interval_id');
                $courseGroupIdUnsigned
                    ? $table->unsignedBigInteger('course_group_id')
                    : $table->bigInteger('course_group_id');

                // Configuración específica para este intervalo
                $table->integer('max_participants')->nullable()
                      ->comment('Override del max_participants del grupo base. Null = usar del grupo');

                // Estado
                $table->boolean('active')->default(true);

                $table->timestamps();

                // Índices
                $table->index(['course_interval_id', 'course_group_id'], 'idx_interval_group');
                $table->unique(['course_interval_id', 'course_group_id'], 'unique_interval_group');

                // Foreign keys
                $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
                $table->foreign('course_interval_id')->references('id')->on('course_intervals')->onDelete('cascade');
                $table->foreign('course_group_id')->references('id')->on('course_groups')->onDelete('cascade');
            });
        }

        $this->ensureBigIntegerColumn('course_interval_groups', 'course_id', $courseIdUnsigned);
        $this->ensureBigIntegerColumn('course_interval_groups', 'course_interval_id', $courseIntervalIdUnsigned);
        $this->ensureBigIntegerColumn('course_interval_groups', 'course_group_id', $courseGroupIdUnsigned);

        $this->ensureForeignKey(
            'course_interval_groups',
            'course_interval_groups_course_id_foreign',
            'course_id',
            'courses',
            'id'
        );
        $this->ensureForeignKey(
            'course_interval_groups',
            'course_interval_groups_course_interval_id_foreign',
            'course_interval_id',
            'course_intervals',
            'id'
        );
        $this->ensureForeignKey(
            'course_interval_groups',
            'course_interval_groups_course_group_id_foreign',
            'course_group_id',
            'course_groups',
            'id'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_interval_groups');
    }

    private function columnIsUnsigned(string $table, string $column): bool
    {
        $columnInfo = $this->getColumnInformation($table, $column);

        if (!$columnInfo || !property_exists($columnInfo, 'COLUMN_TYPE')) {
            return true;
        }

        return str_contains(strtolower($columnInfo->COLUMN_TYPE), 'unsigned');
    }

    private function ensureBigIntegerColumn(string $table, string $column, bool $unsigned): void
    {
        $columnInfo = $this->getColumnInformation($table, $column);

        if (!$columnInfo) {
            return;
        }

        $currentUnsigned = str_contains(strtolower($columnInfo->COLUMN_TYPE), 'unsigned');
        if ($currentUnsigned === $unsigned) {
            return;
        }

        $nullability = (property_exists($columnInfo, 'IS_NULLABLE') && strtoupper($columnInfo->IS_NULLABLE) === 'YES')
            ? 'NULL'
            : 'NOT NULL';

        $type = $unsigned ? 'BIGINT UNSIGNED' : 'BIGINT';

        DB::statement(sprintf(
            'ALTER TABLE `%s` MODIFY `%s` %s %s',
            $table,
            $column,
            $type,
            $nullability
        ));
    }

    private function ensureForeignKey(string $table, string $constraint, string $column, string $referencesTable, string $referencesColumn, string $onDelete = 'cascade'): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column) || !Schema::hasTable($referencesTable)) {
            return;
        }

        $exists = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = ?',
            [$table, $constraint]
        );

        if ($exists) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($constraint, $column, $referencesTable, $referencesColumn, $onDelete) {
            $table->foreign($column, $constraint)
                ->references($referencesColumn)
                ->on($referencesTable)
                ->onDelete($onDelete);
        });
    }

    private function getColumnInformation(string $table, string $column): ?object
    {
        if (!Schema::hasTable($table)) {
            return null;
        }

        return DB::selectOne(
            'SELECT COLUMN_TYPE, IS_NULLABLE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, $column]
        );
    }
};
