<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @OA\Schema(
 *      schema="Monitor",
 *      required={"first_name","last_name","birth_date","avs","work_license","bank_details","children"},
 *      @OA\Property(
 *          property="email",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="first_name",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="last_name",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="birth_date",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="string",
 *          format="date"
 *      ),
 *      @OA\Property(
 *          property="phone",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="telephone",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="address",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="cp",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="city",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="province",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="integer",
 *      ),
 *      @OA\Property(
 *          property="country",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="integer",
 *      ),
 *      @OA\Property(
 *           property="world_country",
 *           description="",
 *           readOnly=false,
 *           nullable=true,
 *           type="integer",
 *       ),
 *      @OA\Property(
 *          property="image",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="avs",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="work_license",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="bank_details",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="children",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="integer",
 *      ),
 *      @OA\Property(
 *          property="civil_status",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="family_allowance",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="boolean",
 *      ),
 *      @OA\Property(
 *          property="partner_work_license",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="partner_works",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="boolean",
 *      ),
 *      @OA\Property(
 *           property="language1_id",
 *           description="ID of the first language",
 *           type="integer",
 *           nullable=true
 *       ),
 *       @OA\Property(
 *           property="language2_id",
 *           description="ID of the second language",
 *           type="integer",
 *           nullable=true
 *       ),
 *       @OA\Property(
 *           property="language3_id",
 *           description="ID of the third language",
 *           type="integer",
 *           nullable=true
 *       ),
 *       @OA\Property(
 *           property="language4_id",
 *           description="ID of the fourth language",
 *           type="integer",
 *           nullable=true
 *       ),
 *       @OA\Property(
 *           property="language5_id",
 *           description="ID of the fifth language",
 *           type="integer",
 *           nullable=true
 *       ),
 *       @OA\Property(
 *           property="language6_id",
 *           description="ID of the sixth language",
 *           type="integer",
 *           nullable=true
 *       ),
 *        @OA\Property(
 *            property="active_school",
 *            description="ID of the active school",
 *            type="integer",
 *            nullable=true
 *        ),
 *            @OA\Property(
 *            property="active_station",
 *            description="ID of the active station",
 *            type="integer",
 *            nullable=true
 *        ),
 *       @OA\Property(
 *           property="partner_percentaje",
 *           description="Percentage of partner's work",
 *           type="string",
 *           nullable=true
 *       ),
 *       @OA\Property(
 *           property="user_id",
 *           description="User ID associated with the monitor",
 *           type="integer",
 *           nullable=true
 *       ),
 *      @OA\Property(
 *           property="active",
 *           description="",
 *           readOnly=false,
 *           nullable=false,
 *           type="boolean",
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
class Monitor extends Model
{
      use LogsActivity, SoftDeletes, HasFactory;     public $table = 'monitors';

    public $fillable = [
        'email',
        'first_name',
        'last_name',
        'birth_date',
        'phone',
        'telephone',
        'address',
        'cp',
        'city',
        'province',
        'country',
        'world_country',
        'language1_id',
        'language2_id',
        'language3_id',
        'language4_id',
        'language5_id',
        'language6_id',
        'image',
        'avs',
        'work_license',
        'bank_details',
        'children',
        'civil_status',
        'family_allowance',
        'partner_work_license',
        'partner_works',
        'partner_percentaje',
        'user_id',
        'active_school',
        'active',
        'old_id',
        'active_station'
    ];

    protected $casts = [
        'email' => 'string',
        'first_name' => 'string',
        'last_name' => 'string',
        'birth_date' => 'date',
        'phone' => 'string',
        'telephone' => 'string',
        'address' => 'string',
        'cp' => 'string',
        'city' => 'string',
        'province' => 'string',
        'country' => 'string',
        'world_country' => 'string',
        'image' => 'string',
        'avs' => 'string',
        'work_license' => 'string',
        'bank_details' => 'string',
        'civil_status' => 'string',
        'family_allowance' => 'boolean',
        'partner_work_license' => 'string',
        'partner_works' => 'boolean'
    ];

    public static array $rules = [
        'email' => 'nullable|string|max:100',
        'first_name' => 'string|max:255',
        'last_name' => 'string|max:255',
        'birth_date' => 'nullable',
        'phone' => 'nullable|string|max:255',
        'telephone' => 'nullable|string|max:255',
        'address' => 'nullable|string|max:255',
        'cp' => 'nullable|string|max:100',
        'city' => 'nullable|string|max:65535',
        'province' => 'nullable',
        'country' => 'nullable',
        'world_country' => 'nullable',
        'language1_id' => 'nullable',
        'language2_id' => 'nullable',
        'language3_id' => 'nullable',
        'language4_id' => 'nullable',
        'language5_id' => 'nullable',
        'language6_id' => 'nullable',
        'image' => 'nullable|string',
        'avs' => 'nullable|string|max:255',
        'work_license' => 'nullable|string|max:255',
        'bank_details' => 'nullable|string|max:255',
        'children' => 'nullable',
        'civil_status' => 'nullable|string',
        'family_allowance' => 'nullable|boolean',
        'partner_work_license' => 'nullable|string|max:255',
        'partner_works' => 'nullable|boolean',
        'partner_percentaje' => 'nullable',
        'user_id' => 'nullable',
        'active_school' => 'nullable',
        'active_station' => 'nullable',
        'active' => 'nullable|boolean',
        'created_at' => 'nullable',
        'updated_at' => 'nullable',
        'deleted_at' => 'nullable'
    ];

    protected $appends = ['monitor_sports_degrees_details'];

    public function language2(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Language::class, 'language2_id');
    }

    public function activeSchool(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\School::class, 'active_school');
    }

    public function activeStation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Station::class, 'active_station');
    }

    public function language3(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Language::class, 'language3_id');
    }

    public function language4(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Language::class, 'language4_id');
    }

    public function language5(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Language::class, 'language5_id');
    }

    public function language6(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Language::class, 'language6_id');
    }

    public function language1(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Language::class, 'language1_id');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function bookingUsers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\BookingUser::class, 'monitor_id');
    }

    public function bookingUsersActive(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\BookingUser::class, 'course_date_id')
            ->where('status', 1) // BookingUser debe tener status 1
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 2); // La Booking no debe tener status 2
            });
    }

    public function courseSubgroups(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\CourseSubgroup::class, 'monitor_id');
    }

    public function monitorNwds(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\MonitorNwd::class, 'monitor_id');
    }

    public function monitorObservations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\MonitorObservation::class, 'monitor_id');
    }

    public function monitorSportsDegrees(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MonitorSportsDegree::class, 'monitor_id')
            ->with(['sport', 'degree']); // Cargar deportes y niveles relacionados
    }

    public function monitorTrainings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MonitorTraining::class, 'monitor_id')
            ->with(['sport', 'school']); // Cargar deportes y escuelas relacionadas
    }

    // ...

    public function getMonitorSportsDegreesDetailsAttribute()
    {
        // Check if monitorSportsDegrees relation is loaded
        if (!$this->relationLoaded('monitorSportsDegrees')) {
            $this->load('monitorSportsDegrees.sport', 'monitorSportsDegrees.degree', 'monitorSportsDegrees.monitorSportAuthorizedDegrees');
        }

        // Check if monitorSportsDegrees exists and is not null
        if (!$this->monitorSportsDegrees) {
            return collect();
        }

        return $this->monitorSportsDegrees->map(function ($monitorSportsDegree) {
            return [
                'sport_name' => $monitorSportsDegree->sport ? $monitorSportsDegree->sport->name : null,
                'sport_icon_selected' => $monitorSportsDegree->sport ? $monitorSportsDegree->sport->icon_selected : null,
                'sport_icon_unselected' => $monitorSportsDegree->sport ? $monitorSportsDegree->sport->icon_unselected : null,
                'school_id' => $monitorSportsDegree->school_id,
                'sport_id' => $monitorSportsDegree->sport_id,
                'degree' => $monitorSportsDegree->degree,
                'monitor_sport_authorized_degrees' => $monitorSportsDegree->monitorSportAuthorizedDegrees ? $monitorSportsDegree->monitorSportAuthorizedDegrees->reverse() : [],
            ];
        });
    }

    public function sports(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            \App\Models\Sport::class, // Modelo final al que quieres llegar
            \App\Models\MonitorSportsDegree::class, // Modelo intermedio
            'monitor_id', // Clave foránea en el modelo intermedio
            'id', // Clave foránea en el modelo final
            'id', // Clave local en el modelo inicial
            'sport_id' // Clave local en el modelo intermedio
        );
    }

    public function monitorsSchools(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\MonitorsSchool::class, 'monitor_id');
    }

    /**
     * NUEVO: Asignaciones de monitores por intervalo
     */
    public function intervalAssignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\CourseIntervalMonitor::class, 'monitor_id');
    }

    /**
     * Scope to filter monitors by spoken languages.
     */
    public function scopeWithLanguages($query, array $languages)
    {
        if (empty($languages)) {
            return $query;
        }

        return $query->where(function ($q) use ($languages) {
            $q->orWhereIn('language1_id', $languages)
                ->orWhereIn('language2_id', $languages)
                ->orWhereIn('language3_id', $languages)
                ->orWhereIn('language4_id', $languages)
                ->orWhereIn('language5_id', $languages)
                ->orWhereIn('language6_id', $languages);
        });
    }

    /**
     * Scope to restrict monitors to a sport and minimum degree.
     *
     * TODO: allow passing multiple sports and level ranges.
     */
    public function scopeWithSportAndDegree($query, $sportId, $schoolId, $degreeOrder = null, $allowAdults = false)
    {
        return $query->whereHas('monitorSportsDegrees', function ($q) use ($sportId, $schoolId, $degreeOrder, $allowAdults) {
            $q->where('sport_id', $sportId)
                ->when($allowAdults, function ($sub) {
                    $sub->where('allow_adults', true);
                })
                ->whereHas('monitorSportAuthorizedDegrees', function ($q2) use ($schoolId, $degreeOrder) {
                    $q2->where('school_id', $schoolId);

                    if (!is_null($degreeOrder)) {
                        $q2->whereHas('degree', function ($d) use ($degreeOrder) {
                            $d->where('degree_order', '>=', $degreeOrder);
                        });
                    }
                });
        })->whereHas('monitorsSchools', function ($q) use ($schoolId) {
            $q->where('school_id', $schoolId)->where('active_school', 1);
        });
    }

    /**
     * Scope to filter monitors available between times on a given date.
     */
    public function scopeAvailableBetween($query, $date, $startTime, $endTime, array $excludeBookingUserIds = [])
    {
        return $query
            ->whereDoesntHave('bookingUsers', function ($q) use ($date, $startTime, $endTime, $excludeBookingUserIds) {
                $q->whereDate('date', $date)
                    ->when(!empty($excludeBookingUserIds), function ($sub) use ($excludeBookingUserIds) {
                        $sub->whereNotIn('id', $excludeBookingUserIds);
                    })
                    ->whereTime('hour_start', '<', $endTime)
                    ->whereTime('hour_end', '>', $startTime)
                    ->where('status', 1)
                    ->whereHas('booking', function ($b) {
                        $b->where('status', '!=', 2);
                    });
            })
            ->whereDoesntHave('monitorNwds', function ($q) use ($date, $startTime, $endTime) {
                $q->whereDate('start_date', '<=', $date)
                    ->whereDate('end_date', '>=', $date)
                    ->where(function ($time) use ($startTime, $endTime) {
                        $time->where('full_day', true)
                            ->orWhere(function ($t) use ($startTime, $endTime) {
                                $t->whereTime('start_time', '<', $endTime)
                                    ->whereTime('end_time', '>', $startTime);
                            });
                    });
            })
            ->whereDoesntHave('courseSubgroups', function ($q) use ($date, $startTime, $endTime) {
                $q->whereHas('courseDate', function ($cd) use ($date, $startTime, $endTime) {
                    $cd->whereDate('date', $date)
                        ->whereTime('hour_start', '<', $endTime)
                        ->whereTime('hour_end', '>', $startTime);
                });
            });
    }

    public function schools(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            \App\Models\School::class, // Modelo final al que quieres llegar
            \App\Models\MonitorsSchool::class, // Modelo intermedio
            'monitor_id', // Clave foránea en el modelo intermedio
            'id', // Clave foránea en el modelo final
            'id', // Clave local en el modelo inicial
            'school_id' // Clave local en el modelo intermedio
        );
    }

    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }


    public static function isMonitorBusy(
        $monitorId,
        $date,
        $startTime = null,
        $endTime = null,
        $excludeNwdId = null,              // igual que tu $excludeId actual
        array $excludeBookingUserIds = [], // NUEVO: evita autoconflictos
        array $excludeSubgroupIds = []     // NUEVO: evita autoconflictos
    ) {
        // Guard clauses
        if (!$monitorId || !$date) {
            // sin monitor o sin fecha no podemos comprobar solapes
            return false;
        }

        // -----------------------------
        // 1) BookingUsers (reservas)
        // -----------------------------
        $bookingQuery = BookingUser::where('monitor_id', $monitorId)
            ->whereDate('date', $date)
            // Exige que la CourseDate exista y no esté borrada
            ->whereHas('courseDate', function ($q) {
                $q->whereNull('deleted_at');
            })
            // El BU debe estar activo y la Booking no cancelada (status != 2)
            ->where('status', 1)
            ->whereHas('booking', function ($q) {
                $q->where('status', '!=', 2);
            });

        if (!empty($excludeBookingUserIds)) {
            $bookingQuery->whereNotIn('id', $excludeBookingUserIds);
        }

        // Solape por horas (si tenemos horas)
        if ($startTime && $endTime) {
            $bookingQuery->where(function ($q) use ($startTime, $endTime) {
                $q->whereTime('hour_start', '<', $endTime)
                    ->whereTime('hour_end', '>', $startTime);
            });
        }

        $isBooked = $bookingQuery->exists();

        if ($isBooked) {
            $conflictingBookings = $bookingQuery->with('booking')->get();
            \Log::channel('nwd')->warning('Solapamiento detectado - Monitor ocupado por booking', [
                'monitor_id' => $monitorId,
                'date'       => $date,
                'start_time' => $startTime,
                'end_time'   => $endTime,
                'conflict_type' => 'booking',
                'conflicting_bookings' => $conflictingBookings->map(function ($bu) {
                    return [
                        'booking_user_id' => $bu->id,
                        'booking_id'      => $bu->booking_id,
                        'hour_start'      => $bu->hour_start,
                        'hour_end'        => $bu->hour_end,
                    ];
                })->toArray()
            ]);
        }

        // -----------------------------
        // 2) NWD día completo
        // -----------------------------
        $fullDayNwdQuery = MonitorNwd::where('monitor_id', $monitorId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->where('full_day', true);

        if ($excludeNwdId !== null) {
            $fullDayNwdQuery->where('id', '!=', $excludeNwdId);
        }

        $hasFullDayNwd = $fullDayNwdQuery->exists();

        if ($hasFullDayNwd) {
            $conflictingNwd = $fullDayNwdQuery->first();
            \Log::channel('nwd')->warning('Solapamiento detectado - Monitor ocupado por NWD día completo', [
                'monitor_id' => $monitorId,
                'date'       => $date,
                'start_time' => $startTime,
                'end_time'   => $endTime,
                'conflict_type' => 'nwd_full_day',
                'conflicting_nwd' => [
                    'nwd_id'     => $conflictingNwd->id,
                    'start_date' => $conflictingNwd->start_date,
                    'end_date'   => $conflictingNwd->end_date,
                    'description'=> $conflictingNwd->description
                ]
            ]);
        }

        // -----------------------------
        // 3) NWD parciales (con horas)
        // -----------------------------
        $nwdQuery = MonitorNwd::where('monitor_id', $monitorId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date);

        if ($startTime && $endTime) {
            $nwdQuery->where(function ($q) use ($startTime, $endTime) {
                $q->whereTime('start_time', '<', $endTime)
                    ->whereTime('end_time', '>', $startTime);
            });
        }

        if ($excludeNwdId !== null) {
            $nwdQuery->where('id', '!=', $excludeNwdId);
        }

        $isNwd = $nwdQuery->exists();

        if ($isNwd) {
            $conflictingNwds = $nwdQuery->get();
            \Log::channel('nwd')->warning('Solapamiento detectado - Monitor ocupado por otro NWD', [
                'monitor_id'   => $monitorId,
                'date'         => $date,
                'start_time'   => $startTime,
                'end_time'     => $endTime,
                'exclude_nwd'  => $excludeNwdId,
                'conflict_type'=> 'nwd_overlap',
                'conflicting_nwds' => $conflictingNwds->map(function ($nwd) {
                    return [
                        'nwd_id'     => $nwd->id,
                        'start_date' => $nwd->start_date,
                        'end_date'   => $nwd->end_date,
                        'start_time' => $nwd->start_time,
                        'end_time'   => $nwd->end_time,
                        'description'=> $nwd->description
                    ];
                })->toArray()
            ]);
        }

        // -----------------------------
        // 4) CourseSubgroups (cursos)
        // -----------------------------
        $courseQuery = CourseSubgroup::where('monitor_id', $monitorId)
            ->when(!empty($excludeSubgroupIds), function ($q) use ($excludeSubgroupIds) {
                $q->whereNotIn('id', $excludeSubgroupIds);
            })
            ->whereHas('courseDate', function ($q) use ($date, $startTime, $endTime) {
                $q->whereNull('deleted_at')
                    ->whereDate('date', $date)
                    ->where(function ($tq) use ($startTime, $endTime) {
                        if ($startTime && $endTime) {
                            $tq->whereTime('hour_start', '<', $endTime)
                                ->whereTime('hour_end', '>', $startTime);
                        }
                    });
            })
            ->whereHas('courseGroup') // que exista el grupo
            ->whereHas('courseGroup.course', function ($q) {
                $q->where('active', 1); // curso activo
            });

        $isCourse = $courseQuery->exists();

        if ($isCourse) {
            $conflictingCourses = $courseQuery->with('courseDate', 'courseGroup.course')->get();
            \Log::channel('nwd')->warning('Solapamiento detectado - Monitor ocupado por curso', [
                'monitor_id' => $monitorId,
                'date'       => $date,
                'start_time' => $startTime,
                'end_time'   => $endTime,
                'conflict_type' => 'course',
                'conflicting_courses' => $conflictingCourses->map(function ($cs) {
                    return [
                        'course_subgroup_id' => $cs->id,
                        'course_id'          => $cs->courseGroup->course->id ?? null,
                        'course_name'        => $cs->courseGroup->course->name ?? null,
                        'hour_start'         => $cs->courseDate->hour_start ?? null,
                        'hour_end'           => $cs->courseDate->hour_end ?? null,
                    ];
                })->toArray()
            ]);
        }

        // Resultado final
        return $isBooked || $hasFullDayNwd || $isNwd || $isCourse;
    }


    public function getActivitylogOptions(): LogOptions
    {
         return LogOptions::defaults();
    }
}
