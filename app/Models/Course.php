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
    ];

    protected $casts = [
        'is_flexible' => 'boolean',
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
        'highlighted' => 'boolean'
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
        return $this->hasMany(\App\Models\CourseDate::class, 'course_id');
    }

    public function courseDatesActive(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\CourseDate::class, 'course_id')
            ->where('active', 1);
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

    public function scopeWithAvailableDates(Builder $query, $type, $startDate, $endDate, $sportId = 1,
                                                    $clientId = null, $degreeId = null, $getLowerDegrees = false,
                                                    $degreeOrders = null, $min_age = null, $max_age = null)
    {

        $clientAge = null;
        $clientDegreeOrder = null;
        $clientDegree = null;
        $isAdultClient = false;
        $clientLanguages = [];

        $query->where('sport_id', $sportId);

        // Si se proporcionó clientId, obtener los detalles del cliente

        if ($clientId) {
            $client = Client::find($clientId);
            if ($client) {
                $clientAge = Carbon::parse($client->birth_date)->age;
                $isAdultClient = $clientAge >= 18;

                // Recolectar idiomas del cliente
                for ($i = 1; $i <= 6; $i++) {
                    $languageField = 'language' . $i . '_id';
                    if (!empty($client->$languageField)) {
                        $clientLanguages[] = $client->$languageField;
                    }
                }
            }
        }

        if ($degreeId) {
            $clientDegree = Degree::find($degreeId);
        }

        if ($type == 1 || $type == null) {
            // Lógica para cursos de tipo 1
            $query->whereHas('courseDates', function (Builder $subQuery) use (
                $startDate, $endDate, $clientDegree, $clientAge, $getLowerDegrees, $min_age, $max_age, $degreeOrders,
                $isAdultClient, $clientLanguages, $clientId
            ) {
                $subQuery->where('date', '>=', $startDate)
                    ->where('date', '<=', $endDate)
                    ->whereHas('courseSubgroups',
                        function (Builder $subQuery) use (
                            $clientDegree, $clientAge, $getLowerDegrees, $min_age, $max_age, $degreeOrders,
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
                            if (!is_null($clientId)) {
                                $subQuery->whereDoesntHave('courseDate', function (Builder $dateQuery) use ($clientId) {
                                    $dateQuery->whereHas('bookingUsers', function (Builder $bookingUserQuery) use ($clientId) {
                                        $bookingUserQuery->where('client_id', $clientId)
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
                            $subQuery->whereHas('courseGroup',
                                function (Builder $groupQuery) use (
                                    $clientDegree, $clientAge, $getLowerDegrees, $min_age, $max_age, $degreeOrders,
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
                                    if ($clientAge !== null) {
                                        // Filtrado por la edad del cliente si está disponible
                                        $groupQuery->where('age_min', '<=', $clientAge)
                                            ->where('age_max', '>=', $clientAge);
                                    } else {
                                        // Filtrado por min_age y max_age si clientId no está disponible
                                        if ($max_age !== null) {
                                            $groupQuery->where('age_min', '<=', $max_age);
                                        }
                                        if ($min_age !== null) {
                                            $groupQuery->where('age_max', '>=', $min_age);
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
            });
        } if (($type == 2 || $type == 3) || $type == null) {
            // Lógica para cursos de tipo 2
            $query->where('course_type', $type)
                ->where('sport_id', $sportId) // Asegúrate de que estás filtrando por el sport_id correcto
                ->whereHas('courseDates', function (Builder $subQuery) use ($startDate, $endDate, $clientAge) {
                    $subQuery->where('date', '>=', $startDate)
                        ->where('date', '<=', $endDate);
                    //TODO: Review availability
//                        ->whereRaw('courses.max_participants > (SELECT COUNT(*) FROM booking_users
//                        WHERE booking_users.course_date_id = course_dates.id AND booking_users.status = 1
//                        AND booking_users.deleted_at IS NULL)');

                });

            if ($clientAge) {
                $query->where('age_min', '<=', $clientAge)
                    ->where('age_max', '>=', $clientAge);
            }

            if ($clientAge) {
                // Filtrado por la edad del cliente si está disponible
                $query->where('age_min', '<=', $clientAge)
                    ->where('age_max', '>=', $clientAge);
            } else {
                // Filtrado por min_age y max_age si clientId no está disponible
                if ($max_age !== null) {
                    $query->where('age_min', '<=', $max_age);
                }
                if ($min_age !== null) {
                    $query->where('age_max', '>=', $min_age);
                }
            }

        }
        //dd($query->toSql(), $query->getBindings());
        return $query;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }
}
