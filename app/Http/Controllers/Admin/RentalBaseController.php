<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class RentalBaseController extends AppBaseController
{
    protected function getSchoolId(Request $request): ?int
    {
        $schoolId = (int) $request->input('school_id', 0);
        if ($schoolId <= 0) {
            $school = $this->getSchool($request);
            $schoolId = $school ? (int) $school->id : 0;
        }

        if ($schoolId > 0 && !$this->isFeatureEnabledForSchool($schoolId)) {
            abort(403, 'Rental feature is disabled for this school');
        }

        return $schoolId > 0 ? $schoolId : null;
    }

    protected function isFeatureEnabledForSchool(int $schoolId): bool
    {
        $raw = (string) env('RENTAL_FEATURE_SCHOOL_IDS', '15');
        $ids = collect(explode(',', $raw))
            ->map(fn ($id) => (int) trim($id))
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        if (empty($ids)) {
            return false;
        }

        return in_array($schoolId, $ids, true);
    }

    protected function tableMissingResponse(string $table)
    {
        return $this->sendResponse([], "Rental table [$table] is not available yet");
    }

    protected function indexByTable(Request $request, string $table, array $filters = [])
    {
        if (!Schema::hasTable($table)) {
            return $this->tableMissingResponse($table);
        }

        $query = DB::table($table);
        $schoolId = $this->getSchoolId($request);
        if ($schoolId && Schema::hasColumn($table, 'school_id')) {
            $query->where('school_id', $schoolId);
        }
        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        foreach ($filters as $column => $value) {
            if ($value === null || $value === '' || !Schema::hasColumn($table, $column)) {
                continue;
            }
            $query->where($column, $value);
        }

        $query->orderByDesc('id');
        $perPage = (int) $request->input('per_page', 100);
        $data = $query->paginate(max(1, min(1000, $perPage)));
        return $this->sendResponse($data, 'Data retrieved successfully');
    }

    protected function showByTable(Request $request, string $table, int $id)
    {
        if (!Schema::hasTable($table)) {
            return $this->tableMissingResponse($table);
        }

        $query = DB::table($table)->where('id', $id);
        $schoolId = $this->getSchoolId($request);
        if ($schoolId && Schema::hasColumn($table, 'school_id')) {
            $query->where('school_id', $schoolId);
        }
        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $row = $query->first();
        if (!$row) {
            return $this->sendError('Not found', [], 404);
        }
        return $this->sendResponse($row, 'Data retrieved successfully');
    }

    protected function storeByTable(Request $request, string $table, array $allowed)
    {
        if (!Schema::hasTable($table)) {
            return $this->tableMissingResponse($table);
        }

        $payload = $request->only($allowed);
        $schoolId = $this->getSchoolId($request);
        if ($schoolId && Schema::hasColumn($table, 'school_id') && !isset($payload['school_id'])) {
            $payload['school_id'] = $schoolId;
        }
        if (Schema::hasColumn($table, 'created_at')) {
            $payload['created_at'] = now();
        }
        if (Schema::hasColumn($table, 'updated_at')) {
            $payload['updated_at'] = now();
        }

        $id = DB::table($table)->insertGetId($payload);
        $row = DB::table($table)->where('id', $id)->first();
        return $this->sendResponse($row, 'Created successfully');
    }

    protected function updateByTable(Request $request, string $table, int $id, array $allowed)
    {
        if (!Schema::hasTable($table)) {
            return $this->tableMissingResponse($table);
        }

        $payload = $request->only($allowed);
        if (empty($payload)) {
            return $this->sendError('No fields to update', [], 422);
        }
        if (Schema::hasColumn($table, 'updated_at')) {
            $payload['updated_at'] = now();
        }

        $query = DB::table($table)->where('id', $id);
        $schoolId = $this->getSchoolId($request);
        if ($schoolId && Schema::hasColumn($table, 'school_id')) {
            $query->where('school_id', $schoolId);
        }
        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $updated = $query->update($payload);
        if (!$updated) {
            return $this->sendError('Not found', [], 404);
        }

        $row = DB::table($table)->where('id', $id)->first();
        return $this->sendResponse($row, 'Updated successfully');
    }

    protected function destroyByTable(Request $request, string $table, int $id)
    {
        if (!Schema::hasTable($table)) {
            return $this->tableMissingResponse($table);
        }

        $query = DB::table($table)->where('id', $id);
        $schoolId = $this->getSchoolId($request);
        if ($schoolId && Schema::hasColumn($table, 'school_id')) {
            $query->where('school_id', $schoolId);
        }

        if (Schema::hasColumn($table, 'deleted_at')) {
            $deleted = $query->update(['deleted_at' => now(), 'updated_at' => now()]);
        } else {
            $deleted = $query->delete();
        }

        if (!$deleted) {
            return $this->sendError('Not found', [], 404);
        }
        return $this->sendSuccess('Deleted successfully');
    }
}
