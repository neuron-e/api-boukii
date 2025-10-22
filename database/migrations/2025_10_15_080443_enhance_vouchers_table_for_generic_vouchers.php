<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Mejoras para permitir "Bonos sin Cliente" (vouchers genéricos)
     * Basado en el diseño UI proporcionado
     */
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            // Hacer client_id nullable para bonos genéricos
            $table->bigInteger('client_id')->nullable()->change();

            // Información adicional del bono
            $table->string('name', 100)->nullable()->after('code')
                ->comment('Nombre descriptivo del bono (ej: Bono Clases Privadas)');

            $table->text('description')->nullable()->after('name')
                ->comment('Descripción opcional del bono');

            // Tipo de curso aplicable (según diseño)
            $table->bigInteger('course_type_id')->nullable()->after('school_id')
                ->comment('Tipo de curso al que aplica el bono (null = cualquier tipo)');

            // Fecha de expiración
            $table->dateTime('expires_at')->nullable()->after('payed')
                ->comment('Fecha de expiración del bono');

            // Máximo de usos
            $table->integer('max_uses')->nullable()->after('expires_at')
                ->comment('Máximo de veces que puede usarse (null = ilimitado hasta agotar saldo)');

            $table->integer('uses_count')->default(0)->after('max_uses')
                ->comment('Contador de veces que se ha usado');

            // Estado de transferibilidad
            $table->boolean('is_transferable')->default(false)->after('is_gift')
                ->comment('Permite que el bono sea transferido a otro cliente');

            // Cliente que recibió el bono (para bonos transferidos)
            $table->bigInteger('transferred_to_client_id')->nullable()->after('is_transferable')
                ->comment('ID del cliente al que se transfirió el bono');

            $table->dateTime('transferred_at')->nullable()->after('transferred_to_client_id')
                ->comment('Fecha de transferencia');

            // Metadatos
            $table->string('created_by')->nullable()->after('transferred_at')
                ->comment('Usuario que creó el bono');

            $table->text('notes')->nullable()->after('created_by')
                ->comment('Notas internas sobre el bono');

            // Índices
            $table->index('expires_at', 'idx_vouchers_expires');
            $table->index('is_transferable', 'idx_vouchers_transferable');
            $table->index('transferred_to_client_id', 'idx_vouchers_transferred_to');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex('idx_vouchers_expires');
            $table->dropIndex('idx_vouchers_transferable');
            $table->dropIndex('idx_vouchers_transferred_to');

            $table->dropColumn([
                'name',
                'description',
                'course_type_id',
                'expires_at',
                'max_uses',
                'uses_count',
                'is_transferable',
                'transferred_to_client_id',
                'transferred_at',
                'created_by',
                'notes'
            ]);

            // Revertir client_id a NOT NULL (cuidado: puede fallar si hay datos)
            // $table->bigInteger('client_id')->nullable(false)->change();
        });
    }
};
