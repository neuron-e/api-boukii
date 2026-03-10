<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\AppBaseController;
use App\Models\School;
use App\Models\SchoolUser;
use App\Models\User;
use App\Models\Booking;
use App\Models\ClientsSchool;
use App\Models\Monitor;
use App\Models\Sport;
use App\Models\Station;
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

    public function details($id): JsonResponse
    {
        $school = $this->schoolRepository->find($id, ['schoolUsers.user.roles']);

        if (empty($school)) {
            return $this->sendError('School not found', 404);
        }

        $settings = $school->settings;
        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $settings = $decoded;
            }
        }

        $instructorsCount = Monitor::where('active_school', $school->id)
            ->where('active', 1)
            ->count();
        $studentsCount = ClientsSchool::where('school_id', $school->id)->count();
        $revenueTotal = (float) Booking::where('school_id', $school->id)->sum('price_total');
        $lastActivity = Booking::where('school_id', $school->id)->latest('updated_at')->value('updated_at');

        $planName = null;
        $planSince = null;
        if (is_array($settings)) {
            $planName = $settings['plan'] ?? $settings['subscription_plan'] ?? null;
            $planSince = $settings['plan_since'] ?? $settings['subscription_since'] ?? null;
        }

        $schoolArray = $school->toArray();
        $schoolArray['settings'] = $settings;
        $schoolArray['payrexx_instance'] = $school->getPayrexxInstance();
        $schoolArray['payrexx_key'] = $school->getPayrexxKey();

        $schoolSports = $school->sports()->get();
        $schoolStations = $school->stations()->get();
        $allSports = Sport::orderBy('name')->get([
            'id',
            'name',
            'icon_collective',
            'icon_prive',
            'icon_activity',
            'icon_selected',
            'icon_unselected',
            'sport_type'
        ]);
        $allStations = Station::orderBy('name')->get([
            'id',
            'name',
            'city',
            'country',
            'province',
            'active'
        ]);

        $rentalPolicyRow = \Illuminate\Support\Facades\DB::table('rental_policies')
            ->where('school_id', $id)
            ->first();

        $rentalPolicy = null;
        if ($rentalPolicyRow) {
            $policySettings = is_string($rentalPolicyRow->settings)
                ? (json_decode($rentalPolicyRow->settings, true) ?? [])
                : (array) ($rentalPolicyRow->settings ?? []);
            $rentalPolicy = [
                'enabled'               => (bool) $rentalPolicyRow->enabled,
                'mode'                  => $policySettings['mode'] ?? 'standalone',
                'reminder_hours_before' => (int) ($policySettings['reminder_hours_before'] ?? 24),
            ];
        }

        return $this->sendResponse([
            'school' => $schoolArray,
            'stats' => [
                'instructors_count' => $instructorsCount,
                'students_count' => $studentsCount,
                'revenue_total' => $revenueTotal,
                'last_activity' => $lastActivity,
            ],
            'plan' => [
                'name' => $planName,
                'since' => $planSince,
            ],
            'admins' => $school->schoolUsers ?? [],
            'school_sports' => $schoolSports,
            'school_stations' => $schoolStations,
            'school_sport_ids' => $schoolSports->pluck('id')->values(),
            'school_station_ids' => $schoolStations->pluck('id')->values(),
            'lookups' => [
                'sports' => $allSports,
                'stations' => $allStations,
            ],
            'rental_policy' => $rentalPolicy,
        ], 'School details retrieved successfully');
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

        if (array_key_exists('sport_ids', $data)) {
            $sportIds = is_array($data['sport_ids']) ? $data['sport_ids'] : [];
            $school->sports()->sync($sportIds);
            unset($data['sport_ids']);
        }
        if (array_key_exists('station_ids', $data)) {
            $stationIds = is_array($data['station_ids']) ? $data['station_ids'] : [];
            $school->stations()->sync($stationIds);
            unset($data['station_ids']);
        }

        if (array_key_exists('payrexx_instance', $data) && $data['payrexx_instance'] !== null && $data['payrexx_instance'] !== '') {
            $data['payrexx_instance'] = encrypt($data['payrexx_instance']);
        }
        if (array_key_exists('payrexx_key', $data) && $data['payrexx_key'] !== null && $data['payrexx_key'] !== '') {
            $data['payrexx_key'] = encrypt($data['payrexx_key']);
        }
        if (array_key_exists('settings', $data) && is_array($data['settings'])) {
            $data['settings'] = json_encode($data['settings']);
        }

        $school = $this->schoolRepository->update($data, $id);

        return $this->sendResponse($school, 'School updated successfully');
    }

    public function updateRentalPolicy($id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled'               => 'required|boolean',
            'mode'                  => 'in:standalone,integrated',
            'reminder_hours_before' => 'integer|min:1|max:72',
        ]);

        $settings = [
            'mode'                  => $validated['mode'] ?? 'standalone',
            'reminder_hours_before' => (int) ($validated['reminder_hours_before'] ?? 24),
        ];

        \Illuminate\Support\Facades\DB::table('rental_policies')->updateOrInsert(
            ['school_id' => $id],
            [
                'enabled'    => $validated['enabled'] ? 1 : 0,
                'settings'   => json_encode($settings),
                'updated_at' => now(),
            ]
        );

        return $this->sendResponse([
            'enabled'               => (bool) $validated['enabled'],
            'mode'                  => $settings['mode'],
            'reminder_hours_before' => $settings['reminder_hours_before'],
        ], 'Rental policy updated successfully');
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
