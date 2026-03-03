<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RentalReservationController extends RentalBaseController
{
    public function index(Request $request)
    {
        return $this->indexByTable($request, 'rental_reservations', [
            'status' => $request->input('status'),
            'booking_id' => $request->input('booking_id'),
            'client_id' => $request->input('client_id'),
        ]);
    }

    public function show(Request $request, int $id)
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
            $reservation['lines'] = DB::table('rental_reservation_lines')
                ->where('rental_reservation_id', $reservationId)
                ->orderBy('id')
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

        return $this->sendResponse($reservation, 'Data retrieved successfully');
    }

    public function store(Request $request)
    {
        if (!Schema::hasTable('rental_reservations')) {
            return $this->tableMissingResponse('rental_reservations');
        }

        $response = $this->storeByTable($request, 'rental_reservations', [
            'school_id',
            'booking_id',
            'client_id',
            'pickup_point_id',
            'warehouse_id',
            'start_date',
            'end_date',
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
        if (empty($payload['success']) || empty($payload['data']['id'])) {
            return $response;
        }

        $reservationId = (int) $payload['data']['id'];
        $lines = $request->input('lines', []);
        if (!is_array($lines) || empty($lines) || !Schema::hasTable('rental_reservation_lines')) {
            return $response;
        }

        $schoolId = $this->getSchoolId($request);
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $linePayload = [
                'rental_reservation_id' => $reservationId,
                'item_id' => $line['item_id'] ?? null,
                'variant_id' => $line['variant_id'] ?? null,
                'quantity' => $line['quantity'] ?? 1,
                'unit_price' => $line['unit_price'] ?? 0,
                'line_total' => $line['line_total'] ?? 0,
                'meta' => isset($line['meta']) ? (is_array($line['meta']) ? json_encode($line['meta']) : $line['meta']) : null,
            ];
            if ($schoolId && Schema::hasColumn('rental_reservation_lines', 'school_id')) {
                $linePayload['school_id'] = $schoolId;
            }
            if (Schema::hasColumn('rental_reservation_lines', 'created_at')) {
                $linePayload['created_at'] = now();
            }
            if (Schema::hasColumn('rental_reservation_lines', 'updated_at')) {
                $linePayload['updated_at'] = now();
            }
            DB::table('rental_reservation_lines')->insert($linePayload);
        }

        return $this->show($request, $reservationId);
    }

    public function update(Request $request, int $id)
    {
        return $this->updateByTable($request, 'rental_reservations', $id, [
            'pickup_point_id',
            'warehouse_id',
            'start_date',
            'end_date',
            'status',
            'currency',
            'subtotal',
            'discount_total',
            'tax_total',
            'total',
            'notes',
            'meta',
        ]);
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

        return $this->sendResponse([
            'reservation_id' => $id,
            'assigned' => 0,
            'message' => 'Auto-assign is enabled but no allocator is configured yet',
        ], 'Auto-assign processed');
    }

    public function registerDamage(Request $request, int $id)
    {
        if (!Schema::hasTable('rental_reservation_unit_assignments')) {
            return $this->tableMissingResponse('rental_reservation_unit_assignments');
        }

        $assignmentId = (int) $request->input('assignment_id', 0);
        if ($assignmentId <= 0) {
            return $this->sendError('assignment_id is required', [], 422);
        }

        $fields = [];
        if (Schema::hasColumn('rental_reservation_unit_assignments', 'condition_out')) {
            $fields['condition_out'] = $request->input('condition', 'damaged');
        }
        if (Schema::hasColumn('rental_reservation_unit_assignments', 'notes')) {
            $fields['notes'] = $request->input('notes');
        }
        if (Schema::hasColumn('rental_reservation_unit_assignments', 'updated_at')) {
            $fields['updated_at'] = now();
        }

        DB::table('rental_reservation_unit_assignments')
            ->where('id', $assignmentId)
            ->where('rental_reservation_id', $id)
            ->update($fields);

        return $this->sendSuccess('Damage registered');
    }

    private function storeAssignments(Request $request, int $reservationId, string $event): \Illuminate\Http\JsonResponse
    {
        if (!Schema::hasTable('rental_reservation_unit_assignments')) {
            return $this->tableMissingResponse('rental_reservation_unit_assignments');
        }

        $rows = $request->input('assignments', []);
        if (!is_array($rows) || empty($rows)) {
            return $this->sendError('assignments is required', [], 422);
        }

        $schoolId = $this->getSchoolId($request);
        $inserted = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $payload = [
                'rental_reservation_id' => $reservationId,
                'rental_reservation_line_id' => $row['line_id'] ?? null,
                'rental_unit_id' => $row['unit_id'] ?? null,
                'assignment_type' => $event,
                'assigned_at' => now(),
                'returned_at' => $event === 'returned' ? now() : null,
                'notes' => $row['notes'] ?? null,
            ];
            if ($schoolId && Schema::hasColumn('rental_reservation_unit_assignments', 'school_id')) {
                $payload['school_id'] = $schoolId;
            }
            if (Schema::hasColumn('rental_reservation_unit_assignments', 'created_at')) {
                $payload['created_at'] = now();
            }
            if (Schema::hasColumn('rental_reservation_unit_assignments', 'updated_at')) {
                $payload['updated_at'] = now();
            }

            DB::table('rental_reservation_unit_assignments')->insert($payload);
            $inserted++;
        }

        return $this->sendResponse([
            'reservation_id' => $reservationId,
            'processed' => $inserted,
            'event' => $event,
        ], 'Assignments processed');
    }
}

