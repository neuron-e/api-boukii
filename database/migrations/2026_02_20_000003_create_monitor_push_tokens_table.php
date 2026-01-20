<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitor_push_tokens', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('monitor_id');
            $table->string('token', 2048)->unique();
            $table->string('platform', 32)->nullable();
            $table->string('device_id', 128)->nullable();
            $table->string('locale', 16)->nullable();
            $table->string('app', 32)->default('teach');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->foreign('monitor_id')->references('id')->on('monitors')->onDelete('cascade');
            $table->index(['monitor_id', 'app']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_push_tokens');
    }
};
