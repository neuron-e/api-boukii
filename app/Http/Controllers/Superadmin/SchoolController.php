<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\AppBaseController;
use App\Models\School;
use App\Models\SchoolUser;
use App\Models\User;
use App\Repositories\SchoolRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SchoolController extends AppBaseController
{
    public function __construct(protected SchoolRepository $schoolRepository)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $schools = $this->schoolRepository->all(
            $request->except(['skip', 'limit', 'search', 'exclude', 'user', 'perPage', 'order', 'orderColumn', 'page', 'with']),
            $request->get('search'),
            $request->get('skip'),
            $request->get('limit'),
            $request->perPage,
            $request->get('with', []),
            $request->get('order', 'desc'),
            $request->get('orderColumn', 'id')
        );

        return $this->sendResponse($schools, 'Schools retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'logo' => ['nullable', 'string'],
            'admin_email' => ['required', 'email'],
            'admin_first_name' => ['required', 'string'],
            'admin_last_name' => ['required', 'string'],
            'admin_password' => ['nullable', 'string', 'min:8'],
            'slug' => ['nullable', 'string', 'max:255'],
        ]);

        $logo = $validated['logo'] ?? null;
        if ($logo && $this->isBase64Image($logo)) {
            $validated['logo'] = $this->saveBase64Image($logo, 'logos');
        }

        $slug = $validated['slug'] ?? Str::slug($validated['name']);
        $settings = School::find(2)?->settings ?? null;

        $school = $this->schoolRepository->create([
            'name' => $validated['name'],
            'slug' => $slug,
            'contact_email' => $validated['contact_email'] ?? null,
            'logo' => $validated['logo'] ?? null,
            'active' => 1,
            'type' => 1,
            'description' => $request->input('description', 'Nueva escuela'),
            'settings' => $settings
        ]);

        $this->createAdminForSchool($school, $validated);

        return $this->sendResponse($school, 'School created successfully');
    }

    public function show($id): JsonResponse
    {
        $school = $this->schoolRepository->find($id);

        if (empty($school)) {
            return $this->sendError('School not found', 404);
        }

        return $this->sendResponse($school, 'School retrieved successfully');
    }

    public function update($id, Request $request): JsonResponse
    {
        $school = $this->schoolRepository->find($id);

        if (empty($school)) {
            return $this->sendError('School not found', 404);
        }

        $data = $request->all();
        if (!empty($data['logo']) && $this->isBase64Image($data['logo'])) {
            $data['logo'] = $this->saveBase64Image($data['logo'], 'logos');
        }

        if (!empty($data['name']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $school = $this->schoolRepository->update($data, $id);

        return $this->sendResponse($school, 'School updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        $school = $this->schoolRepository->find($id);

        if (empty($school)) {
            return $this->sendError('School not found', 404);
        }

        $school->delete();

        return $this->sendSuccess('School deleted successfully');
    }

    protected function createAdminForSchool(School $school, array $validated): void
    {
        $password = $validated['admin_password'] ?? ('School' . date('Y') . '!');
        $user = User::create([
            'first_name' => $validated['admin_first_name'],
            'last_name' => $validated['admin_last_name'],
            'username' => Str::before($validated['admin_email'], '@'),
            'email' => $validated['admin_email'],
            'password' => Hash::make($password),
            'type' => 1,
            'active' => 1,
        ]);

        SchoolUser::create([
            'user_id' => $user->id,
            'school_id' => $school->id,
        ]);
    }

    private function isBase64Image($value): bool
    {
        return is_string($value) && preg_match('/^data:image\/(\w+);base64,/', $value);
    }

    private function saveBase64Image(string $base64Image, string $folder): ?string
    {
        if (!$this->isBase64Image($base64Image)) {
            return null;
        }

        preg_match('/^data:image\/(\w+);base64,/', $base64Image, $matches);

        $imageData = base64_decode(substr($base64Image, strpos($base64Image, ',') + 1));
        if ($imageData === false) {
            return null;
        }

        $extension = strtolower($matches[1] ?? 'png');
        $filename = sprintf('%s/image_%s.%s', $folder, uniqid(), $extension);
        Storage::disk('public')->put($filename, $imageData);

        return url(Storage::url($filename));
    }
}
