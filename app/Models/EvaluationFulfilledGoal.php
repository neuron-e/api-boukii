<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @OA\Schema(
 *      schema="EvaluationFulfilledGoal",
 *      required={"evaluation_id", "degrees_school_sport_goals_id"},
 *      @OA\Property(
 *          property="evaluation_id",
 *          description="ID of the evaluation",
 *          type="integer",
 *          nullable=false
 *      ),
 *      @OA\Property(
 *          property="degrees_school_sport_goals_id",
 *          description="ID of the degree's school sport goal",
 *          type="integer",
 *          nullable=false
 *      ),
 *      @OA\Property(
 *           property="score",
 *           description="Score of the evaluation (0-5-10)",
 *           type="integer",
 *           nullable=false
 *       ),
 *      @OA\Property(
 *          property="created_at",
 *          description="Creation timestamp of the fulfilled goal",
 *          type="string",
 *          format="date-time",
 *          nullable=true
 *      ),
 *      @OA\Property(
 *          property="updated_at",
 *          description="Last update timestamp of the fulfilled goal",
 *          type="string",
 *          format="date-time",
 *          nullable=true
 *      ),
 *      @OA\Property(
 *          property="deleted_at",
 *          description="Deletion timestamp of the fulfilled goal",
 *          type="string",
 *          format="date-time",
 *          nullable=true
 *      )
 * )
 */
class EvaluationFulfilledGoal extends Model
{
      use LogsActivity, SoftDeletes, HasFactory;     public $table = 'evaluation_fulfilled_goals';

    public $fillable = [
        'evaluation_id',
        'degrees_school_sport_goals_id',
        'score'
    ];

    protected $casts = [

    ];

    public static array $rules = [
        'evaluation_id' => 'required',
        'degrees_school_sport_goals_id' => 'required',
        'score' => 'required',
        'created_at' => 'nullable',
        'updated_at' => 'nullable',
        'deleted_at' => 'nullable'
    ];

    public function evaluation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Evaluation::class, 'evaluation_id');
    }

    public function degreeSchoolSportGoal(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\DegreesSchoolSportGoal::class, 'degrees_school_sport_goals_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
         return LogOptions::defaults()
             ->dontSubmitEmptyLogs()
             ->useLogName('evaluation_goal');
    }

    public function tapActivity($activity, string $eventName): void
    {
        $user = request()->user() ?? auth('sanctum')->user();
        if ($user) {
            $activity->causer()->associate($user);
        }
    }
}
