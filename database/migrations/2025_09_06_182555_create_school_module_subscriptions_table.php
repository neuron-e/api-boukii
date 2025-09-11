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
        Schema::create('school_module_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['active', 'inactive', 'trial', 'expired', 'suspended'])->default('inactive');
            $table->enum('subscription_type', ['free', 'basic', 'premium', 'enterprise'])->default('basic');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->json('settings')->nullable(); // Configuraciones específicas del módulo
            $table->json('limits')->nullable(); // Límites del plan (usuarios, registros, etc.)
            $table->decimal('monthly_cost', 8, 2)->nullable(); // Costo mensual
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'module_id']);
            $table->index(['school_id', 'status']);
            $table->index(['module_id', 'status']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_module_subscriptions');
    }
};
