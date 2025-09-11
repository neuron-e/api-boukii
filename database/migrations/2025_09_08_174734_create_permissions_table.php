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
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('module')->default('auth'); // auth, bookings, courses, etc.
            $table->string('resource')->nullable(); // users, bookings, courses, etc.
            $table->string('action')->nullable(); // view, create, update, delete, etc.
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['slug', 'active']);
            $table->index(['module', 'resource', 'action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
