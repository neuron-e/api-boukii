<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RentalSubcategoryController extends RentalBaseController
{
    public function index(Request $request)
    {
        if (!Schema::hasTable('rental_subcategories')) {
            return $this->tableMissingResponse('rental_subcategories');
        }

        $schoolId = $this->getSchoolId($request);
        $query = DB::table('rental_subcategories');
        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }
        $query->whereNull('deleted_at');

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->input('category_id'));
        }

        if ($request->exists('parent_id')) {
            $parentId = $request->input('parent_id');
            if ($parentId === null || $parentId === '' || $parentId === 'null') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', (int) $parentId);
            }
        }

        $query
            ->orderBy('category_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id');

        $perPage = (int) $request->input('per_page', 500);
        $rows = $query->paginate(max(1, min(1000, $perPage)));

        return $this->sendResponse($rows, 'Data retrieved successfully');
    }

    public function show(Request $request, int $id)
    {
        return $this->showByTable($request, 'rental_subcategories', $id);
    }

    public function store(Request $request)
    {
        $schoolId = $this->getSchoolId($request);
        $categoryId = (int) $request->input('category_id', 0);
        $parentId = $this->normalizeParentId($request->input('parent_id'));

        if ($categoryId <= 0) {
            return $this->sendError('category_id is required', [], 422);
        }

        if ($error = $this->validateParentIntegrity($schoolId, $categoryId, $parentId, null)) {
            return $error;
        }

        $request->merge([
            'parent_id' => $parentId,
        ]);

        return $this->storeByTable($request, 'rental_subcategories', [
            'school_id', 'category_id', 'parent_id', 'name', 'slug', 'active', 'sort_order',
        ]);
    }

    public function update(Request $request, int $id)
    {
        if (!Schema::hasTable('rental_subcategories')) {
            return $this->tableMissingResponse('rental_subcategories');
        }

        $schoolId = $this->getSchoolId($request);
        $existing = DB::table('rental_subcategories')
            ->where('id', $id)
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->whereNull('deleted_at')
            ->first();

        if (!$existing) {
            return $this->sendError('Not found', [], 404);
        }

        $categoryId = (int) ($request->input('category_id', $existing->category_id));
        $parentId = $request->exists('parent_id')
            ? $this->normalizeParentId($request->input('parent_id'))
            : $this->normalizeParentId($existing->parent_id);

        if ($categoryId <= 0) {
            return $this->sendError('category_id is required', [], 422);
        }

        if ($error = $this->validateParentIntegrity($schoolId, $categoryId, $parentId, $id)) {
            return $error;
        }

        if ($request->exists('parent_id')) {
            $request->merge(['parent_id' => $parentId]);
        }

        return $this->updateByTable($request, 'rental_subcategories', $id, [
            'category_id', 'parent_id', 'name', 'slug', 'active', 'sort_order',
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        if (!Schema::hasTable('rental_subcategories')) {
            return $this->tableMissingResponse('rental_subcategories');
        }

        $schoolId = $this->getSchoolId($request);
        $exists = DB::table('rental_subcategories')
            ->where('id', $id)
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->whereNull('deleted_at')
            ->exists();

        if (!$exists) {
            return $this->sendError('Not found', [], 404);
        }

        $childrenCount = DB::table('rental_subcategories')
            ->where('parent_id', $id)
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->whereNull('deleted_at')
            ->count();
        if ($childrenCount > 0) {
            return $this->sendError('Cannot delete subcategory with child subcategories', [], 422);
        }

        if (Schema::hasTable('rental_variants')) {
            $variantsCount = DB::table('rental_variants')
                ->where('subcategory_id', $id)
                ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
                ->when(
                    Schema::hasColumn('rental_variants', 'deleted_at'),
                    fn($q) => $q->whereNull('deleted_at')
                )
                ->count();
            if ($variantsCount > 0) {
                return $this->sendError('Cannot delete subcategory with linked variants', [], 422);
            }
        }

        return $this->destroyByTable($request, 'rental_subcategories', $id);
    }

    private function normalizeParentId($raw): ?int
    {
        if ($raw === null || $raw === '' || $raw === 'null') {
            return null;
        }
        $id = (int) $raw;
        return $id > 0 ? $id : null;
    }

    private function validateParentIntegrity(?int $schoolId, int $categoryId, ?int $parentId, ?int $currentId)
    {
        if (!$parentId) {
            return null;
        }

        if ($currentId && $parentId === (int) $currentId) {
            return $this->sendError('A subcategory cannot be its own parent', [], 422);
        }

        $parent = DB::table('rental_subcategories')
            ->select('id', 'parent_id', 'category_id')
            ->where('id', $parentId)
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->whereNull('deleted_at')
            ->first();
        if (!$parent) {
            return $this->sendError('Parent subcategory not found', [], 422);
        }

        if ((int) $parent->category_id !== $categoryId) {
            return $this->sendError('Parent subcategory must belong to the same category', [], 422);
        }

        if ($currentId) {
            $cursor = $parent;
            $guard = 0;
            while ($cursor && $cursor->parent_id && $guard < 100) {
                if ((int) $cursor->parent_id === (int) $currentId) {
                    return $this->sendError('Circular hierarchy is not allowed', [], 422);
                }
                $cursor = DB::table('rental_subcategories')
                    ->select('id', 'parent_id')
                    ->where('id', (int) $cursor->parent_id)
                    ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
                    ->whereNull('deleted_at')
                    ->first();
                $guard++;
            }
        }

        return null;
    }
}
