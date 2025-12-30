<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('booking_price_snapshots')) {
            return;
        }

        Schema::create('booking_price_snapshots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('booking_id')->index();
            $table->unsignedInteger('version')->default(1);
            $table->string('source', 50)->default('basket_import');
            $table->json('snapshot');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('booking_id', 'idx_booking_price_snapshots_booking');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('booking_price_snapshots')) {
            return;
        }
        Schema::dropIfExists('booking_price_snapshots');
    }
};
