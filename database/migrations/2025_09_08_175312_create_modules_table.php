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
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('category')->default('core'); // core, addon, premium
            $table->string('version')->default('1.0.0');
            $table->json('pricing')->nullable(); // Pricing tiers data
            $table->json('features')->nullable(); // Module features list
            $table->boolean('active')->default(true);
            $table->boolean('mandatory')->default(false); // Required modules
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['slug', 'active']);
            $table->index(['category', 'active']);
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
