<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @OA\Schema(
 *      schema="CourseDate",
 *      required={"course_id", "date", "hour_start", "hour_end"},
 *      @OA\Property(
 *          property="course_id",
 *          description="ID of the course",
 *          type="integer",
 *          nullable=false
 *      ),
 *      @OA\Property(
 *           property="interval_id",
 *           description="ID of the interval",
 *           type="integer",
 *           nullable=true
 *       ),
 *      @OA\Property(
 *            property="order",
 *            description="ID order of the interval",
 *            type="integer",
 *            nullable=true
 *        ),
 *      @OA\Property(
 *          property="date",
 *          description="Date of the course session",
 *          type="string",
 *          format="date",
 *          nullable=false
 *      ),
 *      @OA\Property(
 *          property="hour_start",
 *          description="Start hour of the course session",
 *          type="string",
 *          nullable=false
 *      ),
 *      @OA\Property(
 *          property="hour_end",
 *          description="End hour of the course session",
 *          type="string",
 *          nullable=false
 *      ),
 *      @OA\Property(
 *           property="active",
 *           description="",
 *           readOnly=false,
 *           nullable=false,
 *           type="boolean",
 *       ),
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
class CourseDate extends Model
{
    use LogsActivity, SoftDeletes, HasFactory;

    public $table = 'course_dates';

    public $fillable = [
        'course_id',
        'course_interval_id',
        'date',
        'hour_start',
        'hour_end',
        'interval_id',
        'order',
        'active'
    ];

    protected $casts = [
        'date' => 'date'
    ];

    public static array $rules = [
        'course_id' => 'required',
        'date' => 'required',
        'hour_start' => 'required',
        'hour_end' => 'required',
        'interval_id' => 'nullable',
        'order' => 'nullable',
        'active' => 'boolean',
        'created_at' => 'nullable',
        'updated_at' => 'nullable',
        'deleted_at' => 'nullable'
    ];

    protected $appends = ['duration'];
    public function getDurationAttribute()
    {
        if (!$this->hour_start || !$this->hour_end) {
            return null; // Retorna null si falta algún dato
        }

        // Convertir a objetos Carbon
        $start = \Carbon\Carbon::createFromFormat('H:i', $this->hour_start);
        $end = \Carbon\Carbon::createFromFormat('H:i', $this->hour_end);

        // Calcular la diferencia en minutos
        $minutes = $start->diffInMinutes($end);

        // Formatear la duración
        if ($minutes < 60) {
            return "{$minutes}min";
        } else {
            $hours = intdiv($minutes, 60);
            $remainingMinutes = $minutes % 60;
            return $remainingMinutes ? "{$hours}h {$remainingMinutes}min" : "{$hours}h";
        }
    }

    public function setHourStartAttribute($value)
    {
        if ($value && \Carbon\Carbon::hasFormat($value, 'H:i')) {
            // Si el formato es correcto, lo guarda con el formato H:i:s
            $this->attributes['hour_start'] = \Carbon\Carbon::createFromFormat('H:i', $value)->format('H:i:s');
        } else {
            // Si el formato es incorrecto o no viene, se puede manejar de diferentes maneras
            $this->attributes['hour_start'] = null; // Establece el valor como null o algún valor por defecto
        }
    }

    public function setHourEndAttribute($value)
    {
        if ($value && \Carbon\Carbon::hasFormat($value, 'H:i')) {
            // Si el formato es correcto, lo guarda con el formato H:i:s
            $this->attributes['hour_end'] = \Carbon\Carbon::createFromFormat('H:i', $value)->format('H:i:s');
        } else {
            // Si el formato es incorrecto o no viene, lo maneja adecuadamente
            $this->attributes['hour_end'] = null; // Establece el valor como null o algún valor por defecto
        }
    }

    public function getHourStartAttribute($value)
    {
        return $value ? \Carbon\Carbon::createFromFormat('H:i:s', $value)->format('H:i') : null; // Manejar valor null
    }

    public function getHourEndAttribute($value)
    {
        return $value ? \Carbon\Carbon::createFromFormat('H:i:s', $value)->format('H:i') : null; // Manejar valor null
    }

    public function scopeOnlyWeekends($query)
    {
        return $query->whereRaw('WEEKDAY(date) IN (5, 6)');
    }

    public function course(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Course::class, 'course_id');
    }

    public function courseInterval(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\CourseInterval::class, 'course_interval_id');
    }

    public function bookingUsers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\BookingUser::class, 'course_date_id');
    }

    public function bookingUsersActive(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\BookingUser::class, 'course_date_id')
            ->where('status', 1) // BookingUser debe tener status 1
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 2); // La Booking no debe tener status 2
            });
    }

    public function courseGroups(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\CourseGroup::class, 'course_date_id');
    }

    public function courseSubgroups(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\CourseSubgroup::class, 'course_date_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::updated(function ($courseDate) {
            // Obtener el curso asociado
            $course = $courseDate->course; // Asegúrate de tener la relación definida en el modelo

            // Solo actualizar si el curso tiene course_type = 1
            if ($course && $course->course_type == 1 && $courseDate->isDirty(['date', 'hour_start', 'hour_end'])) {
                DB::table('booking_users')
                    ->where('course_date_id', $courseDate->id)
                    ->update([
                        'date' => $courseDate->date,
                        'hour_start' => $courseDate->hour_start,
                        'hour_end' => $courseDate->hour_end
                    ]);
            }
        });

        static::deleting(function ($courseDate) {
            // Verificar si hay reservas activas antes de eliminar
            $activeBookings = $courseDate->bookingUsersActive()->count();

            if ($activeBookings > 0) {
                throw new \Exception(
                    "No se puede eliminar la sesión porque tiene {$activeBookings} reserva(s) activa(s). " .
                    "Por favor, cancela las reservas primero o contacta con los clientes."
                );
            }

            // Solo eliminar booking_users si no hay reservas activas
            BookingUser::where('course_date_id', $courseDate->id)->delete();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
         return LogOptions::defaults();
    }
}
