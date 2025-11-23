<?php

namespace App\Models;

use App\Services\CourseAvailabilityService;
use App\Services\CourseMonitorService;
use App\Models\CourseDate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Traits\OptimizedQueries;

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
    use LogsActivity, SoftDeletes, HasFactory, OptimizedQueries;

    public $table = 'course_subgroups';
    protected $appends = []; // Temporarily disabled ['is_full']
    public $fillable = [
        'course_id',
        'course_date_id',
        'degree_id',
        'course_group_id',
        'monitor_id',
        'old_id',
        'max_participants',
        'subgroup_dates_id'
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

    /**
     * MEJORA CRÍTICA: Verificación thread-safe de disponibilidad de plazas
     * Método específico para reservas concurrentes con conteo directo desde DB
     *
     * @return bool
     */
    public function hasAvailableSlots(): bool
    {
        // Verificar si hay límite de participantes
        if (!$this->max_participants) {
            return true; // Sin límite = siempre disponible
        }

        // Contar directamente desde DB para evitar problemas de cache
        $currentParticipants = BookingUser::where('course_subgroup_id', $this->id)
            ->where('status', 1)
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 2); // Booking no cancelada
            })
            ->count();

        return $currentParticipants < $this->max_participants;
    }

    /**
     * MEJORA CRÍTICA: Obtener número exacto de plazas disponibles
     * Versión optimizada con cache y query eficiente
     *
     * @return int
     */
    public function getAvailableSlotsCount(): int
    {
        if (!$this->max_participants) {
            return 999; // Sin límite
        }

        // Cache la consulta por 30 segundos
        return $this->cacheQuery("available_slots_{$this->id}", function() {
            $currentParticipants = BookingUser::where('course_subgroup_id', $this->id)
                ->where('status', 1)
                ->whereHas('booking', function ($query) {
                    $query->where('status', '!=', 2);
                })
                ->count();

            return max(0, $this->max_participants - $currentParticipants);
        }, 30);
    }

    // ==================================================================================
    // NUEVOS MÉTODOS CON SOPORTE DE INTERVALOS
    // ==================================================================================

    /**
     * NUEVO: Obtener max_participants considerando intervalos
     *
     * @param string $date Fecha en formato Y-m-d
     * @return int|null Max participants para esa fecha, null = sin límite
     */
    public function getMaxParticipantsForDate(string $date): ?int
    {
        $service = app(CourseAvailabilityService::class);
        return $service->getMaxParticipants($this, $date);
    }

    /**
     * NUEVO: Verificar disponibilidad para una fecha específica
     * Reemplaza hasAvailableSlots() pero con soporte de intervalos
     *
     * @param string $date Fecha en formato Y-m-d
     * @param int $needed Número de plazas necesarias (default: 1)
     * @return bool
     */
    public function hasAvailabilityForDate(string $date, int $needed = 1): bool
    {
        $service = app(CourseAvailabilityService::class);
        return $service->hasAvailability($this, $date, $needed);
    }

    /**
     * NUEVO: Obtener plazas disponibles para una fecha específica
     * Reemplaza getAvailableSlotsCount() pero con soporte de intervalos
     *
     * @param string $date Fecha en formato Y-m-d
     * @return int Número de plazas disponibles
     */
    public function getAvailableSlotsForDate(string $date): int
    {
        $service = app(CourseAvailabilityService::class);
        return $service->getAvailableSlots($this, $date);
    }

    /**
     * NUEVO: Obtener disponibilidad para múltiples fechas
     *
     * @param array $dates Array de fechas en formato Y-m-d
     * @return array ['date' => available_slots]
     */
    public function getAvailabilityForDates(array $dates): array
    {
        $service = app(CourseAvailabilityService::class);
        return $service->getAvailabilityForDates($this, $dates);
    }

    /**
     * NUEVO: Invalidar cache de disponibilidad
     * Útil cuando se crea/cancela una reserva
     *
     * @param string|null $date Fecha específica o null para todas
     */
    public function invalidateAvailabilityCache(?string $date = null): void
    {
        $service = app(CourseAvailabilityService::class);
        $service->invalidateCache($this, $date);
    }

    // ==================================================================================
    // MÉTODOS CON SOPORTE DE MONITORES POR INTERVALO
    // ==================================================================================

    /**
     * NUEVO: Obtener monitor para una fecha específica
     * Considera asignaciones por intervalo
     *
     * @param string $date Fecha en formato Y-m-d
     * @return Monitor|null
     */
    public function getMonitorForDate(string $date): ?Monitor
    {
        $service = app(CourseMonitorService::class);
        return $service->getMonitorForDate($this, $date);
    }

    /**
     * NUEVO: Obtener ID del monitor para una fecha específica
     *
     * @param string $date Fecha en formato Y-m-d
     * @return int|null
     */
    public function getMonitorIdForDate(string $date): ?int
    {
        $service = app(CourseMonitorService::class);
        return $service->getMonitorIdForDate($this, $date);
    }

    /**
     * NUEVO: Obtener detalles completos del monitor para una fecha
     *
     * @param string $date Fecha en formato Y-m-d
     * @return array ['monitor', 'source', 'interval_id', 'notes']
     */
    public function getMonitorDetailsForDate(string $date): array
    {
        $service = app(CourseMonitorService::class);
        return $service->getMonitorDetailsForDate($this, $date);
    }

    /**
     * NUEVO: Invalidar cache de monitores
     *
     * @param string|null $date Fecha específica o null para todas
     */
    public function invalidateMonitorCache(?string $date = null): void
    {
        $service = app(CourseMonitorService::class);
        $service->invalidateCache($this, $date);
    }

    // ==================================================================================
    // MÉTODOS PARA SOPORTE DE SUBGROUP_DATES_ID
    // ==================================================================================

    /**
     * NUEVO: Generar o obtener el subgroup_dates_id para este subgrupo
     * Todos los subgrupos homónimos (mismo course_id, degree_id, course_group_id) comparten el mismo ID
     *
     * @return string Identificador en formato SG-XXXXXX
     */
    public function generateOrGetSubgroupDatesId(): string
    {
        // Si ya tiene ID, devolverlo
        if ($this->subgroup_dates_id) {
            return $this->subgroup_dates_id;
        }

        // Buscar si otro subgrupo con las mismas dimensiones ya tiene ID asignado
        $existing = static::where('course_id', $this->course_id)
            ->where('degree_id', $this->degree_id)
            ->where('course_group_id', $this->course_group_id)
            ->whereNotNull('subgroup_dates_id')
            ->first();

        if ($existing) {
            return $existing->subgroup_dates_id;
        }

        // Generar nuevo ID secuencial
        $maxNum = DB::table('course_subgroups')
            ->whereNotNull('subgroup_dates_id')
            ->get()
            ->map(function($row) {
                $matches = [];
                preg_match('/SG-(\d+)/', $row->subgroup_dates_id, $matches);
                return isset($matches[1]) ? (int)$matches[1] : 0;
            })
            ->max() ?? 0;

        return 'SG-' . str_pad($maxNum + 1, 6, '0', STR_PAD_LEFT);
    }

    /**
     * NUEVO: Obtener todos los subgrupos homónimos (mismo subgroup_dates_id)
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getHomonymousSubgroups()
    {
        if (!$this->subgroup_dates_id) {
            return collect([]);
        }

        return static::where('subgroup_dates_id', $this->subgroup_dates_id)
            ->get();
    }

    /**
     * NUEVO: Relación con asignaciones de monitores por intervalo
     */
    public function intervalMonitorAssignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CourseIntervalMonitor::class, 'course_subgroup_id');
    }

    /**
     * NUEVO: Relación con los registros pivot de course_subgroup_dates
     * Permite acceder a todas las fechas asociadas a este subgrupo
     */
    public function courseSubgroupDates(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CourseSubgroupDate::class, 'course_subgroup_id')->orderBy('order');
    }

    /**
     * NUEVO: Relación HasManyThrough para obtener TODAS las fechas del subgrupo
     * A través de la tabla intermedia course_subgroup_dates
     *
     * Uso: $subgroup->allCourseDates()->get()
     */
    public function allCourseDates(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            CourseDate::class,
            CourseSubgroupDate::class,
            'course_subgroup_id',
            'id',
            'id',
            'course_date_id'
        );
    }

    /**
     * NUEVO: Obtener todas las IDs de fechas del subgrupo (homónimas)
     */
    public function getHomonymousDateIds(): \Illuminate\Support\Collection
    {
        return $this->courseSubgroupDates()->pluck('course_date_id');
    }

    /**
     * NUEVO: Obtener todas las fechas con sus detalles, ordenadas por fecha
     */
    public function getHomonymousDates()
    {
        return $this->allCourseDates()
            ->orderBy('date', 'asc')
            ->get();
    }

    /**
     * MEJORA CRÍTICA: Scope optimizado para filtrar subgrupos disponibles
     */
    public function scopeAvailableWithOptimizedQuery(Builder $query, int $neededSlots = 1): Builder
    {
        return $this->optimizedEagerLoad($query, [
                'courseDate' => ['id', 'date', 'course_id'],
                'degree' => ['id', 'name', 'degree_order'],
                'courseGroup' => ['id', 'age_min', 'age_max']
            ])
            ->availableWithCapacity()
            ->havingRaw('available_slots >= ?', [$neededSlots])
            ->withOptimizedIndexes()
            ->orderBy('available_slots', 'desc');
    }

    /**
     * MEJORA CRÍTICA: Obtener subgrupos con información de capacidad pre-calculada
     */
    public static function getAvailableSubgroupsWithCapacity(int $courseDateId, int $degreeId, int $neededSlots = 1): \Illuminate\Support\Collection
    {
        $courseDate = CourseDate::find($courseDateId);
        $referenceDate = $courseDate ? Carbon::parse($courseDate->date)->format('Y-m-d') : null;
        $cacheKeyDate = $referenceDate ?? 'no-date';
        $cacheKey = "available_subgroups_{$courseDateId}_{$degreeId}_{$neededSlots}_{$cacheKeyDate}";

        return Cache::remember($cacheKey, 60, function() use ($courseDateId, $degreeId, $neededSlots, $referenceDate) {
            $service = app(CourseAvailabilityService::class);

            return static::where('course_date_id', $courseDateId)
                ->where('degree_id', $degreeId)
                ->with('courseDate')
                ->get()
                ->map(function($subgroup) use ($service, $referenceDate) {
                    $date = $referenceDate ?? optional($subgroup->courseDate)->date;
                    $date = $date ? Carbon::parse($date)->format('Y-m-d') : null;

                    if ($date) {
                        $maxParticipants = $service->getMaxParticipants($subgroup, $date);
                        $availableSlots = $service->getAvailableSlots($subgroup, $date);
                        $currentParticipants = $maxParticipants === null ? null : max(0, $maxParticipants - $availableSlots);
                    } else {
                        $maxParticipants = $subgroup->max_participants;
                        $availableSlots = $subgroup->getAvailableSlotsCount();
                        $currentParticipants = $maxParticipants === null ? null : max(0, $maxParticipants - $availableSlots);
                    }

                    $capacityPercentage = ($maxParticipants && $maxParticipants > 0 && $currentParticipants !== null)
                        ? ($currentParticipants / $maxParticipants) * 100
                        : 0;

                    return [
                        'id' => $subgroup->id,
                        'max_participants' => $maxParticipants ?? 999,
                        'current_participants' => $currentParticipants ?? 0,
                        'available_slots' => $availableSlots,
                        'capacity_percentage' => round($capacityPercentage, 2),
                        'is_unlimited' => $maxParticipants === null,
                        'course_group_id' => $subgroup->course_group_id
                    ];
                })
                ->filter(function ($data) use ($neededSlots) {
                    return $data['available_slots'] >= $neededSlots;
                })
                ->values();
        });
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
