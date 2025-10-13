<?php

namespace App\Models;

use App\Http\Services\BookingPriceCalculatorService;
use App\Mail\BookingCancelMailer;
use App\Mail\BookingInfoMailer;
use App\Models\School;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @OA\Schema(
 *      schema="Booking",
 *      required={"school_id","price_total","has_cancellation_insurance","price_cancellation_insurance","currency","paid_total","paid","attendance","payrexx_refund","notes","paxes"},
 *      @OA\Property(
 *          property="price_total",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="number",
 *          format="number"
 *      ),
 *      @OA\Property(
 *          property="has_cancellation_insurance",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="boolean",
 *      ),
 *      @OA\Property(
 *          property="price_cancellation_insurance",
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
 *          property="paid_total",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="number",
 *          format="number"
 *      ),
 *      @OA\Property(
 *          property="paid",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="boolean",
 *      ),
 *      @OA\Property(
 *          property="payrexx_reference",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="payrexx_transaction",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="attendance",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="boolean",
 *      ),
 *      @OA\Property(
 *          property="payrexx_refund",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="boolean",
 *      ),
 *      @OA\Property(
 *          property="notes",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="string",
 *      ),
 *     @OA\Property(
 *           property="notes_school",
 *           description="",
 *           readOnly=false,
 *           nullable=true,
 *           type="string",
 *       ),
 *      @OA\Property(
 *           property="school_id",
 *           description="School ID",
 *           type="integer",
 *           nullable=false
 *       ),
 *       @OA\Property(
 *           property="client_main_id",
 *           description="Main Client ID",
 *           type="integer",
 *           nullable=true
 *       ),
 *       @OA\Property(
 *           property="user_id",
 *           description="User ID who performed the action",
 *           type="integer",
 *           nullable=false
 *       ),
 *       @OA\Property(
 *           property="payment_method_id",
 *           description="Payment Method ID",
 *           type="integer",
 *           nullable=true
 *       ),
 *       @OA\Property(
 *           property="paxes",
 *           description="Number of paxes",
 *           type="integer",
 *           nullable=false
 *       ),
 *      @OA\Property(
 *          property="color",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *     @OA\Property(
 *           property="source",
 *           description="",
 *           readOnly=false,
 *           nullable=true,
 *           type="string",
 *       ),
 *      @OA\Property(
 *           property="status",
 *           description="Status of the booking",
 *           type="integer",
 *           example=1
 *       ),
 *      @OA\Property(
 *          // BOUKII CARE DESACTIVADO -  *           property="has_boukii_care",
 *           description="",
 *           readOnly=false,
 *           nullable=false,
 *           type="boolean",
 *       ),
 *     @OA\Property(
 *          // BOUKII CARE DESACTIVADO -  *           property="price_boukii_care",
 *           description="",
 *           readOnly=false,
 *           nullable=false,
 *           type="number",
 *           format="number"
 *      ),
 *      @OA\Property(
 *            property="has_tva",
 *            description="",
 *            readOnly=false,
 *            nullable=false,
 *            type="boolean",
 *        ),
 *      @OA\Property(
 *            property="price_tva",
 *            description="",
 *            readOnly=false,
 *            nullable=false,
 *            type="number",
 *            format="number"
 *       ),
 *      @OA\Property(
 *            property="has_reduction",
 *            description="",
 *            readOnly=false,
 *            nullable=false,
 *            type="boolean",
 *        ),
 *      @OA\Property(
 *            property="price_reduction",
 *            description="",
 *            readOnly=false,
 *            nullable=false,
 *            type="number",
 *            format="number"
 *       ),
 *      @OA\Property(
 *           property="basket",
 *           description="",
 *           readOnly=false,
 *           nullable=true,
 *           type="string",
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

class Booking extends Model
{
    use LogsActivity, SoftDeletes, HasFactory;

    public $table = 'bookings';

    public $fillable = [
        'school_id',
        'client_main_id',
        'user_id',
        'price_total',
        'has_cancellation_insurance',
        'price_cancellation_insurance',
        'source',
        'currency',
        'payment_method_id',
        'paid_total',
        'paid',
        'payrexx_reference',
        'payrexx_transaction',
        'attendance',
        'payrexx_refund',
        'notes',
        'notes_school',
        'paxes',
        'status',
        'old_id',
        // BOUKII CARE DESACTIVADO -         'has_boukii_care',
        // BOUKII CARE DESACTIVADO -         'price_boukii_care',
        'has_tva',
        'price_tva',
        'has_reduction',
        'price_reduction',
        'color',
        'basket'
    ];

    protected $casts = [
        'price_total' => 'decimal:2',
        'has_cancellation_insurance' => 'boolean',
        'has_tva' => 'boolean',
        'has_reduction' => 'boolean',
        'price_cancellation_insurance' => 'decimal:2',
        'price_reduction' => 'decimal:2',
        'price_tva' => 'decimal:2',
        'source' => 'string',
        'currency' => 'string',
        'paid_total' => 'decimal:2',
        // BOUKII CARE DESACTIVADO -         'price_boukii_care' => 'decimal:2',
        'paid' => 'boolean',
        // BOUKII CARE DESACTIVADO -         'has_boukii_care' => 'boolean',
        'payrexx_reference' => 'string',
        'payrexx_transaction' => 'string',
        'attendance' => 'boolean',
        'payrexx_refund' => 'boolean',
        'notes' => 'string',
        'status' => 'integer',
        'notes_school' => 'string',
        'color' => 'string',
        'basket' => 'string'
    ];

    public static array $rules = [
        'school_id' => 'nullable',
        'client_main_id' => 'nullable',
        'user_id' => 'nullable',
        'price_total' => 'nullable|numeric',
        'has_cancellation_insurance' => 'nullable|boolean',
        'price_cancellation_insurance' => 'nullable|numeric',
        'currency' => 'nullable|string|max:3',
        'payment_method_id' => 'nullable',
        'paid_total' => 'nullable',
        'paid' => 'nullable',
        'payrexx_reference' => 'nullable|string|max:65535',
        'payrexx_transaction' => 'nullable|string|max:65535',
        'attendance' => 'nullable',
        'payrexx_refund' => 'nullable|boolean',
        'notes' => 'nullable|string|max:500',
        'notes_school' => 'nullable|string|max:500',
        'paxes' => 'nullable',
        'status' => 'nullable',
        'basket' => 'nullable',
        'color' => 'nullable|string|max:45',
        'source' => 'nullable',
        'created_at' => 'nullable',
        'updated_at' => 'nullable',
        'deleted_at' => 'nullable'
    ];

    public function school(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\School::class, 'school_id');
    }

    public function clientMain(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Client::class, 'client_main_id');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function bookingLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\BookingLog::class, 'booking_id');
    }

    public function bookingUsers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\BookingUser::class, 'booking_id');
    }

    public function bookingUsersActive(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\BookingUser::class, 'booking_id')
            ->where('status', 1);// BookingUser debe tener status 1

    }

    public function vouchersLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\VouchersLog::class, 'booking_id');
    }

    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Payment::class, 'booking_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }
    // Expose minimal computed attributes needed by Admin tables.
    // Keep this list short to avoid unnecessary N+1 queries.
    protected $appends = ['sport'];

    public function getHasObservationsAttribute()
    {
        return $this->bookingUsers()
            ->where(function ($query) {
                $query->whereNotNull('notes')
                    ->orWhereNotNull('notes_school');
            })
            ->exists();
    }


    public function getVouchersUsedAmountAttribute()
    {
        return $this->vouchersLogs()->sum('amount');
    }

    // Agrupa los booking_users por group_id con detalles completos - OPTIMIZADO
    public function getGroupedActivitiesAttribute()
    {
        // Cargar todas las relaciones necesarias de una vez para evitar N+1
        $this->loadMissing([
            'bookingUsers.client.clientSports.degree',
            'bookingUsers.client.clientSports.sport',
            'bookingUsers.course.sport',
            'bookingUsers.degree',
            'bookingUsers.monitor',
            'bookingUsers.bookingUserExtras.courseExtra'
        ]);
        
        return array_values($this->bookingUsers->groupBy('group_id')->map(function ($users) {
            $groupedActivity = [
                'group_id' => $users->first()->group_id,
                'sport' => optional($users->first()->course)->sport,
                'course' => $users->first()->course,
                'course_name' => optional($users->first()->course)->name,
                'sportLevel' => $users->first()->degree,
                'dates' => [],
                'monitors' => [],
                'utilizers' => [],
                'clientObs' => $users->first()->notes,
                'schoolObs' => $users->first()->notes_school,
                'total' => 0,
                'extra_price' => 0, // Precio total de los extras
                'price_base' => 0,   // Precio base sin extras
                'price' => 0,        // Precio total (base + extras)
                'extras' => [],      // Lista de extras consolidada
                'status' => 0,
                'statusList' => [],
                'items' => []        // Para mantener compatibilidad con createBasket
            ];

            foreach ($users as $user) {
                $groupedActivity['statusList'][] = $user->status;
                $groupedActivity['items'][] = $user->id; // Añadir ID del usuario para compatibilidad

                // Añadir utilizers únicos
                if (!$this->utilizerExists($groupedActivity['utilizers'], $user->client)) {
                    $groupedActivity['utilizers'][] = [
                        'id' => $user->client_id,
                        'first_name' => $user->client->first_name,
                        'last_name' => $user->client->last_name,
                        'image' => $user->client->image,
                        'birth_date' => $user->client->birth_date,
                        'language1_id' => $user->client->language1_id,
                        'country' => $user->client->country,
                        'client_sports' => $user->client->clientSports->map(function ($sport) {
                            return [
                                'id' => $sport->id,
                                'client_id' => $sport->client_id,
                                'sport_id' => $sport->sport_id, // Si hay más datos en clientSports
                                'school_id' => $sport->school_id, // Si hay más datos en clientSports
                                'degree_id' => $sport->degree_id, // Si hay más datos en clientSports
                                'degree' => $sport->degree ? [
                                    'id' => $sport->degree->id,
                                    'league' => $sport->degree->league,
                                    'level' => $sport->degree->level, // Agrega más campos si los hay
                                    'name' => $sport->degree->name, // Agrega más campos si los hay
                                    'annotation' => $sport->degree->annotation, // Agrega más campos si los hay
                                    'degree_order' => $sport->degree->degree_order, // Agrega más campos si los hay
                                    'progress' => $sport->degree->progress, // Agrega más campos si los hay
                                    'color' => $sport->degree->color, // Agrega más campos si los hay
                                ] : null,
                            ];
                        })->toArray(),
                        'extras' => []
                    ];
                }

                // Manejar fechas
                $dateKey = $user->course_date_id . '_' . $user->hour_start . '_' . $user->hour_end;
                if (!isset($groupedActivity['dates'][$dateKey])) {
                    $groupedActivity['dates'][$dateKey] = [
                        'id' => $user->course_date_id,
                        'date' => $user->date,
                        'startHour' => $user->hour_start,
                        'endHour' => $user->hour_end,
                        'duration' => $user->formattedDuration,
                        'currency' => $this->currency,
                        'monitor' => $user->monitor,
                        'utilizers' => [],
                        'extras' => [],
                        'booking_users' => []
                    ];
                }

                $groupedActivity['dates'][$dateKey]['booking_users'][] = $user;

                if (!$this->utilizerExists($groupedActivity['dates'][$dateKey]['utilizers'], $user->client)) {
                    $groupedActivity['dates'][$dateKey]['utilizers'][] = [
                        'id' => $user->client_id,
                        'first_name' => $user->client->first_name,
                        'last_name' => $user->client->last_name,
                        'image' => $user->client->image,
                        'birth_date' => $user->client->birth_date,
                        'language1_id' => $user->client->language1_id,
                        'country' => $user->client->country,
                        'client_sports' => $user->client->clientSports->map(function ($sport) {
                            return [
                                'id' => $sport->id,
                                'client_id' => $sport->client_id,
                                'sport_id' => $sport->sport_id, // Si hay más datos en clientSports
                                'school_id' => $sport->school_id, // Si hay más datos en clientSports
                                'degree_id' => $sport->degree_id, // Si hay más datos en clientSports
                                'degree' => $sport->degree ? [
                                    'id' => $sport->degree->id,
                                    'league' => $sport->degree->league,
                                    'level' => $sport->degree->level, // Agrega más campos si los hay
                                    'name' => $sport->degree->name, // Agrega más campos si los hay
                                    'annotation' => $sport->degree->annotation, // Agrega más campos si los hay
                                    'degree_order' => $sport->degree->degree_order, // Agrega más campos si los hay
                                    'progress' => $sport->degree->progress, // Agrega más campos si los hay
                                    'color' => $sport->degree->color, // Agrega más campos si los hay
                                ] : null,
                            ];
                        })->toArray(),
                    ];
                }

                // Añadir extras por cada utilizador
                foreach ($user->bookingUserExtras as $extra) {
                    if ($extra->courseExtra) {
                        // Buscar el utilizador dentro de la fecha actual
                        foreach ($groupedActivity['dates'][$dateKey]['utilizers'] as &$utilizer) {
                            if ($utilizer['id'] === $user->client_id) {
                                // Verificar si el extra ya existe para este utilizador
                                $extraExists = false;
                                if(array_key_exists('extras', $utilizer)) {
                                    foreach ($utilizer['extras'] as &$existingExtra) {
                                        if ($existingExtra['id'] === $extra->courseExtra->id) {
                                            $existingExtra['quantity']++;
                                            $extraExists = true;
                                            break;
                                        }
                                    }
                                }


                                if (!$extraExists) {
                                    $utilizer['extras'][] = [
                                        'id' => $extra->courseExtra->id,
                                        'name' => $extra->courseExtra->name,
                                        'description' => $extra->courseExtra->description,
                                        'price' => $extra->courseExtra->price,
                                        'quantity' => 1
                                    ];
                                    $groupedActivity['extras'][] = [
                                        'id' => $extra->courseExtra->id,
                                        'name' => $extra->courseExtra->name,
                                        'description' => $extra->courseExtra->description,
                                        'price' => $extra->courseExtra->price,
                                        'quantity' => 1
                                    ];
                                }

                                break; // Evita seguir iterando después de encontrar al utilizador
                            }
                        }
                    }
                }


                // Añadir monitores
                if ($user->monitor_id && !in_array($user->monitor_id, $groupedActivity['monitors'])) {
                    $groupedActivity['monitors'][] = $user->monitor_id;
                }
            }

            $groupedActivity['dates'] = array_values($groupedActivity['dates']);

            // Determinar estado del grupo
            $uniqueStatuses = array_unique($groupedActivity['statusList']);
            $groupedActivity['status'] = count($uniqueStatuses) === 1 ? $uniqueStatuses[0] : 3;

            // Calcular precios
            $groupedActivity['price_base'] = $this->calculateActivityPrice($groupedActivity);

            // Calcular el precio total de los extras
            $groupedActivity['extra_price'] = 0;
            foreach ($groupedActivity['extras'] as $extra) {
                $groupedActivity['extra_price'] += $extra['price'] * $extra['quantity'];
            }

            // Calcular precio total (base + extras)
            $groupedActivity['total'] = $groupedActivity['price'] = $groupedActivity['price_base'] + $groupedActivity['extra_price'];

            return $groupedActivity;
        })->toArray());
    }
    public function updateCart()
    {
        $bookingData = $this;
        $groupedCartItems = $this->getGroupedActivitiesAttribute();

        // Si no hay elementos en el carrito, no continuamos
        if (empty($groupedCartItems)) {
            return;
        }

        // Tomamos el primer grupo para la información de precios
        $group = $groupedCartItems[0];

        // Crear el objeto basket con el formato requerido
        $basket = [
            "payment_method_id" => $bookingData['payment_method_id'] ?? 3,
            "price_base" => [
                "name" => $group['course_name'] ?? '',
                "quantity" => count($group['items'] ?? []),
                "price" => $group['price_base'] ?? 0
            ],
            "bonus" => [
                "total" => !empty($bookingData['vouchers']) ? count($bookingData['vouchers']) : 0,
                "bonuses" => !empty($bookingData['vouchers']) ? array_map(function($voucher) {
                    return [
                        "name" => $voucher['bonus']['code'],
                        "quantity" => 1,
                        "price" => -$voucher['bonus']['reducePrice']
                    ];
                }, $bookingData['vouchers']) : []
            ],
            "reduction" => [
                "name" => "Reduction",
                "quantity" => 1,
                "price" => -($bookingData['price_reduction'] ?? 0)
            ],
            // BOUKII CARE DESACTIVADO -             // BOUKII CARE DESACTIVADO -             "boukii_care" => [
            // BOUKII CARE DESACTIVADO -             // BOUKII CARE DESACTIVADO -                 "name" => "Boukii Care",
            // BOUKII CARE DESACTIVADO -             // BOUKII CARE DESACTIVADO -                 "quantity" => 1,
            // BOUKII CARE DESACTIVADO -             // BOUKII CARE DESACTIVADO -                 "price" => $bookingData['price_boukii_care'] ?? 0
            ],
            "cancellation_insurance" => [
                "name" => "Cancellation Insurance",
                "quantity" => 1,
                "price" => $bookingData['price_cancellation_insurance'] ?? 0
            ],
            "extras" => [
                "total" => count($group['extras'] ?? []),
                "extras" => $group['extras'] ?? []
            ],
            "tva" => [
                "name" => "TVA",
                "quantity" => 1,
                "price" => $bookingData['price_tva'] ?? 0
            ],
            "price_total" => $group['price'] ?? 0,
            "paid_total" => $bookingData['paid_total'] ?? $group['price'] ?? 0,
            "pending_amount" => $bookingData['pending_amount'] ?? $group['price'] ?? 0
        ];

        // Actualizar el campo basket en la base de datos con el formato JSON
        $this->update(['basket' => json_encode($basket)]);

        return $basket;
    }

    // Verifica si un utilizer ya fue agregado
    private function utilizerExists($utilizers, $client)
    {
        foreach ($utilizers as $utilizer) {
            if ($utilizer['id'] === $client->id) {
                return true;
            }
        }
        return false;
    }

    // Recalcula el total de la reserva
    public function reloadPrice()
    {
        $total = 0;

        $groupedActivities = $this->getGroupedActivitiesAttribute();

        foreach ($groupedActivities as $activity) {
            if($activity['status'] !== 2) {
                $total += $activity['total'];
            }
        }

        if ($this->has_cancellation_insurance) {
            $school = School::find($this->school_id);
            $this->cancellation_insurance_price =
                $total * json_decode($school->settings, true)['taxes']['cancellation_insurance_percent'];
            $total += $this->cancellation_insurance_price;
        }

        $voucherLogs = $this->vouchersLogs()->with('voucher')->get();

        foreach ($voucherLogs as $log) {
            $voucher = $log->voucher;

            if ($log->amount > $total) {
                // Registrar un nuevo log con el dinero sobrante devuelto al bono
                $refundAmount = $log->amount - $total;
                VouchersLog::create([
                    'voucher_id' => $voucher->id,
                    'booking_id' => $this->id,
                    'amount' => -$refundAmount, // Se registra como negativo
                    'status' => 'refund',
                ]);

                // Actualizar el saldo del bono
                $voucher->remaining_balance += $refundAmount;
            }

            // Si el bono no se ha consumido por completo, actualizar `payed`
            if ($voucher->remaining_balance < $voucher->quantity) {
                $voucher->payed = false;
            } else {
                $voucher->payed = true;
            }

            $voucher->save();
        }

        if ($this->paid_total >= $this->price_total) {
            $this->paid = true;
        } else {
            $this->paid = false;
        }

        $this->save();

        return $total;
    }

    // Calcula el precio total de una actividad
    public function calculateActivityPrice($activity)
    {
        $price = 0;

        // Safety check: ensure activity is not null and is an array
        if (!$activity || !is_array($activity)) {
            return $price;
        }

        if (array_key_exists('course',$activity) && isset($activity['course']['course_type']) && $activity['course']['course_type'] === 1) {
            if (!$activity['course']['is_flexible']) {
                $price = ($activity['course']['price'] ?? 0) * count($activity['utilizers'] ?? []);
            } else {
                $price = ($activity['course']['price'] ?? 0) * count($activity['dates'] ?? []) * count($activity['utilizers'] ?? []);
            }
        } else {
            if (isset($activity['dates']) && is_array($activity['dates'])) {
                foreach ($activity['dates'] as $date) {
                    $price += $this->calculateDatePrice($activity['course'] ?? null, $date);
                }
            }
        }

        return $price;
    }

    // Calcula el precio de una fecha
    public function calculateDatePrice($course, $date)
    {
        $datePrice = 0;

        // Safety checks for null inputs
        if (!$course || !$date || !isset($date['booking_users'])) {
            return $datePrice;
        }

        if (collect($date['booking_users'])->contains('status', 1)) {
            if (($course['course_type'] ?? 0) === 1) {
                $datePrice = $course['price'] ?? 0;
            } elseif ($course['is_flexible'] ?? false) {
                $duration = $date['duration'] ?? 0;
                $participants = count($date['utilizers'] ?? []);
                $interval = collect($course['price_range'] ?? [])->firstWhere('intervalo', $duration);
                $datePrice = $interval ? ($interval[$participants] ?? 0) : 0;
            } else {
                $datePrice = $course['price'] ?? 0;
            }

            $extrasPrice = collect($date['extras'])->sum('price');
            $datePrice += $extrasPrice;
        }

        return $datePrice;
    }

    public function getBonusAttribute() {
        return $this->vouchersLogs()->exists();
    }

    public function getPaymentMethodAttribute() {
        $hasVouchers = $this->vouchersLogs()->exists();
        $hasPayments = $this->payments()->exists();

        if ($hasVouchers && !$hasPayments) {
            return 6;
        }

        return $this->payment_method_id;
    }

    public function getSportAttribute()
    {
        try {
            // Check if bookingUsers relation is loaded
            if ($this->relationLoaded('bookingUsers')) {
                $bookingUsers = $this->bookingUsers;
            } else {
                $bookingUsers = $this->bookingUsers()->with('course.sport')->get();
            }

            // Obtener los cursos únicos asociados a los bookingUsers
            $courses = $bookingUsers->pluck('course')->filter()->unique();

            if ($courses->isNotEmpty()) {
                // Verificar si todos los bookingUsers tienen el mismo course_type
                $courseType = $courses->first()->course_type ?? null;
                $sameCourseType = $courses->every(function ($course) use ($courseType) {
                    if ($course && isset($course->course_type)) {
                        return $course->course_type === $courseType;
                    }
                    return false;
                });

                if ($sameCourseType && $courses->count() == 1) {
                    // Si solo hay un curso y todos tienen el mismo course_type
                    // devolver el deporte de ese curso
                    $firstCourse = $courses->first();
                    if ($firstCourse && $firstCourse->sport) {
                        if ($courseType == 1) {
                            return $firstCourse->sport->icon_collective ?? null;
                        } elseif ($courseType == 2) {
                            return $firstCourse->sport->icon_prive ?? null;
                        } elseif ($courseType == 3) {
                            return $firstCourse->sport->icon_activity ?? null;
                        }
                    }
                } elseif ($sameCourseType && $courses->pluck('sport')->filter()->unique()->count() == 1) {
                    // Si hay varios cursos pero todos tienen el mismo course_type y el mismo deporte
                    // devolver ese deporte
                    $firstCourse = $courses->first();
                    if ($firstCourse && $firstCourse->sport) {
                        if ($courseType == 1) {
                            return $firstCourse->sport->icon_collective ?? null;
                        } elseif ($courseType == 2) {
                            return $firstCourse->sport->icon_prive ?? null;
                        } elseif ($courseType == 3) {
                            return $firstCourse->sport->icon_activity ?? null;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error in getSportAttribute: ' . $e->getMessage());
        }

        // Si hay varios cursos con diferentes course_type o deportes diferentes
        // devolver 'multiple'
        return 'multiple';
    }

    public function getCancellationStatusAttribute()
    {
        $status = 'active';
        $statusPayment = '';

        // Si el estado de la reserva es "totalmente cancelada"
        if ($this->status == 2) {
            $status = 'total_cancel';
            $lastLog = $this->bookingLogs ? $this->bookingLogs->last() : null;
            $statusPayment = $lastLog ? $lastLog->action : '';

        } else {
            // Comprobamos si hay cancelaciones parciales
            $partialCancellation = $this->bookingUsers()->where('status', 2)->exists();
            $status = $partialCancellation ? 'partial_cancel' : 'active';

            // Verificamos si todas las fechas han pasado
            $now = now();
            $allDatesPassed = $this->bookingUsers()
                ->where(function ($query) use ($now) {
                    $query->where('date', '>', $now->toDateString()) // Fecha futura
                    ->orWhere(function ($subQuery) use ($now) {
                        $subQuery->where('date', '=', $now->toDateString()) // Mismo día
                        ->where('hour_end', '>', $now->format('H:i:s')); // Hora final posterior
                    });
                })
                ->exists();

            if (!$allDatesPassed && !$partialCancellation) {
                $status = 'finished';
            }
        }

        return $status;
    }

    public function getPaymentMethodStatusAttribute()
    {
        // Si la reserva está pagada y el estado es 'activa' o 'terminada'
        if ($this->paid && in_array($this->cancellation_status, ['active', 'finished'])) {
            return 'paid';
        }

        // Si la reserva está pagada y está cancelada o parcialmente cancelada
        if ($this->paid && in_array($this->cancellation_status, ['total_cancel', 'partial_cancel'])) {
            $refundPayment = $this->payments()->where('status', 'refund')->latest()->first();
            $noRefundPayment = $this->payments()->where('status', 'no_refund')->exists();

            if ($noRefundPayment) {
                return 'no_refund';
            }

            if ($refundPayment) {
                return $refundPayment->notes;
            }
        }

        // Si la reserva no está pagada, devolver diferentes estados según el `payment_method_id`
        if (!$this->paid) {
            switch ($this->payment_method_id) {
                case 3:
                    return 'link_send';
                case 5:
                    return 'confirmed_without_payment';
                default:
                    return 'unpaid';
            }
        }
    }

    /**
     * Generate an unique reference for Payrexx - only for bookings that wanna pay this way
     * (i.e. BoukiiPay or Online)
     */
    public function getOrGeneratePayrexxReference()
    {
        if (!$this->payrexx_reference &&
            ($this->payment_method_id == 2 || $this->payment_method_id == 3))
        {
            $ref = 'Boukii #' . $this->id;
            $this->payrexx_reference = (env('APP_ENV') == 'production') ? $ref : 'TEST ' . $ref;
            $this->save();
        }

        return $this->payrexx_reference;
    }

    // Special for field "payrexx_transaction": store encrypted
    public function setPayrexxTransaction($value)
    {
        $this->payrexx_transaction = encrypt( json_encode($value) );
    }

    public function getPayrexxTransaction()
    {
        $decrypted = null;
        if ($this->payrexx_transaction)
        {
            try
            {
                $decrypted = decrypt($this->payrexx_transaction);
            }
                // @codeCoverageIgnoreStart
            catch (\Illuminate\Contracts\Encryption\DecryptException $e)
            {
                $decrypted = null;  // Data seems corrupt or tampered
            }
            // @codeCoverageIgnoreEnd
        }

        return $decrypted ? json_decode($decrypted, true) : [];
    }

    public function parseBookedGroupedCourses()
    {
        $this->loadMissing(['bookingUsers', 'bookingUsers.client', 'bookingUsers.degree', 'bookingUsers.monitor',
            'bookingUsers.courseExtras', 'bookingUsers.courseSubGroup', 'bookingUsers.course',
            'bookingUsers.courseDate']);

        $bookingUsers = $this->bookingUsers;

        $groupedCourses = $bookingUsers->groupBy(['course.course_type', 'client_id',
            'course_id', 'degree_id', 'course_date_id']);

        return $groupedCourses;
    }

    public function parseBookedGroupedWithCourses()
    {
        // Cargar las relaciones necesarias para los bookingUsers
        $this->loadMissing([
            'bookingUsers',
            'bookingUsers.client',
            'bookingUsers.degree',
            'bookingUsers.monitor',
            'bookingUsers.courseExtras',
            'bookingUsers.courseSubGroup',
            'bookingUsers.course',
            'bookingUsers.courseDate'
        ]);

        $bookingUsers = $this->bookingUsers;

        // Preparar un array para almacenar el resultado final
        $result = [];

        // Recorrer los bookingUsers para agrupar según el tipo de curso
        foreach ($bookingUsers as $bookingUser) {
            $course = $bookingUser->course;
            if (!$course) {
                continue;
            }
            $groupKey = null;

            // Si el curso es de tipo 1, agrupar por course_id y client_id
            if ($course->course_type == 1) {
                $groupKey = $course->id . '_' . $bookingUser->client_id;
            }
            // Si el curso es de tipo 2 o 3, agrupar por course_id, date, hour_start, hour_end y monitor_id
            elseif (in_array($course->course_type, [2, 3])) {
                $groupKey = $course->id . '_' . $bookingUser->date .
                    '_' . $bookingUser->hour_start . '_' .  $bookingUser->group_id. '_' .
                    $bookingUser->hour_end . '_' . ($bookingUser->monitor_id ?? 'null');
            }

            // Si la clave del grupo aún no existe en el resultado, crearla
            if (!isset($result[$groupKey])) {
                $result[$groupKey] = [
                    'course' => $course,
                    'booking_users' => []
                ];
            }

            // Agregar el bookingUser al grupo correspondiente
            $result[$groupKey]['booking_users'][] = $bookingUser;
        }

        // Devolver el resultado como un array de agrupaciones
        return array_values($result);
    }

    public static function bookingInfo24h()
    {
        /*
            We send reservation information 24 hours before the course starts
        */
        \Illuminate\Support\Facades\Log::debug('Inicio cron bookingInfo24h');

        $bookings = self::with('clientMain')->where('paid', 1)
            ->get();

        foreach($bookings as $booking)
        {
            $closestBookingUser = null;
            $closestTimeDiff = null;
            $currentDateTime = Carbon::now();

            // the booking can have different courses/classes
            $lines = BookingUser::with('course')->where('booking_id', $booking->id)->get();

            foreach ($lines as $line) {
                // Calcular la fecha y hora de inicio de este booking user
                $bookingDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $line->date . ' ' . $line->hour);

                // Calcular la diferencia en horas entre la fecha actual y la fecha del booking
                $timeDiff = $currentDateTime->diffInHours($bookingDateTime);

                // Comprobar si este booking user es el más cercano hasta ahora
                if ($closestBookingUser === null || $timeDiff < $closestTimeDiff) {
                    $closestBookingUser = $line;
                    $closestTimeDiff = $timeDiff;
                }
            }

            // Verificar si el booking user más cercano cumple con la condición de las 24 horas
            if ($closestBookingUser !== null && $closestTimeDiff < 24) {
                $bookingType = $closestBookingUser->course->type == 2 ? 'Privado' : 'Colectivo';
                \Illuminate\Support\Facades\Log::debug('bookingInfo24h: '
                    . $bookingType . ' ID ' . $booking->id
                    . ' - Enviamos info de la reserva: '
                    . $closestBookingUser->id . " : Fecha de inicio "
                    . $closestBookingUser->date . ' '
                    . $closestBookingUser->hour . ' - Dif in hours: '
                    . $closestTimeDiff);


                // Envía el email aquí
                $mySchool = School::find($closestBookingUser->booking->school_id);
                dispatch(function () use ($mySchool, $closestBookingUser) {
                    // N.B. try-catch because some test users enter unexistant emails, throwing Swift_TransportException
                    try {
                        \Mail::to($closestBookingUser->booking->clientMain->email)
                            ->send(new BookingInfoMailer(
                                $mySchool,
                                $closestBookingUser->booking,
                                $closestBookingUser->booking->clientMain->email
                            ));
                    } catch (\Exception $ex) {
                        \Illuminate\Support\Facades\Log::debug('Cron bookingInfo24h: ', $ex->getTrace());
                    }
                })->afterResponse();
            }
        }

        \Illuminate\Support\Facades\Log::debug('Fin cron bookingInfo24h');
    }

    public static function sendPaymentNotice()
    {
        /*
            We send reservation information 24 hours before the course starts
        */
        \Illuminate\Support\Facades\Log::debug('Inicio cron sendPaymentNotice');

        $bookings = self::with('clientMain')->where('payment_method_id', 3)
            ->where('paid', 0)
            ->get();

        foreach($bookings as $booking)
        {
            $closestBookingUser = null;
            $closestTimeDiff = null;
            $currentDateTime = Carbon::now();

            // the booking can have different courses/classes
            $lines = BookingUser::with('course')->where('booking_id', $booking->id)->get();

            foreach ($lines as $line) {
                // Calcular la fecha y hora de inicio de este booking user
                $bookingDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $line->date . ' ' . $line->hour);

                // Calcular la diferencia en horas entre la fecha actual y la fecha del booking
                $timeDiff = $currentDateTime->diffInHours($bookingDateTime);

                // Comprobar si este booking user es el más cercano hasta ahora
                if ($closestBookingUser === null || $timeDiff < $closestTimeDiff) {
                    $closestBookingUser = $line;
                    $closestTimeDiff = $timeDiff;
                }
            }

            // Verificar si el booking user más cercano cumple con la condición de las 24 horas
            if ($closestBookingUser !== null && $closestTimeDiff > 48 && $closestTimeDiff < 72
                && BookingPaymentNoticeLog::checkToNotify($closestBookingUser)) {

                \Illuminate\Support\Facades\Log::debug('sendPaymentNotice: ID '.
                    $booking->id.' - Enviamos aviso para '. $closestBookingUser->id." : Fecha de inicio ".$currentDateTime);



                // Envía el email aquí
                $mySchool = School::find($closestBookingUser->booking->school_id);
                dispatch(function () use ($mySchool, $closestBookingUser) {
                    // N.B. try-catch because some test users enter unexistant emails, throwing Swift_TransportException
                    try {
                        \Mail::to($closestBookingUser->booking->clientMain->email)
                            ->send(new BookingInfoMailer(
                                $mySchool,
                                $closestBookingUser->booking,
                                $closestBookingUser->booking->clientMain->email
                            ));

                        BookingPaymentNoticeLog::create([
                            'booking_id' => $closestBookingUser->booking->id,
                            'booking_user_id' => $closestBookingUser->id,
                            'date' => date('Y-m-d H:i:s')
                        ]);
                    } catch (\Exception $ex) {
                        \Illuminate\Support\Facades\Log::debug('Cron bookingInfo15min: ', $ex->getTrace());
                    }
                })->afterResponse();
            }
        }

        \Illuminate\Support\Facades\Log::debug('Fin cron sendPaymentNotice');
    }

    public static function cancelUnpaids15m()
    {
        /*
            We send reservation information 24 hours before the course starts
        */
        \Illuminate\Support\Facades\Log::debug('Inicio cron cancelUnpaids15m');

        $bookings = self::with('clientMain')->where('status', 3)
            ->get();

        foreach($bookings as $booking)
        {
            {
                $fecha_actual = Carbon::createFromFormat('Y-m-d H:i:s', date("Y-m-d H:i:s"));
                $fecha_creacion = Carbon::createFromFormat('Y-m-d H:i:s', $booking->created_at);

                if( $fecha_actual < $fecha_creacion->addMinutes(15) ) {
                    continue;
                }

                $tipo = 'completa';
                self::cancelBookingFull($booking->id);

                \Illuminate\Support\Facades\Log::debug('cancelUnpaids15m: ID '.$booking->id.' - Eliminamos reserva '.$tipo.'. '." Fecha de creación ".$fecha_creacion.' - Diferencia de tiempo: '.$fecha_actual->diffInMinutes($fecha_creacion));
            }

        }

        \Illuminate\Support\Facades\Log::debug('Fin cron cancelUnpaids15m');
    }

    public static function cancelBookingFull($bookingID)
    {
        $bookingData = self::with('clientMain')->where('id', '=', intval($bookingID))->first();
        if(isset($bookingData->id)) {
            $cancelledLines = $bookingData->parseBookedGroupedCourses();
            BookingUser::where('booking_id', $bookingData->id)->update(['status' => 2]);

            //TODO: que pasa si hacen una reserva con cancellation insurance y no la pagan.
            /*    if ($bookingData->has_cancellation_insurance)
                {
                    $bookingData->price_total = $bookingData->price_cancellation_insurance;
                    $bookingData->save();
                }*/

            $bookingData->status = 3;

            $mySchool = School::find($bookingData->school_id);
            $voucherData = array();

            dispatch(function () use ($mySchool, $bookingData, $cancelledLines, $voucherData) {
                $buyerUser = $bookingData->clientMain;

                // N.B. try-catch because some test users enter unexistant emails, throwing Swift_TransportException
                try
                {
                    \Mail::to($bookingData->email)
                        ->send(new BookingCancelMailer(
                            $mySchool,
                            $bookingData,
                            $cancelledLines,
                            $buyerUser,
                            $voucherData
                        ));
                }
                catch (\Exception $ex)
                {
                    \Illuminate\Support\Facades\Log::debug('BookingController->cancelBookingFull BookingCancelMailer: ' . $ex->getMessage());
                }
            })->afterResponse();
        }
    }
    // ... código existente ...

    /**
     * Servicio de cálculo de precios
     */
    protected $priceCalculator;

    public function getPriceCalculator()
    {
        if (!$this->priceCalculator) {
            $this->priceCalculator = app(BookingPriceCalculatorService::class);
        }
        return $this->priceCalculator;
    }

    /**
     * Calcula el precio total actual de la reserva
     */
    public function calculateCurrentTotal($options = [])
    {
        return $this->getPriceCalculator()->calculateBookingTotal($this, $options);
    }

    /**
     * Recalcula y actualiza el precio total
     */
    public function recalculateAndUpdatePrice($options = [])
    {
        $calculation = $this->calculateCurrentTotal($options);
        $newTotal = $calculation['total_final'];

        $this->update(['price_total' => $newTotal]);

        return [
            'old_total' => $this->getOriginal('price_total'),
            'new_total' => $newTotal,
            'calculation' => $calculation
        ];
    }

    /**
     * Verifica la consistencia del precio almacenado
     */
    public function checkPriceConsistency($tolerance = 0.01)
    {
        $calculation = $this->calculateCurrentTotal();
        $calculatedPrice = $calculation['total_final'];
        $storedPrice = $this->price_total;

        // ✅ NUEVA LÓGICA: Si price_total ya incluye descuentos de vouchers
        if ($this->priceIncludesVoucherDiscounts()) {
            // Comparar con el precio neto (calculado - vouchers)
            $voucherAnalysis = $this->getPriceCalculator()->analyzeVouchersForBalance($this);
            $expectedStoredPrice = $calculatedPrice - $voucherAnalysis['total_used'];

            $discrepancy = abs($expectedStoredPrice - $storedPrice);

            return [
                'is_consistent' => $discrepancy <= $tolerance,
                'stored_price' => $storedPrice,
                'calculated_price' => $calculatedPrice,
                'expected_stored_price' => $expectedStoredPrice,
                'vouchers_in_stored_price' => true,
                'voucher_discount' => $voucherAnalysis['total_used'],
                'discrepancy' => $discrepancy,
                'explanation' => 'Price_total ya incluye descuento de vouchers',
                'calculation_details' => $calculation
            ];
        }

        // Lógica original para cuando price_total NO incluye vouchers
        $discrepancy = abs($calculatedPrice - $storedPrice);

        return [
            'is_consistent' => $discrepancy <= $tolerance,
            'stored_price' => $storedPrice,
            'calculated_price' => $calculatedPrice,
            'vouchers_in_stored_price' => false,
            'discrepancy' => $discrepancy,
            'calculation_details' => $calculation
        ];
    }

    /**
     * Obtiene el precio de actividades sin conceptos adicionales
     */
    public function getActivitiesPrice($excludeCourses = [])
    {
        $activeBookingUsers = $this->bookingUsers
            ->where('status', '!=', 2)
            ->filter(function ($bookingUser) use ($excludeCourses) {
                return !in_array((int)$bookingUser->course_id, $excludeCourses);
            });

        return $this->getPriceCalculator()->calculateActivitiesPrice($activeBookingUsers);
    }

    /**
     * Calcula el balance actual de pagos
     */
    public function getCurrentBalance()
    {
        $totalPaid = $this->payments->whereIn('status', ['paid'])->sum('amount');
        $totalRefunded = $this->payments->whereIn('status', ['refund', 'partial_refund'])->sum('amount');
        $totalNoRefund = $this->payments->whereIn('status', ['no_refund'])->sum('amount');

        // ✅ CORREGIDO: Analizar vouchers correctamente
        $voucherAnalysis = $this->getPriceCalculator()->analyzeVouchersForBalance($this);
        $totalVouchersUsed = $voucherAnalysis['total_used'];
        $totalVouchersRefunded = $voucherAnalysis['total_refunded'];

        return [
            'total_paid' => $totalPaid,
            'total_refunded' => $totalRefunded,
            'total_vouchers_used' => $totalVouchersUsed,
            'total_vouchers_refunded' => $totalVouchersRefunded,
            'total_no_refund' => $totalNoRefund,
            'current_balance' => $totalPaid + $totalVouchersUsed - $totalRefunded - $totalVouchersRefunded - $totalNoRefund,
            'received' => $totalPaid + $totalVouchersUsed,
            'processed' => $totalRefunded + $totalVouchersRefunded + $totalNoRefund
        ];
    }

    /**
     * CORREGIDO: Calcula el importe pendiente considerando vouchers
     */
    public function getPendingAmount()
    {
        $calculation = $this->calculateCurrentTotal();
        $expectedTotal = $calculation['total_final'];
        $balance = $this->getCurrentBalance();

        return max(0, $expectedTotal - $balance['current_balance']);
    }

    /**
     * NUEVO: Método para detectar si price_total ya incluye descuentos de vouchers
     */
    public function priceIncludesVoucherDiscounts(): bool
    {
        $vouchersLogs = $this->vouchersLogs;
        if ($vouchersLogs->isEmpty()) {
            return false;
        }

        // Calcular vouchers aplicados como descuento
        $totalVoucherDiscount = 0;
        foreach ($vouchersLogs as $log) {
            $analysis = $this->getPriceCalculator()->determineVoucherLogType($log, $log->voucher, $this);
            if ($analysis['type'] === 'payment') {
                $totalVoucherDiscount += $analysis['amount'];
            }
        }

        // Si price_total + voucher_discount está cerca del calculated_total,
        // significa que price_total ya tiene vouchers descontados
        $calculatedTotal = $this->calculateCurrentTotal(['exclude_voucher_discounts' => true])['total_final'];
        $priceWithVoucherDiscount = $this->price_total + $totalVoucherDiscount;

        return abs($priceWithVoucherDiscount - $calculatedTotal) < 1.0;
    }

    /**
     * Verifica si la reserva está completamente pagada
     */
    public function isFullyPaid($tolerance = 0.01)
    {
        return $this->getPendingAmount() <= $tolerance;
    }

    /**
     * Recalcula vouchers según el precio actual
     */
    public function recalculateVouchers()
    {
        $calculation = $this->calculateCurrentTotal();
        $totalPrice = $calculation['total_final'];

        return $this->getPriceCalculator()->recalculateVouchers($this, $totalPrice);
    }

    /**
     * NUEVO: Verificar consistencia basada en realidad financiera
     */
    public function checkFinancialReality($tolerance = 0.50)
    {
        return $this->getPriceCalculator()->analyzeFinancialReality($this, [
            'exclude_courses' => [260, 243] // Cursos excluidos estándar
        ]);
    }

    /**
     * ACTUALIZADO: Resumen financiero basado en realidad
     */
    public function getFinancialSummary()
    {
        $realityAnalysis = $this->checkFinancialReality();

        return [
            'booking_id' => $this->id,
            'status' => $this->status,
            'stored_price_total' => $this->price_total, // Solo informativo
            'calculated_price' => $realityAnalysis['calculated_total'],
            'financial_reality' => $realityAnalysis['financial_reality'],
            'reality_check' => $realityAnalysis['reality_check'],
            'is_consistent' => $realityAnalysis['reality_check']['is_consistent'],
            'main_discrepancy' => $realityAnalysis['reality_check']['main_discrepancy'] ?? 0,
            'recommendation' => $realityAnalysis['recommendation'],
            'pending_amount' => max(0, $realityAnalysis['calculated_total'] - $realityAnalysis['financial_reality']['net_balance']),
            'is_fully_paid' => $realityAnalysis['reality_check']['is_consistent'] && $this->status == 1,
            'client' => [
                'name' => $this->clientMain->first_name . ' ' . $this->clientMain->last_name,
                'email' => $this->clientMain->email
            ],
            'analysis_method' => 'financial_reality',
            'created_at' => $this->created_at,
            'currency' => $this->currency
        ];
    }

    /**
     * Aplica descuentos automáticos según reglas de negocio
     */
    public function applyAutomaticDiscounts()
    {
        // Ejemplo: descuento por múltiples actividades
        $activeUsers = $this->bookingUsers->where('status', '!=', 2);
        $uniqueCourses = $activeUsers->pluck('course_id')->unique()->count();

        if ($uniqueCourses >= 3 && !$this->has_reduction) {
            $activitiesPrice = $this->getActivitiesPrice();
            $discountPercentage = 0.10; // 10% de descuento
            $discountAmount = $activitiesPrice * $discountPercentage;

            $this->update([
                'has_reduction' => true,
                'price_reduction' => $discountAmount
            ]);

            return [
                'applied' => true,
                'type' => 'multiple_courses_discount',
                'discount_amount' => $discountAmount,
                'courses_count' => $uniqueCourses
            ];
        }

        return ['applied' => false];
    }

    /**
     * Valida que todos los precios estén correctamente calculados
     */
    public function validateAllPrices()
    {
        $errors = [];
        $warnings = [];

        // Validar precio total
        $consistency = $this->checkPriceConsistency();
        if (!$consistency['is_consistent']) {
            $errors[] =
                "Precio total inconsistente: almacenado {$consistency['stored_price']}, calculado {$consistency['calculated_price']}";
        }

        // Validar vouchers
        $totalVoucherAmount = abs($this->vouchersLogs->sum('amount'));
        if ($totalVoucherAmount > $this->price_total) {
            $errors[] = "El total de vouchers ({$totalVoucherAmount}) excede el precio total ({$this->price_total})";
        }

        // Validar seguro de cancelación
        if ($this->has_cancellation_insurance && $this->price_cancellation_insurance <= 0) {
            $warnings[] = "Seguro de cancelación activado pero sin precio";
        }

        // Validar estado vs pagos
        if ($this->paid && $this->getPendingAmount() > 0.01) {
            $errors[] = "Marcada como pagada pero tiene importe pendiente";
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'validated_at' => now()
        ];
    }

    /**
     * Scope para reservas con discrepancias de precio
     */
    public function scopeWithPriceDiscrepancies($query, $tolerance = 0.01)
    {
        return $query->get()->filter(function ($booking) use ($tolerance) {
            $consistency = $booking->checkPriceConsistency($tolerance);
            return !$consistency['is_consistent'];
        });
    }

    /**
     * Scope para reservas con problemas de balance
     */
    public function scopeWithBalanceIssues($query)
    {
        return $query->get()->filter(function ($booking) {
            if ($booking->status == 1) {
                // Reservas activas que deberían estar pagadas
                return $booking->paid && $booking->getPendingAmount() > 0.01;
            } elseif ($booking->status == 2) {
                // Reservas canceladas con dinero sin procesar
                $balance = $booking->getCurrentBalance();
                return $balance['received'] > 0 && $balance['current_balance'] > 0.01;
            }
            return false;
        });
    }

    /**
     * Método para exportar datos de cálculo para debugging
     */
    public function exportCalculationData()
    {
        return [
            'booking_id' => $this->id,
            'status' => $this->status,
            'stored_data' => [
                'price_total' => $this->price_total,
                'has_cancellation_insurance' => $this->has_cancellation_insurance,
                'price_cancellation_insurance' => $this->price_cancellation_insurance,
                'has_reduction' => $this->has_reduction,
                'price_reduction' => $this->price_reduction,
                // BOUKII CARE DESACTIVADO -                 'has_boukii_care' => $this->has_boukii_care,
                // BOUKII CARE DESACTIVADO -                 'price_boukii_care' => $this->price_boukii_care,
                'has_tva' => $this->has_tva,
                'price_tva' => $this->price_tva
            ],
            'calculated_data' => $this->calculateCurrentTotal(),
            'balance_data' => $this->getCurrentBalance(),
            'booking_users' => $this->bookingUsers->map(function ($bu) {
                return [
                    'id' => $bu->id,
                    'course_id' => $bu->course_id,
                    'course_name' => $bu->course->name ?? 'N/A',
                    'course_type' => $bu->course->course_type ?? 'N/A',
                    'client_id' => $bu->client_id,
                    'status' => $bu->status,
                    'date' => $bu->date,
                    'price' => $bu->price,
                    'extras_count' => $bu->bookingUserExtras->count()
                ];
            }),
            'vouchers' => $this->vouchersLogs->map(function ($vl) {
                return [
                    'id' => $vl->id,
                    'voucher_id' => $vl->voucher_id,
                    'amount' => $vl->amount,
                    'voucher_code' => $vl->voucher->code ?? 'N/A'
                ];
            }),
            'payments' => $this->payments->map(function ($p) {
                return [
                    'id' => $p->id,
                    'amount' => $p->amount,
                    'status' => $p->status,
                    'notes' => $p->notes,
                    'created_at' => $p->created_at
                ];
            })
        ];
    }

    public function getProportionalPaymentMethods($groupPrice, $totalPrice)
    {
        $result = [
            'cash' => 0,
            'card' => 0,
            'online' => 0,
            'transfer' => 0,
            'other' => 0
        ];

        $validPayments = $this->payments->where('status', 'paid');
        $factor = $groupPrice / ($totalPrice ?: 1);

        foreach ($validPayments as $payment) {
            $method = $this->resolvePaymentMethod($payment);
            if (!isset($result[$method])) $method = 'other';

            $result[$method] += $payment->amount * $factor;
        }

        return array_map(fn($v) => round($v, 2), $result);
    }

    // Constant PaymentMethod IDs as of 2022-1021:
    /** PaymentMethod for 'Cash' */
    const ID_CASH = 1;
    /** PaymentMethod for 'BoukiiPay' (i.e. credit card now) */
    const ID_BOUKIIPAY = 2;
    /** PaymentMethod for 'Online' (i.e. credit card via email) */
    const ID_ONLINE = 3;
    /** PaymentMethod for 'Other' */
    const ID_OTHER = 4;
    /** PaymentMethod for 'No payment' */
    const ID_NOPAYMENT = 5;

}
