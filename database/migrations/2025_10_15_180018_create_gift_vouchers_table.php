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
        Schema::create('gift_vouchers', function (Blueprint $table) {
            $table->id();

            // Relación con voucher base
            $table->bigInteger('voucher_id')->nullable()
                ->comment('ID del voucher asociado (se crea al generar el bono regalo)');

            // Información del regalo
            $table->decimal('amount', 10, 2)
                ->comment('Monto del bono regalo');
            $table->text('personal_message')->nullable()
                ->comment('Mensaje personalizado del remitente');
            $table->string('sender_name', 100)->nullable()
                ->comment('Nombre del remitente');

            // Personalización
            $table->string('template', 50)->default('default')
                ->comment('Template/diseño del bono regalo (default, christmas, birthday, etc.)');
            $table->string('background_color', 7)->nullable()
                ->comment('Color de fondo personalizado (#RRGGBB)');
            $table->string('text_color', 7)->nullable()
                ->comment('Color del texto personalizado (#RRGGBB)');

            // Configuración de entrega
            $table->string('recipient_email', 255)
                ->comment('Email del destinatario del bono regalo');
            $table->string('recipient_name', 100)->nullable()
                ->comment('Nombre del destinatario');
            $table->dateTime('delivery_date')->nullable()
                ->comment('Fecha programada de envío (null = enviar inmediatamente)');
            $table->boolean('is_delivered')->default(false)
                ->comment('Indica si el email ya fue enviado');
            $table->dateTime('delivered_at')->nullable()
                ->comment('Fecha y hora en que se envió el email');

            // Estado y tracking
            $table->boolean('is_redeemed')->default(false)
                ->comment('Indica si el bono regalo ya fue canjeado');
            $table->dateTime('redeemed_at')->nullable()
                ->comment('Fecha en que se canjeó el bono');
            $table->bigInteger('redeemed_by_client_id')->nullable()
                ->comment('ID del cliente que canjeó el bono');

            // Información de compra
            $table->bigInteger('purchased_by_client_id')->nullable()
                ->comment('ID del cliente que compró el bono regalo');
            $table->bigInteger('school_id')
                ->comment('ID de la escuela');
            $table->boolean('is_paid')->default(false)
                ->comment('Indica si el bono fue pagado');
            $table->string('payment_reference')->nullable()
                ->comment('Referencia de pago (Payrexx, Stripe, etc.)');

            // Metadatos
            $table->text('notes')->nullable()
                ->comment('Notas internas');
            $table->string('created_by')->nullable()
                ->comment('Usuario que creó el bono regalo');

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('voucher_id');
            $table->index('recipient_email');
            $table->index('delivery_date');
            $table->index('is_delivered');
            $table->index('is_redeemed');
            $table->index('school_id');
            $table->index('purchased_by_client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gift_vouchers');
    }
};
