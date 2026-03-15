<?php

namespace App\Http\Controllers\Admin;

use App\Models\RentalUnit;
use App\Services\RentalStockMovementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RentalUnitController extends RentalBaseController
{
    public function __construct(private readonly RentalStockMovementService $stockMovementService)
    {
    }

    public function index(Request $request)
    {
        return $this->indexByTable($request, 'rental_units', [
            'variant_id' => $request->input('variant_id'),
            'warehouse_id' => $request->input('warehouse_id'),
            'status' => $request->input('status'),
        ]);
    }

    public function store(Request $request)
    {
        $response = $this->storeByTable($request, 'rental_units', [
            'school_id', 'variant_id', 'warehouse_id', 'serial', 'status', 'condition', 'notes',
        ]);

        $payload = $response->getData(true);
        if (!empty($payload['success']) && !empty($payload['data']['id'])) {
            $row = $payload['data'];
            $this->stockMovementService->log([
                'school_id' => (int) ($row['school_id'] ?? 0),
                'rental_unit_id' => (int) ($row['id'] ?? 0),
                'variant_id' => (int) ($row['variant_id'] ?? 0),
                'warehouse_id_to' => (int) ($row['warehouse_id'] ?? 0),
                'movement_type' => 'manual_adjustment',
                'reason' => 'unit_created',
                'payload' => ['status' => $row['status'] ?? null],
            ]);
        }

        return $response;
    }

    public function update(Request $request, int $id)
    {
        if (!Schema::hasTable('rental_units')) {
            return $this->tableMissingResponse('rental_units');
        }

        $schoolId = $this->getSchoolId($request);
        $before = RentalUnit::query()
            ->when($schoolId, fn ($query) => $query->where('school_id', $schoolId))
            ->find($id);

        if (!$before) {
            return $this->sendError('Not found', [], 404);
        }

        $response = $this->updateByTable($request, 'rental_units', $id, [
            'variant_id', 'warehouse_id', 'serial', 'status', 'condition', 'notes',
        ]);

        $payload = $response->getData(true);
        if (!empty($payload['success']) && !empty($payload['data']['id'])) {
            $row = $payload['data'];
            $afterStatus = strtolower((string) ($row['status'] ?? ''));
            $beforeStatus = strtolower((string) ($before->status ?? ''));

            if ($beforeStatus !== $afterStatus || (int) $before->warehouse_id !== (int) ($row['warehouse_id'] ?? 0)) {
                $movementType = 'manual_adjustment';
                $reason = 'unit_updated';
                if ($beforeStatus !== 'maintenance' && $afterStatus === 'maintenance') {
                    $movementType = 'maintenance_set';
                    $reason = 'manual_maintenance_set';
                } elseif ($beforeStatus === 'maintenance' && $afterStatus !== 'maintenance') {
                    $movementType = 'maintenance_released';
                    $reason = 'manual_maintenance_released';
                }

                $this->stockMovementService->log([
                    'school_id' => (int) ($row['school_id'] ?? $before->school_id),
                    'rental_unit_id' => (int) ($row['id'] ?? $before->id),
                    'variant_id' => (int) ($row['variant_id'] ?? $before->variant_id),
                    'warehouse_id_from' => (int) ($before->warehouse_id ?? 0),
                    'warehouse_id_to' => (int) ($row['warehouse_id'] ?? 0),
                    'movement_type' => $movementType,
                    'reason' => $reason,
                    'payload' => [
                        'before_status' => $beforeStatus,
                        'after_status' => $afterStatus,
                        'before_condition' => $before->condition,
                        'after_condition' => $row['condition'] ?? null,
                    ],
                ]);
            }
        }

        return $response;
    }

    public function setMaintenance(Request $request, int $id)
    {
        if (!Schema::hasTable('rental_units')) {
            return $this->tableMissingResponse('rental_units');
        }

        $reason = trim((string) $request->input('reason', ''));
        if ($reason === '') {
            return $this->sendError('reason is required', [], 422);
        }

        $schoolId = $this->getSchoolId($request);
        $unit = RentalUnit::query()
            ->when($schoolId, fn ($query) => $query->where('school_id', $schoolId))
            ->find($id);

        if (!$unit) {
            return $this->sendError('Not found', [], 404);
        }

        if (strtolower((string) $unit->status) === 'maintenance') {
            return $this->sendError('Unit is already in maintenance', [], 422);
        }

        $previousStatus = strtolower((string) $unit->status);
        $unit->update([
            'status' => 'maintenance',
            'notes' => $reason,
            'condition' => $request->input('condition', $unit->condition),
        ]);

        $this->stockMovementService->log([
            'school_id' => (int) $unit->school_id,
            'rental_unit_id' => (int) $unit->id,
            'variant_id' => (int) $unit->variant_id,
            'warehouse_id_from' => (int) ($unit->warehouse_id ?? 0),
            'warehouse_id_to' => (int) ($unit->warehouse_id ?? 0),
            'movement_type' => 'maintenance_set',
            'reason' => $reason,
            'payload' => [
                'previous_status' => $previousStatus,
                'condition' => $unit->condition,
            ],
        ]);

        return $this->sendResponse($unit->fresh(), 'Unit moved to maintenance');
    }

    public function releaseMaintenance(Request $request, int $id)
    {
        if (!Schema::hasTable('rental_units')) {
            return $this->tableMissingResponse('rental_units');
        }

        $reason = trim((string) $request->input('reason', ''));
        if ($reason === '') {
            return $this->sendError('reason is required', [], 422);
        }

        $schoolId = $this->getSchoolId($request);
        $unit = RentalUnit::query()
            ->when($schoolId, fn ($query) => $query->where('school_id', $schoolId))
            ->find($id);

        if (!$unit) {
            return $this->sendError('Not found', [], 404);
        }

        if (strtolower((string) $unit->status) !== 'maintenance') {
            return $this->sendError('Unit is not in maintenance', [], 422);
        }

        $unit->update([
            'status' => 'available',
            'notes' => $reason,
            'condition' => $request->input('condition', $unit->condition),
        ]);

        $this->stockMovementService->log([
            'school_id' => (int) $unit->school_id,
            'rental_unit_id' => (int) $unit->id,
            'variant_id' => (int) $unit->variant_id,
            'warehouse_id_from' => (int) ($unit->warehouse_id ?? 0),
            'warehouse_id_to' => (int) ($unit->warehouse_id ?? 0),
            'movement_type' => 'maintenance_released',
            'reason' => $reason,
            'payload' => [
                'new_status' => 'available',
                'condition' => $unit->condition,
            ],
        ]);

        return $this->sendResponse($unit->fresh(), 'Unit released from maintenance');
    }

    public function maintenanceHistory(Request $request, int $id)
    {
        if (!Schema::hasTable('rental_stock_movements')) {
            return $this->tableMissingResponse('rental_stock_movements');
        }

        $schoolId = $this->getSchoolId($request);
        $actorNameSql = "COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), NULLIF(u.username, ''), NULLIF(u.email, ''), CONCAT('User #', u.id))";
        $history = DB::table('rental_stock_movements as rsm')
            ->select([
                'rsm.*',
                DB::raw("$actorNameSql as actor_name"),
            ])
            ->leftJoin('users as u', 'u.id', '=', 'rsm.user_id')
            ->where('rsm.rental_unit_id', $id)
            ->when($schoolId, fn ($query) => $query->where('rsm.school_id', $schoolId))
            ->whereIn('rsm.movement_type', ['maintenance_set', 'maintenance_released', 'damage'])
            ->orderByDesc('rsm.occurred_at')
            ->orderByDesc('rsm.id')
            ->get();

        return $this->sendResponse($history, 'Data retrieved successfully');
    }

    public function destroy(Request $request, int $id)
    {
        if (!Schema::hasTable('rental_units')) {
            return $this->tableMissingResponse('rental_units');
        }

        $schoolId = $this->getSchoolId($request);
        $before = RentalUnit::query()
            ->when($schoolId, fn ($query) => $query->where('school_id', $schoolId))
            ->find($id);

        $response = $this->destroyByTable($request, 'rental_units', $id);
        $payload = $response->getData(true);
        if (!empty($payload['success']) && $before) {
            $this->stockMovementService->log([
                'school_id' => (int) $before->school_id,
                'rental_unit_id' => (int) $before->id,
                'variant_id' => (int) $before->variant_id,
                'warehouse_id_from' => (int) ($before->warehouse_id ?? 0),
                'movement_type' => 'manual_adjustment',
                'reason' => 'unit_deleted',
                'payload' => ['status' => $before->status],
            ]);
        }

        return $response;
    }
}
