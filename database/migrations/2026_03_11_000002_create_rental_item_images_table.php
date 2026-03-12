<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rental_item_images')) {
            Schema::create('rental_item_images', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->unsignedBigInteger('item_id')->index();
                $table->string('image_url', 2048);
                $table->boolean('is_primary')->default(false)->index();
                $table->integer('sort_order')->default(0)->index();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_item_images');
    }
};

