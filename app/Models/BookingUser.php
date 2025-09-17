<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @OA\Schema(
 *      schema="BookingUser",
 *      required={"school_id","booking_id","client_id","price","currency","course_date_id","attended"},
 *           @OA\Property(
 *           property="school_id",
 *           description="School ID",
 *           type="integer",
 *           nullable=false
 *       ),
 *       @OA\Property(
 *           property="booking_id",
 *           description="Booking ID",
 *           type="integer",
 *           nullable=false
 *       ),
 *       @OA\Property(
 *           property="client_id",
 *           description="Client ID",
 *           type="integer",
 *           nullable=false
 *       ),
 *       @OA\Property(
 *           property="course_subgroup_id",
 *           description="Course Subgroup ID",
 *           type="integer",
 *           nullable=true
 *       ),
 *       @OA\Property(
 *           property="course_id",
 *           description="Course ID",
 *           type="integer",
 *           nullable=true
 *       ),
 *       @OA\Property(
 *           property="course_date_id",
 *           description="Course Date ID",
 *           type="integer",
 *           nullable=false
 *       ),
 *       @OA\Property(
 *           property="degree_id",
 *           description="Degree ID",
 *           type="integer",
 *           nullable=true
 *       ),
 *       @OA\Property(
 *           property="course_group_id",
 *           description="Course Group ID",
 *           type="integer",
 *           nullable=true
 *       ),
 *       @OA\Property(
 *           property="monitor_id",
 *           description="Monitor ID",
 *           type="integer",
 *           nullable=true
 *       ),
 *       @OA\Property(
 *           property="group_id",
 *           description="Grouped bookings ID",
 *           type="integer",
 *           nullable=true
 *       ),
 *       @OA\Property(
 *           property="hour_start",
 *           description="Start Hour",
 *           type="string",
 *           nullable=true,
 *           format="time"
 *       ),
 *       @OA\Property(
 *           property="hour_end",
 *           description="End Hour",
 *           type="string",
 *           nullable=true,
 *           format="time"
 *       ),
 *      @OA\Property(
 *          property="price",
 *          description="",
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
 *          property="date",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *          format="date"
 *      ),
 *      @OA\Property(
 *          property="attended",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="boolean",
 *      ),
 *      @OA\Property(
 *          property="group_changed",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="boolean",
 *      ),
 *      @OA\Property(
 *          property="color",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *           property="notes",
 *           description="",
 *           readOnly=false,
 *           nullable=false,
 *           type="string",
 *       ),
 *      @OA\Property(
 *            property="notes_school",
 *            description="",
 *            readOnly=false,
 *            nullable=true,
 *            type="string",
 *        ),
 *      @OA\Property(
 *            property="status",
 *            description="Status of the booking user",
 *            type="integer",
 *            example=1
 *        ),
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
class BookingUser extends Model
{
    use LogsActivity, SoftDeletes, HasFactory;

    public $table = 'booking_users';

    public $fillable = [
        'school_id',
        'booking_id',
        'client_id',
        'accepted',
        'group_changed',
        'price',
        'currency',
        'course_subgroup_id',
        'course_id',
        'course_date_id',
        'degree_id',
        'course_group_id',
        'monitor_id',
        'group_id',
        'date',
        'hour_start',
        'hour_end',
        'attended',
        'status',
        'notes',
        'notes_school',
        'color'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'currency' => 'string',
        'date' => 'date',
        'attended' => 'boolean',
        'group_changed' => 'boolean',
        'accepted' => 'boolean',
        'status' => 'integer',
        'notes_school' => 'string',
        'notes' => 'string',
        'color' => 'string'
    ];

    public static array $rules = [
        'school_id' => 'nullable',
        'booking_id' => 'nullable',
        'client_id' => 'nullable',
        'price' => 'nullable|numeric',
        'currency' => 'nullable|string|max:3',
        'course_subgroup_id' => 'nullable',
        'course_id' => 'nullable',
        'course_date_id' => 'nullable',
        'degree_id' => 'nullable',
        'course_group_id' => 'nullable',
        'monitor_id' => 'nullable',
        'group_id' => 'nullable',
        'date' => 'nullable',
        'hour_start' => 'nullable',
        'hour_end' => 'nullable',
        'attended' => 'nullable',
        'accepted' => 'nullable',
        'status' => 'nullable',
        'color' => 'nullable|string|max:45',
        'notes' => 'nullable|string|max:500',
        'notes_school' => 'nullable|string|max:500',
        'created_at' => 'nullable',
        'updated_at' => 'nullable',
        'deleted_at' => 'nullable'
    ];

    protected $appends = []; // Temporarily disabled ['duration', 'formattedDuration']

    /**
     * Calculate duration between start and end time.
     *
     * @return string|null
     */
    public function getDurationAttribute(): ?string
    {
        if ($this->hour_start && $this->hour_end) {
            $startTime = \Carbon\Carbon::parse($this->hour_start);
            $endTime = \Carbon\Carbon::parse($this->hour_end);
            return $startTime->diff($endTime)->format('%H:%I:%S');
        }
        return null;
    }

    public function getHourStartAttribute($value)
    {
        return \Carbon\Carbon::parse($value)->format('H:i');
    }

    public function getHourEndAttribute($value)
    {
        return \Carbon\Carbon::parse($value)->format('H:i');
    }

    public function getFormattedDurationAttribute(): ?string
    {
        if ($this->hour_start && $this->hour_end) {
            $startTime = \Carbon\Carbon::parse($this->hour_start);
            $endTime = \Carbon\Carbon::parse($this->hour_end);

            // Obtener la diferencia en horas y minutos
            $diffInMinutes = $startTime->diffInMinutes($endTime);
            $hours = intdiv($diffInMinutes, 60); // Horas completas
            $minutes = $diffInMinutes % 60; // Minutos restantes

            // Formatear el resultado
            $duration = '';
            if ($hours > 0) {
                $duration .= $hours . 'h';
            }
            if ($minutes > 0) {
                $duration .= ' ' . $minutes . 'm';
            }

            return trim($duration); // Eliminar espacios innecesarios
        } else {

            $startTime = \Carbon\Carbon::parse($this->courseDate()->hour_start);
            $endTime = \Carbon\Carbon::parse($this->courseDate()->hour_end);

            // Obtener la diferencia en horas y minutos
            $diffInMinutes = $startTime->diffInMinutes($endTime);
            $hours = intdiv($diffInMinutes, 60); // Horas completas
            $minutes = $diffInMinutes % 60; // Minutos restantes

            // Formatear el resultado
            $duration = '';
            if ($hours > 0) {
                $duration .= $hours . 'h';
            }
            if ($minutes > 0) {
                $duration .= ' ' . $minutes . 'm';
            }

            return trim($duration); // Eliminar espacios innecesarios
        }

        return null;
    }

    public function getSportAttribute()
    {
        // Obtener el curso asociado a este bookingUser
        $course = $this->course;

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
                        return 'multiple';
                }
            }
        }

        // Si no se puede determinar el deporte, devolver 'multiple'
        return 'multiple';
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Client::class, 'client_id');
    }

    public function school(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\School::class, 'school_id');
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

    public function courseSubGroup(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\CourseSubgroup::class, 'course_subgroup_id');
    }

    public function booking(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Booking::class, 'booking_id');
    }

    public function courseDate(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\CourseDate::class, 'course_date_id');
    }

    public function courseDateActive(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\CourseDate::class, 'course_date_id')->where('active', true);
    }

    public function monitor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Monitor::class, 'monitor_id');
    }

    public function bookingUserExtras(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\BookingUserExtra::class, 'booking_user_id');
    }

    public function courseExtras()
    {
        return $this->hasManyThrough(
            \App\Models\CourseExtra::class,
            \App\Models\BookingUserExtra::class,
            'booking_user_id',
            'id',
            'id',
            'course_extra_id'
        );
    }

    public function scopeByMonitor($query, $monitor)
    {
        return $query->where('monitor_id', $monitor);
    }

    // En BookingUser.php
    public function scopeOnlyWeekends($query)
    {
        return $query->whereRaw('WEEKDAY(date) IN (5, 6)');
    }

    public static function hasOverlappingBookings($bookingUser, $bookingUserIds)
    {

        $clientBookings = BookingUser::where('client_id', $bookingUser['client_id'])
            ->where('date', $bookingUser['date'])
            ->where('status', 1)
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 2); // La Booking no debe tener status 2
            })
            ->when(count($bookingUserIds) > 0, function ($query) use ($bookingUserIds) {
                return $query->whereNotIn('id', $bookingUserIds);
            })
            ->get();

        foreach ($clientBookings as $existingBooking) {

            // Comprobar si hay solapamiento de horarios
            if (
                ($bookingUser['hour_start'] < $existingBooking->hour_end &&
                    $existingBooking->hour_start < $bookingUser['hour_end']) ||
                // Comprobar si los tiempos son exactamente iguales
                ($bookingUser['hour_start'] === $existingBooking->hour_start &&
                    $bookingUser['hour_end'] === $existingBooking->hour_end)
            ) {
                return true; // Hay solapamiento
            }
        }

        return false; // No hay solapamiento
    }


    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }
}
