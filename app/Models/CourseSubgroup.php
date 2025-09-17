<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @OA\Schema(
 *      schema="CourseSubgroup",
 *      required={"course_id","course_date_id","degree_id","course_group_id"},
 *      @OA\Property(
 *           property="course_id",
 *           description="ID of the course",
 *           type="integer",
 *           nullable=false
 *       ),
 *       @OA\Property(
 *           property="course_date_id",
 *           description="ID of the course date",
 *           type="integer",
 *           nullable=false
 *       ),
 *       @OA\Property(
 *           property="degree_id",
 *           description="ID of the degree",
 *           type="integer",
 *           nullable=false
 *       ),
 *       @OA\Property(
 *           property="course_group_id",
 *           description="ID of the course group",
 *           type="integer",
 *           nullable=false
 *       ),
 *       @OA\Property(
 *           property="monitor_id",
 *           description="ID of the assigned monitor",
 *           type="integer",
 *           nullable=true
 *       ),
 *       @OA\Property(
 *           property="max_participants",
 *           description="Maximum number of participants",
 *           type="integer",
 *           nullable=true
 *       ),
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
 */
class CourseSubgroup extends Model
{
    use LogsActivity, SoftDeletes, HasFactory;

    public $table = 'course_subgroups';
    protected $appends = []; // Temporarily disabled ['is_full']
    public $fillable = [
        'course_id',
        'course_date_id',
        'degree_id',
        'course_group_id',
        'monitor_id',
        'old_id',
        'max_participants'
    ];

    protected $casts = [

    ];

    public static array $rules = [
        'course_id' => 'required',
        'course_date_id' => 'required',
        'degree_id' => 'required',
        'course_group_id' => 'required',
        'monitor_id' => 'nullable',
        'max_participants' => 'nullable',
        'created_at' => 'nullable',
        'updated_at' => 'nullable',
        'deleted_at' => 'nullable'
    ];

    /**
     * Accesor para determinar si el subgrupo está lleno.
     *
     * @return bool
     */
    public function getIsFullAttribute(): bool
    {
        // Check if max_participants is set
        if (!$this->max_participants) {
            return false;
        }

        // Check if bookingUsers relation is loaded
        if (!$this->relationLoaded('bookingUsers')) {
            // Load the relation if not loaded
            $this->load('bookingUsers');
        }

        // Check if bookingUsers exists and is not null
        if (!$this->bookingUsers) {
            return false;
        }

        // Filtrar los bookingUsers con status = 1
        $activeBookingUsers = $this->bookingUsers->filter(function ($user) {
            return $user->status == 1;
        });

        return $activeBookingUsers->count() >= $this->max_participants;
    }

    public function degree(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Degree::class, 'degree_id');
    }

    public function course(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Course::class, 'course_id');
    }

    public function courseGroup(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\CourseGroup::class, 'course_group_id');
    }

    public function courseDate(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\CourseDate::class, 'course_date_id');
    }

    public function monitor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Monitor::class, 'monitor_id');
    }

    public function bookingUserss(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\BookingUser::class, 'course_subgroup_id');
    }

    public function bookingUsers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\BookingUser::class, 'course_subgroup_id')
            ->where('status', 1) // BookingUser debe tener status 1
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 2); // La Booking no debe tener status 2
            });
    }

    /**
     * Filtra los subgrupos según la disponibilidad y otros criterios.
     */
    public function scopeWhereHasAvailableSubgroups(Builder $query, $startDate, $endDate, $sportId = 1,
                                                            $clientId = null, $degreeId = null, $getLowerDegrees = false,
                                                            $degreeOrders = null, $min_age = null, $max_age = null)
    {
        $client = $clientId ? Client::find($clientId) : null;
        $clientAge = $client ? Carbon::parse($client->birth_date)->age : null;
        $isAdultClient = $clientAge >= 18;

        $clientLanguages = [];
        if ($client) {
            for ($i = 1; $i <= 6; $i++) {
                $languageField = 'language' . $i . '_id';
                if (!empty($client->$languageField)) {
                    $clientLanguages[] = $client->$languageField;
                }
            }
        }

        // Obtener grado del cliente si se proporcionó degreeId
        $clientDegree = $degreeId ? Degree::find($degreeId) : null;

        // Filtro por fecha
        $query->whereHas('courseDate', function ($dateQuery) use ($startDate, $endDate) {
            $dateQuery->where('date', '>=', $startDate)
                ->where('date', '<=', $endDate);
        });

        $query->whereHas('course', function ($groupQuery) use ($sportId, $clientDegree, $clientAge, $getLowerDegrees, $min_age, $max_age, $degreeOrders) {
            $groupQuery->where('sport_id', $sportId);
        });

        $query->whereHas('courseGroup', function ($groupQuery) use ($clientDegree, $clientAge, $getLowerDegrees,
            $min_age, $max_age, $degreeOrders) {
            if ($clientDegree) {
                $degreeOrder = $clientDegree->degree_order;
                if ($getLowerDegrees) {
                    $groupQuery->whereHas('degree', function ($degreeQuery) use ($degreeOrder) {
                        $degreeQuery->where
                        ('degree_order', '<=', $degreeOrder);
                    });
                } else {
                    $groupQuery->where('degree_id', $clientDegree->id);
                }
            }
            if ($clientAge !== null) {
                $groupQuery->where('age_min', '<=', $clientAge)
                    ->where('age_max', '>=', $clientAge);
            } elseif ($min_age !== null || $max_age !== null) {
                if ($min_age !== null) {
                    $groupQuery->where('age_min', '<=', $min_age);
                }
                if ($max_age !== null) {
                    $groupQuery->where('age_max', '>=', $max_age);
                }
            }

            if (!empty($degreeOrders)) {
                $groupQuery->whereHas('degree', function ($degreeQuery) use ($degreeOrders, $getLowerDegrees) {
                    if ($getLowerDegrees) {
                        $degreeQuery->whereIn('degree_order', array_map('min', $degreeOrders));
                    } else {
                        $degreeQuery->whereIn('degree_order', $degreeOrders);
                    }
                });
            }
        });

        $query->where(function ($query) use ($isAdultClient, $clientLanguages) {
            $query->doesntHave('monitor') // Subgrupo sin monitor asignado es válido automáticamente
            ->orWhereHas('monitor', function ($monitorQuery) use ($isAdultClient, $clientLanguages) {
                // Si el subgrupo tiene monitor, realizar comprobaciones adicionales
                if ($isAdultClient) {
                    $monitorQuery->whereHas('monitorSportsDegrees', function ($query) {
                        $query->where('allow_adults', true);
                    });
                }

                if (!empty($clientLanguages)) {
                    $monitorQuery->where(function ($query) use ($clientLanguages) {
                        for ($i = 1; $i <= 6; $i++) {
                            $query->orWhereIn("language{$i}_id", $clientLanguages);
                        }
                    });
                }
            });
        });

        return $query;
    }

    public function getActivitylogOptions(): LogOptions
    {
         return LogOptions::defaults();
    }
}
