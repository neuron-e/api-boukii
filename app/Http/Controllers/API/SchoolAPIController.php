<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\API\CreateSchoolAPIRequest;
use App\Http\Requests\API\UpdateSchoolAPIRequest;
use App\Http\Resources\API\SchoolResource;
use App\Models\Degree;
use App\Models\School;
use App\Models\SchoolColor;
use App\Models\SchoolSport;
use App\Models\SchoolUser;
use App\Models\Season;
use App\Models\Station;
use App\Models\StationsSchool;
use App\Models\User;
use App\Repositories\SchoolRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Class SchoolController
 */

class SchoolAPIController extends AppBaseController
{
    /** @var  SchoolRepository */
    private $schoolRepository;

    public function __construct(SchoolRepository $schoolRepo)
    {
        $this->schoolRepository = $schoolRepo;
    }

    /**
     * @OA\Get(
     *      path="/schools",
     *      summary="getSchoolList",
     *      tags={"School"},
     *      description="Get all Schools",
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(ref="#/components/schemas/School")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
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

    /**
     * @OA\Post(
     *      path="/schools",
     *      summary="createSchool",
     *      tags={"School"},
     *      description="Create School",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/School")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  ref="#/components/schemas/School"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function store(CreateSchoolAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        if (!empty($input['logo']) && $this->isBase64Image($input['logo'])) {
            $input['logo'] = $this->saveBase64Image($input['logo'], 'logos');
        }

        $school = $this->schoolRepository->create($input);

        return $this->sendResponse($school, 'School saved successfully');
    }

    /**
     * @OA\Post(
     *      path="/schools",
     *      summary="createSchool",
     *      tags={"School"},
     *      description="Create School",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/School")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  ref="#/components/schemas/School"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function storeFull(Request $request): JsonResponse
    {
        $defaultSchool = School::find(2); // Cambiado a escuela 2 como default
        DB::beginTransaction();
        $description = $request->input('description', $defaultSchool?->description ?? 'École sans description');
        // Generar slug a partir del nombre
        $name = $request->input('name', 'École sans nom');
        $slug = Str::slug($name);
        try {
            // 1. Crear nueva escuela
            $school = School::create(array_merge([
                'type' => 1,
                'active' => 1,
                'settings' => $defaultSchool?->settings,
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
            ], $request->only([
                'logo',
                'contact_email', 'contact_phone', 'contact_telephone',
                'contact_address', 'contact_cp', 'contact_city', 'contact_province', 'contact_country',
                'fiscal_name', 'fiscal_address', 'fiscal_cp', 'fiscal_city', 'fiscal_province', 'fiscal_country',
                'iban', 'cancellation_insurance_percent', 'bookings_comission_cash',
                'bookings_comission_boukii_pay', 'bookings_comission_other', 'school_rate',
                'has_ski', 'has_snowboard', 'has_telemark', 'has_rando', 'inscription'
            ])));

            // 2. Relacionar deporte Ski (ID 1)
            SchoolSport::create([
                'sport_id' => 1,
                'school_id' => $school->id,
            ]);

            // 3. Copiar degrees desde escuela 2
            $degrees = Degree::where('school_id', 2)->get();
            foreach ($degrees as $degree) {
                Degree::create($degree->replicate()->fill([
                    'school_id' => $school->id,
                ])->toArray());
            }

            // 4. Copiar school_colors
            $colors = SchoolColor::where('school_id', 2)->get();
            foreach ($colors as $color) {
                SchoolColor::create($color->replicate()->fill([
                    'school_id' => $school->id,
                ])->toArray());
            }

            // 5. Crear usuarios
            $adminEmail = $request->input('admin_email', $school->contact_email);
            $schoolSlug = strtolower(Str::slug($school->name));
            $boukiiEmail = 'boukiiteam' . $schoolSlug . '@gmail.com';

            $user1 = User::create([
                'first_name' => $request->input('admin_first_name', 'Admin'),
                'last_name' => $request->input('admin_last_name', 'User'),
                'username' => Str::before($adminEmail, '@'),
                'email' => $adminEmail,
                'password' => Hash::make('School' . date('Y') . '!'),
                'type' => 1,
                'active' => 1
            ]);

            $user2 = User::create([
                'first_name' => 'Boukii Team ' . $school->name,
                'last_name' => '',
                'username' => 'boukiiteam' . $schoolSlug,
                'email' => $boukiiEmail,
                'password' => Hash::make('Boukii' . date('Y') . '!'),
                'type' => 1,
                'active' => 1
            ]);

            foreach ([$user1, $user2] as $user) {
                SchoolUser::create([
                    'user_id' => $user->id,
                    'school_id' => $school->id,
                ]);
            }

            // 6. Crear season
            Season::create([
                'name' => $request->input('season_name', 'Temporada 1'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'is_active' => true,
                'hour_start' => $request->input('hour_start', '09:00:00'),
                'hour_end' => $request->input('hour_end', '18:00:00'),
                'vacation_days' => json_encode([]),
                'school_id' => $school->id,
            ]);

            // 7. Crear estación
            $station = Station::create([
                'name' => $request->input('station_name', $school->name),
                'cp' => $request->input('station_cp', $school->contact_cp),
                'city' => $request->input('station_city', $school->contact_city),
                'country' => $request->input('station_country', $school->contact_country),
                'province' => $request->input('station_province', $school->contact_province),
                'address' => $request->input('station_address', $school->contact_address),
                'image' => $request->input('station_image', ''),
                'map' => $request->input('station_map', ''),
                'latitude' => $request->input('station_latitude', '0.000000'),
                'longitude' => $request->input('station_longitude', '0.000000'),
                'show_details' => $request->input('station_show_details', true),
                'active' => $request->input('station_active', true),
                'accuweather' => $request->input('station_accuweather', null),
                'num_hanger' => $request->input('num_hanger', 0),
                'num_chairlift' => $request->input('num_chairlift', 0),
                'num_cabin' => $request->input('num_cabin', 0),
                'num_cabin_large' => $request->input('num_cabin_large', 0),
                'num_fonicular' => $request->input('num_fonicular', 0),
            ]);

            StationsSchool::create([
                'station_id' => $station->id,
                'school_id' => $school->id,
            ]);

            // 8. Guardar Payrexx si viene
            if ($request->filled('payrexx')) {
                $school->payrexx = encrypt($request->input('payrexx'));
                $school->save();
            }

            DB::commit();
            return response()->json([
                'message' => 'Escuela creada correctamente',
                'school' => $school,
                'users' => [$user1->email => 'School' . date('Y') . '!', $user2->email => 'Boukii' . date('Y') . '!']
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al crear la escuela',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *      path="/schools/{id}",
     *      summary="getSchoolItem",
     *      tags={"School"},
     *      description="Get School",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of School",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  ref="#/components/schemas/School"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function show($id, Request $request): JsonResponse
    {
        /** @var School $school */
        $school = $this->schoolRepository->find($id, with: $request->get('with', []));

        if (empty($school)) {
            return $this->sendError('School not found');
        }

        return $this->sendResponse($school, 'School retrieved successfully');
    }

    /**
     * @OA\Put(
     *      path="/schools/{id}",
     *      summary="updateSchool",
     *      tags={"School"},
     *      description="Update School",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of School",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/School")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  ref="#/components/schemas/School"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function update($id, UpdateSchoolAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var School $school */
        $school = $this->schoolRepository->find($id, with: $request->get('with', []));

        if (empty($school)) {
            return $this->sendError('School not found');
        }

        // Procesar imágenes en el campo settings si existen
        if (!empty($input['settings'])) {
            $settings = json_decode($input['settings'], true);

            if (isset($settings['bookingPage'])) {
                // Guardar imágenes de sponsors si son base64
                if (!empty($settings['bookingPage']['sponsors']) && is_array($settings['bookingPage']['sponsors'])) {
                    foreach ($settings['bookingPage']['sponsors'] as $key => $sponsor) {
                        if (isset($sponsor['img']) && $this->isBase64Image($sponsor['img'])) {
                            $settings['bookingPage']['sponsors'][$key]['img'] = $this->saveBase64Image($sponsor['img'], 'sponsors');
                        }
                    }
                }

                // Guardar imágenes del banner (desktopImg y mobileImg) si son base64
                if (!empty($settings['bookingPage']['banner']['desktopImg']) && $this->isBase64Image($settings['bookingPage']['banner']['desktopImg'])) {
                    $settings['bookingPage']['banner']['desktopImg'] = $this->saveBase64Image($settings['bookingPage']['banner']['desktopImg'], 'banners');
                }
                if (!empty($settings['bookingPage']['banner']['mobileImg']) && $this->isBase64Image($settings['bookingPage']['banner']['mobileImg'])) {
                    $settings['bookingPage']['banner']['mobileImg'] = $this->saveBase64Image($settings['bookingPage']['banner']['mobileImg'], 'banners');
                }
            }

            // Normalizar enlaces de redes sociales en settings.booking.social
            if (isset($settings['booking']['social']) && is_array($settings['booking']['social'])) {
                $social = $settings['booking']['social'];
                $platforms = ['facebook', 'instagram', 'x', 'youtube', 'tiktok', 'linkedin'];

                foreach ($platforms as $platform) {
                    if (!array_key_exists($platform, $social)) {
                        continue; // claves faltantes: ausentes o null
                    }
                    $value = $social[$platform];
                    if ($value === null) {
                        continue;
                    }
                    if (is_string($value)) {
                        // Validación de longitud de entrada
                        if (mb_strlen($value) > 255) {
                            return $this->sendError('Invalid URL or handle for ' . $platform, 422);
                        }

                        $normalized = $this->normalizeSocialUrl($platform, $value);
                        if ($normalized === false) {
                            return $this->sendError('Invalid URL or handle for ' . $platform, 422);
                        }
                        if ($normalized !== null && mb_strlen($normalized) > 255) {
                            return $this->sendError('Invalid URL or handle for ' . $platform, 422);
                        }
                        $settings['booking']['social'][$platform] = $normalized;
                    } else {
                        // Si no es string, rechazamos
                        return $this->sendError('Invalid value for ' . $platform, 422);
                    }
                }
            }

            // Convertimos de nuevo a JSON después de modificarlo
            $input['settings'] = json_encode($settings);
        }

        $school = $this->schoolRepository->update($input, $id);

        return $this->sendResponse(new SchoolResource($school), 'School updated successfully');
    }

    public function uploadLogo($id, Request $request): JsonResponse
    {
        /** @var School $school */
        $school = $this->schoolRepository->find($id, with: $request->get('with', []));

        if (empty($school)) {
            return $this->sendError('School not found', 404);
        }

        if (!$request->hasFile('logo') || !$request->file('logo')->isValid()) {
            return $this->sendError('Invalid logo file provided', 422);
        }

        $file = $request->file('logo');
        $path = $file->store('logos', 'public');

        if (!$path) {
            return $this->sendError('Unable to store logo', 500);
        }

        $school->logo = url(Storage::url($path));
        $school->save();

        return $this->sendResponse(new SchoolResource($school), 'Logo uploaded successfully');
    }

    /**
     * Función para guardar imágenes base64 y devolver la URL pública
     */
    private function saveBase64Image($base64Image, $folder)
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
            $imageData = substr($base64Image, strpos($base64Image, ',') + 1);
            $type = strtolower($type[1]);
            $imageData = base64_decode($imageData);

            if ($imageData === false) {
                return null; // Si la decodificación falla, devuelve null
            }

            // Generar un nombre único para la imagen
            $imageName = $folder . '/image_' . time() . '_' . uniqid() . '.' . $type;
            Storage::disk('public')->put($imageName, $imageData);

            return url(Storage::url($imageName)); // Devolver la URL pública de la imagen
        }

        return null; // Si la imagen no es válida, devuelve null
    }

    /**
     * Normaliza un valor de red social a URL http(s) válida o devuelve false si no es válida.
     */
    private function normalizeSocialUrl(string $platform, string $raw)
    {
        $value = trim($raw);
        if ($value === '') {
            return null; // tratar vacío como null
        }

        // Si empieza con esquema, validar que sea http(s) y URL válida
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9+\-.]*:/', $value) === 1) {
            $parsed = parse_url($value);
            $scheme = $parsed['scheme'] ?? '';
            if (!in_array(strtolower($scheme), ['http', 'https'], true)) {
                return false; // protocolos no seguros/no permitidos
            }
            if (filter_var($value, FILTER_VALIDATE_URL) === false) {
                return false;
            }
            return $value; // URL http(s) ya válida, se conserva
        }

        // Manejar como handle: quitar '@' inicial y barras sobrantes
        $handle = ltrim($value);
        $handle = ltrim($handle, '@');
        $handle = trim($handle, "/ ");
        if ($handle === '') {
            return null;
        }

        switch ($platform) {
            case 'facebook':
                return 'https://www.facebook.com/' . rawurlencode($handle);
            case 'instagram':
                return 'https://www.instagram.com/' . rawurlencode($handle);
            case 'x': // Twitter/X
                return 'https://twitter.com/' . rawurlencode($handle);
            case 'youtube':
                // Forzamos formato @handle
                return 'https://www.youtube.com/@' . rawurlencode($handle);
            case 'tiktok':
                return 'https://www.tiktok.com/@' . rawurlencode($handle);
            case 'linkedin':
                // Si detectamos prefijo in/ (perfil personal) respetarlo
                if (str_starts_with($handle, 'in/')) {
                    return 'https://www.linkedin.com/' . $handle; // in/<handle>
                }
                if (str_starts_with($handle, 'company/')) {
                    $handle = substr($handle, 8);
                }
                return 'https://www.linkedin.com/company/' . rawurlencode($handle);
            default:
                return false;
        }
    }

    /**
     * Función para verificar si un string es una imagen en base64
     */
    private function isBase64Image($str)
    {
        return preg_match('/^data:image\/(\w+);base64,/', $str);
    }


    /**
     * @OA\Put(
     *      path="/schools/{id}/sports",
     *      summary="updateSchoolSports",
     *      tags={"School"},
     *      description="Update School Sports",
     *      @OA\Parameter(
     *          name="id",
     *          description="ID of School",
     *          @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(
     *           @OA\Property(
     *               property="sport_ids",
     *               type="array",
     *               @OA\Items(type="integer"),
     *               description="An array of Sport IDs to synchronize with the school."
     *           )
     *        )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="School not found",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="error",
     *                  type="string",
     *                  description="Error message"
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="error",
     *                  type="string",
     *                  description="Error message"
     *              )
     *          )
     *      )
     * )
     */
    public function updateSchoolSports($schoolId, Request $request): JsonResponse
    {
        $input = $request->all();

        /** @var School $school */
        $school = School::find($schoolId);

        if (empty($school)) {
            return $this->sendError('School not found');
        }

        // Sincroniza los deportes relacionados con los IDs proporcionados en $input['sport_ids']
        $school->sports()->sync($input['sport_ids']);
        $school->load('sports');

        return $this->sendResponse($school,'School sports updated successfully');
    }

    /**
     * @OA\Delete(
     *      path="/schools/{id}",
     *      summary="deleteSchool",
     *      tags={"School"},
     *      description="Delete School",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of School",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function destroy($id): JsonResponse
    {
        /** @var School $school */
        $school = $this->schoolRepository->find($id);

        if (empty($school)) {
            return $this->sendError('School not found');
        }

        $school->delete();

        return $this->sendSuccess('School deleted successfully');
    }
}
