<?php

namespace App\V5\Modules\Upload\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    /**
     * Upload an image and return its public URL.
     */
    public function storeImage(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'image', 'max:10240'], // 10MB max
        ]);

        $schoolId = (int) $request->get('context_school_id');
        $file = $request->file('file');

        $path = $file->store("uploads/schools/{$schoolId}/avatars", 'public');
        $url = Storage::disk('public')->url($path);

        return response()->json([
            'success' => true,
            'data' => [
                'url' => $url,
                'path' => $path,
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ],
        ], 201);
    }
}

