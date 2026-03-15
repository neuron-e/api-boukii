<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RentalBrandController extends RentalBaseController
{
    public function index(Request $request)
    {
        if (!Schema::hasTable('rental_brands')) {
            return $this->tableMissingResponse('rental_brands');
        }

        $schoolId = $this->getSchoolId($request);
        $query = DB::table('rental_brands as b')->select('b.*');

        if ($schoolId && Schema::hasColumn('rental_brands', 'school_id')) {
            $query->where('b.school_id', $schoolId);
        }
        if (Schema::hasColumn('rental_brands', 'deleted_at')) {
            $query->whereNull('b.deleted_at');
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('b.name', 'like', '%' . $search . '%');
        }

        if (Schema::hasTable('rental_models')) {
            $query->addSelect(DB::raw('(
                select count(*)
                from rental_models rm
                where rm.brand_id = b.id
                ' . (Schema::hasColumn('rental_models', 'deleted_at') ? 'and rm.deleted_at is null' : '') . '
            ) as models_count'));
        } else {
            $query->addSelect(DB::raw('0 as models_count'));
        }

        $query->orderBy('b.name')->orderByDesc('b.id');
        $perPage = (int) $request->input('per_page', 100);
        $rows = $query->paginate(max(1, min(1000, $perPage)));
        return $this->sendResponse($rows, 'Data retrieved successfully');
    }

    public function show(Request $request, int $id)
    {
        return $this->showByTable($request, 'rental_brands', $id);
    }

    public function store(Request $request)
    {
        if (!Schema::hasTable('rental_brands')) {
            return $this->tableMissingResponse('rental_brands');
        }

        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            return $this->sendError('name is required', [], 422);
        }

        $schoolId = $this->getSchoolId($request);
        $query = DB::table('rental_brands')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);
        if ($schoolId && Schema::hasColumn('rental_brands', 'school_id')) {
            $query->where('school_id', $schoolId);
        }

        $existing = $query->first();
        if ($existing) {
            if (Schema::hasColumn('rental_brands', 'deleted_at') && !empty($existing->deleted_at)) {
                DB::table('rental_brands')->where('id', $existing->id)->update([
                    'deleted_at' => null,
                    'active' => true,
                    'updated_at' => now(),
                ]);
                $existing = DB::table('rental_brands')->where('id', $existing->id)->first();
            }
            return $this->sendResponse($existing, 'Brand already exists');
        }

        $payload = [
            'name' => $name,
            'slug' => Str::slug($name) ?: null,
            'active' => $request->boolean('active', true),
        ];
        if ($schoolId && Schema::hasColumn('rental_brands', 'school_id')) {
            $payload['school_id'] = $schoolId;
        }
        if (Schema::hasColumn('rental_brands', 'created_at')) {
            $payload['created_at'] = now();
        }
        if (Schema::hasColumn('rental_brands', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        $id = DB::table('rental_brands')->insertGetId($payload);
        $row = DB::table('rental_brands')->where('id', $id)->first();
        return $this->sendResponse($row, 'Created successfully');
    }

    public function update(Request $request, int $id)
    {
        if (!Schema::hasTable('rental_brands')) {
            return $this->tableMissingResponse('rental_brands');
        }

        $payload = [];
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
        if (Schema::hasColumn('rental_brands', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        $query = DB::table('rental_brands')->where('id', $id);
        $schoolId = $this->getSchoolId($request);
        if ($schoolId && Schema::hasColumn('rental_brands', 'school_id')) {
            $query->where('school_id', $schoolId);
        }
        if (Schema::hasColumn('rental_brands', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $updated = $query->update($payload);
        if (!$updated) {
            return $this->sendError('Not found', [], 404);
        }
        $row = DB::table('rental_brands')->where('id', $id)->first();
        return $this->sendResponse($row, 'Updated successfully');
    }

    public function destroy(Request $request, int $id)
    {
        return $this->destroyByTable($request, 'rental_brands', $id);
    }
}
