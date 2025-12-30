<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('booking_price_audits')) {
            return;
        }

        Schema::create('booking_price_audits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('booking_id')->index();
            $table->unsignedBigInteger('booking_price_snapshot_id')->nullable()->index();
            $table->string('event_type', 50);
            $table->text('note')->nullable();
            $table->json('diff')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('booking_id', 'idx_booking_price_audits_booking');
            $table->index('booking_price_snapshot_id', 'idx_booking_price_audits_snapshot');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('booking_price_audits')) {
            return;
        }
        Schema::dropIfExists('booking_price_audits');
    }
};
