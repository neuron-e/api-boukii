<?php

namespace App\Http\Services;

use App\Models\Booking;
use App\Models\BookingPriceAudit;
use App\Models\BookingPriceSnapshot;
use App\Models\BookingLog;

class BookingPriceSnapshotService
{
    public function __construct(
        private BookingPriceCalculatorService $calculator
    ) {}

    public function getLatestSnapshot(Booking $booking): ?BookingPriceSnapshot
    {
        return $booking->priceSnapshots()->latest('id')->first();
    }

    public function createSnapshotFromBasket(
        Booking $booking,
        ?int $actorId,
        string $source = 'basket_import',
        ?string $note = null
    ): BookingPriceSnapshot {
        $snapshot = $this->buildBaseSnapshot($booking);
        $snapshot['calculated'] = $this->calculator->calculateBookingTotal($booking);
        $snapshot['basket'] = $this->decodeBasket($booking->basket);
        $snapshot['totals'] = $this->buildTotalsFromBooking($booking, $snapshot['basket']);
        $snapshot['pricing_context'] = $this->buildPricingContext($booking);
        $snapshot['financial_reality'] = $this->calculator->analyzeFinancialReality($booking);

        return $this->storeSnapshot($booking, $snapshot, $actorId, $source, $note);
    }

    public function createSnapshotFromCalculator(
        Booking $booking,
        ?int $actorId,
        string $source = 'reprice',
        ?string $note = null
    ): BookingPriceSnapshot {
        $snapshot = $this->buildBaseSnapshot($booking);
        $calculated = $this->calculator->calculateBookingTotal($booking);
        $snapshot['calculated'] = $calculated;
        $snapshot['pricing_context'] = $this->buildPricingContext($booking);
        $snapshot['financial_reality'] = $this->calculator->analyzeFinancialReality($booking);
        $snapshot['totals'] = [
            'subtotal' => $calculated['activities_price'],
            'discount_total' => array_sum($calculated['discounts'] ?? []),
            'total' => $calculated['total_final'],
            'paid_total' => (float)($booking->paid_total ?? 0),
            'pending_amount' => max(0, (float)($calculated['total_final'] ?? 0) - (float)($booking->paid_total ?? 0))
        ];

        return $this->storeSnapshot($booking, $snapshot, $actorId, $source, $note);
    }

    public function createManualSnapshot(
        Booking $booking,
        array $overrides,
        ?int $actorId,
        string $source = 'manual_adjust',
        ?string $note = null
    ): BookingPriceSnapshot {
        $snapshot = $this->buildBaseSnapshot($booking);
        $snapshot['basket'] = $this->decodeBasket($booking->basket);
        $snapshot['pricing_context'] = $this->buildPricingContext($booking);
        $snapshot['financial_reality'] = $this->calculator->analyzeFinancialReality($booking);
        $snapshot['manual_overrides'] = $overrides;

        $currentTotals = $this->buildTotalsFromBooking($booking, $snapshot['basket']);
        $snapshot['totals'] = array_merge($currentTotals, $overrides['totals'] ?? []);

        return $this->storeSnapshot($booking, $snapshot, $actorId, $source, $note);
    }

    private function buildBaseSnapshot(Booking $booking): array
    {
        return [
            'version' => 1,
            'booking_id' => $booking->id,
            'currency' => $booking->currency,
            'booking_status' => $booking->status,
            'booking_source' => $booking->source,
            'payment_method_id' => $booking->payment_method_id,
            'has_cancellation_insurance' => (bool)$booking->has_cancellation_insurance,
            'price_cancellation_insurance' => (float)($booking->price_cancellation_insurance ?? 0),
            'discounts_meta' => [
                'discount_code_id' => $booking->discount_code_id,
                'discount_code_value' => $booking->discount_code_value,
                'discount_type' => $booking->discount_type,
                'interval_discount_id' => $booking->interval_discount_id,
                'course_discount_id' => $booking->course_discount_id,
                'original_price' => $booking->original_price,
                'discount_amount' => $booking->discount_amount,
                'final_price' => $booking->final_price,
                'has_reduction' => (bool)$booking->has_reduction,
                'price_reduction' => $booking->price_reduction,
                'has_tva' => (bool)$booking->has_tva,
                'price_tva' => $booking->price_tva
            ]
        ];
    }

    private function buildTotalsFromBooking(Booking $booking, ?array $basket): array
    {
        $priceTotal = (float)($booking->price_total ?? 0);
        $paidTotal = (float)($booking->paid_total ?? 0);
        $pendingAmount = max(0, $priceTotal - $paidTotal);

        if (is_array($basket)) {
            $priceTotal = (float)($basket['price_total'] ?? $priceTotal);
            $paidTotal = (float)($basket['paid_total'] ?? $paidTotal);
            $pendingAmount = (float)($basket['pending_amount'] ?? $pendingAmount);
        }

        return [
            'total' => $priceTotal,
            'paid_total' => $paidTotal,
            'pending_amount' => $pendingAmount
        ];
    }

    private function buildPricingContext(Booking $booking): array
    {
        return [
            'school_id' => $booking->school_id,
            'client_main_id' => $booking->client_main_id,
            'course_discount_id' => $booking->course_discount_id,
            'interval_discount_id' => $booking->interval_discount_id,
            'discount_code_id' => $booking->discount_code_id,
            'school_settings' => $booking->school?->settings
        ];
    }

    private function decodeBasket(?string $basket): ?array
    {
        if (!$basket) {
            return null;
        }
        $decoded = json_decode($basket, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function storeSnapshot(
        Booking $booking,
        array $snapshot,
        ?int $actorId,
        string $source,
        ?string $note
    ): BookingPriceSnapshot {
        $latest = $this->getLatestSnapshot($booking);
        $version = $latest ? ((int)$latest->version + 1) : 1;

        $record = BookingPriceSnapshot::create([
            'booking_id' => $booking->id,
            'version' => $version,
            'source' => $source,
            'snapshot' => $snapshot,
            'created_by' => $actorId
        ]);

        $this->storeAudit($booking, $record, $latest, $note, $actorId);
        $this->storeBookingLog($booking, $source, $note, $actorId, $latest);

        return $record;
    }

    private function storeAudit(
        Booking $booking,
        BookingPriceSnapshot $record,
        ?BookingPriceSnapshot $previous,
        ?string $note,
        ?int $actorId
    ): void {
        $previousTotal = $previous?->snapshot['totals']['total'] ?? null;
        $currentTotal = $record->snapshot['totals']['total'] ?? null;
        $diff = [
            'previous_total' => $previousTotal,
            'current_total' => $currentTotal
        ];

        BookingPriceAudit::create([
            'booking_id' => $booking->id,
            'booking_price_snapshot_id' => $record->id,
            'event_type' => $record->source,
            'note' => $note,
            'diff' => $diff,
            'created_by' => $actorId
        ]);
    }

    private function storeBookingLog(
        Booking $booking,
        string $source,
        ?string $note,
        ?int $actorId,
        ?BookingPriceSnapshot $previous
    ): void {
        $action = 'price_snapshot_' . $source;
        $description = $note ?: 'Snapshot pricing created.';
        $before = $previous?->snapshot ? json_encode($previous->snapshot) : null;

        BookingLog::create([
            'booking_id' => $booking->id,
            'action' => $action,
            'description' => $description,
            'user_id' => $actorId,
            'before_change' => $before
        ]);
    }
}
