<?php

namespace App\Services;

use App\Models\DiscountCode;
use Illuminate\Support\Facades\DB;
use Illuminate\\Support\\Facades\\Cache;\nuse Illuminate\\Support\\Arr;
use Carbon\Carbon;

/**
 * DiscountCodeService
 *
 * Servicio central para la validaci贸n y aplicaci贸n de c贸digos de descuento.
 * Maneja toda la l贸gica de negocio relacionada con c贸digos promocionales.
 */
class DiscountCodeService
{
    /**
     * Validar un c贸digo de descuento para una reserva espec铆fica
     *
     * @param string $code C贸digo promocional
     * @param array $bookingData Datos de la reserva (school_id, course_id, sport_id, degree_id, amount, user_id)
     * @return array ['valid' => bool, 'discount_code' => DiscountCode|null, 'message' => string, 'discount_amount' => float]
     */
    public function validateCode(string $code, array $bookingData): array
    {
        // Buscar el c贸digo
        $discountCode = DiscountCode::where('code', strtoupper($code))->first();

        if (!$discountCode) {
            return [
                'valid' => false,
                'discount_code' => null,
                'message' => 'C贸digo de descuento no encontrado',
                'discount_amount' => 0
            ];
        }

        // 1. Verificar si est谩 activo
        if (!$discountCode->isActive()) {
            return [
                'valid' => false,
                'discount_code' => $discountCode,
                'message' => 'C贸digo de descuento inactivo o eliminado',
                'discount_amount' => 0
            ];
        }

        // 2. Verificar validez por fecha
        if (!$discountCode->isValidForDate()) {
            $validFrom = $discountCode->valid_from?->format('d/m/Y');
            $validTo = $discountCode->valid_to?->format('d/m/Y');

            if ($validFrom && $validTo) {
                $message = "C贸digo v谩lido solo entre $validFrom y $validTo";
            } elseif ($validFrom) {
                $message = "C贸digo v谩lido a partir del $validFrom";
            } elseif ($validTo) {
                $message = "C贸digo expirado el $validTo";
            } else {
                $message = "C贸digo fuera del periodo de validez";
            }

            return [
                'valid' => false,
                'discount_code' => $discountCode,
                'message' => $message,
                'discount_amount' => 0
            ];
        }

        // 3. Verificar usos disponibles
        if (!$discountCode->hasUsesAvailable()) {
            return [
                'valid' => false,
                'discount_code' => $discountCode,
                'message' => 'C贸digo sin usos disponibles',
                'discount_amount' => 0
            ];
        }

        // 4. Verificar restricciones de entidad
        $schoolId = $bookingData['school_id'] ?? null;
        $courseId = $bookingData['course_id'] ?? null;
        $sportId = $bookingData['sport_id'] ?? null;
        $degreeId = $bookingData['degree_id'] ?? null;
        $userId = $bookingData['user_id'] ?? null;

        $applicableTo = $discountCode->applicable_to ?? 'all';

        if ($applicableTo === 'specific_courses') {
            $courseIds = Arr::wrap($discountCode->course_ids);
            if (!empty($courseIds) && (!$courseId || !in_array((int) $courseId, array_map('intval', $courseIds)))) {
                return [
                    'valid' => false,
                    'discount_code' => $discountCode,
                    'message' => 'C骴igo no aplicable a este curso',
                    'discount_amount' => 0
                ];
            }
        }

        if ($applicableTo === 'specific_clients') {
            $clientIds = Arr::wrap($discountCode->client_ids);
            if (empty($clientIds) || !$userId || !in_array((int) $userId, array_map('intval', $clientIds))) {
                return [
                    'valid' => false,
                    'discount_code' => $discountCode,
                    'message' => 'C骴igo no aplicable a este cliente',
                    'discount_amount' => 0
                ];
            }
        }

        if (!$discountCode->isValidForSchool($schoolId)) {
            return [
                'valid' => false,
                'discount_code' => $discountCode,
                'message' => 'C骴igo no aplicable a esta escuela',
                'discount_amount' => 0
            ];
        }

        if (!$discountCode->isValidForCourse($courseId)) {
            return [
                'valid' => false,
                'discount_code' => $discountCode,
                'message' => 'C骴igo no aplicable a este curso',
                'discount_amount' => 0
            ];
        }

        if (!$discountCode->isValidForSport($sportId)) {
            return [
                'valid' => false,
                'discount_code' => $discountCode,
                'message' => 'C骴igo no aplicable a este deporte',
                'discount_amount' => 0
            ];
        }

        if (!$discountCode->isValidForDegree($degreeId)) {
            return [
                'valid' => false,
                'discount_code' => $discountCode,
                'message' => 'C骴igo no aplicable a este nivel',
                'discount_amount' => 0
            ];
        }

        // 5. Verificar monto m铆nimo de compra
        $amount = $bookingData['amount'] ?? 0;
        if (!$discountCode->meetsMinimumPurchase($amount)) {
            $minAmount = number_format($discountCode->min_purchase_amount, 2);
            return [
                'valid' => false,
                'discount_code' => $discountCode,
                'message' => "Monto m铆nimo de compra: $$minAmount",
                'discount_amount' => 0
            ];
        }

        // 6. Verificar usos por usuario (si se proporciona user_id)
        $userId = $bookingData['user_id'] ?? null;
        if ($userId && !$this->canUserUseCode($discountCode->id, $userId)) {
            return [
                'valid' => false,
                'discount_code' => $discountCode,
                'message' => 'Has alcanzado el l铆mite de usos para este c贸digo',
                'discount_amount' => 0
            ];
        }

        // 7. Calcular descuento
        $discountAmount = $discountCode->calculateDiscountAmount($amount);

        return [
            'valid' => true,
            'discount_code' => $discountCode,
            'message' => 'C贸digo v谩lido',
            'discount_amount' => $discountAmount
        ];
    }

    /**
     * Verificar si un usuario puede usar un c贸digo de descuento
     *
     * @param int $discountCodeId
     * @param int $userId
     * @return bool
     */
    public function canUserUseCode(int $discountCodeId, int $userId): bool
    {
        $discountCode = DiscountCode::find($discountCodeId);

        if (!$discountCode) {
            return false;
        }

        // Si no hay l铆mite por usuario, siempre puede usar
        if (!$discountCode->max_uses_per_user) {
            return true;
        }

        // Contar cu谩ntas veces ha usado este c贸digo
        // NOTA: Esto requiere una tabla discount_code_usages (implementar en siguiente iteraci贸n)
        // Por ahora, retornamos true
        $usageCount = $this->getUserCodeUsageCount($discountCodeId, $userId);

        return $usageCount < $discountCode->max_uses_per_user;
    }

    /**
     * Obtener el conteo de usos de un c贸digo por un usuario
     *
     * @param int $discountCodeId
     * @param int $userId
     * @return int
     */
    protected function getUserCodeUsageCount(int $discountCodeId, int $userId): int
    {
        return DB::table('discount_code_usages')
            ->where('discount_code_id', $discountCodeId)
            ->where('user_id', $userId)
            ->count();
    }

    /**
     * Aplicar un c贸digo de descuento a una reserva
     *
     * @param DiscountCode $discountCode
     * @param array $bookingData
     * @return array ['success' => bool, 'message' => string, 'discount_amount' => float]
     */
    public function applyCodeToBooking(DiscountCode $discountCode, array $bookingData): array
    {
        $validation = $this->validateCode($discountCode->code, $bookingData);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
                'discount_amount' => 0
            ];
        }

        return [
            'success' => true,
            'message' => 'Descuento aplicado correctamente',
            'discount_amount' => $validation['discount_amount']
        ];
    }

    /**
     * Registrar el uso de un c贸digo de descuento
     *
     * @param int $discountCodeId
     * @param int $userId
     * @param int $bookingId
     * @param float $discountAmount
     * @return bool
     */
    public function recordCodeUsage(int $discountCodeId, int $userId, int $bookingId, float $discountAmount): bool
    {
        $discountCode = DiscountCode::find($discountCodeId);

        if (!$discountCode) {
            return false;
        }

        DB::beginTransaction();

        try {
            // Decrementar remaining
            if ($discountCode->remaining !== null) {
                $discountCode->decrementRemaining();
            }

            // Registrar en tabla discount_code_usages
            DB::table('discount_code_usages')->insert([
                'discount_code_id' => $discountCodeId,
                'user_id' => $userId,
                'booking_id' => $bookingId,
                'discount_amount' => $discountAmount,
                'used_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::commit();

            // Invalidar cache
            $this->invalidateCache($discountCodeId);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error recording discount code usage: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Revertir el uso de un c贸digo de descuento (煤til para cancelaciones)
     *
     * @param int $discountCodeId
     * @param int $userId
     * @param int $bookingId
     * @return bool
     */
    public function revertCodeUsage(int $discountCodeId, int $userId, int $bookingId): bool
    {
        $discountCode = DiscountCode::find($discountCodeId);

        if (!$discountCode) {
            return false;
        }

        DB::beginTransaction();

        try {
            // Incrementar remaining
            if ($discountCode->remaining !== null) {
                $discountCode->incrementRemaining();
            }

            // Eliminar de tabla discount_code_usages
            DB::table('discount_code_usages')
                ->where('discount_code_id', $discountCodeId)
                ->where('user_id', $userId)
                ->where('booking_id', $bookingId)
                ->delete();

            DB::commit();

            // Invalidar cache
            $this->invalidateCache($discountCodeId);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error reverting discount code usage: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener estad铆sticas de uso de un c贸digo
     *
     * @param int $discountCodeId
     * @return array
     */
    public function getCodeStats(int $discountCodeId): array
    {
        $discountCode = DiscountCode::find($discountCodeId);

        if (!$discountCode) {
            return [];
        }

        $usesTotal = $discountCode->total ?? 'ilimitado';
        $usesRemaining = $discountCode->remaining ?? 'ilimitado';
        $usesConsumed = is_numeric($usesTotal) && is_numeric($usesRemaining)
            ? ($usesTotal - $usesRemaining)
            : 0;

        return [
            'code' => $discountCode->code,
            'uses_total' => $usesTotal,
            'uses_remaining' => $usesRemaining,
            'uses_consumed' => $usesConsumed,
            'is_active' => $discountCode->isActive(),
            'is_valid_now' => $discountCode->isValidForDate(),
            'has_uses_available' => $discountCode->hasUsesAvailable(),
        ];
    }

    /**
     * Listar c贸digos activos para una escuela
     *
     * @param int|null $schoolId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveCodesForSchool(?int $schoolId = null)
    {
        $query = DiscountCode::where('active', true)
            ->where(function($q) {
                $q->whereNull('valid_from')
                  ->orWhere('valid_from', '<=', now());
            })
            ->where(function($q) {
                $q->whereNull('valid_to')
                  ->orWhere('valid_to', '>=', now());
            });

        if ($schoolId) {
            $query->where(function($q) use ($schoolId) {
                $q->whereNull('school_id')
                  ->orWhere('school_id', $schoolId);
            });
        }

        return $query->get();
    }

    /**
     * Buscar c贸digo por c贸digo exacto
     *
     * @param string $code
     * @return DiscountCode|null
     */
    public function findByCode(string $code): ?DiscountCode
    {
        return DiscountCode::where('code', strtoupper($code))->first();
    }

    /**
     * Invalidar cache de un c贸digo
     *
     * @param int $discountCodeId
     * @return void
     */
    protected function invalidateCache(int $discountCodeId): void
    {
        Cache::forget("discount_code_{$discountCodeId}");
        Cache::forget("discount_code_validation_{$discountCodeId}");
    }

    /**
     * Obtener detalles completos de validaci贸n de un c贸digo
     *
     * @param string $code
     * @param array $bookingData
     * @return array
     */
    public function getValidationDetails(string $code, array $bookingData): array
    {
        $validation = $this->validateCode($code, $bookingData);

        if (!$validation['discount_code']) {
            return $validation;
        }

        $discountCode = $validation['discount_code'];

        return array_merge($validation, [
            'code_details' => [
                'id' => $discountCode->id,
                'code' => $discountCode->code,
                'description' => $discountCode->description,
                'discount_type' => $discountCode->discount_type,
                'discount_value' => $discountCode->discount_value,
                'restrictions' => [
                    'school_id' => $discountCode->school_id,
                    'sport_ids' => $discountCode->sport_ids,
                    'course_ids' => $discountCode->course_ids,
                    'degree_ids' => $discountCode->degree_ids,
                    'min_purchase_amount' => $discountCode->min_purchase_amount,
                    'max_discount_amount' => $discountCode->max_discount_amount,
                ],
                'usage' => [
                    'total' => $discountCode->total,
                    'remaining' => $discountCode->remaining,
                    'max_per_user' => $discountCode->max_uses_per_user,
                ],
                'validity' => [
                    'from' => $discountCode->valid_from?->format('Y-m-d H:i:s'),
                    'to' => $discountCode->valid_to?->format('Y-m-d H:i:s'),
                ]
            ]
        ]);
    }
}


