<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @OA\Schema(
 *      schema="ClientsSchool",
 *      required={"client_id", "school_id"},
 *      @OA\Property(
 *          property="client_id",
 *          description="Client ID",
 *          type="integer",
 *          nullable=false
 *      ),
 *      @OA\Property(
 *          property="school_id",
 *          description="School ID",
 *          type="integer",
 *          nullable=false
 *      ),
 *      @OA\Property(
 *          property="status_updated_at",
 *          description="Timestamp of the last status update",
 *          type="string",
 *          format="date-time",
 *          nullable=true
 *      ),
 *      @OA\Property(
 *          property="accepted_at",
 *          description="Timestamp of acceptance",
 *          type="string",
 *          format="date-time",
 *          nullable=true
 *      ),
 *      @OA\Property(
 *          property="created_at",
 *          description="Creation timestamp",
 *          type="string",
 *          format="date-time",
 *          nullable=true
 *      ),
 *      @OA\Property(
 *          property="updated_at",
 *          description="Update timestamp",
 *          type="string",
 *          format="date-time",
 *          nullable=true
 *      ),
 *      @OA\Property(
 *          property="deleted_at",
 *          description="Deletion timestamp",
 *          type="string",
 *          format="date-time",
 *          nullable=true
 *      )
 * )
 */
class ClientsSchool extends Model
{
    use LogsActivity, SoftDeletes, HasFactory;

    public $table = 'clients_schools';

    public $fillable = [
        'client_id',
        'school_id',
        'status_updated_at',
        'accepted_at',
        'accepts_newsletter',
        'is_vip'
    ];

    protected $casts = [
        'status_updated_at' => 'datetime',
        'accepted_at' => 'datetime',
        'accepts_newsletter' => 'boolean',
        'is_vip' => 'boolean',
    ];

    public static array $rules = [
        'client_id' => 'nullable',
        'school_id' => 'nullable',
        'updated_at' => 'nullable',
        'created_at' => 'nullable',
        'deleted_at' => 'nullable',
        'status_updated_at' => 'nullable',
        'accepted_at' => 'nullable',
        'accepts_newsletter' => 'nullable|boolean',
        'is_vip' => 'nullable|boolean',
    ];

    public function school(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\School::class, 'school_id');
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Client::class, 'client_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
         return LogOptions::defaults();
    }
}
