<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
 use Illuminate\Database\Eloquent\SoftDeletes; use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @OA\Schema(
 *      schema="EvaluationFile",
 *      required={"evaluation_id"},
 *      @OA\Property(
 *          property="name",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="type",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="file",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="created_at",
 *          description="",
 *          readOnly=true,
 *          nullable=true,
 *          type="string",
 *          format="date-time"
 *      ),
 *      @OA\Property(
 *          property="updated_at",
 *          description="",
 *          readOnly=true,
 *          nullable=true,
 *          type="string",
 *          format="date-time"
 *      ),
 *      @OA\Property(
 *          property="deleted_at",
 *          description="",
 *          readOnly=true,
 *          nullable=true,
 *          type="string",
 *          format="date-time"
 *      )
 * )
 */class EvaluationFile extends Model
{
       use LogsActivity, SoftDeletes, HasFactory;

       public $table = 'evaluation_files';

    public $fillable = [
        'evaluation_id',
        'name',
        'type',
        'file'
    ];

    protected $casts = [
        'name' => 'string',
        'type' => 'string',
        'file' => 'string'
    ];

    public static array $rules = [
        'evaluation_id' => 'required',
        'name' => 'nullable|string|max:255',
        'type' => 'nullable|string|max:255',
        'file' => 'nullable|string',
        'created_at' => 'nullable',
        'updated_at' => 'nullable',
        'deleted_at' => 'nullable'
    ];

    public function evaluation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Evaluation::class, 'evaluation_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
         return LogOptions::defaults()
             ->dontSubmitEmptyLogs()
             ->useLogName('evaluation_file');
    }

    public function tapActivity($activity, string $eventName): void
    {
        $user = request()->user() ?? auth('sanctum')->user();
        if ($user) {
            $activity->causer()->associate($user);
        }
    }
}
