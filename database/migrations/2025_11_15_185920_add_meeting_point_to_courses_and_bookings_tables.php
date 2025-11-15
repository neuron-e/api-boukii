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
        // Add meeting point fields to courses table
        Schema::table('courses', function (Blueprint $table) {
            $table->string('meeting_point')->nullable();
            $table->string('meeting_point_address')->nullable();
            $table->text('meeting_point_instructions')->nullable();
        });

        // Add meeting point fields to bookings table
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('meeting_point')->nullable();
            $table->string('meeting_point_address')->nullable();
            $table->text('meeting_point_instructions')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['meeting_point', 'meeting_point_address', 'meeting_point_instructions']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['meeting_point', 'meeting_point_address', 'meeting_point_instructions']);
        });
    }
};
