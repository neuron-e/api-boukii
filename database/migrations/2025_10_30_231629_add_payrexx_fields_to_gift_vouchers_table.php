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
            // Campos para integración con Payrexx
            $table->string('payrexx_reference', 100)->nullable()->after('payment_reference')
                ->comment('Referencia única de Payrexx para este gift voucher');
            $table->text('payrexx_link')->nullable()->after('payrexx_reference')
                ->comment('URL del gateway de pago de Payrexx');
            $table->json('payrexx_transaction')->nullable()->after('payrexx_link')
                ->comment('Datos de la transacción de Payrexx (JSON)');
            $table->timestamp('payment_confirmed_at')->nullable()->after('payrexx_transaction')
                ->comment('Fecha y hora de confirmación del pago');
            $table->timestamp('email_sent_at')->nullable()->after('delivered_at')
                ->comment('Fecha y hora del último envío de email');

            // Índices
            $table->index('payrexx_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gift_vouchers', function (Blueprint $table) {
            $table->dropIndex(['payrexx_reference']);
            $table->dropColumn([
                'payrexx_reference',
                'payrexx_link',
                'payrexx_transaction',
                'payment_confirmed_at',
                'email_sent_at'
            ]);
        });
    }
};
