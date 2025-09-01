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
        if (Schema::hasTable('v5_booking_equipment')) {
            return;
        }
        Schema::create('v5_booking_equipment', function (Blueprint $table) {
            $table->id();
            
            // Relationship
            $table->foreignId('booking_id')->constrained('v5_bookings')->onDelete('cascade');
            
            // Equipment information
            $table->enum('equipment_type', [
                'skis', 'boots', 'poles', 'helmet', 'goggles', 
                'snowboard', 'bindings', 'clothing', 'protection', 'other'
            ])->index();
            $table->string('name', 100);
            $table->string('brand', 50)->nullable();
            $table->string('model', 50)->nullable();
            $table->string('size', 20)->nullable();
            $table->string('serial_number', 50)->nullable()->index();
            
            // Participant assignment
            $table->string('participant_name', 200);
            $table->integer('participant_index')->nullable()->index();
            
            // Pricing
            $table->decimal('daily_rate', 8, 2);
            $table->integer('rental_days');
            $table->decimal('total_price', 8, 2);
            $table->string('currency', 3)->default('EUR');
            $table->decimal('deposit', 8, 2)->nullable();
            
            // Condition tracking
            $table->enum('condition_out', ['excellent', 'good', 'fair', 'poor', 'damaged'])->default('good');
            $table->enum('condition_in', ['excellent', 'good', 'fair', 'poor', 'damaged'])->nullable();
            
            // Rental tracking
            $table->timestamp('rented_at')->nullable()->index();
            $table->timestamp('returned_at')->nullable()->index();
            
            // Additional data
            $table->json('equipment_data')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Indexes for equipment management
            $table->index(['booking_id', 'equipment_type']);
            $table->index(['booking_id', 'participant_name']);
            $table->index(['equipment_type', 'rented_at']);
            $table->index(['equipment_type', 'returned_at']);
            $table->index(['rented_at', 'returned_at']); // For outstanding equipment
            $table->index(['serial_number', 'rented_at']); // For inventory tracking
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('v5_booking_equipment');
    }
};
