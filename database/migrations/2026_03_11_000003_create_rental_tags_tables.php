<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rental_tags')) {
            Schema::create('rental_tags', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->string('name');
                $table->string('slug')->nullable()->index();
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['school_id', 'name'], 'rental_tags_school_name_unique');
            });
        }

        if (!Schema::hasTable('rental_item_tags')) {
            Schema::create('rental_item_tags', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->unsignedBigInteger('item_id')->index();
                $table->unsignedBigInteger('tag_id')->index();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['item_id', 'tag_id'], 'rental_item_tags_item_tag_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_item_tags');
        Schema::dropIfExists('rental_tags');
    }
};

