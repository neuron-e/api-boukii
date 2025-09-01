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
        if (Schema::hasTable('v5_booking_extras')) {
            return;
        }
        Schema::create('v5_booking_extras', function (Blueprint $table) {
            $table->id();
            
            // Relationship
            $table->foreignId('booking_id')->constrained('v5_bookings')->onDelete('cascade');
            
            // Extra information
            $table->enum('extra_type', [
                'insurance', 'equipment', 'transport', 'meal', 'photo', 
                'video', 'certificate', 'special_service', 'other'
            ])->index();
            $table->string('name', 100);
            $table->text('description')->nullable();
            
            // Pricing
            $table->decimal('unit_price', 8, 2);
            $table->integer('quantity')->default(1);
            $table->decimal('total_price', 8, 2);
            $table->string('currency', 3)->default('EUR');
            
            // Status and flags
            $table->boolean('is_required')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            
            // Additional data
            $table->json('extra_data')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['booking_id', 'extra_type']);
            $table->index(['booking_id', 'is_active']);
            $table->index(['extra_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('v5_booking_extras');
    }
};
