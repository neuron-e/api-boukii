<?php

namespace App\Services;

use App\Models\RentalEvent;
use App\Models\RentalPickupPoint;
use App\Models\RentalReservation;
use App\Models\RentalReservationLine;
use App\Models\RentalReservationUnitAssignment;
use App\Models\RentalUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

class RentalReservationCreateService
{
    private const STATUS_PENDING = 'pending';
    private const STATUS_ASSIGNED = 'assigned';
    private const STATUS_CHECKED_OUT = 'checked_out';
    private const STATUS_PARTIAL_RETURN = 'partial_return';
    private const STATUS_RETURNED = 'returned';
    private const STATUS_COMPLETED = 'completed';

    public function __construct(
        private readonly RentalPricingService $rentalPricingService,
        private readonly RentalNotificationService $rentalNotificationService
    ) {
    }

    /**
     * @return array{reservation:\App\Models\RentalReservation,quote:array,lines_inserted:int}
     */
    public function create(int $schoolId, array $payload, bool $sendConfirmation = true): array
    {
        $pickupPointId = (int) ($payload['pickup_point_id'] ?? 0);
        if ($pickupPointId <= 0) {
            throw new InvalidArgumentException('pickup_point_id is required');
        }
        if (!$this->pickupPointExistsForSchool($pickupPointId, $schoolId)) {
            throw new InvalidArgumentException('pickup_point_id is invalid');
        }

        $inputLines = $this->normalizeInputLines($payload);
        if (empty($inputLines)) {
            throw new InvalidArgumentException('lines is required');
        }

        DB::beginTransaction();
        try {
            $quotePayload = $payload;
            $quotePayload['lines'] = $inputLines;
            $pricingQuote = $this->rentalPricingService->quote($schoolId, $quotePayload);

            $reservationPayload = collect($payload)->only([
                'booking_id',
                'client_id',
                'pickup_point_id',
                'return_point_id',
                'warehouse_id',
                'start_date',
                'end_date',
                'start_time',
                'end_time',
                'status',
                'currency',
                'subtotal',
                'discount_total',
                'tax_total',
                'total',
                'notes',
                'meta',
            ])->all();

            $reservationPayload['school_id'] = $schoolId;
            $reservationPayload['status'] = $reservationPayload['status'] ?? self::STATUS_PENDING;
            $reservationPayload['currency'] = $reservationPayload['currency'] ?? ($pricingQuote['currency'] ?? 'CHF');
            $reservationPayload['start_date'] = $pricingQuote['period']['start_date'] ?? ($reservationPayload['start_date'] ?? null);
            $reservationPayload['end_date'] = $pricingQuote['period']['end_date'] ?? ($reservationPayload['end_date'] ?? null);
            $reservationPayload['start_time'] = $pricingQuote['period']['start_time'] ?? ($reservationPayload['start_time'] ?? null);
            $reservationPayload['end_time'] = $pricingQuote['period']['end_time'] ?? ($reservationPayload['end_time'] ?? null);
            $reservationPayload['subtotal'] = $pricingQuote['subtotal'] ?? 0;
            $reservationPayload['total'] = $pricingQuote['total'] ?? ($pricingQuote['subtotal'] ?? 0);
            $reservationPayload['return_point_id'] = $reservationPayload['return_point_id'] ?? $pickupPointId;

            $reservation = RentalReservation::create($reservationPayload);
            $reservationId = (int) $reservation->id;
            $lineColumnSet = array_flip(Schema::getColumnListing('rental_reservation_lines'));

            $insertedLines = 0;
            foreach (($pricingQuote['lines'] ?? []) as $line) {
                $linePayload = [
                    'school_id' => $schoolId,
                    'rental_reservation_id' => $reservationId,
                    'item_id' => isset($line['item_id']) ? (int) $line['item_id'] : null,
                    'variant_id' => isset($line['variant_id']) ? (int) $line['variant_id'] : null,
                    'quantity' => max(1, (int) ($line['quantity'] ?? 1)),
                    'unit_price' => (float) ($line['unit_price'] ?? 0),
                    'line_total' => (float) ($line['line_total'] ?? 0),
                ];

                if (isset($lineColumnSet['period_type'])) {
                    $linePayload['period_type'] = $line['period_type'] ?? 'full_day';
                }
                if (isset($lineColumnSet['start_date'])) {
                    $linePayload['start_date'] = $line['start_date'] ?? ($reservationPayload['start_date'] ?? null);
                }
                if (isset($lineColumnSet['end_date'])) {
                    $linePayload['end_date'] = $line['end_date'] ?? ($reservationPayload['end_date'] ?? null);
                }
                if (isset($lineColumnSet['start_time'])) {
                    $linePayload['start_time'] = $line['start_time'] ?? ($reservationPayload['start_time'] ?? null);
                }
                if (isset($lineColumnSet['end_time'])) {
                    $linePayload['end_time'] = $line['end_time'] ?? ($reservationPayload['end_time'] ?? null);
                }
                if (isset($lineColumnSet['qty_assigned'])) {
                    $linePayload['qty_assigned'] = 0;
                }
                if (isset($lineColumnSet['status'])) {
                    $linePayload['status'] = self::STATUS_PENDING;
                }
                if (isset($lineColumnSet['meta'])) {
                    $linePayload['meta'] = json_encode([
                        'pricing' => [
                            'pricing_rule_id' => $line['pricing_rule_id'] ?? null,
                            'pricing_mode' => $line['pricing_mode'] ?? null,
                            'pricing_basis_key' => $line['pricing_basis_key'] ?? null,
                            'pricing_source' => $line['pricing_source'] ?? null,
                            'rental_days' => $line['rental_days'] ?? null,
                            'unit_price' => $line['unit_price'] ?? null,
                            'line_total' => $line['line_total'] ?? null,
                        ],
                    ]);
                }

                RentalReservationLine::create($linePayload);
                $insertedLines++;
            }

            if ($insertedLines <= 0) {
                throw new InvalidArgumentException('No valid lines to create reservation');
            }

            $this->syncReservationTotalsAndStatus($reservationId);
            RentalEvent::log($reservationId, $schoolId, 'created', [
                'lines_count' => $insertedLines,
                'status' => self::STATUS_PENDING,
            ]);

            DB::commit();

            if ($sendConfirmation) {
                try {
                    $this->rentalNotificationService->sendConfirmation($reservationId);
                } catch (Throwable $notificationError) {
                    Log::warning('RENTAL_CONFIRMATION_MAIL_SKIPPED', [
                        'reservation_id' => $reservationId,
                        'error' => $notificationError->getMessage(),
                    ]);
                }
            }

            return [
                'reservation' => RentalReservation::query()->findOrFail($reservationId),
                'quote' => $pricingQuote,
                'lines_inserted' => $insertedLines,
            ];
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function normalizeInputLines(array $payload): array
    {
        $inputLines = $payload['lines'] ?? [];
        if ((!is_array($inputLines) || empty($inputLines)) && is_array($payload['items'] ?? null)) {
            $inputLines = collect($payload['items'])->map(function ($item) use ($payload) {
                if (!is_array($item)) {
                    return null;
                }

                return [
                    'item_id' => $item['item_id'] ?? null,
                    'variant_id' => $item['variant_id'] ?? null,
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => $item['unit_price'] ?? 0,
                    'line_total' => $item['line_total'] ?? 0,
                    'period_type' => $item['period_type'] ?? ($payload['period_type'] ?? 'full_day'),
                    'start_date' => $item['start_date'] ?? ($payload['start_date'] ?? null),
                    'end_date' => $item['end_date'] ?? ($payload['end_date'] ?? null),
                    'start_time' => $item['start_time'] ?? ($payload['start_time'] ?? null),
                    'end_time' => $item['end_time'] ?? ($payload['end_time'] ?? null),
                    'meta' => $item['meta'] ?? null,
                ];
            })->filter()->values()->all();
        }

        return is_array($inputLines) ? $inputLines : [];
    }

    private function pickupPointExistsForSchool(int $pickupPointId, int $schoolId): bool
    {
        return RentalPickupPoint::query()
            ->where('id', $pickupPointId)
            ->where('school_id', $schoolId)
            ->exists();
    }

    private function syncReservationTotalsAndStatus(int $reservationId): void
    {
        $lineColumnSet = array_flip(Schema::getColumnListing('rental_reservation_lines'));
        $lines = RentalReservationLine::where('rental_reservation_id', $reservationId)
            ->get(['id', 'line_total', 'quantity']);

        if ($lines->isEmpty()) {
            return;
        }

        $subtotal = (float) $lines->sum('line_total');
        $requiredUnits = (int) $lines->sum('quantity');
        $assignedUnits = 0;
        $returnedUnits = 0;

        foreach ($lines as $line) {
            $lineAssigned = $this->getCurrentlyAssignedQty($reservationId, $line->id);
            $lineReturned = $this->getReturnedQty($reservationId, $line->id);
            $assignedUnits += $lineAssigned;
            $returnedUnits += $lineReturned;

            $lineStatus = self::STATUS_PENDING;
            if ($lineAssigned <= 0 && $lineReturned > 0) {
                $lineStatus = self::STATUS_RETURNED;
            } elseif ($lineAssigned > 0 && $lineAssigned < $line->quantity) {
                $lineStatus = self::STATUS_PARTIAL_RETURN;
            } elseif ($line->quantity > 0 && $lineAssigned >= $line->quantity) {
                $lineStatus = self::STATUS_ASSIGNED;
            }

            $lineUpdate = [];
            if (isset($lineColumnSet['qty_assigned'])) {
                $lineUpdate['qty_assigned'] = $lineAssigned;
            }
            if (isset($lineColumnSet['status'])) {
                $lineUpdate['status'] = $lineStatus;
            }
            if (!empty($lineUpdate)) {
                $line->update($lineUpdate);
            }
        }

        $reservation = RentalReservation::find($reservationId);
        if (!$reservation) {
            return;
        }

        $status = strtolower((string) $reservation->status);
        if ($requiredUnits > 0) {
            if ($assignedUnits <= 0) {
                $status = $returnedUnits > 0 ? self::STATUS_RETURNED : self::STATUS_PENDING;
            } elseif ($assignedUnits < $requiredUnits) {
                $status = self::STATUS_PARTIAL_RETURN;
            } elseif (!in_array($status, [self::STATUS_CHECKED_OUT, self::STATUS_RETURNED, self::STATUS_COMPLETED], true)) {
                $status = self::STATUS_ASSIGNED;
            }
        }

        $reservation->update([
            'subtotal' => $subtotal,
            'total' => $subtotal - (float) $reservation->discount_total + (float) $reservation->tax_total,
            'status' => $status,
        ]);
    }

    private function getCurrentlyAssignedQty(int $reservationId, int $lineId): int
    {
        $qty = 0;
        RentalReservationUnitAssignment::where('rental_reservation_id', $reservationId)
            ->where('rental_reservation_line_id', $lineId)
            ->orderBy('id')
            ->pluck('assignment_type')
            ->each(function ($type) use (&$qty) {
                $t = strtolower((string) $type);
                if (in_array($t, ['assigned', 'checked_out'], true)) {
                    $qty++;
                } elseif ($t === 'returned') {
                    $qty--;
                }
            });

        return max(0, $qty);
    }

    private function getReturnedQty(int $reservationId, int $lineId): int
    {
        return RentalReservationUnitAssignment::where('rental_reservation_id', $reservationId)
            ->where('rental_reservation_line_id', $lineId)
            ->where('assignment_type', 'returned')
            ->count();
    }
}
