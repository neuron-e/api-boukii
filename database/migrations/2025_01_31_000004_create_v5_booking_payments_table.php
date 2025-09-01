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
        if (Schema::hasTable('v5_booking_payments')) {
            return;
        }
        Schema::create('v5_booking_payments', function (Blueprint $table) {
            $table->id();
            
            // Relationship
            $table->foreignId('booking_id')->constrained('v5_bookings')->onDelete('cascade');
            
            // Payment reference
            $table->string('payment_reference', 50)->unique()->index();
            
            // Payment information
            $table->enum('payment_type', ['deposit', 'full_payment', 'partial_payment', 'refund', 'fee'])->index();
            $table->enum('payment_method', [
                'credit_card', 'debit_card', 'bank_transfer', 'paypal', 
                'apple_pay', 'google_pay', 'cash', 'voucher', 'other'
            ])->index();
            
            // Amount information
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->enum('status', [
                'pending', 'processing', 'completed', 'failed', 
                'cancelled', 'refunded', 'partially_refunded'
            ])->index();
            
            // Gateway information
            $table->string('gateway', 50)->nullable()->index();
            $table->string('gateway_transaction_id')->nullable()->index();
            $table->string('gateway_reference')->nullable();
            $table->json('gateway_response')->nullable();
            
            // Fee information
            $table->decimal('fee_amount', 8, 2)->nullable();
            $table->string('fee_currency', 3)->nullable();
            
            // Processing timestamps
            $table->timestamp('processed_at')->nullable()->index();
            
            // Refund information
            $table->timestamp('refunded_at')->nullable()->index();
            $table->decimal('refunded_amount', 10, 2)->nullable();
            $table->text('refund_reason')->nullable();
            
            // Additional data
            $table->json('payment_data')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Indexes for payment management and reporting
            $table->index(['booking_id', 'status']);
            $table->index(['booking_id', 'payment_type']);
            $table->index(['payment_method', 'status']);
            $table->index(['gateway', 'gateway_transaction_id']);
            $table->index(['status', 'created_at']);
            $table->index(['processed_at', 'status']);
            $table->index(['refunded_at', 'refunded_amount']);
            
            // Financial reporting indexes
            $table->index(['status', 'processed_at', 'amount']);
            $table->index(['payment_method', 'processed_at', 'amount']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('v5_booking_payments');
    }
};
