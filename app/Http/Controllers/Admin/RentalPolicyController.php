<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RentalPolicyController extends RentalBaseController
{
    protected function shouldEnforceFeatureEnabled(Request $request): bool
    {
        return false;
    }

    public function show(Request $request)
    {
        if (!Schema::hasTable('rental_policies')) {
            return $this->tableMissingResponse('rental_policies');
        }

        $schoolId = $this->getSchoolId($request);
        $query = DB::table('rental_policies');
        if ($schoolId && Schema::hasColumn('rental_policies', 'school_id')) {
            $query->where('school_id', $schoolId);
        }
        $row = $query->orderByDesc('id')->first();
        return $this->sendResponse($this->normalizePolicyRow($row), 'Data retrieved successfully');
    }

    public function update(Request $request)
    {
        if (!Schema::hasTable('rental_policies')) {
            return $this->tableMissingResponse('rental_policies');
        }

        $schoolId = $this->getSchoolId($request);
        $payload = $request->only([
            'enabled',
            'default_deposit_mode',
            'default_deposit_value',
            'auto_assign_on_create',
            'allow_overbooking',
            'grace_minutes',
            'terms',
            'settings',
        ]);

        if ($schoolId && Schema::hasColumn('rental_policies', 'school_id')) {
            $payload['school_id'] = $schoolId;
        }
        if (array_key_exists('enabled', $payload) && Schema::hasColumn('rental_policies', 'enabled')) {
            $payload['enabled'] = $request->boolean('enabled') ? 1 : 0;
        } else {
            unset($payload['enabled']);
        }
        if (isset($payload['settings']) && is_array($payload['settings'])) {
            $payload['settings'] = json_encode($payload['settings']);
        }

        $existing = DB::table('rental_policies')
            ->when($schoolId && Schema::hasColumn('rental_policies', 'school_id'), function ($q) use ($schoolId) {
                $q->where('school_id', $schoolId);
            })
            ->first();

        if ($existing) {
            $payload['updated_at'] = now();
            DB::table('rental_policies')->where('id', $existing->id)->update($payload);
            $row = DB::table('rental_policies')->where('id', $existing->id)->first();
            return $this->sendResponse($this->normalizePolicyRow($row), 'Updated successfully');
        }

        $payload['created_at'] = now();
        $payload['updated_at'] = now();
        $id = DB::table('rental_policies')->insertGetId($payload);
        $row = DB::table('rental_policies')->where('id', $id)->first();
        return $this->sendResponse($this->normalizePolicyRow($row), 'Created successfully');
    }

    private function normalizePolicyRow($row)
    {
        if (!$row) {
            return [];
        }

        $data = (array) $row;
        if (array_key_exists('enabled', $data)) {
            $data['enabled'] = (bool) $data['enabled'];
        }

        return $data;
    }
}
