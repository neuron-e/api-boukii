<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Arr;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @OA\Schema(
 *      schema="Course",
 *      required={"course_type","is_flexible","sport_id","school_id","name","short_description","description","price","currency","max_participants","confirm_attendance","active","online"},
 *      @OA\Property(
 *          property="course_type",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="boolean",
 *      ),
 *      @OA\Property(
 *          property="is_flexible",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="boolean",
 *      ),
 *      @OA\Property(
 *          property="name",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="short_description",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="description",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="price",
 *          description="If duration_flexible, per 15min",
 *          readOnly=false,
 *          nullable=false,
 *          type="number",
 *          format="number"
 *      ),
 *      @OA\Property(
 *          property="currency",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="date_start",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *          format="date"
 *      ),
 *      @OA\Property(
 *          property="date_end",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *          format="date"
 *      ),
 *      @OA\Property(
 *          property="date_start_res",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *          format="date"
 *      ),
 *      @OA\Property(
 *          property="date_end_res",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *          format="date"
 *      ),
 *     @OA\Property(
 *            property="age_min",
 *            description="Minimum age for participants",
 *            type="integer",
 *            nullable=true
 *        ),
 *        @OA\Property(
 *            property="age_max",
 *            description="Maximum age for participants",
 *            type="integer",
 *            nullable=true
 *        ),
 *      @OA\Property(
 *          property="confirm_attendance",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="boolean",
 *      ),
 *      @OA\Property(
 *           property="highlighted",
 *           description="",
 *           readOnly=false,
 *           nullable=false,
 *           type="boolean",
 *       ),
 *      @OA\Property(
 *          property="active",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="boolean",
 *      ),
 *     @OA\Property(
 *          property="unique",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="boolean",
 *      ),
 *      @OA\Property(
 *          property="online",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="boolean",
 *      ),
 *      @OA\Property(
 *           property="options",
 *           description="",
 *           readOnly=false,
 *           nullable=false,
 *           type="boolean",
 *       ),
 *      @OA\Property(
 *          property="image",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="translations",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="claim_text",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="price_range",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="discounts",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="settings",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *           property="sport_id",
 *           description="Sport ID",
 *           type="integer",
 *           nullable=false
 *       ),
 *       @OA\Property(
 *           property="school_id",
 *           description="School ID",
 *           type="integer",
 *           nullable=false
 *       ),
 *       @OA\Property(
 *           property="station_id",
 *           description="Station ID",
 *           type="integer",
 *           nullable=true
 *       ),
 *       @OA\Property(
 *           property="max_participants",
 *           description="Maximum number of participants",
 *           type="integer",
 *           nullable=false
 *       ),
 *       @OA\Property(
 *           property="duration",
 *           description="Duration of the course",
 *           type="string",
 *           nullable=false
 *       ),
 *       @OA\Property(
 *           property="hour_min",
 *           description="Minimum hour for the course",
 *           type="string",
 *           nullable=true
 *       ),
 *       @OA\Property(
 *           property="hour_max",
 *           description="Maximum hour for the course",
 *           type="string",
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
class Course extends Model
{

    use LogsActivity, SoftDeletes, HasFactory;

    public $table = 'courses';

    public $fillable = [
        'course_type',
        'is_flexible',
        'use_interval_groups',
        'intervals_config_mode',
        'sport_id',
        'school_id',
        'station_id',
        'name',
        'short_description',
        'description',
        'old_id',
        'user_id',
        'price',
        'currency',
        'max_participants',
        'duration',
        'date_start',
        'date_end',
        'date_start_res',
        'date_end_res',
        'hour_min',
        'hour_max',
        'age_min',
        'age_max',
        'confirm_attendance',
        'active',
        'unique',
        'online',
        'options',
        'image',
        'translations',
        'price_range',
        'discounts',
        'settings',
        'highlighted', // Nuevo campo
        'claim_text', // Nuevo campo
        'archived_at', // Campo para archivar cursos con reservas
        'meeting_point',
        'meeting_point_address',
        'meeting_point_instructions',
    ];

    protected $casts = [
        'is_flexible' => 'boolean',
        'use_interval_groups' => 'boolean',
        'name' => 'string',
        'short_description' => 'string',
        'description' => 'string',
        'price' => 'decimal:2',
        'currency' => 'string',
        'duration' => 'string',
        'date_start' => 'date',
        'date_end' => 'date',
        'date_start_res' => 'date',
        'date_end_res' => 'date',
        'hour_min' => 'string',
        'hour_max' => 'string',
        'confirm_attendance' => 'boolean',
        'active' => 'boolean',
        'unique' => 'boolean',
        'options' => 'boolean',
        'online' => 'boolean',
        'image' => 'string',
        'translations' => 'string',
        'price_range' => 'json',
        'discounts' => 'json',
        'settings' => 'json',
        'highlighted' => 'boolean',
        'archived_at' => 'datetime',
        'meeting_point' => 'string',
        'meeting_point_address' => 'string',
        'meeting_point_instructions' => 'string'
    ];

    public static function rules($isUpdate = false): array
    {
        $rules = [
            'course_type' => 'required',
            'is_flexible' => 'required|boolean',
            'sport_id' => 'required',
            'school_id' => 'required',
            'user_id' => 'nullable',
            'station_id' => 'nullable',
            'name' => 'required|string|max:65535',
            'short_description' => 'required|string|max:65535',
            'description' => 'required|string|max:65535',
            'price' => 'required|numeric',
            'currency' => 'required|string|max:3',
            'max_participants' => 'required',
            'duration' => 'nullable',
            'date_start' => 'nullable',
            'date_end' => 'nullable',
            'date_start_res' => 'nullable',
            'date_end_res' => 'nullable',
            'hour_min' => 'nullable|string|max:255',
            'hour_max' => 'nullable|string|max:255',
            'confirm_attendance' => 'required|boolean',
            'highlighted' => 'required|boolean',
            'active' => 'required|boolean',
            'unique' => 'nullable',
            'options' => 'nullable',
            'online' => 'required|boolean',
            'image' => 'nullable|string',
            'age_min' => 'nullable',
            'age_max' => 'nullable',
            'translations' => 'nullable|string',
            'claim_text' => 'nullable|string',
            'price_range' => 'nullable',
            'discounts' => 'nullable|string',
            'settings' => 'nullable|string',
            'created_at' => 'nullable',
            'updated_at' => 'nullable',
            'deleted_at' => 'nullable',
        ];

        // Modifica las reglas si es una actualización
        if ($isUpdate) {
            foreach ($rules as $key => $rule) {
                // Solo elimina la validación de "required" para las actualizaciones
                $rules[$key] = str_replace('required|', '', $rule);
                $rules[$key] = str_replace('required', 'nullable', $rules[$key]);
            }
        }

        return $rules;
    }

    /**
     * Get the settings as an array or null if invalid.
     */
    public function getSettingsAttribute($value)
    {
        return $this->getJsonOrNull($value);
    }

    /**
     * Helper function to return the JSON or null.
     */
    private function getJsonOrNull($value)
    {
        $decoded = json_decode($value, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
    }

    public function sport(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Sport::class, 'sport_id');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function school(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\School::class, 'school_id');
    }

    public function station(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Station::class, 'station_id');
    }

    public function bookingUsers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\BookingUser::class, 'course_id');
    }

    public function bookingUsersActive(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\BookingUser::class, 'course_id')
            ->where('status', 1) // BookingUser debe tener status 1
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 2); // La Booking no debe tener status 2
            })
            ->where(function ($query) {
                $query->whereNull('course_group_id') // Permitir si es null
                ->orWhereHas('courseGroup');  // Solo si el grupo existe

                $query->whereNull('course_subgroup_id') // Permitir si es null
                ->orWhereHas('courseSubgroup'); // Solo si el subgrupo existe
            });
    }





    public function bookings(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            \App\Models\Booking::class, // Modelo final al que queremos llegar
            \App\Models\BookingUser::class, // Modelo intermedio
            'course_id', // Llave foránea en el modelo intermedio (BookingUser) que conecta con Course
            'id', // Llave primaria en el modelo Booking que conecta con BookingUser
            'id', // Llave primaria en el modelo Course que conecta con BookingUser
            'booking_id' // Llave foránea en el modelo BookingUser que conecta con Booking
        );
    }

    public function courseDates(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\CourseDate::class, 'course_id')->orderBy('date', 'asc');
    }

    public function courseDatesActive(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\CourseDate::class, 'course_id')
            ->where('active', 1)->orderBy('date', 'asc');
    }


    public function courseExtras(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\CourseExtra::class, 'course_id');
    }

    public function courseGroups(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\CourseGroup::class, 'course_id');
    }

    public function courseSubgroups(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\CourseSubgroup::class, 'course_id')
            ->whereHas('courseGroup');
    }

    public function courseIntervals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\CourseInterval::class, 'course_id')
            ->orderBy('display_order');
    }

    protected $appends = ['icon', 'minPrice', 'minDuration', 'typeString'];

    public function getStartDateAttribute()
    {
        // Obtiene la primera fecha activa ordenada cronológicamente
        return $this->courseDates()
            ->where('active', 1)
            ->orderBy('date', 'asc')
            ->first()
            ?->date;
    }

    public function getTypeStringAttribute()
    {
        // Devuelve un string basado en el valor de course_type
        switch ($this->course_type) {
            case 1:
                return 'colective';
            case 2:
                return 'private';
            case 3:
                return 'activity';
            default:
                return null; // O devuelve un valor por defecto si es necesario
        }
    }

    public function getEndDateAttribute()
    {
        // Obtiene la última fecha activa ordenada cronológicamente
        return $this->courseDates()
            ->where('active', 1)
            ->orderBy('date', 'desc')
            ->first()
            ?->date;
    }

    public function getMinPriceAttribute()
    {
        $priceRange = $this->price_range;

        if (is_array($priceRange) && !empty($priceRange)) {
            $minPrice = null;

            foreach ($priceRange as $interval) {
                $prices = array_filter(Arr::except($interval, ['intervalo']), function ($value) {
                    return $value !== null;
                });

                if (!empty($prices)) {
                    $currentMin = min($prices);
                    $minPrice = $minPrice === null ? $currentMin : min($minPrice, $currentMin);
                }
            }

            return $minPrice;
        }

        return $this->price;
    }

    public function getMinDurationAttribute()
    {
        $priceRange = $this->price_range;

        if (is_array($priceRange) && !empty($priceRange)) {
            $minDuration = null;

            foreach ($priceRange as $interval) {
                // Comprobar si hay precios en el intervalo
                $prices = array_filter(Arr::except($interval, ['intervalo']), function ($value) {
                    return $value !== null;
                });

                if (!empty($prices) && isset($interval['intervalo'])) {
                    $duration = $interval['intervalo'];

                    if ($minDuration === null) {
                        $minDuration = $duration;
                    } else {
                        $minDuration = $this->compareDurations($minDuration, $duration);
                    }
                }
            }

            return $minDuration;
        }

        return $this->duration;
    }

    private function compareDurations($duration1, $duration2)
    {
        $duration1Minutes = $this->durationToMinutes($duration1);
        $duration2Minutes = $this->durationToMinutes($duration2);

        return $duration1Minutes < $duration2Minutes ? $duration1 : $duration2;
    }

    private function durationToMinutes($duration)
    {
        $parts = explode(' ', $duration);
        $minutes = 0;

        foreach ($parts as $part) {
            if (str_ends_with($part, 'h')) {
                $minutes += (int) str_replace('h', '', $part) * 60;
            } elseif (str_ends_with($part, 'm')) {
                $minutes += (int) str_replace('m', '', $part);
            }
        }

        return $minutes;
    }

    public function getIconAttribute()
    {
        // Obtener el curso asociado a este bookingUser
        $course = $this;

        if ($course) {
            $courseType = $course->course_type ?? null;
            if (!$course->sport) {
                return null;
            }

            // Verificar si hay un course_type definido
            if ($courseType !== null) {
                // Devolver el deporte basado en el course_type
                switch ($courseType) {
                    case 1:
                        return $course->sport->icon_collective;
                        break;
                    case 2:
                        return $course->sport->icon_prive;
                        break;
                    case 3:
                        return $course->sport->icon_activity;
                        break;
                    default:
                        return $course->sport->icon_selected;
                }
            }
        }

        // Si no se puede determinar el deporte, devolver 'multiple'
        return 'multiple';
    }

    public function scopeWithAvailableDates(Builder $query, $type = null, $startDate, $endDate, $sportId = 1,
                                                    $clientId = null, $degreeId = null, $getLowerDegrees = false,
                                                    $degreeOrders = null, $min_age = null, $max_age = null)
    {
        // Add indexes for performance
        $query->addSelect(['courses.*']);

        if($sportId) {
            $query->where('courses.sport_id', $sportId);
        }

        // Optimize client data retrieval with single query
        $clientAge = null;
        $clientDegreeOrder = null;
        $clientDegree = null;
        $isAdultClient = false;
        $clientLanguages = [];
        $clientAges = [];

        if ($clientId) {
            $clientIds = is_array($clientId) ? $clientId : [$clientId];

            // Single query to get all client data
            $clients = Client::whereIn('id', $clientIds)
                ->select(['id', 'birth_date', 'language1_id', 'language2_id', 'language3_id', 'language4_id', 'language5_id', 'language6_id'])
                ->get();

            foreach ($clients as $client) {
                $age = Carbon::parse($client->birth_date)->age;
                $clientAges[] = $age;

                if ($age >= 18) {
                    $isAdultClient = true;
                }

                // Collect client languages efficiently
                for ($i = 1; $i <= 6; $i++) {
                    $languageField = 'language' . $i . '_id';
                    if (!empty($client->$languageField) && !in_array($client->$languageField, $clientLanguages)) {
                        $clientLanguages[] = $client->$languageField;
                    }
                }
            }

            if (count($clientAges) === 1) {
                $clientAge = $clientAges[0];
            }
        }

        if ($degreeId) {
            $clientDegree = Degree::find($degreeId);
        }

        // Si no se especifica el tipo, prepara una consulta que incluya todos los tipos
        if ($type === null) {
            // Crea una consulta que combine todos los tipos con una condición OR
            $query->where(function($query) use ($startDate, $endDate, $clientDegree, $clientAge, $clientAges, $getLowerDegrees, $min_age, $max_age, $degreeOrders, $isAdultClient, $clientLanguages, $clientId) {
                // Lógica para tipo 1
                $query->where(function($subquery) use ($startDate, $endDate, $clientDegree, $clientAge, $clientAges, $getLowerDegrees, $min_age, $max_age, $degreeOrders, $isAdultClient, $clientLanguages, $clientId) {
                    $this->applyType1Filters($subquery, $startDate, $endDate, $clientDegree, $clientAge, $clientAges, $getLowerDegrees, $min_age, $max_age, $degreeOrders, $isAdultClient, $clientLanguages, $clientId);
                });

                // Lógica para tipos 2 y 3
                $query->orWhere(function($subquery) use ($startDate, $endDate, $clientAge, $clientAges, $min_age, $max_age) {
                    $this->applyTypes2And3Filters($subquery, $startDate, $endDate, $clientAge, $clientAges, $min_age, $max_age);
                });
            });
        } else if ($type == 1) {
            // Lógica para cursos de tipo 1
            $this->applyType1Filters($query, $startDate, $endDate, $clientDegree, $clientAge, $clientAges, $getLowerDegrees, $min_age, $max_age, $degreeOrders, $isAdultClient, $clientLanguages, $clientId);
        } else if ($type == 2 || $type == 3) {
            // Lógica para cursos de tipo 2 o 3
            $query->where('course_type', $type);
            $this->applyTypes2And3Filters($query, $startDate, $endDate, $clientAge, $clientAges, $min_age, $max_age);
        }

        return $query;
    }

    // Método auxiliar para aplicar filtros de tipo 1
    private function applyType1Filters($query, $startDate, $endDate, $clientDegree, $clientAge, $clientAges, $getLowerDegrees, $min_age, $max_age, $degreeOrders, $isAdultClient, $clientLanguages, $clientId)
    {
        $query->where('course_type', 1)
            ->whereHas('courseDates', function (Builder $subQuery) use (
                $startDate, $endDate, $clientDegree, $clientAge, $clientAges, $getLowerDegrees, $min_age, $max_age, $degreeOrders,
                $isAdultClient, $clientLanguages, $clientId
            ) {
                $subQuery->where('date', '>=', $startDate)
                    ->where('date', '<=', $endDate);

                // Aplicar lógica de subgrupos según si usa grupos por intervalo o no
                $this->applySubgroupAvailabilityFilters(
                    $subQuery,
                    $clientDegree,
                    $clientAge,
                    $clientAges,
                    $getLowerDegrees,
                    $min_age,
                    $max_age,
                    $degreeOrders,
                    $isAdultClient,
                    $clientLanguages,
                    $clientId
                );
            });
    }

    /**
     * Aplica los filtros de disponibilidad de subgrupos.
     * Usa grupos de intervalo si use_interval_groups = true, caso contrario usa grupos globales.
     */
    private function applySubgroupAvailabilityFilters(
        Builder $dateQuery,
        $clientDegree,
        $clientAge,
        $clientAges,
        $getLowerDegrees,
        $min_age,
        $max_age,
        $degreeOrders,
        $isAdultClient,
        $clientLanguages,
        $clientId
    ) {
        if ($this->use_interval_groups) {
            // Usar grupos de intervalo
            $this->applyIntervalGroupFilters(
                $dateQuery,
                $clientDegree,
                $clientAge,
                $clientAges,
                $getLowerDegrees,
                $min_age,
                $max_age,
                $degreeOrders,
                $isAdultClient,
                $clientLanguages,
                $clientId
            );
        } else {
            // Usar grupos globales (lógica original)
            $this->applyGlobalGroupFilters(
                $dateQuery,
                $clientDegree,
                $clientAge,
                $clientAges,
                $getLowerDegrees,
                $min_age,
                $max_age,
                $degreeOrders,
                $isAdultClient,
                $clientLanguages,
                $clientId
            );
        }
    }

    /**
     * Aplica filtros usando grupos de intervalo
     */
    private function applyIntervalGroupFilters(
        Builder $dateQuery,
        $clientDegree,
        $clientAge,
        $clientAges,
        $getLowerDegrees,
        $min_age,
        $max_age,
        $degreeOrders,
        $isAdultClient,
        $clientLanguages,
        $clientId
    ) {
        $courseId = $this->id;

        $dateQuery->where(function (Builder $query) use (
            $courseId,
            $clientDegree,
            $clientAge,
            $clientAges,
            $getLowerDegrees,
            $min_age,
            $max_age,
            $degreeOrders,
            $isAdultClient,
            $clientLanguages,
            $clientId
        ) {
            // Verificar que exista al menos un subgrupo de intervalo activo con capacidad
            $query->whereExists(function ($subQuery) use (
                $courseId,
                $clientDegree,
                $clientAge,
                $clientAges,
                $getLowerDegrees,
                $min_age,
                $max_age,
                $degreeOrders,
                $isAdultClient,
                $clientLanguages,
                $clientId
            ) {
                $subQuery->select(\DB::raw(1))
                    ->from('course_interval_subgroups as cis')
                    ->join('course_interval_groups as cig', 'cis.course_interval_group_id', '=', 'cig.id')
                    ->join('course_subgroups as cs', 'cis.course_subgroup_id', '=', 'cs.id')
                    ->join('course_groups as cg', 'cs.course_group_id', '=', 'cg.id')
                    ->whereColumn('cig.course_interval_id', 'course_dates.course_interval_id')
                    ->where('cig.course_id', $courseId)
                    ->where('cig.active', true)
                    ->where('cis.active', true)
                    ->where('cs.course_id', $courseId)

                    // Verificar capacidad disponible usando max_participants del intervalo si existe
                    ->whereRaw('COALESCE(cis.max_participants, cig.max_participants, cs.max_participants) > (
                        SELECT COUNT(*)
                        FROM booking_users
                        JOIN bookings ON booking_users.booking_id = bookings.id
                        WHERE booking_users.course_subgroup_id = cs.id
                            AND booking_users.status = 1
                            AND booking_users.deleted_at IS NULL
                            AND bookings.deleted_at IS NULL
                    )');

                // Verificar solapamiento de horarios si se proporcionó clientId
                if (!is_null($clientId)) {
                    $clientIds = is_array($clientId) ? $clientId : [$clientId];

                    foreach ($clientIds as $cId) {
                        $subQuery->whereNotExists(function ($overlapQuery) use ($cId) {
                            $overlapQuery->select(\DB::raw(1))
                                ->from('booking_users as bu')
                                ->join('course_dates as cd_overlap', 'bu.course_date_id', '=', 'cd_overlap.id')
                                ->where('bu.client_id', $cId)
                                ->whereColumn('cd_overlap.date', 'course_dates.date')
                                ->where(function ($timeQuery) {
                                    $timeQuery->where(function ($subTimeQuery) {
                                        // Solapamiento
                                        $subTimeQuery->whereColumn('cd_overlap.hour_start', '<', 'course_dates.hour_end')
                                            ->whereColumn('cd_overlap.hour_end', '>', 'course_dates.hour_start');
                                    })->orWhere(function ($subTimeQuery) {
                                        // Horarios idénticos
                                        $subTimeQuery->whereColumn('cd_overlap.hour_start', '=', 'course_dates.hour_start')
                                            ->whereColumn('cd_overlap.hour_end', '=', 'course_dates.hour_end');
                                    });
                                });
                        });
                    }
                }

                // Filtros de grupo (age, degree)
                $this->applyGroupConstraints($subQuery, 'cg', $clientDegree, $clientAge, $clientAges, $getLowerDegrees, $min_age, $max_age, $degreeOrders);

                // Filtros de monitor
                $this->applyMonitorConstraints($subQuery, 'cs', $isAdultClient, $clientLanguages);
            });
        });
    }

    /**
     * Aplica filtros usando grupos globales (lógica original)
     */
    private function applyGlobalGroupFilters(
        Builder $dateQuery,
        $clientDegree,
        $clientAge,
        $clientAges,
        $getLowerDegrees,
        $min_age,
        $max_age,
        $degreeOrders,
        $isAdultClient,
        $clientLanguages,
        $clientId
    ) {
        $dateQuery->whereHas('courseSubgroups',
            function (Builder $subQuery) use (
                $clientDegree, $clientAge, $clientAges, $getLowerDegrees, $min_age, $max_age, $degreeOrders,
                $isAdultClient, $clientLanguages, $clientId
            ) {
                // Verificamos que haya al menos un subgrupo con capacidad disponible
                $subQuery->whereRaw('max_participants > (
                SELECT COUNT(*)
                FROM booking_users
                JOIN bookings ON booking_users.booking_id = bookings.id
                WHERE booking_users.course_subgroup_id = course_subgroups.id
                    AND booking_users.status = 1
                    AND booking_users.deleted_at IS NULL
                    AND bookings.deleted_at IS NULL
                     )');

                // Si se proporcionó clientId
                if (!is_null($clientId)) {
                    // Convertir $clientId a array si es un único valor
                    $clientIds = is_array($clientId) ? $clientId : [$clientId];

                    foreach ($clientIds as $cId) {
                        $subQuery->whereDoesntHave('courseDate', function (Builder $dateQuery) use ($cId) {
                            $dateQuery->whereHas('bookingUsers', function (Builder $bookingUserQuery) use ($cId) {
                                $bookingUserQuery->where('client_id', $cId)
                                    ->where(function ($query) {
                                        $query->where(function ($subQuery) {
                                            // Excluir si hay solapamiento
                                            $subQuery->whereColumn('hour_start', '<', 'course_dates.hour_end')
                                                ->whereColumn('hour_end', '>', 'course_dates.hour_start');
                                        })->orWhere(function ($subQuery) {
                                            // Excluir si son horarios idénticos
                                            $subQuery->whereColumn('hour_start', '=', 'course_dates.hour_start')
                                                ->whereColumn('hour_end', '=', 'course_dates.hour_end');
                                        });
                                    });
                            });
                        });
                    }
                }

                $subQuery->whereHas('courseGroup',
                    function (Builder $groupQuery) use (
                        $clientDegree, $clientAge, $clientAges, $getLowerDegrees, $min_age, $max_age, $degreeOrders,
                        $isAdultClient, $clientLanguages
                    ) {

                        // Comprobación de degree_order y rango de edad
                        if ($clientDegree !== null && $getLowerDegrees) {
                            $groupQuery->whereHas('degree',
                                function (Builder $degreeQuery) use ($clientDegree) {
                                    $degreeQuery->where('degree_order', '<=',
                                        $clientDegree->degree_order);
                                });
                        } else if ($clientDegree !== null && !$getLowerDegrees) {
                            //TODO: Fix degree
                            /*$groupQuery->whereHas('degree',
                                 function (Builder $degreeQuery) use ($clientDegree) {
                                     $degreeQuery->orWhere('id', $clientDegree->id);
                                 });*/
                        }

                        // Filtrar por edad
                        if (count($clientAges) > 0) {
                            // Si tenemos múltiples edades, debemos encontrar cursos que sean adecuados para todos los clientes
                            $groupQuery->where(function($query) use ($clientAges) {
                                foreach ($clientAges as $age) {
                                    $query->where('age_min', '<=', $age)
                                        ->where('age_max', '>=', $age);
                                }
                            });
                        } else if ($clientAge !== null) {
                            // Filtrado por la edad del cliente si está disponible (para compatibilidad)
                            $groupQuery->where('age_min', '<=', $clientAge)
                                ->where('age_max', '>=', $clientAge);
                        } else {
                            // Filtrado por min_age y max_age si clientId no está disponible
                            if ($max_age !== null) {
                                $groupQuery->where(function($q) use ($max_age) {
                                    $q->where('age_min', '<=', $max_age)
                                      ->orWhereNull('age_min');
                                });
                            }
                            if ($min_age !== null) {
                                $groupQuery->where(function($q) use ($min_age) {
                                    $q->where('age_max', '>=', $min_age)
                                      ->orWhereNull('age_max');
                                });
                            }
                        }

                        // Comprobación de degree_order y rango de edad
                        if (!empty($degreeOrders)) {
                            $groupQuery->whereHas('degree',
                                function (Builder $degreeQuery) use ($degreeOrders, $getLowerDegrees
                                ) {
                                    if ($getLowerDegrees) {
                                        // Si se pide obtener grados inferiores, compara con el menor grado
                                        $degreeQuery->where('degree_order', '<=',
                                            min($degreeOrders));
                                    } else {
                                        // En caso contrario, filtra por los grados específicos
                                        $degreeQuery->whereIn('degree_order', $degreeOrders);
                                    }
                                });
                        }

                    });
                $subQuery->where(function ($query) use ($isAdultClient, $clientLanguages) {
                    $query->doesntHave('monitor') // Subgrupo sin monitor asignado
                    ->orWhereHas('monitor', function (Builder $monitorQuery) use ($isAdultClient, $clientLanguages) {
                        // Si el subgrupo tiene monitor, comprobar si permite adultos y los idiomas
                        if ($isAdultClient) {
                            $monitorQuery->whereHas('monitorSportsDegrees', function ($query) {
                                $query->where('allow_adults', true);
                            });
                        }

                        // Verificación de idiomas
                        if (!empty($clientLanguages)) {
                            $monitorQuery->where(function ($query) use ($clientLanguages) {
                                $query->whereIn('language1_id', $clientLanguages)
                                    ->orWhereIn('language2_id', $clientLanguages)
                                    ->orWhereIn('language3_id', $clientLanguages)
                                    ->orWhereIn('language4_id', $clientLanguages)
                                    ->orWhereIn('language5_id', $clientLanguages)
                                    ->orWhereIn('language6_id', $clientLanguages);
                            });
                        }
                    });
                });
            });
    }

    /**
     * Aplica restricciones de grupo (age, degree) a una consulta
     */
    private function applyGroupConstraints($query, $groupTableAlias, $clientDegree, $clientAge, $clientAges, $getLowerDegrees, $min_age, $max_age, $degreeOrders)
    {
        // Comprobación de degree_order
        if ($clientDegree !== null && $getLowerDegrees) {
            $query->whereExists(function ($degreeQuery) use ($groupTableAlias, $clientDegree) {
                $degreeQuery->select(\DB::raw(1))
                    ->from('degrees as d')
                    ->whereColumn('d.id', "$groupTableAlias.degree_id")
                    ->where('d.degree_order', '<=', $clientDegree->degree_order);
            });
        }

        // Filtrar por edad
        if (count($clientAges) > 0) {
            foreach ($clientAges as $age) {
                $query->where("$groupTableAlias.age_min", '<=', $age)
                    ->where("$groupTableAlias.age_max", '>=', $age);
            }
        } else if ($clientAge !== null) {
            $query->where("$groupTableAlias.age_min", '<=', $clientAge)
                ->where("$groupTableAlias.age_max", '>=', $clientAge);
        } else {
            if ($max_age !== null) {
                $query->where(function($q) use ($max_age, $groupTableAlias) {
                    $q->where("$groupTableAlias.age_min", '<=', $max_age)
                      ->orWhereNull("$groupTableAlias.age_min");
                });
            }
            if ($min_age !== null) {
                $query->where(function($q) use ($min_age, $groupTableAlias) {
                    $q->where("$groupTableAlias.age_max", '>=', $min_age)
                      ->orWhereNull("$groupTableAlias.age_max");
                });
            }
        }

        // Comprobación de degree orders
        if (!empty($degreeOrders)) {
            $query->whereExists(function ($degreeQuery) use ($groupTableAlias, $degreeOrders, $getLowerDegrees) {
                $degreeQuery->select(\DB::raw(1))
                    ->from('degrees as d')
                    ->whereColumn('d.id', "$groupTableAlias.degree_id");

                if ($getLowerDegrees) {
                    $degreeQuery->where('d.degree_order', '<=', min($degreeOrders));
                } else {
                    $degreeQuery->whereIn('d.degree_order', $degreeOrders);
                }
            });
        }
    }

    /**
     * Aplica restricciones de monitor a una consulta
     */
    private function applyMonitorConstraints($query, $subgroupTableAlias, $isAdultClient, $clientLanguages)
    {
        $query->where(function ($monitorQuery) use ($subgroupTableAlias, $isAdultClient, $clientLanguages) {
            // Caso 1: Subgrupo sin monitor
            $monitorQuery->whereNull("$subgroupTableAlias.monitor_id")

                // Caso 2: Subgrupo con monitor que cumple requisitos
                ->orWhereExists(function ($monitorExistsQuery) use ($subgroupTableAlias, $isAdultClient, $clientLanguages) {
                    $monitorExistsQuery->select(\DB::raw(1))
                        ->from('monitors as m')
                        ->whereColumn('m.id', "$subgroupTableAlias.monitor_id");

                    // Verificar adultos si es necesario
                    if ($isAdultClient) {
                        $monitorExistsQuery->whereExists(function ($adultQuery) {
                            $adultQuery->select(\DB::raw(1))
                                ->from('monitor_sports_degrees as msd')
                                ->whereColumn('msd.monitor_id', 'm.id')
                                ->where('msd.allow_adults', true);
                        });
                    }

                    // Verificar idiomas
                    if (!empty($clientLanguages)) {
                        $monitorExistsQuery->where(function ($langQuery) use ($clientLanguages) {
                            $langQuery->whereIn('m.language1_id', $clientLanguages)
                                ->orWhereIn('m.language2_id', $clientLanguages)
                                ->orWhereIn('m.language3_id', $clientLanguages)
                                ->orWhereIn('m.language4_id', $clientLanguages)
                                ->orWhereIn('m.language5_id', $clientLanguages)
                                ->orWhereIn('m.language6_id', $clientLanguages);
                        });
                    }
                });
        });
    }

    // Método auxiliar para aplicar filtros de tipos 2 y 3
    private function applyTypes2And3Filters($query, $startDate, $endDate, $clientAge, $clientAges, $min_age, $max_age)
    {
        $query->whereIn('course_type', [2, 3])
            ->whereHas('courseDates', function (Builder $subQuery) use ($startDate, $endDate) {
                $subQuery->where('date', '>=', $startDate)
                    ->where('date', '<=', $endDate);
                //TODO: Review availability
                // ->whereRaw('courses.max_participants > (SELECT COUNT(*) FROM booking_users
                // WHERE booking_users.course_date_id = course_dates.id AND booking_users.status = 1
                // AND booking_users.deleted_at IS NULL)');
            });

        // Filtrar por edad
        if (count($clientAges) > 0) {
            // Si tenemos múltiples edades, debemos encontrar cursos que sean adecuados para todos los clientes
            $query->where(function($query) use ($clientAges) {
                foreach ($clientAges as $age) {
                    $query->where('age_min', '<=', $age)
                        ->where('age_max', '>=', $age);
                }
            });
        } else if ($clientAge !== null) {
            // Filtrado por la edad del cliente si está disponible (para compatibilidad)
            $query->where('age_min', '<=', $clientAge)
                ->where('age_max', '>=', $clientAge);
        } else {
            // Filtrado por min_age y max_age si clientId no está disponible
            if ($max_age !== null) {
                $query->where(function($q) use ($max_age) {
                    $q->where('age_min', '<=', $max_age)
                      ->orWhereNull('age_min');
                });
            }
            if ($min_age !== null) {
                $query->where(function($q) use ($min_age) {
                    $q->where('age_max', '>=', $min_age)
                      ->orWhereNull('age_max');
                });
            }
        }
    }
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    /**
     * Verifica si el curso está archivado
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Archiva el curso (para cursos con reservas que no se pueden eliminar)
     */
    public function archive(): bool
    {
        $this->archived_at = now();
        return $this->save();
    }

    /**
     * Restaura un curso archivado
     */
    public function unarchive(): bool
    {
        $this->archived_at = null;
        return $this->save();
    }

    /**
     * Verifica si el curso tiene reservas activas (no anuladas)
     */
    public function hasActiveBookings(): bool
    {
        return $this->bookingUsers()
            ->where('status', '!=', 'cancelled')
            ->where('status', '!=', 'anulado')
            ->exists();
    }

    /**
     * Verifica si el curso solo tiene reservas anuladas
     */
    public function hasOnlyCancelledBookings(): bool
    {
        $totalBookings = $this->bookingUsers()->count();
        if ($totalBookings === 0) {
            return false;
        }

        $cancelledBookings = $this->bookingUsers()
            ->whereIn('status', ['cancelled', 'anulado'])
            ->count();

        return $cancelledBookings === $totalBookings;
    }

    /**
     * Scope para excluir cursos archivados
     */
    public function scopeNotArchived($query)
    {
        return $query->whereNull('archived_at');
    }

    /**
     * Scope para solo cursos archivados
     */
    public function scopeOnlyArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    /**
     * Scope para incluir cursos archivados
     */
    public function scopeWithArchived($query)
    {
        return $query; // No hace nada, pero es explícito
    }
}
