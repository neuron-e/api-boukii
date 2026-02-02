<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\API\CreateBookingUserAPIRequest;
use App\Http\Requests\API\UpdateBookingUserAPIRequest;
use App\Http\Resources\API\BookingUserResource;
use App\Models\BookingUser;
use App\Repositories\BookingUserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class BookingUserController
 */

class BookingUserAPIController extends AppBaseController
{
    /** @var  BookingUserRepository */
    private $bookingUserRepository;

    public function __construct(BookingUserRepository $bookingUserRepo)
    {
        $this->bookingUserRepository = $bookingUserRepo;
    }

    /**
     * @OA\Get(
     *      path="/booking-users",
     *      summary="getBookingUserList",
     *      tags={"BookingUser"},
     *      description="Get all BookingUsers",
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
     *                  @OA\Items(ref="#/components/schemas/BookingUser")
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
        // OPTIMIZACIÓN: Limitar las relaciones permitidas para evitar N+1
        // Las relaciones anidadas profundas causan problemas de rendimiento
        $allowedWith = [
            'booking',
            'client',
            'course',
            'degree',
            'monitor',
            'courseGroup',
            'courseSubGroup',
            'courseDate'
        ];

        $requestedWith = $request->get('with', []);

        // Filtrar relaciones anidadas profundas (más de 2 niveles)
        $validatedWith = array_filter($requestedWith, function($relation) use ($allowedWith) {
            // Contar niveles de anidación (puntos en la string)
            $depth = substr_count($relation, '.');

            // Rechazar relaciones con más de 1 nivel de profundidad
            if ($depth > 1) {
                \Log::warning('DEEP NESTED RELATION BLOCKED', [
                    'relation' => $relation,
                    'reason' => 'Depth > 1 causes N+1 problems'
                ]);
                return false;
            }

            // Obtener la relación de primer nivel
            $firstLevel = explode('.', $relation)[0];

            if (!in_array($firstLevel, $allowedWith)) {
                \Log::warning('INVALID RELATION BLOCKED', [
                    'relation' => $relation,
                    'allowed' => $allowedWith
                ]);
                return false;
            }

            return true;
        });

        \Log::info('BOOKING USERS INDEX', [
            'requested_relations' => $requestedWith,
            'validated_relations' => array_values($validatedWith),
            'blocked_count' => count($requestedWith) - count($validatedWith)
        ]);

        $bookingUsers = $this->bookingUserRepository->all(
            $request->except(['skip', 'limit', 'search', 'exclude', 'user', 'perPage', 'order', 'orderColumn', 'page', 'with']),
            $request->get('search'),
            $request->get('skip'),
            $request->get('limit'),
            $request->perPage,
            array_values($validatedWith), // Usar relaciones validadas
            $request->get('order', 'desc'),
            $request->get('orderColumn', 'id'),
            additionalConditions: function ($query) {
                $query->whereHas('booking');
            }
        );

        return $this->sendResponse($bookingUsers, 'Booking Users retrieved successfully');
    }

    /**
     * @OA\Post(
     *      path="/booking-users",
     *      summary="createBookingUser",
     *      tags={"BookingUser"},
     *      description="Create BookingUser",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/BookingUser")
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
     *                  ref="#/components/schemas/BookingUser"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function store(CreateBookingUserAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        if (!array_key_exists('attended', $input) && array_key_exists('attendance', $input)) {
            $input['attended'] = $input['attendance'];
        }

        // Seguridad: solo Teach/admin o monitores pueden modificar 'attended'
        if (array_key_exists('attended', $input)) {
            $user = $request->user() ?? auth('sanctum')->user();
            $canTeach = $user && method_exists($user, 'tokenCan') && (
                $user->tokenCan('teach:all') ||
                $user->tokenCan('admin:all') ||
                $user->tokenCan('monitor:all')
            );
            $isMonitor = $user && (
                $user->type === 'monitor' ||
                $user->type === 3 ||
                (string)$user->type === '3' ||
                ($user->relationLoaded('monitors') ? $user->monitors->isNotEmpty() : $user->monitors()->exists())
            );
            $isAdmin = $user && (
                $user->type === 'admin' ||
                $user->type === 1 ||
                (string)$user->type === '1'
            );
            if (!$canTeach && !$isMonitor && !$isAdmin) {
                unset($input['attended']);
            } else {
                $input['attended'] = (bool)$input['attended'];
            }
        }
        if (array_key_exists('attended', $input)) {
            $input['attendance'] = $input['attended'];
        }

        $bookingUser = $this->bookingUserRepository->create($input);

        return $this->sendResponse($bookingUser, 'Booking User saved successfully');
    }

    /**
     * @OA\Get(
     *      path="/booking-users/{id}",
     *      summary="getBookingUserItem",
     *      tags={"BookingUser"},
     *      description="Get BookingUser",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of BookingUser",
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
     *                  ref="#/components/schemas/BookingUser"
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
        /** @var BookingUser $bookingUser */
        $bookingUser = $this->bookingUserRepository->find($id, with: $request->get('with', []));

        if (empty($bookingUser)) {
            return $this->sendError('Booking User not found');
        }

        return $this->sendResponse($bookingUser, 'Booking User retrieved successfully');
    }

    /**
     * @OA\Put(
     *      path="/booking-users/{id}",
     *      summary="updateBookingUser",
     *      tags={"BookingUser"},
     *      description="Update BookingUser",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of BookingUser",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/BookingUser")
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
     *                  ref="#/components/schemas/BookingUser"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function update($id, UpdateBookingUserAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        if (!array_key_exists('attended', $input) && array_key_exists('attendance', $input)) {
            $input['attended'] = $input['attendance'];
        }

        // Seguridad: solo Teach/admin o monitores pueden modificar 'attended'
        if (array_key_exists('attended', $input)) {
            $user = $request->user() ?? auth('sanctum')->user();
            $canTeach = $user && method_exists($user, 'tokenCan') && (
                $user->tokenCan('teach:all') ||
                $user->tokenCan('admin:all') ||
                $user->tokenCan('monitor:all')
            );
            $isMonitor = $user && (
                $user->type === 'monitor' ||
                $user->type === 3 ||
                (string)$user->type === '3' ||
                ($user->relationLoaded('monitors') ? $user->monitors->isNotEmpty() : $user->monitors()->exists())
            );
            $isAdmin = $user && (
                $user->type === 'admin' ||
                $user->type === 1 ||
                (string)$user->type === '1'
            );
            if (!$canTeach && !$isMonitor && !$isAdmin) {
                unset($input['attended']);
            } else {
                $input['attended'] = (bool)$input['attended'];
            }
        }
        if (array_key_exists('attended', $input)) {
            $input['attendance'] = $input['attended'];
        }

        $user = $request->user() ?? auth('sanctum')->user();
        if (!$this->canManageSchoolNotes($user)) {
            unset($input['notes_school']);
        }

        /** @var BookingUser $bookingUser */
        $bookingUser = $this->bookingUserRepository->find($id, with: $request->get('with', []));

        if (empty($bookingUser)) {
            return $this->sendError('Booking User not found');
        }

        $bookingUser = $this->bookingUserRepository->update($input, $id);

        $notePayload = [];
        if (array_key_exists('notes', $input)) {
            $notePayload['notes'] = $input['notes'];
        }
        if (array_key_exists('notes_school', $input)) {
            $notePayload['notes_school'] = $input['notes_school'];
        }
        if (!empty($notePayload) && !empty($bookingUser->group_id)) {
            BookingUser::query()
                ->where('booking_id', $bookingUser->booking_id)
                ->where('group_id', $bookingUser->group_id)
                ->where('client_id', $bookingUser->client_id)
                ->where('school_id', $bookingUser->school_id)
                ->update($notePayload);
        }

        return $this->sendResponse(new BookingUserResource($bookingUser), 'BookingUser updated successfully');
    }

    private function canManageSchoolNotes($user): bool
    {
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'tokenCan') && (
            $user->tokenCan('admin:all') ||
            $user->tokenCan('teach:all') ||
            $user->tokenCan('monitor:all')
        )) {
            return true;
        }

        return in_array((string) $user->type, ['1', '3'], true) || in_array($user->type, ['admin', 'monitor'], true);
    }

    /**
     * OPTIMIZED ENDPOINT: Get booking users for monitor profile
     * GET /booking-users/monitor/list
     *
     * This endpoint uses Query Builder with JOINs instead of Eloquent eager loading
     * to avoid N+1 problems when loading deeply nested relations.
     *
     * Performance: 50-100+ queries reduced to 1 query
     */
    public function monitorBookings(Request $request): JsonResponse
    {
        $request->validate([
            'monitor_id' => 'required|integer',
            'school_id' => 'required|integer',
            'perPage' => 'nullable|integer|max:100',
            'page' => 'nullable|integer',
            'finished' => 'nullable|in:0,1',
            'status' => 'nullable|string',
        ]);

        $monitorId = $request->get('monitor_id');
        $schoolId = $request->get('school_id');
        $perPage = $request->get('perPage', 10);
        $finished = $request->get('finished');
        $status = $request->get('status');

        // OPTIMIZACIÓN: Una sola query con JOINs en lugar de eager loading anidado
        $query = \DB::table('booking_users as bu')
            ->join('bookings as b', 'bu.booking_id', '=', 'b.id')
            ->join('clients as c', 'bu.client_id', '=', 'c.id')
            ->leftJoin('courses as co', 'bu.course_id', '=', 'co.id')
            ->leftJoin('degrees as d', 'bu.degree_id', '=', 'd.id')
            ->leftJoin('course_groups as cg', 'bu.course_group_id', '=', 'cg.id')
            ->leftJoin('sports as s', 'co.sport_id', '=', 's.id')
            ->where('bu.monitor_id', $monitorId)
            ->where('bu.school_id', $schoolId)
            ->whereNull('bu.deleted_at')
            ->whereNull('b.deleted_at');

        // Aplicar filtros
        if ($finished !== null) {
            if ($finished == 1) {
                $query->where('bu.date', '<', now());
            } else {
                $query->where('bu.date', '>=', now());
            }
        }

        if ($status) {
            $statusArray = explode(',', $status);
            $query->whereIn('b.status', $statusArray);
        }

        // Seleccionar campos necesarios
        $query->selectRaw('
            bu.id,
            bu.booking_id,
            bu.client_id,
            bu.course_id,
            bu.degree_id,
            bu.monitor_id,
            bu.date,
            bu.hour_start,
            bu.hour_end,
            bu.price,
            bu.currency,
            bu.attended,
            bu.status as booking_user_status,
            b.status as booking_status,
            b.paid as booking_paid,
            b.price_total as booking_price_total,
            CONCAT(c.first_name, " ", c.last_name) as client_name,
            c.email as client_email,
            c.phone as client_phone,
            co.name as course_name,
            co.course_type,
            d.name as degree_name,
            cg.name as course_group_name,
            s.name as sport_name,
            s.icon_collective as sport_icon
        ');

        // Ordenar por fecha más reciente
        $query->orderBy('bu.date', 'desc')
              ->orderBy('bu.hour_start', 'desc');

        // Paginar resultados
        $bookingUsers = $query->paginate($perPage);

        \Log::info('MONITOR BOOKINGS OPTIMIZED', [
            'monitor_id' => $monitorId,
            'total_results' => $bookingUsers->total(),
            'query_optimization' => 'Using single JOIN query instead of N+1 eager loading'
        ]);

        return $this->sendResponse($bookingUsers, 'Monitor booking users retrieved successfully');
    }

    /**
     * @OA\Delete(
     *      path="/booking-users/{id}",
     *      summary="deleteBookingUser",
     *      tags={"BookingUser"},
     *      description="Delete BookingUser",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of BookingUser",
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
        /** @var BookingUser $bookingUser */
        $bookingUser = $this->bookingUserRepository->find($id);

        if (empty($bookingUser)) {
            return $this->sendError('Booking User not found');
        }

        $bookingUser->delete();

        return $this->sendSuccess('Booking User deleted successfully');
    }
}
