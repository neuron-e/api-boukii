<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rental_stock_movements')) {
            return;
        }

        Schema::create('rental_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->index();
            $table->unsignedBigInteger('rental_reservation_id')->nullable()->index();
            $table->unsignedBigInteger('rental_reservation_line_id')->nullable()->index();
            $table->unsignedBigInteger('rental_unit_id')->nullable()->index();
            $table->unsignedBigInteger('variant_id')->nullable()->index();
            $table->unsignedBigInteger('item_id')->nullable()->index();
            $table->unsignedBigInteger('warehouse_id_from')->nullable()->index();
            $table->unsignedBigInteger('warehouse_id_to')->nullable()->index();
            $table->string('movement_type', 50)->index();
            $table->integer('quantity')->default(1);
            $table->string('reason', 255)->nullable();
            $table->json('payload')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['school_id', 'movement_type', 'occurred_at'], 'rsm_school_type_date_idx');
            $table->index(['school_id', 'variant_id', 'occurred_at'], 'rsm_school_variant_date_idx');
            $table->index(['school_id', 'warehouse_id_from', 'warehouse_id_to'], 'rsm_school_warehouse_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_stock_movements');
    }
};

