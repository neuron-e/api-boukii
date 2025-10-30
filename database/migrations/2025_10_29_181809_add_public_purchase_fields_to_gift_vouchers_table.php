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
        Schema::table('gift_vouchers', function (Blueprint $table) {
            // Añadir código único para gift vouchers
            $table->string('code', 50)->unique()->nullable()->after('id')
                ->comment('Código único del gift voucher (ej: GV-XXXX-XXXX)');

            // Estado del gift voucher
            $table->enum('status', ['pending', 'active', 'used', 'expired', 'cancelled'])
                ->default('pending')->after('is_paid')
                ->comment('Estado del gift voucher: pending=esperando pago, active=listo para usar, used=usado completamente, expired=expirado, cancelled=cancelado');

            // Balance restante (para permitir uso parcial)
            $table->decimal('balance', 10, 2)->nullable()->after('amount')
                ->comment('Balance restante del voucher (null si no se ha activado aún)');

            // Referencia de transacción Payrexx
            $table->string('payrexx_transaction_id')->nullable()->after('payment_reference')
                ->comment('ID de transacción de Payrexx');

            // Moneda del voucher
            $table->string('currency', 3)->default('EUR')->after('amount')
                ->comment('Moneda del gift voucher');

            // Fecha de expiración
            $table->date('expires_at')->nullable()->after('delivery_date')
                ->comment('Fecha de expiración del gift voucher');

            // Índices adicionales
            $table->index('code');
            $table->index('status');
            $table->index('payrexx_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gift_vouchers', function (Blueprint $table) {
            $table->dropIndex(['code']);
            $table->dropIndex(['status']);
            $table->dropIndex(['payrexx_transaction_id']);

            $table->dropColumn([
                'code',
                'status',
                'balance',
                'payrexx_transaction_id',
                'currency',
                'expires_at'
            ]);
        });
    }
};
