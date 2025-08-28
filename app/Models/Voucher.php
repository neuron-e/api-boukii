<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
 use Illuminate\Database\Eloquent\SoftDeletes; use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @OA\Schema(
 *      schema="Voucher",
 *      required={"code","quantity","remaining_balance","payed","client_id","school_id"},
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
 *          property="payyo_reference",
 *          description="The reference related to payment through Payyo",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="payyo_transaction",
 *          description="The transaction related to payment through Payyo",
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
        'quantity',
        'remaining_balance',
        'payed',
        'is_gift',
        'client_id',
        'school_id',
        'payrexx_reference',
        'payrexx_transaction',
        'payyo_reference',
        'payyo_transaction',
        'old_id'
    ];

    protected $casts = [
        'code' => 'string',
        'quantity' => 'float',
        'remaining_balance' => 'float',
        'payed' => 'boolean',
        'is_gift' => 'boolean',
        'payrexx_reference' => 'string',
        'payrexx_transaction' => 'string',
        'payyo_reference' => 'string',
        'payyo_transaction' => 'string',
    ];

    public static array $rules = [
        'code' => 'string|max:255',
        'quantity' => 'numeric',
        'remaining_balance' => 'numeric',
        'payed' => 'boolean',
        'is_gift' => 'boolean',
        'client_id' => 'numeric',
        'school_id' => 'numeric',
        'payrexx_reference' => 'nullable|string|max:65535',
        'payrexx_transaction' => 'nullable|string|max:65535',
        'payyo_reference' => 'nullable|string|max:65535',
        'payyo_transaction' => 'nullable|string|max:65535',
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

    // Special for field "payrexx_transaction": store encrypted
    public function setPayrexxTransaction($value)
    {
        $this->payrexx_transaction = encrypt(json_encode($value));
    }

    public function getPayrexxTransaction()
    {
        $decrypted = null;
        if ($this->payrexx_transaction) {
            try {
                $decrypted = decrypt($this->payrexx_transaction);
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                $decrypted = null; // Data seems corrupt or tampered
            }
        }

        return $decrypted ? json_decode($decrypted, true) : [];
    }

    // Special for field "payyo_transaction": store encrypted
    public function setPayyoTransaction($value)
    {
        $this->payyo_transaction = encrypt(json_encode($value));
    }

    public function getPayyoTransaction()
    {
        $decrypted = null;
        if ($this->payyo_transaction) {
            try {
                $decrypted = decrypt($this->payyo_transaction);
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                $decrypted = null; // Data seems corrupt or tampered
            }
        }

        return $decrypted ? json_decode($decrypted, true) : [];
    }
}
