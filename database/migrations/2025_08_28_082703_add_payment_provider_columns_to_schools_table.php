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
        Schema::table('schools', function (Blueprint $table) {
            $table->enum('payment_provider', ['payrexx', 'payyo'])->nullable();
            $table->string('payyo_instance')->nullable();
            $table->string('payyo_key')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['payment_provider', 'payyo_instance', 'payyo_key']);
        });
    }
};
