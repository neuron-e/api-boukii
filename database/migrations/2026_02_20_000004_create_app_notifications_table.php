<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('recipient_type', 20);
            $table->bigInteger('recipient_id');
            $table->bigInteger('actor_id')->nullable();
            $table->bigInteger('school_id')->nullable();
            $table->string('type', 50);
            $table->string('title', 150);
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->date('event_date')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_type', 'recipient_id']);
            $table->index(['recipient_type', 'recipient_id', 'read_at']);
            $table->index(['school_id']);
            $table->index(['event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
