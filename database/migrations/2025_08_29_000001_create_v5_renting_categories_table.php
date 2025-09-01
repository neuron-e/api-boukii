<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('v5_renting_categories')) {
            return;
        }
        Schema::create('v5_renting_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->integer('position')->default(0);
            $table->boolean('active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'parent_id']);
            $table->index(['school_id', 'active']);
            $table->unique(['school_id', 'slug']);

            // Foreign keys (optional to avoid legacy constraints issues)
            // $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            // $table->foreign('parent_id')->references('id')->on('v5_renting_categories')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v5_renting_categories');
    }
};
