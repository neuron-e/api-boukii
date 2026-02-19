<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
 use Illuminate\Database\Eloquent\SoftDeletes; use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @OA\Schema(
 *      schema="Payment",
 *      required={"booking_id","school_id","amount","status"},
 *      @OA\Property(
 *          property="amount",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="number",
 *          format="number"
 *      ),
 *      @OA\Property(
 *          property="status",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="notes",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
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

class Payment extends Model
{
      use LogsActivity, SoftDeletes, HasFactory;     public $table = 'payments';

    public $fillable = [
        'booking_id',
        'school_id',
        'amount',
        'status',
        'invoice_status',
        'invoice_due_at',
        'invoice_url',
        'invoice_pdf_url',
        'invoice_meta',
        'notes',
        'payrexx_reference',
        'payrexx_invoice_id',
        'payrexx_transaction'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'status' => 'string',
        'invoice_status' => 'string',
        'invoice_due_at' => 'datetime',
        'invoice_url' => 'string',
        'invoice_pdf_url' => 'string',
        'invoice_meta' => 'array',
        'notes' => 'string',
        'payrexx_reference' => 'string',
        'payrexx_invoice_id' => 'string',
        'payrexx_transaction' => 'string'
    ];

    public static array $rules = [
        'booking_id' => 'required',
        'school_id' => 'required',
        'amount' => 'required|numeric',
        'status' => 'required|string|max:255',
        'invoice_status' => 'nullable|string|max:255',
        'invoice_due_at' => 'nullable|date',
        'invoice_url' => 'nullable|string|max:65535',
        'invoice_pdf_url' => 'nullable|string|max:65535',
        'invoice_meta' => 'nullable|array',
        'notes' => 'nullable|string|max:65535',
        'payrexx_reference' => 'nullable|string|max:65535',
        'payrexx_invoice_id' => 'nullable|string|max:255',
        'payrexx_transaction' => 'nullable|string|max:65535',
        'created_at' => 'nullable',
        'updated_at' => 'nullable',
        'deleted_at' => 'nullable'
    ];

    public function school(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\School::class, 'school_id');
    }

    public function booking(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Booking::class, 'booking_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
         return LogOptions::defaults();
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
}
