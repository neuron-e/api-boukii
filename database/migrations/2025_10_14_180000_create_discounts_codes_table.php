<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Sistema de códigos de descuento promocionales.
     * Permite crear códigos que los clientes pueden usar en el booking flow
     * para obtener descuentos en sus reservas.
     *
     * @return void
     */
    public function up(): void
    {
        if (!Schema::hasTable('discounts_codes')) {
            Schema::create('discounts_codes', function (Blueprint $table) {
                $table->id();

                // Información básica del código
                $table->string('code', 50)->unique()
                    ->comment('Código promocional único (ej: VERANO2025, WELCOME10)');

                $table->string('description', 255)->nullable()
                    ->comment('Descripción interna del código');

                // Tipo y valor del descuento
                $table->enum('discount_type', ['percentage', 'fixed_amount'])->default('percentage')
                    ->comment('Tipo: porcentaje o monto fijo');

                $table->decimal('discount_value', 10, 2)
                    ->comment('Valor del descuento (% o monto fijo según tipo)');

                $table->decimal('quantity', 10, 2)->nullable()
                    ->comment('DEPRECATED: Usar discount_value en su lugar');

                $table->decimal('percentage', 10, 2)->nullable()
                    ->comment('DEPRECATED: Usar discount_value en su lugar');

                // Límites de uso
                $table->integer('total')->nullable()
                    ->comment('Total de usos permitidos (null = ilimitado)');

                $table->integer('remaining')->nullable()
                    ->comment('Usos restantes');

                $table->integer('max_uses_per_user')->default(1)
                    ->comment('Máximo de veces que un usuario puede usar este código');

                // Vigencia
                $table->dateTime('valid_from')->nullable()
                    ->comment('Fecha/hora desde la cual el código es válido');

                $table->dateTime('valid_to')->nullable()
                    ->comment('Fecha/hora hasta la cual el código es válido');

                // Restricciones por entidad
                $table->bigInteger('school_id')->nullable()
                    ->comment('Escuela específica (null = todas las escuelas)');

                $table->json('sport_ids')->nullable()
                    ->comment('IDs de deportes aplicables (null = todos)');

                $table->json('course_ids')->nullable()
                    ->comment('IDs de cursos específicos (null = todos)');

                $table->json('degree_ids')->nullable()
                    ->comment('IDs de niveles/grados (null = todos)');

                // Restricciones de monto
                $table->decimal('min_purchase_amount', 10, 2)->nullable()
                    ->comment('Monto mínimo de compra para aplicar el descuento');

                $table->decimal('max_discount_amount', 10, 2)->nullable()
                    ->comment('Descuento máximo aplicable (útil para % altos)');

                // Estado
                $table->boolean('active')->default(true)
                    ->comment('Si el código está activo y puede ser usado');

                // Metadatos
                $table->string('created_by')->nullable()
                    ->comment('Usuario/admin que creó el código');

                $table->text('notes')->nullable()
                    ->comment('Notas internas sobre el código');

                $table->timestamps();
                $table->softDeletes();

                // Índices
                $table->index(['active', 'valid_from', 'valid_to'], 'idx_active_valid');
                $table->index('school_id', 'idx_school');
                $table->index(['code', 'active'], 'idx_code_active');
            });

            return;
        }

        $this->makeColumnNullable('discounts_codes', 'school_id');

        $this->addColumnIfMissing('discounts_codes', 'description', function (Blueprint $table) {
            $table->string('description', 255)->nullable()->after('code')
                ->comment('Descripción interna del código');
        });

        $this->addColumnIfMissing('discounts_codes', 'discount_type', function (Blueprint $table) {
            $table->enum('discount_type', ['percentage', 'fixed_amount'])->default('percentage')
                ->after('description')
                ->comment('Tipo: porcentaje o monto fijo');
        });

        $this->addColumnIfMissing('discounts_codes', 'discount_value', function (Blueprint $table) {
            $table->decimal('discount_value', 10, 2)
                ->after('discount_type')
                ->comment('Valor del descuento (% o monto fijo según tipo)');
        });

        $this->addColumnIfMissing('discounts_codes', 'max_uses_per_user', function (Blueprint $table) {
            $table->integer('max_uses_per_user')->default(1)->after('remaining')
                ->comment('Máximo de veces que un usuario puede usar este código');
        });

        $this->addColumnIfMissing('discounts_codes', 'valid_from', function (Blueprint $table) {
            $table->dateTime('valid_from')->nullable()->after('max_uses_per_user')
                ->comment('Fecha/hora desde la cual el código es válido');
        });

        $this->addColumnIfMissing('discounts_codes', 'valid_to', function (Blueprint $table) {
            $table->dateTime('valid_to')->nullable()->after('valid_from')
                ->comment('Fecha/hora hasta la cual el código es válido');
        });

        $this->addColumnIfMissing('discounts_codes', 'sport_ids', function (Blueprint $table) {
            $table->json('sport_ids')->nullable()->after('school_id')
                ->comment('IDs de deportes aplicables (null = todos)');
        });

        $this->addColumnIfMissing('discounts_codes', 'course_ids', function (Blueprint $table) {
            $table->json('course_ids')->nullable()->after('sport_ids')
                ->comment('IDs de cursos específicos (null = todos)');
        });

        $this->addColumnIfMissing('discounts_codes', 'degree_ids', function (Blueprint $table) {
            $table->json('degree_ids')->nullable()->after('course_ids')
                ->comment('IDs de niveles/grados (null = todos)');
        });

        $this->addColumnIfMissing('discounts_codes', 'min_purchase_amount', function (Blueprint $table) {
            $table->decimal('min_purchase_amount', 10, 2)->nullable()->after('degree_ids')
                ->comment('Monto mínimo de compra para aplicar el descuento');
        });

        $this->addColumnIfMissing('discounts_codes', 'max_discount_amount', function (Blueprint $table) {
            $table->decimal('max_discount_amount', 10, 2)->nullable()->after('min_purchase_amount')
                ->comment('Descuento máximo aplicable (útil para % altos)');
        });

        $this->addColumnIfMissing('discounts_codes', 'active', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('max_discount_amount')
                ->comment('Si el código está activo y puede ser usado');
        });

        $this->addColumnIfMissing('discounts_codes', 'created_by', function (Blueprint $table) {
            $table->string('created_by')->nullable()->after('active')
                ->comment('Usuario/admin que creó el código');
        });

        $this->addColumnIfMissing('discounts_codes', 'notes', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('created_by')
                ->comment('Notas internas sobre el código');
        });

        $this->ensureIndex('discounts_codes', 'idx_active_valid', function (Blueprint $table) {
            $table->index(['active', 'valid_from', 'valid_to'], 'idx_active_valid');
        });

        $this->ensureIndex('discounts_codes', 'idx_school', function (Blueprint $table) {
            $table->index('school_id', 'idx_school');
        }, ['school_id']);

        $this->ensureIndex('discounts_codes', 'idx_code_active', function (Blueprint $table) {
            $table->index(['code', 'active'], 'idx_code_active');
        });

        $this->ensureUniqueCodeIndex();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('discounts_codes');
    }

    private function addColumnIfMissing(string $table, string $column, callable $definition): void
    {
        if (Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($definition) {
            $definition($table);
        });
    }

    private function ensureIndex(string $table, string $indexName, callable $definition, array $aliases = []): void
    {
        $candidates = array_merge([$indexName], $aliases);
        foreach ($candidates as $candidate) {
            if ($this->indexExists($table, $candidate)) {
                return;
            }
        }

        Schema::table($table, function (Blueprint $table) use ($definition) {
            $definition($table);
        });
    }

    private function ensureUniqueCodeIndex(): void
    {
        $table = 'discounts_codes';
        $indexName = 'discounts_codes_code_unique';

        if ($this->indexExists($table, $indexName)) {
            return;
        }

        if ($this->indexExists($table, 'code')) {
            return;
        }

        $hasDuplicates = DB::table($table)
            ->select('code')
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->exists();

        if ($hasDuplicates) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($indexName) {
            $table->unique('code', $indexName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return DB::selectOne(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $indexName]
        ) !== null;
    }

    private function makeColumnNullable(string $table, string $column): void
    {
        if (!Schema::hasColumn($table, $column)) {
            return;
        }

        $columnInfo = DB::selectOne(
            'SELECT IS_NULLABLE, COLUMN_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, $column]
        );

        if (!$columnInfo || (property_exists($columnInfo, 'IS_NULLABLE') && strtoupper($columnInfo->IS_NULLABLE) === 'YES')) {
            return;
        }

        $type = $columnInfo->COLUMN_TYPE ?? 'bigint';

        DB::statement(sprintf(
            'ALTER TABLE `%s` MODIFY `%s` %s NULL',
            $table,
            $column,
            $type
        ));
    }
};
