<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RentalTagController extends RentalBaseController
{
    public function index(Request $request)
    {
        if (!Schema::hasTable('rental_tags')) {
            return $this->sendResponse([], 'Data retrieved successfully');
        }

        $schoolId = $this->getSchoolId($request);
        $query = DB::table('rental_tags');
        if ($schoolId && Schema::hasColumn('rental_tags', 'school_id')) {
            $query->where('school_id', $schoolId);
        }
        if (Schema::hasColumn('rental_tags', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            if ($search !== '') {
                $query->where('name', 'like', '%' . $search . '%');
            }
        }

        $rows = $query->orderBy('name')->paginate((int) $request->input('per_page', 200));
        return $this->sendResponse($rows, 'Data retrieved successfully');
    }

    public function store(Request $request)
    {
        if (!Schema::hasTable('rental_tags')) {
            return $this->sendError('Rental tags table is missing', [], 422);
        }

        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            return $this->sendError('Tag name is required', [], 422);
        }

        $schoolId = $this->getSchoolId($request);
        $existing = DB::table('rental_tags')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($schoolId && Schema::hasColumn('rental_tags', 'school_id'), function ($query) use ($schoolId) {
                $query->where('school_id', $schoolId);
            })
            ->first();

        if ($existing && empty($existing->deleted_at)) {
            return $this->sendResponse($existing, 'Data retrieved successfully');
        }

        if ($existing && !empty($existing->deleted_at) && Schema::hasColumn('rental_tags', 'deleted_at')) {
            DB::table('rental_tags')->where('id', $existing->id)->update([
                'deleted_at' => null,
                'active' => true,
                'updated_at' => now(),
            ]);
            $row = DB::table('rental_tags')->where('id', $existing->id)->first();
            return $this->sendResponse($row, 'Created successfully');
        }

        $id = DB::table('rental_tags')->insertGetId([
            'school_id' => $schoolId,
            'name' => $name,
            'slug' => Str::slug($name) ?: null,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('rental_tags')->where('id', $id)->first();
        return $this->sendResponse($row, 'Created successfully');
    }
}

