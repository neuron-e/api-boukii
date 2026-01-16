<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @OA\Schema(
 *      schema="School",
 *      required={"name","description"},
 *      @OA\Property(
 *          property="name",
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
 *          property="contact_email",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="contact_phone",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="contact_telephone",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="contact_address",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="contact_cp",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="contact_city",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="contact_province",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="contact_country",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="fiscal_name",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="fiscal_id",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="fiscal_address",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="fiscal_cp",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="fiscal_city",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="fiscal_province",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="fiscal_country",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="iban",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="logo",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="slug",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="cancellation_insurance_percent",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="number",
 *          format="number"
 *      ),
 *      @OA\Property(
 *          property="payrexx_instance",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="payrexx_key",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="conditions_url",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="bookings_comission_cash",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="number",
 *          format="number"
 *      ),
 *      @OA\Property(
 *          property="bookings_comission_boukii_pay",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="number",
 *          format="number"
 *      ),
 *      @OA\Property(
 *          property="bookings_comission_other",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="number",
 *          format="number"
 *      ),
 *      @OA\Property(
 *          property="school_rate",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="number",
 *          format="number"
 *      ),
 *      @OA\Property(
 *          property="has_ski",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="boolean",
 *      ),
 *      @OA\Property(
 *          property="has_snowboard",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="boolean",
 *      ),
 *      @OA\Property(
 *          property="has_telemark",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="boolean",
 *      ),
 *      @OA\Property(
 *          property="has_rando",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="boolean",
 *      ),
 *      @OA\Property(
 *          property="inscription",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="boolean",
 *      ),
 *      @OA\Property(
 *          property="type",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="active",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="boolean",
 *      ),
 *      @OA\Property(
 *          property="settings",
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
 */
class School extends Model
{
     use LogsActivity, SoftDeletes, HasFactory;     public $table = 'schools';

    public $fillable = [
        'name',
        'description',
        'contact_email',
        'contact_phone',
        'contact_telephone',
        'contact_address',
        'contact_cp',
        'contact_city',
        'contact_province',
        'contact_country',
        'fiscal_name',
        'fiscal_id',
        'fiscal_address',
        'fiscal_cp',
        'fiscal_city',
        'fiscal_province',
        'fiscal_country',
        'iban',
        'logo',
        'slug',
        'cancellation_insurance_percent',
        'payrexx_instance',
        'payrexx_key',
        'payment_link_validity_hours',
        'conditions_url',
        'bookings_comission_cash',
        'bookings_comission_boukii_pay',
        'bookings_comission_other',
        'school_rate',
        'has_ski',
        'has_snowboard',
        'has_telemark',
        'has_rando',
        'inscription',
        'type',
        'active',
        'settings'
    ];

    protected $casts = [
        'name' => 'string',
        'description' => 'string',
        'contact_email' => 'string',
        'contact_phone' => 'string',
        'contact_telephone' => 'string',
        'contact_address' => 'string',
        'contact_cp' => 'string',
        'contact_city' => 'string',
        'contact_province' => 'string',
        'contact_country' => 'string',
        'fiscal_name' => 'string',
        'fiscal_id' => 'string',
        'fiscal_address' => 'string',
        'fiscal_cp' => 'string',
        'fiscal_city' => 'string',
        'fiscal_province' => 'string',
        'fiscal_country' => 'string',
        'iban' => 'string',
        'logo' => 'string',
        'slug' => 'string',
        'cancellation_insurance_percent' => 'decimal:2',
        'payrexx_instance' => 'string',
        'payrexx_key' => 'string',
        'payment_link_validity_hours' => 'integer',
        'conditions_url' => 'string',
        'bookings_comission_cash' => 'decimal:2',
        'bookings_comission_boukii_pay' => 'decimal:2',
        'bookings_comission_other' => 'decimal:2',
        'school_rate' => 'float',
        'has_ski' => 'boolean',
        'has_snowboard' => 'boolean',
        'has_telemark' => 'boolean',
        'has_rando' => 'boolean',
        'inscription' => 'boolean',
        'type' => 'string',
        'active' => 'boolean',
        'settings' => 'string'
    ];

    public static array $rules = [
        'name' => 'nullable',
        'description' => 'nullable',
        'contact_email' => 'nullable',
        'contact_phone' => 'nullable',
        'contact_telephone' => 'nullable',
        'contact_address' => 'nullable',
        'contact_cp' => 'nullable',
        'contact_city' => 'nullable',
        'contact_province' => 'nullable',
        'contact_country' => 'nullable',
        'fiscal_name' => 'nullable',
        'fiscal_id' => 'nullable',
        'fiscal_address' => 'nullable',
        'fiscal_cp' => 'nullable',
        'fiscal_city' => 'nullable',
        'fiscal_province' => 'nullable',
        'fiscal_country' => 'nullable',
        'iban' => 'nullable',
        'logo' => 'nullable',
        'slug' => 'nullable',
        'cancellation_insurance_percent' => 'nullable',
        'payrexx_instance' => 'nullable',
        'payrexx_key' => 'nullable',
        'payment_link_validity_hours' => 'nullable',
        'conditions_url' => 'nullable',
        'bookings_comission_cash' => 'nullable',
        'bookings_comission_boukii_pay' => 'nullable',
        'bookings_comission_other' => 'nullable',
        'school_rate' => 'nullable',
        'has_ski' => 'nullable',
        'has_snowboard' => 'nullable',
        'has_telemark' => 'nullable',
        'has_rando' => 'nullable',
        'inscription' => 'nullable',
        'type' => 'nullable',
        'active' => 'nullable',
        'settings' => 'nullable',
        'created_at' => 'nullable',
        'updated_at' => 'nullable',
        'deleted_at' => 'nullable'
    ];

    public function bookings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Booking::class, 'school_id');
    }

    public function clientObservations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\ClientObservation::class, 'school_id');
    }

    public function clientsSchools(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\ClientsSchool::class, 'school_id');
    }

    public function courses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Course::class, 'school_id');
    }

    public function degrees(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Degree::class, 'school_id');
    }
    public function sports()
    {
        return $this->belongsToMany(Sport::class, 'school_sports', 'school_id');
    }

    public function monitorNwds(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\MonitorNwd::class, 'school_id');
    }

    public function monitorObservations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\MonitorObservation::class, 'school_id');
    }

    public function monitorSportsDegrees(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\MonitorSportsDegree::class, 'school_id');
    }

    public function monitors(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Monitor::class, 'active_school');
    }

    public function monitorsSchools(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\MonitorsSchool::class, 'school_id');
    }

    public function schoolColors(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\SchoolColor::class, 'school_id');
    }

    public function schoolSalaryLevels(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\SchoolSalaryLevel::class, 'school_id');
    }

    public function schoolSports(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\SchoolSport::class, 'school_id');
    }

    public function schoolUsers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\SchoolUser::class, 'school_id');
    }

    public function seasons(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Season::class, 'school_id');
    }

    public function stationsSchools(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\StationsSchool::class, 'school_id');
    }

    public function tasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Task::class, 'school_id');
    }

    public function vouchers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Voucher::class, 'school_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
         return LogOptions::defaults();
    }

    /**
     * Payrexx
     */

    // Special for field "payrexx_instance" & "payrexx_key": store encrypted
    public function setPayrexxInstance($value)
    {
        $this->payrexx_instance = encrypt($value);
    }

    public function getPayrexxInstance()
    {
        if ($this->payrexx_instance) {
            try {
                return decrypt($this->payrexx_instance);
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                // Data corrupt, continue to fallback
            }
        }

        return config('services.payrexx.instance');
    }

    public function setPayrexxKey($value)
    {
        $this->payrexx_key = encrypt($value);
    }

    public function getPayrexxKey()
    {
        if ($this->payrexx_key) {
            try {
                return decrypt($this->payrexx_key);
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                // Data corrupt, continue to fallback
            }
        }

        return config('services.payrexx.key');
    }

    /**
     * Get payment link validity in hours.
     * Returns school-specific setting or default 48 hours.
     */
    public function getPaymentLinkValidityHours(): int
    {
        return $this->payment_link_validity_hours ?? 48;
    }
}
