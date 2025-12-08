<?php

namespace App\Services;

use App\Models\DiscountCode;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
/**
 * DiscountCodeService
 *
 * Servicio central para la validaciÃ³n y aplicaciÃ³n de cÃ³digos de descuento.
 * Maneja toda la lÃ³gica de negocio relacionada con cÃ³digos promocionales.
 */
class DiscountCodeService
{
    /**
     * Validar un cÃ³digo de descuento para una reserva especÃ­fica
     *
     * @param string $code CÃ³digo promocional
     * @param array $bookingData Datos de la reserva (school_id, course_id, sport_id, degree_id, amount, user_id)
     * @return array ['valid' => bool, 'discount_code' => DiscountCode|null, 'message' => string, 'discount_amount' => float]
     */
    public function validateCode(string $code, array $bookingData): array
    {
        // Buscar el cÃ³digo
        $discountCode = DiscountCode::where('code', strtoupper($code))->first();

        if (!$discountCode) {
            return [
                'valid' => false,
                'discount_code' => null,
                'message' => 'CÃ³digo de descuento no encontrado',
                'discount_amount' => 0
            ];
        }

        // 1. Verificar si estÃ¡ activo
        if (!$discountCode->isActive()) {
            return [
                'valid' => false,
                'discount_code' => $discountCode,
                'message' => 'CÃ³digo de descuento inactivo o eliminado',
                'discount_amount' => 0
            ];
        }

        // 2. Verificar validez por fecha
        if (!$discountCode->isValidForDate()) {
            $validFrom = $discountCode->valid_from?->format('d/m/Y');
            $validTo = $discountCode->valid_to?->format('d/m/Y');

            if ($validFrom && $validTo) {
                $message = "CÃ³digo vÃ¡lido solo entre $validFrom y $validTo";
            } elseif ($validFrom) {
                $message = "CÃ³digo vÃ¡lido a partir del $validFrom";
            } elseif ($validTo) {
                $message = "CÃ³digo expirado el $validTo";
            } else {
                $message = "CÃ³digo fuera del periodo de validez";
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
                'message' => 'CÃ³digo sin usos disponibles',
                'discount_amount' => 0
            ];
        }

        // 4. Verificar restricciones de entidad
        $schoolId = $bookingData['school_id'] ?? null;
        $userId = $bookingData['user_id'] ?? null;
        $clientId = $bookingData['client_id'] ?? null;

        $courseId = $bookingData['course_id'] ?? null;
        $courseIds = Arr::wrap($bookingData['course_ids'] ?? []);
        if ($courseId !== null) {
            $courseIds[] = (int) $courseId;
        }
        $courseIds = array_values(array_unique(array_map('intval', array_filter($courseIds, static function ($value) {
            return $value !== null && $value !== '';
        }))));

        $sportId = $bookingData['sport_id'] ?? null;
        $sportIds = Arr::wrap($bookingData['sport_ids'] ?? []);
        if ($sportId !== null) {
            $sportIds[] = (int) $sportId;
        }
        $sportIds = array_values(array_unique(array_map('intval', array_filter($sportIds, static function ($value) {
            return $value !== null && $value !== '';
        }))));

        $degreeId = $bookingData['degree_id'] ?? null;
        $degreeIds = Arr::wrap($bookingData['degree_ids'] ?? []);
        if ($degreeId !== null) {
            $degreeIds[] = (int) $degreeId;
        }
        $degreeIds = array_values(array_unique(array_map('intval', array_filter($degreeIds, static function ($value) {
            return $value !== null && $value !== '';
        }))));

        $applicableTo = $discountCode->applicable_to ?? 'all';

        // Validación de restricciones por curso
        if ($applicableTo === 'specific_courses') {
            $allowedCourseIds = array_map('intval', Arr::wrap($discountCode->course_ids));

            // Verificar que el código tenga cursos configurados
            if (empty($allowedCourseIds)) {
                return [
                    'valid' => false,
                    'discount_code' => $discountCode,
                    'message' => 'Código no aplicable: el código no tiene cursos configurados',
                    'discount_amount' => 0
                ];
            }

            // Verificar que la reserva incluya cursos
            if (empty($courseIds)) {
                return [
                    'valid' => false,
                    'discount_code' => $discountCode,
                    'message' => 'Código no aplicable: no hay cursos en la reserva',
                    'discount_amount' => 0
                ];
            }

            // Verificar que los cursos de la reserva coincidan con los permitidos
            $invalidCourseIds = array_diff($courseIds, $allowedCourseIds);
            if (!empty($invalidCourseIds)) {
                return [
                    'valid' => false,
                    'discount_code' => $discountCode,
                    'message' => 'Código no aplicable a los cursos seleccionados',
                    'discount_amount' => 0
                ];
            }
        }

        // Validación de restricciones por cliente
        if ($applicableTo === 'specific_clients') {
            $allowedClientIds = array_map('intval', Arr::wrap($discountCode->client_ids));
            $matchesClient = $clientId && in_array((int) $clientId, $allowedClientIds, true);
            $matchesUser = $userId && in_array((int) $userId, $allowedClientIds, true);

            if (empty($allowedClientIds) || (!$matchesClient && !$matchesUser)) {
                return [
                    'valid' => false,
                    'discount_code' => $discountCode,
                    'message' => 'Código no aplicable a este cliente',
                    'discount_amount' => 0
                ];
            }
        }

        // Validación de restricciones por escuela
        if (!$discountCode->isValidForSchool($schoolId)) {
            return [
                'valid' => false,
                'discount_code' => $discountCode,
                'message' => 'Código no aplicable a esta escuela',
                'discount_amount' => 0
            ];
        }

        // Validación de restricciones por deporte (solo si applicable_to lo especifica)
        if ($applicableTo === 'specific_sports') {
            $allowedSportIds = array_map('intval', Arr::wrap($discountCode->sport_ids));

            if (empty($allowedSportIds)) {
                return [
                    'valid' => false,
                    'discount_code' => $discountCode,
                    'message' => 'Código no aplicable: el código no tiene deportes configurados',
                    'discount_amount' => 0
                ];
            }

            if (empty($sportIds)) {
                return [
                    'valid' => false,
                    'discount_code' => $discountCode,
                    'message' => 'Código no aplicable: no hay deportes en la reserva',
                    'discount_amount' => 0
                ];
            }

            $invalidSportIds = array_diff($sportIds, $allowedSportIds);
            if (!empty($invalidSportIds)) {
                return [
                    'valid' => false,
                    'discount_code' => $discountCode,
                    'message' => 'Código no aplicable a este deporte',
                    'discount_amount' => 0
                ];
            }
        }

        // Validación de restricciones por nivel (solo si applicable_to lo especifica)
        if ($applicableTo === 'specific_degrees') {
            $allowedDegreeIds = array_map('intval', Arr::wrap($discountCode->degree_ids));

            if (empty($allowedDegreeIds)) {
                return [
                    'valid' => false,
                    'discount_code' => $discountCode,
                    'message' => 'Código no aplicable: el código no tiene niveles configurados',
                    'discount_amount' => 0
                ];
            }

            if (empty($degreeIds)) {
                return [
                    'valid' => false,
                    'discount_code' => $discountCode,
                    'message' => 'Código no aplicable: no hay niveles en la reserva',
                    'discount_amount' => 0
                ];
            }

            $invalidDegreeIds = array_diff($degreeIds, $allowedDegreeIds);
            if (!empty($invalidDegreeIds)) {
                return [
                    'valid' => false,
                    'discount_code' => $discountCode,
                    'message' => 'Código no aplicable a este nivel',
                    'discount_amount' => 0
                ];
            }
        }// 5. Verificar monto mÃ­nimo de compra
        $amount = $bookingData['amount'] ?? 0;
        if (!$discountCode->meetsMinimumPurchase($amount)) {
            $minAmount = number_format($discountCode->min_purchase_amount, 2);
            return [
                'valid' => false,
                'discount_code' => $discountCode,
                'message' => "Monto mÃ­nimo de compra: $$minAmount",
                'discount_amount' => 0
            ];
        }

        // 6. Verificar usos por usuario (si se proporciona user_id)
        $userId = $bookingData['user_id'] ?? null;
        if ($userId && !$this->canUserUseCode($discountCode->id, $userId)) {
            return [
                'valid' => false,
                'discount_code' => $discountCode,
                'message' => 'Has alcanzado el lÃ­mite de usos para este cÃ³digo',
                'discount_amount' => 0
            ];
        }

        // 7. Calcular descuento
        $discountAmount = $discountCode->calculateDiscountAmount($amount);

        return [
            'valid' => true,
            'discount_code' => $discountCode,
            'message' => 'CÃ³digo vÃ¡lido',
            'discount_amount' => $discountAmount
        ];
    }

    /**
     * Verificar si un usuario puede usar un cÃ³digo de descuento
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

        // Si no hay lÃ­mite por usuario, siempre puede usar
        if (!$discountCode->max_uses_per_user) {
            return true;
        }

        // Contar cuÃ¡ntas veces ha usado este cÃ³digo
        // NOTA: Esto requiere una tabla discount_code_usages (implementar en siguiente iteraciÃ³n)
        // Por ahora, retornamos true
        $usageCount = $this->getUserCodeUsageCount($discountCodeId, $userId);

        return $usageCount < $discountCode->max_uses_per_user;
    }

    /**
     * Obtener el conteo de usos de un cÃ³digo por un usuario
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
     * Aplicar un cÃ³digo de descuento a una reserva
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
     * Registrar el uso de un cÃ³digo de descuento
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
     * Revertir el uso de un cÃ³digo de descuento (Ãºtil para cancelaciones)
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
     * Obtener estadÃ­sticas de uso de un cÃ³digo
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
     * Listar cÃ³digos activos para una escuela
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
     * Buscar cÃ³digo por cÃ³digo exacto
     *
     * @param string $code
     * @return DiscountCode|null
     */
    public function findByCode(string $code): ?DiscountCode
    {
        return DiscountCode::where('code', strtoupper($code))->first();
    }

    /**
     * Invalidar cache de un cÃ³digo
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
     * Obtener detalles completos de validaciÃ³n de un cÃ³digo
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











