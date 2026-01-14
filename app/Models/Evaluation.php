<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @OA\Schema(
 *      schema="Evaluation",
 *      required={"client_id", "degree_id"},
 *      @OA\Property(
 *          property="client_id",
 *          description="ID of the client being evaluated",
 *          type="integer",
 *          nullable=false
 *      ),
 *      @OA\Property(
 *          property="degree_id",
 *          description="ID of the degree related to the evaluation",
 *          type="integer",
 *          nullable=false
 *      ),
 *      @OA\Property(
 *          property="observations",
 *          description="Observations made during the evaluation",
 *          type="string",
 *          nullable=true
 *      ),
 *      @OA\Property(
 *          property="created_at",
 *          description="Creation timestamp of the evaluation",
 *          type="string",
 *          format="date-time",
 *          nullable=true
 *      ),
 *      @OA\Property(
 *          property="updated_at",
 *          description="Last update timestamp of the evaluation",
 *          type="string",
 *          format="date-time",
 *          nullable=true
 *      ),
 *      @OA\Property(
 *          property="deleted_at",
 *          description="Deletion timestamp of the evaluation",
 *          type="string",
 *          format="date-time",
 *          nullable=true
 *      )
 * )
 */
class Evaluation extends Model
{

    use LogsActivity, SoftDeletes, HasFactory;

    public $table = 'evaluations';

    protected $with = ['files'];

    public $fillable = [
        'client_id',
        'degree_id',
        'observations'
    ];

    protected $casts = [
        'observations' => 'string'
    ];

    public static array $rules = [
        'client_id' => 'required',
        'degree_id' => 'required',
        'observations' => 'nullable|string|max:65535',
        'created_at' => 'nullable',
        'updated_at' => 'nullable',
        'deleted_at' => 'nullable'
    ];

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Client::class, 'client_id');
    }

    public function degree(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Degree::class, 'degree_id');
    }

    public function evaluationFulfilledGoals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\EvaluationFulfilledGoal::class, 'evaluation_id');
    }

    public function files(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\EvaluationFile::class, 'evaluation_id');
    }


    public function getActivitylogOptions(): LogOptions
    {
         return LogOptions::defaults()
             ->logFillable()
             ->logOnlyDirty()
             ->dontSubmitEmptyLogs()
             ->useLogName('evaluation');
    }

    public function tapActivity($activity, string $eventName): void
    {
        $user = request()->user() ?? auth('sanctum')->user();
        if ($user) {
            $activity->causer()->associate($user);
        }
    }
}
