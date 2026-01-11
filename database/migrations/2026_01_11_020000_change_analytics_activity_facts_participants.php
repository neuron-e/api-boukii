<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_activity_facts', function (Blueprint $table) {
            $table->decimal('participants', 10, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('analytics_activity_facts', function (Blueprint $table) {
            $table->unsignedInteger('participants')->default(0)->change();
        });
    }
};
