<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class RentalItemImageController extends RentalBaseController
{
    private const MAX_IMAGES_PER_ITEM = 6;

    public function index(Request $request, int $itemId)
    {
        if (!Schema::hasTable('rental_item_images')) {
            return $this->sendResponse([], 'Data retrieved successfully');
        }

        $schoolId = $this->getSchoolId($request);
        if (!$this->itemExistsForSchool($itemId, $schoolId)) {
            return $this->sendError('Not found', [], 404);
        }

        $rows = DB::table('rental_item_images')
            ->where('item_id', $itemId)
            ->when($schoolId && Schema::hasColumn('rental_item_images', 'school_id'), function ($query) use ($schoolId) {
                $query->where('school_id', $schoolId);
            })
            ->when(Schema::hasColumn('rental_item_images', 'deleted_at'), function ($query) {
                $query->whereNull('deleted_at');
            })
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $this->sendResponse($rows, 'Data retrieved successfully');
    }

    public function store(Request $request, int $itemId)
    {
        if (!Schema::hasTable('rental_item_images')) {
            return $this->sendError('Rental item images table is missing', [], 422);
        }

        $schoolId = $this->getSchoolId($request);
        if (!$this->itemExistsForSchool($itemId, $schoolId)) {
            return $this->sendError('Not found', [], 404);
        }

        $currentCount = DB::table('rental_item_images')
            ->where('item_id', $itemId)
            ->when($schoolId && Schema::hasColumn('rental_item_images', 'school_id'), function ($query) use ($schoolId) {
                $query->where('school_id', $schoolId);
            })
            ->when(Schema::hasColumn('rental_item_images', 'deleted_at'), function ($query) {
                $query->whereNull('deleted_at');
            })
            ->count();

        if ($currentCount >= self::MAX_IMAGES_PER_ITEM) {
            return $this->sendError('Límite alcanzado: máximo 6 imágenes por producto.', [], 422);
        }

        $imageUrl = $this->normalizeImageInput((string) $request->input('image', ''));
        if (!$imageUrl) {
            return $this->sendError('Imagen inválida', [], 422);
        }

        $maxSortOrder = (int) DB::table('rental_item_images')
            ->where('item_id', $itemId)
            ->when($schoolId && Schema::hasColumn('rental_item_images', 'school_id'), function ($query) use ($schoolId) {
                $query->where('school_id', $schoolId);
            })
            ->when(Schema::hasColumn('rental_item_images', 'deleted_at'), function ($query) {
                $query->whereNull('deleted_at');
            })
            ->max('sort_order');

        $isPrimary = $currentCount === 0;
        $id = DB::table('rental_item_images')->insertGetId([
            'school_id' => $schoolId,
            'item_id' => $itemId,
            'image_url' => $imageUrl,
            'is_primary' => $isPrimary,
            'sort_order' => $maxSortOrder + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($isPrimary && Schema::hasTable('rental_items') && Schema::hasColumn('rental_items', 'image')) {
            DB::table('rental_items')->where('id', $itemId)->update([
                'image' => $imageUrl,
                'updated_at' => now(),
            ]);
        }

        $row = DB::table('rental_item_images')->where('id', $id)->first();
        return $this->sendResponse($row, 'Created successfully');
    }

    public function setPrimary(Request $request, int $itemId, int $imageId)
    {
        if (!Schema::hasTable('rental_item_images')) {
            return $this->sendError('Rental item images table is missing', [], 422);
        }

        $schoolId = $this->getSchoolId($request);
        if (!$this->itemExistsForSchool($itemId, $schoolId)) {
            return $this->sendError('Not found', [], 404);
        }

        $image = DB::table('rental_item_images')
            ->where('id', $imageId)
            ->where('item_id', $itemId)
            ->when($schoolId && Schema::hasColumn('rental_item_images', 'school_id'), function ($query) use ($schoolId) {
                $query->where('school_id', $schoolId);
            })
            ->when(Schema::hasColumn('rental_item_images', 'deleted_at'), function ($query) {
                $query->whereNull('deleted_at');
            })
            ->first();

        if (!$image) {
            return $this->sendError('Not found', [], 404);
        }

        DB::table('rental_item_images')
            ->where('item_id', $itemId)
            ->when($schoolId && Schema::hasColumn('rental_item_images', 'school_id'), function ($query) use ($schoolId) {
                $query->where('school_id', $schoolId);
            })
            ->when(Schema::hasColumn('rental_item_images', 'deleted_at'), function ($query) {
                $query->whereNull('deleted_at');
            })
            ->update(['is_primary' => false, 'updated_at' => now()]);

        DB::table('rental_item_images')
            ->where('id', $imageId)
            ->update(['is_primary' => true, 'updated_at' => now()]);

        if (Schema::hasTable('rental_items') && Schema::hasColumn('rental_items', 'image')) {
            DB::table('rental_items')->where('id', $itemId)->update([
                'image' => $image->image_url,
                'updated_at' => now(),
            ]);
        }

        return $this->sendSuccess('Updated successfully');
    }

    public function destroy(Request $request, int $itemId, int $imageId)
    {
        if (!Schema::hasTable('rental_item_images')) {
            return $this->sendError('Rental item images table is missing', [], 422);
        }

        $schoolId = $this->getSchoolId($request);
        if (!$this->itemExistsForSchool($itemId, $schoolId)) {
            return $this->sendError('Not found', [], 404);
        }

        $image = DB::table('rental_item_images')
            ->where('id', $imageId)
            ->where('item_id', $itemId)
            ->when($schoolId && Schema::hasColumn('rental_item_images', 'school_id'), function ($query) use ($schoolId) {
                $query->where('school_id', $schoolId);
            })
            ->when(Schema::hasColumn('rental_item_images', 'deleted_at'), function ($query) {
                $query->whereNull('deleted_at');
            })
            ->first();

        if (!$image) {
            return $this->sendError('Not found', [], 404);
        }

        if (Schema::hasColumn('rental_item_images', 'deleted_at')) {
            DB::table('rental_item_images')->where('id', $imageId)->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('rental_item_images')->where('id', $imageId)->delete();
        }

        $newPrimary = DB::table('rental_item_images')
            ->where('item_id', $itemId)
            ->when($schoolId && Schema::hasColumn('rental_item_images', 'school_id'), function ($query) use ($schoolId) {
                $query->where('school_id', $schoolId);
            })
            ->when(Schema::hasColumn('rental_item_images', 'deleted_at'), function ($query) {
                $query->whereNull('deleted_at');
            })
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($newPrimary) {
            DB::table('rental_item_images')
                ->where('id', $newPrimary->id)
                ->update(['is_primary' => true, 'updated_at' => now()]);
        }

        if (Schema::hasTable('rental_items') && Schema::hasColumn('rental_items', 'image')) {
            DB::table('rental_items')->where('id', $itemId)->update([
                'image' => $newPrimary?->image_url,
                'updated_at' => now(),
            ]);
        }

        return $this->sendSuccess('Deleted successfully');
    }

    private function itemExistsForSchool(int $itemId, ?int $schoolId): bool
    {
        if (!Schema::hasTable('rental_items')) {
            return false;
        }

        $query = DB::table('rental_items')->where('id', $itemId);
        if ($schoolId && Schema::hasColumn('rental_items', 'school_id')) {
            $query->where('school_id', $schoolId);
        }
        if (Schema::hasColumn('rental_items', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->exists();
    }

    private function normalizeImageInput(string $input): ?string
    {
        $trimmed = trim($input);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^https?:\\/\\//i', $trimmed)) {
            return $trimmed;
        }

        if (preg_match('/^data:image\\/(\\w+);base64,/', $trimmed, $matches)) {
            $extension = strtolower((string) ($matches[1] ?? 'png'));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
            if (!in_array($extension, $allowed, true)) {
                $extension = 'png';
            }

            $base64Body = substr($trimmed, strpos($trimmed, ',') + 1);
            $binary = base64_decode($base64Body);
            if ($binary === false) {
                return null;
            }

            $path = 'rental/items/' . date('Y/m') . '/' . uniqid('img_', true) . '.' . $extension;
            Storage::disk('public')->put($path, $binary);
            return url(Storage::url($path));
        }

        return null;
    }
}

