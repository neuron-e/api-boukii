<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('v5_renting_items')) {
            return;
        }
        Schema::create('v5_renting_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('category_id');
            $table->string('name');
            $table->string('sku')->nullable();
            $table->text('description')->nullable();
            $table->decimal('base_daily_rate', 10, 2);
            $table->decimal('deposit', 10, 2)->nullable();
            $table->char('currency', 3);
            $table->unsignedInteger('inventory_count')->default(0);
            $table->json('attributes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['school_id', 'category_id']);
            $table->index(['school_id', 'active']);
            $table->unique(['school_id', 'sku']);

            // Foreign keys (optional)
            // $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            // $table->foreign('category_id')->references('id')->on('v5_renting_categories')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v5_renting_items');
    }
};
