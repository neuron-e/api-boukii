<?php

namespace App\Http\Controllers\Admin;

use App\Models\RentalEvent;
use App\Models\RentalPickupPoint;
use App\Models\RentalReservation;
use App\Models\RentalReservationLine;
use App\Models\RentalReservationUnitAssignment;
use App\Models\RentalUnit;
use App\Models\RentalVariant;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\RentalReservationCreateService;
use App\Services\RentalNotificationService;
use App\Services\RentalPricingService;
use App\Services\RentalStockMovementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

class RentalReservationController extends RentalBaseController
{
    private const STATUS_PENDING = 'pending';
    private const STATUS_ASSIGNED = 'assigned';
    private const STATUS_CHECKED_OUT = 'checked_out';
    private const STATUS_PARTIAL_RETURN = 'partial_return';
    private const STATUS_RETURNED = 'returned';
    private const STATUS_COMPLETED = 'completed';

    public function __construct(
        private readonly RentalPricingService $rentalPricingService,
        private readonly RentalReservationCreateService $rentalReservationCreateService,
        private readonly RentalStockMovementService $stockMovementService
    )
    {
    }

    public function index(Request $request)
    {
        if (!Schema::hasTable('rental_reservations')) {
            return $this->tableMissingResponse('rental_reservations');
        }

        $schoolId = $this->getSchoolId($request);
        $query = DB::table('rental_reservations as rr')->select('rr.*');

        if ($schoolId && Schema::hasColumn('rental_reservations', 'school_id')) {
            $query->where('rr.school_id', $schoolId);
        }
        if (Schema::hasColumn('rental_reservations', 'deleted_at')) {
            $query->whereNull('rr.deleted_at');
        }

        $status = $request->input('status');
        if ($status !== null && $status !== '' && Schema::hasColumn('rental_reservations', 'status')) {
            $query->where('rr.status', $status);
        }

        $bookingId = $request->input('booking_id');
        if ($bookingId !== null && $bookingId !== '' && Schema::hasColumn('rental_reservations', 'booking_id')) {
            $query->where('rr.booking_id', (int) $bookingId);
        }

        // booking_id_in: comma-separated list for bulk rental-flag lookup
        $bookingIdIn = $request->input('booking_id_in');
        if ($bookingIdIn !== null && $bookingIdIn !== '' && Schema::hasColumn('rental_reservations', 'booking_id')) {
            $ids = array_filter(array_map('intval', explode(',', (string) $bookingIdIn)));
            if (!empty($ids)) {
                $query->whereIn('rr.booking_id', $ids);
            }
        }

        $clientId = $request->input('client_id');
        if ($clientId !== null && $clientId !== '' && Schema::hasColumn('rental_reservations', 'client_id')) {
            $query->where('rr.client_id', (int) $clientId);
        }

        // Date range overlap filter (for planner integration)
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');
        if ($dateFrom && $dateTo
            && Schema::hasColumn('rental_reservations', 'start_date')
            && Schema::hasColumn('rental_reservations', 'end_date')
        ) {
            $query->where('rr.start_date', '<=', $dateTo)
                  ->where('rr.end_date', '>=', $dateFrom);
        } elseif ($dateFrom && Schema::hasColumn('rental_reservations', 'start_date')) {
            $query->whereDate('rr.start_date', '>=', $dateFrom);
        }

        if (Schema::hasTable('clients')) {
            $clientSelect = [
                DB::raw('c.id as _client_id'),
                DB::raw('c.first_name as _client_first_name'),
                DB::raw('c.last_name as _client_last_name'),
                DB::raw('c.email as _client_email'),
            ];
            if (Schema::hasColumn('clients', 'phone')) {
                $clientSelect[] = DB::raw('c.phone as _client_phone');
            } else {
                $clientSelect[] = DB::raw('NULL as _client_phone');
            }
            if (Schema::hasColumn('clients', 'mobile')) {
                $clientSelect[] = DB::raw('c.mobile as _client_mobile');
            } else {
                $clientSelect[] = DB::raw('NULL as _client_mobile');
            }

            $query
                ->leftJoin('clients as c', 'c.id', '=', 'rr.client_id')
                ->addSelect($clientSelect);
        }

        // Pickup point name
        if (Schema::hasTable('rental_pickup_points') && Schema::hasColumn('rental_reservations', 'rental_pickup_point_id')) {
            $query->leftJoin('rental_pickup_points as pp', 'pp.id', '=', 'rr.rental_pickup_point_id')
                  ->addSelect(DB::raw('pp.name as pickup_point_name'));
        }

        if (Schema::hasTable('rental_reservation_lines')) {
            $lineAgg = DB::table('rental_reservation_lines as rrl')
                ->select([
                    'rrl.rental_reservation_id',
                    DB::raw('COUNT(*) as lines_count'),
                    DB::raw('COALESCE(SUM(rrl.quantity), 0) as items_count'),
                ])
                ->when(
                    $schoolId && Schema::hasColumn('rental_reservation_lines', 'school_id'),
                    function ($lineQuery) use ($schoolId) {
                        $lineQuery->where('rrl.school_id', $schoolId);
                    }
                )
                ->groupBy('rrl.rental_reservation_id');

            $query
                ->leftJoinSub($lineAgg, 'agg', function ($join) {
                    $join->on('agg.rental_reservation_id', '=', 'rr.id');
                })
                ->addSelect([
                    DB::raw('COALESCE(agg.lines_count, 0) as lines_count'),
                    DB::raw('COALESCE(agg.items_count, 0) as items_count'),
                ]);
        } else {
            $query->addSelect([
                DB::raw('0 as lines_count'),
                DB::raw('0 as items_count'),
            ]);
        }

        $query->orderByDesc('rr.id');
        $perPage = (int) $request->input('per_page', 100);
        $data = $query->paginate(max(1, min(1000, $perPage)));

        $linesPreviewByReservationId = [];
        if (Schema::hasTable('rental_reservation_lines')) {
            $reservationIds = collect($data->items())
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->values()
                ->all();

            if (!empty($reservationIds)) {
                $linePreviewSelect = [
                    'rrl.id',
                    'rrl.rental_reservation_id',
                    'rrl.item_id',
                    'rrl.variant_id',
                    'rrl.quantity',
                    DB::raw('rv.name as variant_name'),
                    DB::raw('rv.size_label as variant_size_label'),
                    DB::raw('rv.sku as variant_sku'),
                    DB::raw('ri.name as item_name'),
                    DB::raw('ri.brand as item_brand'),
                    DB::raw('ri.model as item_model'),
                ];

                if (Schema::hasColumn('rental_reservation_lines', 'status')) {
                    $linePreviewSelect[] = 'rrl.status';
                } else {
                    $linePreviewSelect[] = DB::raw('NULL as status');
                }

                if (Schema::hasColumn('rental_reservation_lines', 'start_date')) {
                    $linePreviewSelect[] = 'rrl.start_date';
                } else {
                    $linePreviewSelect[] = DB::raw('NULL as start_date');
                }

                if (Schema::hasColumn('rental_reservation_lines', 'end_date')) {
                    $linePreviewSelect[] = 'rrl.end_date';
                } else {
                    $linePreviewSelect[] = DB::raw('NULL as end_date');
                }

                $linePreviewQuery = DB::table('rental_reservation_lines as rrl')
                    ->select($linePreviewSelect)
                    ->leftJoin('rental_variants as rv', 'rv.id', '=', 'rrl.variant_id')
                    ->leftJoin('rental_items as ri', 'ri.id', '=', 'rrl.item_id')
                    ->whereIn('rrl.rental_reservation_id', $reservationIds)
                    ->orderBy('rrl.rental_reservation_id')
                    ->orderBy('rrl.id');

                if ($schoolId && Schema::hasColumn('rental_reservation_lines', 'school_id')) {
                    $linePreviewQuery->where('rrl.school_id', $schoolId);
                }

                $linePreviews = $linePreviewQuery->get();
                $linesPreviewByReservationId = $linePreviews
                    ->groupBy('rental_reservation_id')
                    ->map(function ($group) {
                        return collect($group)->take(4)->values()->all();
                    })
                    ->toArray();
            }
        }

        $data->setCollection(
            $data->getCollection()->map(function ($row) use ($linesPreviewByReservationId) {
                $clientId = (int) ($row->_client_id ?? 0);
                $row->client = $clientId > 0 ? [
                    'id' => $clientId,
                    'first_name' => $row->_client_first_name ?? null,
                    'last_name' => $row->_client_last_name ?? null,
                    'email' => $row->_client_email ?? null,
                    'phone' => $row->_client_phone ?? null,
                    'mobile' => $row->_client_mobile ?? null,
                ] : null;
                unset(
                    $row->_client_id,
                    $row->_client_first_name,
                    $row->_client_last_name,
                    $row->_client_email,
                    $row->_client_phone,
                    $row->_client_mobile
                );
                $reservationId = (int) ($row->id ?? 0);
                $row->lines_preview = $linesPreviewByReservationId[$reservationId] ?? [];
                return $row;
            })
        );

        return $this->sendResponse($data, 'Data retrieved successfully');
    }

    public function show(Request $request, int $id, ?float $oldTotalForReconciliation = null)
    {
        if (!Schema::hasTable('rental_reservations')) {
            return $this->tableMissingResponse('rental_reservations');
        }

        $reservationResponse = $this->showByTable($request, 'rental_reservations', $id);
        $payload = $reservationResponse->getData(true);
        if (empty($payload['success'])) {
            return $reservationResponse;
        }

        $reservation = $payload['data'] ?? [];
        $reservationId = (int) ($reservation['id'] ?? 0);
        if ($reservationId <= 0) {
            return $reservationResponse;
        }

        if (Schema::hasTable('rental_reservation_lines')) {
            $lineSelect = [
                'rrl.*',
                DB::raw('rv.name as variant_name'),
                DB::raw('rv.size_label as variant_size_label'),
                DB::raw('rv.sku as variant_sku'),
                DB::raw('ri.name as item_name'),
                DB::raw('ri.brand as item_brand'),
                DB::raw('ri.model as item_model'),
            ];

            $reservation['lines'] = DB::table('rental_reservation_lines as rrl')
                ->select($lineSelect)
                ->leftJoin('rental_variants as rv', 'rv.id', '=', 'rrl.variant_id')
                ->leftJoin('rental_items as ri', 'ri.id', '=', 'rrl.item_id')
                ->where('rrl.rental_reservation_id', $reservationId)
                ->orderBy('rrl.id')
                ->get();
        } else {
            $reservation['lines'] = [];
        }

        if (Schema::hasTable('rental_reservation_unit_assignments')) {
            $reservation['unit_assignments'] = DB::table('rental_reservation_unit_assignments')
                ->where('rental_reservation_id', $reservationId)
                ->orderBy('id')
                ->get();
        } else {
            $reservation['unit_assignments'] = [];
        }

        $reservation['items_count'] = collect($reservation['lines'] ?? [])->sum(function ($line) {
            return (int) ($line->quantity ?? 0);
        });
        $reservation['lines_count'] = is_array($reservation['lines'] ?? null)
            ? count($reservation['lines'])
            : 0;

        $clientId = (int) ($reservation['client_id'] ?? 0);
        if ($clientId > 0 && Schema::hasTable('clients')) {
            $clientColumns = ['id', 'first_name', 'last_name', 'email'];
            if (Schema::hasColumn('clients', 'phone')) {
                $clientColumns[] = 'phone';
            }
            if (Schema::hasColumn('clients', 'mobile')) {
                $clientColumns[] = 'mobile';
            }

            $client = DB::table('clients')
                ->select($clientColumns)
                ->where('id', $clientId)
                ->first();
            $reservation['client'] = $client ?: null;
        } else {
            $reservation['client'] = null;
        }

        $reservation['financial_reconciliation'] = $this->buildFinancialReconciliation($reservationId, $oldTotalForReconciliation);

        return $this->sendResponse($reservation, 'Data retrieved successfully');
    }

    public function store(Request $request)
    {
        if (!Schema::hasTable('rental_reservations')) {
            return $this->tableMissingResponse('rental_reservations');
        }

        $schoolId = $this->getSchoolId($request);
        try {
            $result = $this->rentalReservationCreateService->create(
                $schoolId ?: (int) $request->input('school_id', 0),
                $request->all()
            );

            return $this->show($request, (int) $result['reservation']->id);
        } catch (InvalidArgumentException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        } catch (Throwable $e) {
            return $this->sendError('Error creating rental reservation: ' . $e->getMessage(), [], 500);
        }
    }

    public function quote(Request $request)
    {
        if (!Schema::hasTable('rental_reservations')) {
            return $this->tableMissingResponse('rental_reservations');
        }

        try {
            $schoolId = $this->getSchoolId($request) ?: (int) $request->input('school_id', 0);
            if ($schoolId <= 0) {
                return $this->sendError('school_id is required', [], 422);
            }

            $quote = $this->rentalPricingService->quote($schoolId, $request->all());
            return $this->sendResponse($quote, 'Rental pricing calculated successfully');
        } catch (InvalidArgumentException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        } catch (Throwable $e) {
            return $this->sendError('Error calculating rental pricing: ' . $e->getMessage(), [], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        $oldTotal = (float) (RentalReservation::where('id', $id)->value('total') ?? 0);
        $linePayload = $request->input('lines');

        if ($request->has('pickup_point_id')) {
            $pickupPointId = (int) $request->input('pickup_point_id', 0);
            if ($pickupPointId <= 0) {
                return $this->sendError('pickup_point_id is required', [], 422);
            }
            $schoolId = $this->getSchoolId($request);
            if (!$this->pickupPointExistsForSchool($pickupPointId, $schoolId)) {
                return $this->sendError('pickup_point_id is invalid', [], 422);
            }
        }

        $response = $this->updateByTable($request, 'rental_reservations', $id, [
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
        ]);

        $payload = $response->getData(true);
        if (!empty($payload['success']) && !empty($payload['data']['id'])) {
            if (is_array($linePayload)) {
                $lineApply = $this->applyLineChanges((int) $id, $linePayload, $this->getSchoolId($request));
                if ($lineApply !== true) {
                    return $lineApply;
                }
            }

            $requestedStatus = strtolower((string) $request->input('status', ''));
            if (in_array($requestedStatus, ['active', self::STATUS_CHECKED_OUT], true)) {
                $ready = $this->hasEnoughAssignedUnits((int) $id);
                if (!$ready) {
                    return $this->sendError('Cannot checkout without all required units assigned', [], 422);
                }
                RentalReservation::where('id', $id)->update(['status' => self::STATUS_CHECKED_OUT]);
            }

            $this->syncReservationTotalsAndStatus((int) $id);
            return $this->show($request, (int) $id, $oldTotal);
        }

        return $response;
    }

    private function applyLineChanges(int $reservationId, array $linePayload, ?int $schoolId)
    {
        if (!Schema::hasTable('rental_reservation_lines')) {
            return $this->tableMissingResponse('rental_reservation_lines');
        }

        if (!$schoolId) {
            $schoolId = (int) RentalReservation::where('id', $reservationId)->value('school_id');
        }
        if ($schoolId <= 0) {
            return $this->sendError('school_id is required', [], 422);
        }

        $reservation = RentalReservation::find($reservationId);
        if (!$reservation) {
            return $this->sendError('Reservation not found', [], 404);
        }

        $existingLines = RentalReservationLine::where('rental_reservation_id', $reservationId)
            ->when($schoolId, fn($query) => $query->where('school_id', $schoolId))
            ->get()
            ->keyBy('id');

        $requested = collect($linePayload)
            ->filter(fn($line) => is_array($line))
            ->map(function (array $line) {
                return [
                    'id' => (int) ($line['id'] ?? 0),
                    'item_id' => (int) ($line['item_id'] ?? 0),
                    'variant_id' => (int) ($line['variant_id'] ?? 0),
                    'quantity' => max(1, (int) ($line['quantity'] ?? 1)),
                    'period_type' => (string) ($line['period_type'] ?? ''),
                    'start_date' => (string) ($line['start_date'] ?? ''),
                    'end_date' => (string) ($line['end_date'] ?? ''),
                    'start_time' => (string) ($line['start_time'] ?? ''),
                    'end_time' => (string) ($line['end_time'] ?? ''),
                ];
            })
            ->filter(fn(array $line) => $line['variant_id'] > 0 || $line['item_id'] > 0)
            ->values();

        $variantIds = $requested
            ->pluck('variant_id')
            ->filter(fn($id) => (int) $id > 0)
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $variantMap = empty($variantIds)
            ? collect()
            : RentalVariant::query()
                ->select(['id', 'item_id', 'school_id'])
                ->whereIn('id', $variantIds)
                ->when($schoolId, fn($query) => $query->where('school_id', $schoolId))
                ->get()
                ->keyBy('id');

        $availabilityLines = $requested->map(function (array $row) use ($reservation) {
            return [
                'variant_id' => $row['variant_id'],
                'quantity' => $row['quantity'],
                'start_date' => $row['start_date'] ?: (string) $reservation->start_date,
                'end_date' => $row['end_date'] ?: (string) $reservation->end_date,
            ];
        })->values()->all();

        try {
            $this->rentalReservationCreateService->assertAvailabilityForLines(
                $schoolId,
                $availabilityLines,
                $reservationId
            );
        } catch (InvalidArgumentException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        }

        $quoteRequestLines = $requested->map(function (array $row) use ($reservation) {
            return [
                'variant_id' => $row['variant_id'],
                'item_id' => $row['item_id'],
                'quantity' => $row['quantity'],
                'period_type' => $row['period_type'] ?: null,
                'start_date' => $row['start_date'] ?: null,
                'end_date' => $row['end_date'] ?: null,
                'start_time' => $row['start_time'] ?: null,
                'end_time' => $row['end_time'] ?: null,
            ];
        })->values()->all();

        try {
            $quote = $this->rentalPricingService->quote($schoolId, [
                'currency' => (string) ($reservation->currency ?? 'CHF'),
                'start_date' => (string) ($reservation->start_date ?? ''),
                'end_date' => (string) ($reservation->end_date ?? ''),
                'start_time' => (string) ($reservation->start_time ?? '09:00'),
                'end_time' => (string) ($reservation->end_time ?? '17:00'),
                'period_type' => 'full_day',
                'lines' => $quoteRequestLines,
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        }

        $quotedLines = collect($quote['lines'] ?? [])->values();
        if ($quotedLines->count() !== $requested->count()) {
            return $this->sendError('Could not price all reservation lines', [], 422);
        }

        $lineColumnSet = array_flip(Schema::getColumnListing('rental_reservation_lines'));

        DB::beginTransaction();
        try {
            $keepLineIds = [];

            foreach ($requested as $index => $row) {
                $variant = $row['variant_id'] > 0 ? $variantMap->get($row['variant_id']) : null;
                if ($row['variant_id'] > 0 && !$variant) {
                    continue;
                }

                $quoted = (array) ($quotedLines->get($index) ?? []);
                $resolvedItemId = $row['item_id'] > 0
                    ? $row['item_id']
                    : (int) ($variant->item_id ?? 0);

                $lineData = [
                    'item_id' => $resolvedItemId > 0 ? $resolvedItemId : null,
                    'variant_id' => $row['variant_id'] > 0 ? $row['variant_id'] : null,
                    'quantity' => $row['quantity'],
                    'unit_price' => (float) ($quoted['unit_price'] ?? 0),
                    'line_total' => (float) ($quoted['line_total'] ?? 0),
                ];
                if (isset($lineColumnSet['period_type'])) {
                    $lineData['period_type'] = $quoted['period_type'] ?? ($row['period_type'] ?: 'full_day');
                }
                if (isset($lineColumnSet['start_date'])) {
                    $lineData['start_date'] = $quoted['start_date'] ?? ($row['start_date'] ?: $reservation->start_date);
                }
                if (isset($lineColumnSet['end_date'])) {
                    $lineData['end_date'] = $quoted['end_date'] ?? ($row['end_date'] ?: $reservation->end_date);
                }
                if (isset($lineColumnSet['start_time'])) {
                    $lineData['start_time'] = $quoted['start_time'] ?? ($row['start_time'] ?: $reservation->start_time);
                }
                if (isset($lineColumnSet['end_time'])) {
                    $lineData['end_time'] = $quoted['end_time'] ?? ($row['end_time'] ?: $reservation->end_time);
                }
                if (isset($lineColumnSet['meta'])) {
                    $lineData['meta'] = json_encode([
                        'pricing' => [
                            'pricing_rule_id' => $quoted['pricing_rule_id'] ?? null,
                            'pricing_mode' => $quoted['pricing_mode'] ?? null,
                            'pricing_basis_key' => $quoted['pricing_basis_key'] ?? null,
                            'pricing_source' => $quoted['pricing_source'] ?? null,
                            'rental_days' => $quoted['rental_days'] ?? null,
                            'unit_price' => $quoted['unit_price'] ?? null,
                            'line_total' => $quoted['line_total'] ?? null,
                        ],
                    ]);
                }

                if ($row['id'] > 0 && $existingLines->has($row['id'])) {
                    $line = $existingLines->get($row['id']);
                    $line->update($lineData);
                    $keepLineIds[] = (int) $line->id;
                    continue;
                }

                $createPayload = [
                    'school_id' => $schoolId,
                    'rental_reservation_id' => $reservationId,
                    'item_id' => $lineData['item_id'],
                    'variant_id' => $lineData['variant_id'],
                    'quantity' => $lineData['quantity'],
                    'unit_price' => $lineData['unit_price'],
                    'line_total' => $lineData['line_total'],
                ];
                foreach (['period_type', 'start_date', 'end_date', 'start_time', 'end_time', 'meta'] as $optionalColumn) {
                    if (isset($lineColumnSet[$optionalColumn])) {
                        $createPayload[$optionalColumn] = $lineData[$optionalColumn] ?? null;
                    }
                }

                $line = RentalReservationLine::create($createPayload);
                $keepLineIds[] = (int) $line->id;
            }

            $removeQuery = RentalReservationLine::where('rental_reservation_id', $reservationId)
                ->when($schoolId, fn($query) => $query->where('school_id', $schoolId));

            if (!empty($keepLineIds)) {
                $removeQuery->whereNotIn('id', $keepLineIds);
            }

            $removeLineIds = $removeQuery->pluck('id')->map(fn($id) => (int) $id)->all();
            if (!empty($removeLineIds) && Schema::hasTable('rental_reservation_unit_assignments')) {
                RentalReservationUnitAssignment::whereIn('rental_reservation_line_id', $removeLineIds)->delete();
            }
            if (!empty($removeLineIds)) {
                RentalReservationLine::whereIn('id', $removeLineIds)->delete();
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            return $this->sendError('Could not apply reservation items: ' . $e->getMessage(), [], 422);
        }

        return true;
    }

    public function assignUnits(Request $request, int $id)
    {
        return $this->storeAssignments($request, $id, 'assigned');
    }

    public function returnUnits(Request $request, int $id)
    {
        return $this->storeAssignments($request, $id, 'returned');
    }

    public function autoAssignUnits(Request $request, int $id)
    {
        if (!Schema::hasTable('rental_reservation_unit_assignments')) {
            return $this->tableMissingResponse('rental_reservation_unit_assignments');
        }

        if (!Schema::hasTable('rental_reservation_lines') || !Schema::hasTable('rental_units')) {
            return $this->sendError('Required rental tables are missing', [], 422);
        }

        $schoolId = $this->getSchoolId($request);
        $lines = RentalReservationLine::where('rental_reservation_id', $id)
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->get();

        if ($lines->isEmpty()) {
            return $this->sendError('Reservation has no lines', [], 422);
        }

        $processed = 0;
        DB::beginTransaction();
        try {
            foreach ($lines as $line) {
                $needed = max(0, $line->quantity - $this->getCurrentlyAssignedQty($id, $line->id));
                if ($needed <= 0 || empty($line->variant_id)) {
                    continue;
                }

                $units = RentalUnit::where('variant_id', $line->variant_id)
                    ->where('status', 'available')
                    ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
                    ->lockForUpdate()
                    ->orderBy('id')
                    ->limit($needed)
                    ->get();

                foreach ($units as $unit) {
                    RentalReservationUnitAssignment::create([
                        'school_id'                  => $schoolId,
                        'rental_reservation_id'      => $id,
                        'rental_reservation_line_id' => $line->id,
                        'rental_unit_id'             => $unit->id,
                        'assignment_type'            => 'assigned',
                        'assigned_at'                => now(),
                        'notes'                      => 'Auto-assigned',
                    ]);

                    $unit->update(['status' => 'assigned']);
                    $this->stockMovementService->log([
                        'school_id' => (int) ($schoolId ?? $unit->school_id),
                        'rental_reservation_id' => $id,
                        'rental_reservation_line_id' => (int) $line->id,
                        'rental_unit_id' => (int) $unit->id,
                        'variant_id' => (int) ($line->variant_id ?? $unit->variant_id),
                        'item_id' => (int) ($line->item_id ?? 0),
                        'warehouse_id_from' => (int) ($unit->warehouse_id ?? 0),
                        'movement_type' => 'assign',
                        'reason' => 'auto_assign',
                        'payload' => ['source' => 'auto_assign'],
                    ]);
                    $processed++;
                }
            }

            $this->syncReservationTotalsAndStatus($id);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            return $this->sendError('Auto-assign failed: ' . $e->getMessage(), [], 500);
        }

        return $this->sendResponse([
            'reservation_id' => $id,
            'assigned' => $processed,
        ], 'Auto-assign processed');
    }

    public function registerDamage(Request $request, int $id)
    {
        if (!Schema::hasTable('rental_reservation_unit_assignments')) {
            return $this->tableMissingResponse('rental_reservation_unit_assignments');
        }

        $reservation = RentalReservation::find($id);
        if (!$reservation) {
            return $this->sendError('Reservation not found', [], 404);
        }

        $assignmentId = (int) $request->input('assignment_id', 0);
        if ($assignmentId <= 0) {
            return $this->sendError('assignment_id is required', [], 422);
        }

        $damageCost = (float) $request->input('damage_cost', 0);
        $schoolId   = $this->getSchoolId($request);

        RentalReservationUnitAssignment::where('id', $assignmentId)
            ->where('rental_reservation_id', $id)
            ->update([
                'condition_out' => $request->input('condition', 'damaged'),
                'notes'         => $request->input('notes'),
            ]);

        if ($damageCost > 0) {
            $reservation->increment('damage_total', $damageCost);
        }

        $lineId = (int) $request->input('line_id', 0);
        if ($lineId > 0) {
            RentalReservationLine::where('id', $lineId)->update(['damage_notes' => $request->input('notes')]);
        }

        $assignment = RentalReservationUnitAssignment::query()
            ->where('id', $assignmentId)
            ->where('rental_reservation_id', $id)
            ->first();
        if ($assignment && (int) ($assignment->rental_unit_id ?? 0) > 0) {
            $unit = RentalUnit::query()->find((int) $assignment->rental_unit_id);
            $this->stockMovementService->log([
                'school_id' => (int) ($schoolId ?? $reservation->school_id),
                'rental_reservation_id' => $id,
                'rental_reservation_line_id' => (int) ($assignment->rental_reservation_line_id ?? 0),
                'rental_unit_id' => (int) $assignment->rental_unit_id,
                'variant_id' => (int) ($unit->variant_id ?? 0),
                'item_id' => (int) ($lineId > 0 ? RentalReservationLine::where('id', $lineId)->value('item_id') : 0),
                'warehouse_id_from' => (int) ($unit->warehouse_id ?? 0),
                'movement_type' => 'damage',
                'reason' => (string) $request->input('notes', 'damage_registered'),
                'payload' => [
                    'assignment_id' => $assignmentId,
                    'damage_cost' => $damageCost,
                    'condition' => $request->input('condition', 'damaged'),
                ],
            ]);
        }

        RentalEvent::log($id, $schoolId ?? $reservation->school_id, 'damage_registered', [
            'assignment_id' => $assignmentId,
            'damage_cost' => $damageCost,
            'condition' => $request->input('condition', 'damaged'),
        ], $request);

        // Notify school admin via email
        if ($damageCost > 0) {
            try {
                app(RentalNotificationService::class)->sendDamage(
                    $id,
                    $damageCost,
                    $request->input('notes', '')
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('RENTAL_DAMAGE_NOTIFY_SKIPPED', [
                    'reservation_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->sendSuccess('Damage registered');
    }

    public function cancel(Request $request, int $id)
    {
        if (!Schema::hasTable('rental_reservations')) {
            return $this->tableMissingResponse('rental_reservations');
        }

        $reservation = RentalReservation::find($id);
        if (!$reservation) {
            return $this->sendError('Reservation not found', [], 404);
        }

        $status = strtolower((string) $reservation->status);
        if (in_array($status, [self::STATUS_RETURNED, self::STATUS_COMPLETED, 'cancelled'], true)) {
            return $this->sendError('Cannot cancel a reservation with status: ' . $status, [], 422);
        }
        if (in_array($status, [self::STATUS_CHECKED_OUT, self::STATUS_ASSIGNED, self::STATUS_PARTIAL_RETURN], true)) {
            return $this->sendError('Reservation is active — process return before cancelling', [], 422);
        }

        $schoolId = $this->getSchoolId($request);
        $reason   = (string) $request->input('cancellation_reason', '');

        $reservation->update([
            'status'              => 'cancelled',
            'cancelled_at'        => now(),
            'cancellation_reason' => $reason ?: null,
        ]);

        RentalEvent::log($id, $schoolId ?? $reservation->school_id, 'cancelled', [
            'reason' => $reason,
            'previous_status' => $status,
        ], $request);

        try {
            app(RentalNotificationService::class)->sendCancellation($id, $reason);
        } catch (\Exception $e) {
            Log::warning('RENTAL_CANCEL_NOTIFY_FAILED', [
                'reservation_id' => $id,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->show($request, $id);
    }

    public function linkBooking(Request $request, int $id)
    {
        if (!Schema::hasTable('rental_reservations')) {
            return $this->tableMissingResponse('rental_reservations');
        }
        if (!Schema::hasTable('bookings')) {
            return $this->tableMissingResponse('bookings');
        }
        if (!Schema::hasColumn('rental_reservations', 'booking_id')) {
            return $this->sendError('booking_id column is missing on rental_reservations', [], 422);
        }

        $bookingId = (int) $request->input('booking_id', 0);
        if ($bookingId <= 0) {
            return $this->sendError('booking_id is required', [], 422);
        }

        $schoolId = $this->getSchoolId($request);
        $reservation = RentalReservation::query()
            ->when($schoolId, fn($query) => $query->where('school_id', $schoolId))
            ->find($id);

        if (!$reservation) {
            return $this->sendError('Reservation not found', [], 404);
        }

        $booking = Booking::query()
            ->when($schoolId, fn($query) => $query->where('school_id', $schoolId))
            ->find($bookingId);

        if (!$booking) {
            return $this->sendError('Booking not found', [], 404);
        }

        if ((int) $reservation->school_id !== (int) $booking->school_id) {
            return $this->sendError('Reservation and booking must belong to the same school', [], 422);
        }

        $reservation->update([
            'booking_id' => $booking->id,
        ]);

        $this->logEvent($reservation->id, $reservation->school_id, 'booking_linked', [
            'booking_id' => $booking->id,
            'previous_booking_id' => $reservation->getOriginal('booking_id'),
        ], $request);

        return $this->show($request, $reservation->id);
    }

    public function unlinkBooking(Request $request, int $id)
    {
        if (!Schema::hasTable('rental_reservations')) {
            return $this->tableMissingResponse('rental_reservations');
        }
        if (!Schema::hasColumn('rental_reservations', 'booking_id')) {
            return $this->sendError('booking_id column is missing on rental_reservations', [], 422);
        }

        $schoolId = $this->getSchoolId($request);
        $reservation = RentalReservation::query()
            ->when($schoolId, fn($query) => $query->where('school_id', $schoolId))
            ->find($id);

        if (!$reservation) {
            return $this->sendError('Reservation not found', [], 404);
        }

        $previousBookingId = (int) ($reservation->booking_id ?? 0);
        if ($previousBookingId <= 0) {
            return $this->show($request, $reservation->id);
        }

        $reservation->update([
            'booking_id' => null,
        ]);

        $this->logEvent($reservation->id, $reservation->school_id, 'booking_unlinked', [
            'previous_booking_id' => $previousBookingId,
        ], $request);

        return $this->show($request, $reservation->id);
    }

    public function events(Request $request, int $id)
    {
        if (!Schema::hasTable('rental_events')) {
            return $this->sendResponse([], 'No event log yet');
        }

        $events = RentalEvent::where('rental_reservation_id', $id)
            ->orderByDesc('created_at')
            ->get();

        return $this->sendResponse($events, 'Events retrieved successfully');
    }

    private function logEvent(int $reservationId, ?int $schoolId, string $eventType, array $payload, Request $request): void
    {
        RentalEvent::log($reservationId, $schoolId ?? 0, $eventType, $payload);
    }

    private function storeAssignments(Request $request, int $reservationId, string $event): \Illuminate\Http\JsonResponse
    {
        if (!Schema::hasTable('rental_reservation_unit_assignments')) {
            return $this->tableMissingResponse('rental_reservation_unit_assignments');
        }

        $rows = $request->input('assignments', []);
        if ((!is_array($rows) || empty($rows)) && $event === 'returned') {
            $returnLines = $request->input('return_lines', []);
            if (is_array($returnLines) && !empty($returnLines)) {
                $rows = $this->buildReturnAssignmentsFromLines($reservationId, $returnLines);
            } else {
                $lineId = (int) $request->input('line_id', 0);
                $quantity = (int) $request->input('quantity', 0);
                if ($lineId > 0) {
                    $rows = $this->buildReturnAssignmentsFromLines($reservationId, [[
                        'line_id' => $lineId,
                        'quantity' => max(1, $quantity),
                        'notes' => $request->input('notes'),
                    ]]);
                } else {
                    $rows = $this->buildReturnAssignmentsFromCurrent($reservationId);
                }
            }
        }
        if (!is_array($rows) || empty($rows)) {
            return $this->sendError('assignments is required', [], 422);
        }

        $schoolId = $this->getSchoolId($request);
        $inserted = 0;
        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $payload = [
                'school_id'                  => $schoolId,
                'rental_reservation_id'      => $reservationId,
                'rental_reservation_line_id' => $row['line_id'] ?? null,
                'rental_unit_id'             => $row['unit_id'] ?? null,
                'assignment_type'            => $event,
                'assigned_at'                => now(),
                'returned_at'                => $event === 'returned' ? now() : null,
                'notes'                      => $row['notes'] ?? null,
            ];

                $unitId = (int) ($row['unit_id'] ?? 0);
                if ($unitId > 0) {
                    $unit = RentalUnit::query()
                        ->lockForUpdate()
                        ->find($unitId);
                    if ($unit) {
                        if ($event === 'assigned' && !$this->unitCanBeAssigned($unit)) {
                            throw new \RuntimeException("Unit {$unitId} is no longer available for assignment");
                        }
                        if ($event === 'assigned' && $this->unitHasActiveAssignment($unitId)) {
                            throw new \RuntimeException("Unit {$unitId} is already actively assigned");
                        }

                        $variantId = (int) ($row['variant_id'] ?? $unit->variant_id ?? 0);
                        $itemId = (int) ($row['item_id'] ?? 0);
                        if ($itemId <= 0 && (int) $variantId > 0) {
                            $itemId = (int) (RentalVariant::query()->where('id', $variantId)->value('item_id') ?? 0);
                        }

                        RentalReservationUnitAssignment::create($payload);
                        $inserted++;

                        RentalUnit::where('id', $unitId)->update(['status' => $event === 'returned' ? 'available' : 'assigned']);
                        $this->stockMovementService->log([
                            'school_id' => (int) ($schoolId ?? $unit->school_id),
                            'rental_reservation_id' => $reservationId,
                            'rental_reservation_line_id' => (int) ($row['line_id'] ?? 0),
                            'rental_unit_id' => $unitId,
                            'variant_id' => $variantId,
                            'item_id' => $itemId,
                            'warehouse_id_from' => (int) ($unit->warehouse_id ?? 0),
                            'warehouse_id_to' => (int) ($unit->warehouse_id ?? 0),
                            'movement_type' => $event === 'returned' ? 'return' : 'assign',
                            'reason' => $row['notes'] ?? ($event === 'returned' ? 'manual_return' : 'manual_assign'),
                            'payload' => ['assignment_type' => $event],
                        ]);
                    }
                } else {
                    RentalReservationUnitAssignment::create($payload);
                    $inserted++;
                }
            }

            $this->syncReservationTotalsAndStatus($reservationId);
            DB::commit();

            // Send returned notification if this was a return event and reservation is now completed/returned
            if ($event === 'returned') {
                try {
                    $newStatus = RentalReservation::where('id', $reservationId)->value('status');
                    if (in_array($newStatus, [self::STATUS_RETURNED, self::STATUS_COMPLETED], true)) {
                        app(RentalNotificationService::class)->sendReturned($reservationId);
                    }
                } catch (\Throwable $notifyEx) {
                    \Illuminate\Support\Facades\Log::warning('RENTAL_RETURNED_MAIL_SKIPPED', [
                        'reservation_id' => $reservationId,
                        'error' => $notifyEx->getMessage(),
                    ]);
                }
            }
        } catch (Throwable $e) {
            DB::rollBack();
            return $this->sendError('Assignments failed: ' . $e->getMessage(), [], 500);
        }

        return $this->sendResponse([
            'reservation_id' => $reservationId,
            'processed' => $inserted,
            'event' => $event,
        ], 'Assignments processed');
    }

    private function unitCanBeAssigned(RentalUnit $unit): bool
    {
        if ((string) ($unit->status ?? '') !== 'available') {
            return false;
        }

        if (Schema::hasColumn('rental_units', 'blocked_until') && !empty($unit->blocked_until)) {
            try {
                return \Carbon\Carbon::parse($unit->blocked_until)->isPast();
            } catch (\Throwable $e) {
                return false;
            }
        }

        return true;
    }

    private function unitHasActiveAssignment(int $unitId): bool
    {
        return RentalReservationUnitAssignment::query()
            ->where('rental_unit_id', $unitId)
            ->whereNull('returned_at')
            ->exists();
    }

    private function buildReturnAssignmentsFromCurrent(int $reservationId): array
    {
        $rows = [];
        $lines = RentalReservationLine::where('rental_reservation_id', $reservationId)->get(['id']);

        foreach ($lines as $line) {
            foreach ($this->getCurrentlyAssignedUnitIds($reservationId, $line->id) as $unitId) {
                $rows[] = ['line_id' => $line->id, 'unit_id' => $unitId];
            }
        }

        return $rows;
    }

    private function buildReturnAssignmentsFromLines(int $reservationId, array $returnLines): array
    {
        if (!Schema::hasTable('rental_reservation_unit_assignments')) {
            return [];
        }

        $rows = [];
        foreach ($returnLines as $line) {
            if (!is_array($line)) {
                continue;
            }

            $lineId = (int) ($line['line_id'] ?? 0);
            if ($lineId <= 0) {
                continue;
            }

            $quantity = (int) ($line['quantity'] ?? 0);
            $availableUnits = $this->getCurrentlyAssignedUnitIds($reservationId, $lineId);
            if (empty($availableUnits)) {
                continue;
            }

            $unitsToReturn = $quantity > 0
                ? array_slice($availableUnits, 0, $quantity)
                : $availableUnits;

            foreach ($unitsToReturn as $unitId) {
                $rows[] = [
                    'line_id' => $lineId,
                    'unit_id' => $unitId,
                    'notes' => $line['notes'] ?? null,
                ];
            }
        }

        return $rows;
    }

    private function getCurrentlyAssignedUnitIds(int $reservationId, int $lineId): array
    {
        $base = RentalReservationUnitAssignment::where('rental_reservation_id', $reservationId)
            ->where('rental_reservation_line_id', $lineId);

        $assigned = (clone $base)->whereIn('assignment_type', ['assigned', 'checked_out'])
            ->pluck('rental_unit_id')->filter()->map(fn($id) => (int) $id)->all();

        $returned = (clone $base)->where('assignment_type', 'returned')
            ->pluck('rental_unit_id')->filter()->map(fn($id) => (int) $id)->all();

        return array_values(array_diff($assigned, $returned));
    }

    private function getCurrentlyAssignedQty(int $reservationId, int $lineId): int
    {
        $qty = 0;
        RentalReservationUnitAssignment::where('rental_reservation_id', $reservationId)
            ->where('rental_reservation_line_id', $lineId)
            ->orderBy('id')
            ->pluck('assignment_type')
            ->each(function ($type) use (&$qty) {
                $t = strtolower($type ?? '');
                if (in_array($t, ['assigned', 'checked_out'], true)) $qty++;
                elseif ($t === 'returned') $qty--;
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

    private function hasEnoughAssignedUnits(int $reservationId): bool
    {
        $lines = RentalReservationLine::where('rental_reservation_id', $reservationId)->get(['id', 'quantity']);

        foreach ($lines as $line) {
            if ($line->quantity <= 0) continue;
            if ($this->getCurrentlyAssignedQty($reservationId, $line->id) < $line->quantity) {
                return false;
            }
        }

        return true;
    }

    private function syncReservationTotalsAndStatus(int $reservationId): void
    {
        $lineColumnSet = array_flip(Schema::getColumnListing('rental_reservation_lines'));
        $lines = RentalReservationLine::where('rental_reservation_id', $reservationId)
            ->get(['id', 'line_total', 'quantity']);

        if ($lines->isEmpty()) {
            return;
        }

        $subtotal      = (float) $lines->sum('line_total');
        $requiredUnits = (int)   $lines->sum('quantity');
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
            'total'    => $subtotal - (float) $reservation->discount_total + (float) $reservation->tax_total,
            'status'   => $status,
        ]);
    }

    private function pickupPointExistsForSchool(int $pickupPointId, ?int $schoolId): bool
    {
        if ($pickupPointId <= 0) {
            return false;
        }

        return RentalPickupPoint::where('id', $pickupPointId)
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->exists();
    }

    private function buildFinancialReconciliation(int $reservationId, ?float $oldTotal = null): array
    {
        $reservation = RentalReservation::find($reservationId);
        if (!$reservation) {
            return [
                'old_total' => 0.0,
                'new_total' => 0.0,
                'delta_total' => 0.0,
                'paid_total' => 0.0,
                'balance_due' => 0.0,
                'overpaid_amount' => 0.0,
                'deposit_amount' => 0.0,
                'deposit_status' => 'none',
                'action_required' => 'none',
                'allowed_actions' => [],
            ];
        }

        $newTotal = (float) ($reservation->total ?? 0);
        $oldTotalValue = $oldTotal === null ? $newTotal : (float) $oldTotal;
        $deltaTotal = round($newTotal - $oldTotalValue, 2);

        $paymentQuery = Payment::query()
            ->where('rental_reservation_id', $reservationId)
            ->whereNull('deleted_at');

        $paidTotal = (float) (clone $paymentQuery)
            ->where('status', 'paid')
            ->where(function ($query) {
                $query->whereNull('payment_type')
                    ->orWhere('payment_type', '!=', 'deposit');
            })
            ->sum('amount');

        $depositAmount = (float) ($reservation->deposit_amount ?? 0);
        $depositStatus = (string) ($reservation->deposit_status ?? 'none');
        $balanceDue = round(max(0, $newTotal - $paidTotal), 2);
        $overpaidAmount = round(max(0, $paidTotal - $newTotal), 2);

        $actionRequired = 'none';
        $allowedActions = [];
        if ($balanceDue > 0.0) {
            $actionRequired = 'collect_additional_payment';
            $allowedActions = ['add_payment', 'create_paylink'];
        } elseif ($overpaidAmount > 0.0) {
            $actionRequired = 'resolve_overpayment';
            $allowedActions = ['refund', 'keep_credit'];
        }

        return [
            'old_total' => round($oldTotalValue, 2),
            'new_total' => round($newTotal, 2),
            'delta_total' => $deltaTotal,
            'paid_total' => round($paidTotal, 2),
            'balance_due' => $balanceDue,
            'overpaid_amount' => $overpaidAmount,
            'deposit_amount' => round($depositAmount, 2),
            'deposit_status' => $depositStatus,
            'action_required' => $actionRequired,
            'allowed_actions' => $allowedActions,
        ];
    }
}
