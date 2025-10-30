<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
 use Illuminate\Database\Eloquent\SoftDeletes; use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @OA\Schema(
 *      schema="Voucher",
 *      required={"code","quantity","remaining_balance","payed","school_id"},
 *      @OA\Property(
 *          property="code",
 *          description="The voucher code",
 *          readOnly=false,
 *          nullable=false,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="quantity",
 *          description="The quantity of the voucher",
 *          readOnly=false,
 *          nullable=false,
 *          type="number",
 *          format="number"
 *      ),
 *      @OA\Property(
 *          property="remaining_balance",
 *          description="The remaining balance of the voucher",
 *          readOnly=false,
 *          nullable=false,
 *          type="number",
 *          format="number"
 *      ),
 *      @OA\Property(
 *          property="payed",
 *          description="Indicates whether the voucher has been paid or not",
 *          readOnly=false,
 *          nullable=false,
 *          type="boolean",
 *      ),
 *     @OA\Property(
 *          property="is_gift",
 *          description="Indicates whether the voucher has been gift or not",
 *          readOnly=false,
 *          nullable=false,
 *          type="boolean",
 *      ),
 *      @OA\Property(
 *            property="school_id",
 *            description="ID of the school associated with the voucher",
 *            type="integer",
 *            nullable=true
 *        ),
 *       @OA\Property(
 *            property="client_id",
 *            description="ID of the client associated with the voucher",
 *            type="integer",
 *            nullable=true
 *        ),
 *      @OA\Property(
 *          property="buyer_name",
 *          description="Display name of the voucher purchaser when no client is linked",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="buyer_email",
 *          description="Contact email of the voucher purchaser when no client is linked",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="buyer_phone",
 *          description="Contact phone of the voucher purchaser when no client is linked",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="recipient_name",
 *          description="Name of the voucher recipient when different from purchaser",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="recipient_email",
 *          description="Email of the voucher recipient when different from purchaser",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="recipient_phone",
 *          description="Phone of the voucher recipient when different from purchaser",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="payrexx_reference",
 *          description="The reference related to payment through Payrexx",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="payrexx_transaction",
 *          description="The transaction related to payment through Payrexx",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="created_at",
 *          description="The timestamp when the voucher was created",
 *          readOnly=true,
 *          nullable=true,
 *          type="string",
 *          format="date-time"
 *      ),
 *      @OA\Property(
 *          property="updated_at",
 *          description="The timestamp when the voucher was last updated",
 *          readOnly=true,
 *          nullable=true,
 *          type="string",
 *          format="date-time"
 *      ),
 *      @OA\Property(
 *          property="deleted_at",
 *          description="The timestamp when the voucher was deleted",
 *          readOnly=true,
 *          nullable=true,
 *          type="string",
 *          format="date-time"
 *      )
 * )
 */
class Voucher extends Model
{
    use LogsActivity, SoftDeletes, HasFactory;
    public $table = 'vouchers';

    public $fillable = [
        'code',
        'name',
        'description',
        'quantity',
        'remaining_balance',
        'payed',
        'is_gift',
        'is_transferable',
        'client_id',
        'buyer_name',
        'buyer_email',
        'buyer_phone',
        'recipient_name',
        'recipient_email',
        'recipient_phone',
        'school_id',
        'course_type_id',
        'expires_at',
        'max_uses',
        'uses_count',
        'transferred_to_client_id',
        'transferred_at',
        'payrexx_reference',
        'payrexx_transaction',
        'created_by',
        'notes',
        'old_id'
    ];

    protected $casts = [
        'code' => 'string',
        'name' => 'string',
        'description' => 'string',
        'quantity' => 'float',
        'remaining_balance' => 'float',
        'payed' => 'boolean',
        'is_gift' => 'boolean',
        'is_transferable' => 'boolean',
        'buyer_name' => 'string',
        'buyer_email' => 'string',
        'buyer_phone' => 'string',
        'recipient_name' => 'string',
        'recipient_email' => 'string',
        'recipient_phone' => 'string',
        'expires_at' => 'datetime',
        'max_uses' => 'integer',
        'uses_count' => 'integer',
        'transferred_at' => 'datetime',
        'payrexx_reference' => 'string',
        'payrexx_transaction' => 'string',
    ];

    public static array $rules = [
        'code' => 'string|max:255',
        'quantity' => 'numeric',
        'remaining_balance' => 'numeric',
        'payed' => 'boolean',
        'is_gift' => 'boolean',
        'client_id' => 'nullable|numeric',
        'buyer_name' => 'nullable|string|max:255',
        'buyer_email' => 'nullable|email|max:255',
        'buyer_phone' => 'nullable|string|max:50',
        'recipient_name' => 'nullable|string|max:255',
        'recipient_email' => 'nullable|email|max:255',
        'recipient_phone' => 'nullable|string|max:50',
        'school_id' => 'numeric',
        'payrexx_reference' => 'nullable|string|max:65535',
        'payrexx_transaction' => 'nullable|string|max:65535',
        'created_at' => 'nullable',
        'updated_at' => 'nullable',
        'deleted_at' => 'nullable'
    ];

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Client::class, 'client_id');
    }

    public function school(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\School::class, 'school_id');
    }

    public function vouchersLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\VouchersLog::class, 'voucher_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
         return LogOptions::defaults();
    }

    public function transferredToClient(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Client::class, 'transferred_to_client_id');
    }

    /**
     * Verifica si el bono es genérico (sin cliente asignado)
     */
    public function isGeneric(): bool
    {
        return $this->client_id === null;
    }

    /**
     * Verifica si el bono ha expirado
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return now() > $this->expires_at;
    }

    /**
     * Verifica si el bono tiene saldo disponible
     */
    public function hasBalance(): bool
    {
        return $this->remaining_balance > 0;
    }

    /**
     * Verifica si el bono ha alcanzado el máximo de usos
     */
    public function hasReachedMaxUses(): bool
    {
        if ($this->max_uses === null) {
            return false;
        }

        return $this->uses_count >= $this->max_uses;
    }

    /**
     * Verifica si el bono puede ser usado
     */
    public function canBeUsed(): bool
    {
        return !$this->isExpired()
            && $this->hasBalance()
            && !$this->hasReachedMaxUses()
            && !$this->trashed();
    }

    /**
     * Verifica si el bono puede ser usado por un cliente específico
     */
    public function canBeUsedByClient(?int $clientId): bool
    {
        if (!$this->canBeUsed()) {
            return false;
        }

        // Si el bono es genérico, cualquier cliente puede usarlo
        if ($this->isGeneric()) {
            return true;
        }

        // Si tiene cliente asignado, solo ese cliente puede usarlo
        if ($this->client_id && $this->client_id !== $clientId) {
            return false;
        }

        // Si fue transferido, solo el destinatario puede usarlo
        if ($this->transferred_to_client_id && $this->transferred_to_client_id !== $clientId) {
            return false;
        }

        return true;
    }

    /**
     * Verifica si el bono es válido para un tipo de curso
     */
    public function isValidForCourseType(?int $courseTypeId): bool
    {
        // Si no tiene restricción de tipo, es válido para cualquier tipo
        if ($this->course_type_id === null) {
            return true;
        }

        return $this->course_type_id === $courseTypeId;
    }

    /**
     * Transferir bono a otro cliente
     */
    public function transferTo(int $clientId): bool
    {
        if (!$this->is_transferable) {
            return false;
        }

        if (!$this->canBeUsed()) {
            return false;
        }

        $this->transferred_to_client_id = $clientId;
        $this->transferred_at = now();

        return $this->save();
    }

    /**
     * Usar una cantidad del bono
     */
    public function use(float $amount): bool
    {
        if (!$this->canBeUsed()) {
            return false;
        }

        if ($amount <= 0) {
            return false;
        }

        if ($amount > $this->remaining_balance) {
            return false;
        }

        $this->remaining_balance = round($this->remaining_balance - $amount, 2);
        if ($this->remaining_balance < 0) {
            $this->remaining_balance = 0;
        }

        $this->uses_count++;
        $this->payed = $this->remaining_balance <= 0;

        return $this->save();
    }

    /**
     * Revertir el uso de una cantidad del bono (para cancelaciones)
     */
    public function refund(float $amount): bool
    {
        $this->remaining_balance = round($this->remaining_balance + $amount, 2);
        if ($this->remaining_balance > $this->quantity) {
            $this->remaining_balance = $this->quantity;
        }

        $this->uses_count = max(0, $this->uses_count - 1);
        $this->payed = $this->remaining_balance <= 0;

        return $this->save();
    }

    /**
     * Obtener el cliente efectivo que puede usar el bono
     */
    public function getEffectiveClientId(): ?int
    {
        // Si fue transferido, el cliente efectivo es el destinatario
        if ($this->transferred_to_client_id) {
            return $this->transferred_to_client_id;
        }

        // Si no, es el cliente original
        return $this->client_id;
    }

    /**
     * Generar código único de bono
     */
    public static function generateUniqueCode(string $prefix = 'VOUCHER'): string
    {
        do {
            $code = strtoupper($prefix . '-' . substr(md5(uniqid(rand(), true)), 0, 8));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /**
     * Obtener resumen del estado del bono
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'is_generic' => $this->isGeneric(),
            'client_id' => $this->client_id,
            'transferred_to_client_id' => $this->transferred_to_client_id,
            'effective_client_id' => $this->getEffectiveClientId(),
            'buyer_name' => $this->buyer_name,
            'buyer_email' => $this->buyer_email,
            'buyer_phone' => $this->buyer_phone,
            'recipient_name' => $this->recipient_name,
            'recipient_email' => $this->recipient_email,
            'recipient_phone' => $this->recipient_phone,
            'quantity' => $this->quantity,
            'remaining_balance' => $this->remaining_balance,
            'used_amount' => $this->quantity - $this->remaining_balance,
            'usage_percentage' => $this->quantity > 0
                ? round((($this->quantity - $this->remaining_balance) / $this->quantity) * 100, 2)
                : 0,
            'expires_at' => $this->expires_at?->format('Y-m-d H:i:s'),
            'is_expired' => $this->isExpired(),
            'max_uses' => $this->max_uses,
            'uses_count' => $this->uses_count,
            'has_reached_max_uses' => $this->hasReachedMaxUses(),
            'can_be_used' => $this->canBeUsed(),
            'is_transferable' => $this->is_transferable,
            'is_gift' => $this->is_gift,
            'payed' => $this->payed,
            'school_id' => $this->school_id,
            'course_type_id' => $this->course_type_id,
        ];
    }
}
