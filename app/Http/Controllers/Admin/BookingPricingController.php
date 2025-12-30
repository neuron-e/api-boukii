<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Http\Services\BookingPriceSnapshotService;
use App\Models\Booking;
use App\Models\BookingPriceAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingPricingController extends AppBaseController
{
    public function __construct(
        private BookingPriceSnapshotService $snapshotService
    ) {}

    public function show(int $id, Request $request): JsonResponse
    {
        /** @var Booking $booking */
        $booking = Booking::with(['payments', 'vouchersLogs.voucher', 'school'])->find($id);
        if (!$booking) {
            return $this->sendError('Booking not found');
        }

        $snapshot = $this->snapshotService->getLatestSnapshot($booking);
        $created = false;
        if (!$snapshot) {
            $snapshot = $this->snapshotService->createSnapshotFromBasket(
                $booking,
                $request->user()?->id,
                'basket_import',
                'Snapshot created from basket'
            );
            $created = true;
        }

        $audits = BookingPriceAudit::where('booking_id', $booking->id)
            ->orderBy('id', 'desc')
            ->get();

        return $this->sendResponse([
            'snapshot' => $snapshot,
            'audits' => $audits,
            'created' => $created
        ], 'Booking pricing snapshot loaded');
    }

    public function reprice(int $id, Request $request): JsonResponse
    {
        /** @var Booking $booking */
        $booking = Booking::with(['payments', 'vouchersLogs.voucher', 'school'])->find($id);
        if (!$booking) {
            return $this->sendError('Booking not found');
        }

        $snapshot = $this->snapshotService->createSnapshotFromCalculator(
            $booking,
            $request->user()?->id,
            'reprice',
            $request->input('note')
        );

        return $this->sendResponse($snapshot, 'Booking pricing snapshot recalculated');
    }

    public function adjust(int $id, Request $request): JsonResponse
    {
        /** @var Booking $booking */
        $booking = Booking::with(['payments', 'vouchersLogs.voucher', 'school'])->find($id);
        if (!$booking) {
            return $this->sendError('Booking not found');
        }

        $overrides = $request->input('overrides', []);
        $snapshot = $this->snapshotService->createManualSnapshot(
            $booking,
            $overrides,
            $request->user()?->id,
            'manual_adjust',
            $request->input('note')
        );

        return $this->sendResponse($snapshot, 'Booking pricing snapshot adjusted');
    }
}
