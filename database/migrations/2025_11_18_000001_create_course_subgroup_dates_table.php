<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_subgroup_dates', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('course_subgroup_id');
            $table->bigInteger('course_date_id');
            $table->unsignedInteger('order')->nullable();
            $table->timestamps();
            $table->unique(['course_subgroup_id', 'course_date_id'], 'idx_course_subgroup_date');

            $table->foreign('course_subgroup_id')
                ->references('id')
                ->on('course_subgroups')
                ->cascadeOnDelete();

            $table->foreign('course_date_id')
                ->references('id')
                ->on('course_dates')
                ->cascadeOnDelete();
        });

        $now = Carbon::now()->toDateTimeString();

        DB::table('course_subgroups')
            ->whereNotNull('course_date_id')
            ->select('id', 'course_date_id')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($now) {
                $payload = [];
                foreach ($rows as $row) {
                    $payload[] = [
                        'course_subgroup_id' => $row->id,
                        'course_date_id' => $row->course_date_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (!empty($payload)) {
                    DB::table('course_subgroup_dates')->insert($payload);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_subgroup_dates');
    }
};
