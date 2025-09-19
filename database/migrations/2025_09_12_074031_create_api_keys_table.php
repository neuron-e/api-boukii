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
        if (!Schema::hasTable('api_keys')) {
            Schema::create('api_keys', function (Blueprint $table) {
                $table->id();
                $table->string('name')->comment('Human readable name for the key');
                $table->string('key_hash')->unique()->comment('SHA256 hash of the API key');
                $table->unsignedBigInteger('school_id');
                $table->json('scopes')->comment('Array of scopes like ["timing:write", "timing:read"]');
                $table->timestamp('last_used_at')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
                
                // Foreign key constraint
                $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
                
                // Indexes
                $table->index('school_id');
                $table->index(['key_hash', 'active']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
