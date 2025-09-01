<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('v5_bookings')) {
            return;
        }
        Schema::create('v5_bookings', function (Blueprint $table) {
            $table->id();
            
            // Reference and context
            $table->string('booking_reference', 50)->unique()->index();
            $table->unsignedBigInteger('season_id')->index();
            $table->unsignedBigInteger('school_id')->index();
            
            // Core booking information
            $table->unsignedBigInteger('client_id')->index();
            $table->unsignedBigInteger('course_id')->nullable()->index();
            $table->unsignedBigInteger('monitor_id')->nullable()->index();
            $table->enum('type', ['course', 'activity', 'material'])->index();
            $table->enum('status', ['pending', 'confirmed', 'paid', 'completed', 'cancelled', 'no_show'])->index();
            
            // Booking data (from wizard)
            $table->json('booking_data')->nullable();
            $table->json('participants');
            
            // Pricing information
            $table->decimal('base_price', 10, 2)->default(0.00);
            $table->decimal('extras_price', 10, 2)->default(0.00);
            $table->decimal('equipment_price', 10, 2)->default(0.00);
            $table->decimal('insurance_price', 10, 2)->default(0.00);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('discount_amount', 10, 2)->default(0.00);
            $table->decimal('total_price', 10, 2)->index();
            $table->string('currency', 3)->default('EUR');
            
            // Schedule information
            $table->date('start_date')->nullable()->index();
            $table->date('end_date')->nullable()->index();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('meeting_point')->nullable();
            
            // Features and options
            $table->boolean('has_insurance')->default(false)->index();
            $table->boolean('has_equipment')->default(false)->index();
            
            // Additional information
            $table->text('special_requests')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            
            // Status timestamps
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            
            // Standard timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['season_id', 'school_id', 'status']);
            $table->index(['season_id', 'school_id', 'type']);
            $table->index(['season_id', 'school_id', 'start_date']);
            $table->index(['client_id', 'status']);
            $table->index(['course_id', 'start_date', 'start_time']);
            $table->index(['monitor_id', 'start_date', 'start_time']);
            $table->index(['status', 'start_date']);
            $table->index(['created_at', 'status']);
            
            // Full-text search indexes (only on drivers that support it)
            if (DB::getDriverName() !== 'sqlite') {
                $table->fullText(['special_requests', 'notes'], 'bookings_text_search');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('v5_bookings');
    }
};
