<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RentalModelController extends RentalBaseController
{
    public function index(Request $request)
    {
        if (!Schema::hasTable('rental_models')) {
            return $this->tableMissingResponse('rental_models');
        }

        $schoolId = $this->getSchoolId($request);
        $query = DB::table('rental_models as m')
            ->leftJoin('rental_brands as b', 'b.id', '=', 'm.brand_id')
            ->select('m.*', 'b.name as brand_name');

        if ($schoolId && Schema::hasColumn('rental_models', 'school_id')) {
            $query->where('m.school_id', $schoolId);
        }
        if (Schema::hasColumn('rental_models', 'deleted_at')) {
            $query->whereNull('m.deleted_at');
        }
        if (Schema::hasColumn('rental_brands', 'deleted_at')) {
            $query->where(function ($inner) {
                $inner->whereNull('b.id')->orWhereNull('b.deleted_at');
            });
        }

        $brandId = (int) $request->input('brand_id', 0);
        if ($brandId > 0 && Schema::hasColumn('rental_models', 'brand_id')) {
            $query->where('m.brand_id', $brandId);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('m.name', 'like', '%' . $search . '%')
                    ->orWhere('b.name', 'like', '%' . $search . '%');
            });
        }

        $query->orderBy('b.name')->orderBy('m.name')->orderByDesc('m.id');
        $perPage = (int) $request->input('per_page', 100);
        $rows = $query->paginate(max(1, min(1000, $perPage)));
        return $this->sendResponse($rows, 'Data retrieved successfully');
    }

    public function show(Request $request, int $id)
    {
        return $this->showByTable($request, 'rental_models', $id);
    }

    public function store(Request $request)
    {
        if (!Schema::hasTable('rental_models')) {
            return $this->tableMissingResponse('rental_models');
        }

        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            return $this->sendError('name is required', [], 422);
        }

        $schoolId = $this->getSchoolId($request);
        $brandId = (int) $request->input('brand_id', 0);
        if ($brandId <= 0) {
            $brandId = null;
        }

        $query = DB::table('rental_models')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);
        if ($brandId) {
            $query->where('brand_id', $brandId);
        } else {
            $query->whereNull('brand_id');
        }
        if ($schoolId && Schema::hasColumn('rental_models', 'school_id')) {
            $query->where('school_id', $schoolId);
        }

        $existing = $query->first();
        if ($existing) {
            if (Schema::hasColumn('rental_models', 'deleted_at') && !empty($existing->deleted_at)) {
                DB::table('rental_models')->where('id', $existing->id)->update([
                    'deleted_at' => null,
                    'active' => true,
                    'updated_at' => now(),
                ]);
                $existing = DB::table('rental_models')->where('id', $existing->id)->first();
            }
            return $this->sendResponse($existing, 'Model already exists');
        }

        $payload = [
            'brand_id' => $brandId,
            'name' => $name,
            'slug' => Str::slug($name) ?: null,
            'active' => $request->boolean('active', true),
        ];
        if ($schoolId && Schema::hasColumn('rental_models', 'school_id')) {
            $payload['school_id'] = $schoolId;
        }
        if (Schema::hasColumn('rental_models', 'created_at')) {
            $payload['created_at'] = now();
        }
        if (Schema::hasColumn('rental_models', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        $id = DB::table('rental_models')->insertGetId($payload);
        $row = DB::table('rental_models')->where('id', $id)->first();
        return $this->sendResponse($row, 'Created successfully');
    }

    public function update(Request $request, int $id)
    {
        if (!Schema::hasTable('rental_models')) {
            return $this->tableMissingResponse('rental_models');
        }

        $payload = [];
        if ($request->has('brand_id')) {
            $brandId = (int) $request->input('brand_id', 0);
            $payload['brand_id'] = $brandId > 0 ? $brandId : null;
        }
        if ($request->has('name')) {
            $name = trim((string) $request->input('name', ''));
            if ($name === '') {
                return $this->sendError('name is required', [], 422);
            }
            $payload['name'] = $name;
            $payload['slug'] = Str::slug($name) ?: null;
        }
        if ($request->has('active')) {
            $payload['active'] = $request->boolean('active');
        }
        if (empty($payload)) {
            return $this->sendError('No fields to update', [], 422);
        }
        if (Schema::hasColumn('rental_models', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        $query = DB::table('rental_models')->where('id', $id);
        $schoolId = $this->getSchoolId($request);
        if ($schoolId && Schema::hasColumn('rental_models', 'school_id')) {
            $query->where('school_id', $schoolId);
        }
        if (Schema::hasColumn('rental_models', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $updated = $query->update($payload);
        if (!$updated) {
            return $this->sendError('Not found', [], 404);
        }
        $row = DB::table('rental_models')->where('id', $id)->first();
        return $this->sendResponse($row, 'Updated successfully');
    }

    public function destroy(Request $request, int $id)
    {
        return $this->destroyByTable($request, 'rental_models', $id);
    }
}

