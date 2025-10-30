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
        Schema::table('vouchers', function (Blueprint $table) {
            if (!Schema::hasColumn('vouchers', 'buyer_name')) {
                $table->string('buyer_name', 255)->nullable()->after('client_id')
                    ->comment('Nombre de la persona que compra el bono (si no es cliente)');
            }

            if (!Schema::hasColumn('vouchers', 'buyer_email')) {
                $table->string('buyer_email', 255)->nullable()->after('buyer_name')
                    ->comment('Email de contacto del comprador del bono');
            }

            if (!Schema::hasColumn('vouchers', 'buyer_phone')) {
                $table->string('buyer_phone', 50)->nullable()->after('buyer_email')
                    ->comment('Tel\xE9fono de contacto del comprador del bono');
            }

            if (!Schema::hasColumn('vouchers', 'recipient_name')) {
                $table->string('recipient_name', 255)->nullable()->after('buyer_phone')
                    ->comment('Nombre de quien recibir\xE1 el bono');
            }

            if (!Schema::hasColumn('vouchers', 'recipient_email')) {
                $table->string('recipient_email', 255)->nullable()->after('recipient_name')
                    ->comment('Email de quien recibir\xE1 el bono');
            }

            if (!Schema::hasColumn('vouchers', 'recipient_phone')) {
                $table->string('recipient_phone', 50)->nullable()->after('recipient_email')
                    ->comment('Tel\xE9fono de quien recibir\xE1 el bono');
            }
        });

        Schema::table('gift_vouchers', function (Blueprint $table) {
            if (!Schema::hasColumn('gift_vouchers', 'buyer_name')) {
                $table->string('buyer_name', 255)->nullable()->after('sender_name')
                    ->comment('Nombre de la persona que compra el gift voucher (si no es cliente)');
            }

            if (!Schema::hasColumn('gift_vouchers', 'buyer_email')) {
                $table->string('buyer_email', 255)->nullable()->after('buyer_name')
                    ->comment('Email de contacto del comprador del gift voucher');
            }

            if (!Schema::hasColumn('gift_vouchers', 'buyer_phone')) {
                $table->string('buyer_phone', 50)->nullable()->after('buyer_email')
                    ->comment('Tel\xE9fono de contacto del comprador del gift voucher');
            }

            if (!Schema::hasColumn('gift_vouchers', 'buyer_locale')) {
                $table->string('buyer_locale', 10)->nullable()->after('buyer_phone')
                    ->comment('Locale preferido del comprador para comunicaciones');
            }

            if (!Schema::hasColumn('gift_vouchers', 'recipient_phone')) {
                $table->string('recipient_phone', 50)->nullable()->after('recipient_email')
                    ->comment('Tel\xE9fono de contacto del destinatario');
            }

            if (!Schema::hasColumn('gift_vouchers', 'recipient_locale')) {
                $table->string('recipient_locale', 10)->nullable()->after('recipient_phone')
                    ->comment('Locale preferido del destinatario para comunicaciones');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            if (Schema::hasColumn('vouchers', 'recipient_phone')) {
                $table->dropColumn('recipient_phone');
            }

            if (Schema::hasColumn('vouchers', 'recipient_email')) {
                $table->dropColumn('recipient_email');
            }

            if (Schema::hasColumn('vouchers', 'recipient_name')) {
                $table->dropColumn('recipient_name');
            }

            if (Schema::hasColumn('vouchers', 'buyer_phone')) {
                $table->dropColumn('buyer_phone');
            }

            if (Schema::hasColumn('vouchers', 'buyer_email')) {
                $table->dropColumn('buyer_email');
            }

            if (Schema::hasColumn('vouchers', 'buyer_name')) {
                $table->dropColumn('buyer_name');
            }
        });

        Schema::table('gift_vouchers', function (Blueprint $table) {
            if (Schema::hasColumn('gift_vouchers', 'recipient_locale')) {
                $table->dropColumn('recipient_locale');
            }

            if (Schema::hasColumn('gift_vouchers', 'recipient_phone')) {
                $table->dropColumn('recipient_phone');
            }

            if (Schema::hasColumn('gift_vouchers', 'buyer_locale')) {
                $table->dropColumn('buyer_locale');
            }

            if (Schema::hasColumn('gift_vouchers', 'buyer_phone')) {
                $table->dropColumn('buyer_phone');
            }

            if (Schema::hasColumn('gift_vouchers', 'buyer_email')) {
                $table->dropColumn('buyer_email');
            }

            if (Schema::hasColumn('gift_vouchers', 'buyer_name')) {
                $table->dropColumn('buyer_name');
            }
        });
    }
};

