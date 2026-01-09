<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->string('origin_type', 50)->nullable()->after('notes')
                ->comment('gift_purchase | refund_credit | manual | unknown');
            $table->unsignedBigInteger('origin_booking_id')->nullable()->after('origin_type');
            $table->unsignedBigInteger('origin_booking_log_id')->nullable()->after('origin_booking_id');
            $table->unsignedBigInteger('origin_gift_voucher_id')->nullable()->after('origin_booking_log_id');

            $table->index('origin_type', 'idx_vouchers_origin_type');
            $table->index('origin_booking_id', 'idx_vouchers_origin_booking');
            $table->index('origin_gift_voucher_id', 'idx_vouchers_origin_gift');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex('idx_vouchers_origin_type');
            $table->dropIndex('idx_vouchers_origin_booking');
            $table->dropIndex('idx_vouchers_origin_gift');

            $table->dropColumn([
                'origin_type',
                'origin_booking_id',
                'origin_booking_log_id',
                'origin_gift_voucher_id',
            ]);
        });
    }
};
