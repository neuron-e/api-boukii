<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @OA\Schema(
 *      schema="GiftVoucher",
 *      required={"amount","recipient_email","school_id"},
 *      @OA\Property(
 *          property="amount",
 *          description="Monto del bono regalo",
 *          type="number",
 *          format="number"
 *      ),
 *      @OA\Property(
 *          property="personal_message",
 *          description="Mensaje personalizado",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="sender_name",
 *          description="Nombre que aparece como remitente del bono",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="buyer_name",
 *          description="Nombre del comprador del gift voucher cuando no est\u00e1 vinculado a un cliente",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="buyer_email",
 *          description="Email de contacto del comprador del gift voucher",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="buyer_phone",
 *          description="Tel\u00e9fono de contacto del comprador del gift voucher",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="buyer_locale",
 *          description="Idioma preferido del comprador para comunicaciones",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="recipient_email",
 *          description="Email del destinatario",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="recipient_name",
 *          description="Nombre del destinatario del bono",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="recipient_phone",
 *          description="Tel\u00e9fono del destinatario del bono",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="recipient_locale",
 *          description="Idioma preferido del destinatario para comunicaciones",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="template",
 *          description="Template del diseño",
 *          type="string"
 *      )
 * )
 */
class GiftVoucher extends Model
{
    use LogsActivity, SoftDeletes, HasFactory;

    public $table = 'gift_vouchers';

    public $fillable = [
        'code',
        'voucher_id',
        'amount',
        'balance',
        'currency',
        'personal_message',
        'sender_name',
        'buyer_name',
        'buyer_email',
        'buyer_phone',
        'buyer_locale',
        'template',
        'background_color',
        'text_color',
        'recipient_email',
        'recipient_name',
        'recipient_phone',
        'recipient_locale',
        'delivery_date',
        'expires_at',
        'is_delivered',
        'delivered_at',
        'is_redeemed',
        'redeemed_at',
        'redeemed_by_client_id',
        'purchased_by_client_id',
        'school_id',
        'is_paid',
        'payment_reference',
        'payrexx_transaction_id',
        'status',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'personal_message' => 'string',
        'sender_name' => 'string',
        'buyer_name' => 'string',
        'buyer_email' => 'string',
        'buyer_phone' => 'string',
        'buyer_locale' => 'string',
        'template' => 'string',
        'background_color' => 'string',
        'text_color' => 'string',
        'recipient_email' => 'string',
        'recipient_name' => 'string',
        'recipient_phone' => 'string',
        'recipient_locale' => 'string',
        'currency' => 'string',
        'delivery_date' => 'datetime',
        'expires_at' => 'date',
        'is_delivered' => 'boolean',
        'delivered_at' => 'datetime',
        'is_redeemed' => 'boolean',
        'redeemed_at' => 'datetime',
        'is_paid' => 'boolean',
    ];

    public static array $rules = [
        'amount' => 'required|numeric|min:1',
        'personal_message' => 'nullable|string',
        'sender_name' => 'nullable|string|max:255',
        'buyer_name' => 'nullable|string|max:255',
        'buyer_email' => 'nullable|email|max:255',
        'buyer_phone' => 'nullable|string|max:50',
        'buyer_locale' => 'nullable|string|max:10',
        'template' => 'nullable|string|max:50',
        'background_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        'text_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        'recipient_email' => 'required|email|max:255',
        'recipient_name' => 'nullable|string|max:255',
        'recipient_phone' => 'nullable|string|max:50',
        'recipient_locale' => 'nullable|string|max:10',
        'delivery_date' => 'nullable|date',
        'school_id' => 'required|integer',
    ];

    /**
     * Relación con el voucher asociado
     */
    public function voucher(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Voucher::class, 'voucher_id');
    }

    /**
     * Relación con la escuela
     */
    public function school(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\School::class, 'school_id');
    }

    /**
     * Relación con el cliente que compró el bono
     */
    public function purchasedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Client::class, 'purchased_by_client_id');
    }

    /**
     * Relación con el cliente que canjeó el bono
     */
    public function redeemedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Client::class, 'redeemed_by_client_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    /**
     * Verifica si el bono regalo está pendiente de envío
     */
    public function isPendingDelivery(): bool
    {
        if ($this->is_delivered) {
            return false;
        }

        // Si no tiene fecha de entrega programada, está pendiente
        if (!$this->delivery_date) {
            return true;
        }

        // Si la fecha de entrega ya pasó y no se envió, está pendiente
        return now() >= $this->delivery_date;
    }

    /**
     * Verifica si el bono regalo puede ser canjeado
     */
    public function canBeRedeemed(): bool
    {
        return $this->is_paid
            && $this->is_delivered
            && !$this->is_redeemed
            && !$this->trashed();
    }

    /**
     * Canjear el bono regalo creando un voucher para el cliente
     */
    public function redeem(int $clientId): bool
    {
        if (!$this->canBeRedeemed()) {
            return false;
        }

        // Si ya tiene un voucher asociado, actualizar el cliente
        if ($this->voucher_id) {
            $voucher = Voucher::find($this->voucher_id);
            if ($voucher) {
                $voucher->client_id = $clientId;
                $voucher->save();
            }
        } else {
            // Crear nuevo voucher
            $voucher = Voucher::create([
                'code' => Voucher::generateUniqueCode('GIFT'),
                'name' => 'Gift Voucher',
                'quantity' => $this->amount,
                'remaining_balance' => $this->amount,
                'payed' => true,
                'is_gift' => true,
                'client_id' => $clientId,
                'school_id' => $this->school_id,
            ]);

            $this->voucher_id = $voucher->id;
        }

        $this->is_redeemed = true;
        $this->redeemed_at = now();
        $this->redeemed_by_client_id = $clientId;

        return $this->save();
    }

    /**
     * Marcar como entregado
     */
    public function markAsDelivered(): bool
    {
        $this->is_delivered = true;
        $this->delivered_at = now();
        return $this->save();
    }

    /**
     * Generar código único de bono regalo
     */
    public static function generateUniqueCode(string $prefix = 'GV'): string
    {
        do {
            // Formato: GV-XXXX-XXXX (más legible)
            $part1 = strtoupper(substr(md5(uniqid(rand(), true)), 0, 4));
            $part2 = strtoupper(substr(md5(uniqid(rand(), true)), 0, 4));
            $code = "{$prefix}-{$part1}-{$part2}";
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /**
     * Verificar si el gift voucher es válido para uso
     */
    public function isValid(): bool
    {
        return $this->status === 'active'
            && $this->balance > 0
            && (!$this->expires_at || $this->expires_at->isFuture())
            && !$this->trashed();
    }

    /**
     * Activar el gift voucher después del pago
     */
    public function activate(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        $this->status = 'active';
        $this->balance = $this->amount;
        $this->is_paid = true;

        // Establecer fecha de expiración (1 año por defecto)
        if (!$this->expires_at) {
            $this->expires_at = now()->addYear();
        }

        return $this->save();
    }

    /**
     * Obtener templates disponibles
     */
    public static function getAvailableTemplates(): array
    {
        return [
            'default' => 'Default',
            'christmas' => 'Christmas',
            'birthday' => 'Birthday',
            'anniversary' => 'Anniversary',
            'thank_you' => 'Thank You',
            'congratulations' => 'Congratulations',
            'valentine' => "Valentine's Day",
            'easter' => 'Easter',
            'summer' => 'Summer',
            'winter' => 'Winter',
        ];
    }

    /**
     * Obtener resumen del bono regalo
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'sender_name' => $this->sender_name,
            'buyer_name' => $this->buyer_name,
            'buyer_email' => $this->buyer_email,
            'buyer_phone' => $this->buyer_phone,
            'buyer_locale' => $this->buyer_locale,
            'recipient_email' => $this->recipient_email,
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'recipient_locale' => $this->recipient_locale,
            'template' => $this->template,
            'delivery_date' => $this->delivery_date?->format('Y-m-d H:i:s'),
            'is_delivered' => $this->is_delivered,
            'delivered_at' => $this->delivered_at?->format('Y-m-d H:i:s'),
            'is_redeemed' => $this->is_redeemed,
            'redeemed_at' => $this->redeemed_at?->format('Y-m-d H:i:s'),
            'can_be_redeemed' => $this->canBeRedeemed(),
            'is_pending_delivery' => $this->isPendingDelivery(),
            'is_paid' => $this->is_paid,
            'voucher_id' => $this->voucher_id,
        ];
    }
}
