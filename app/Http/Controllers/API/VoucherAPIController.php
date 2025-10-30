<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\API\CreateVoucherAPIRequest;
use App\Http\Requests\API\UpdateVoucherAPIRequest;
use App\Http\Resources\API\VoucherResource;
use App\Models\Voucher;
use App\Repositories\VoucherRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class VoucherController
 */

class VoucherAPIController extends AppBaseController
{
    /** @var  VoucherRepository */
    private $voucherRepository;

    public function __construct(VoucherRepository $voucherRepo)
    {
        $this->voucherRepository = $voucherRepo;
    }

    /**
     * @OA\Get(
     *      path="/vouchers",
     *      summary="getVoucherList",
     *      tags={"Voucher"},
     *      description="Get all Vouchers",
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
     *                  @OA\Items(ref="#/components/schemas/Voucher")
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
        // Prepare filters, removing special filter parameters
        $filters = $request->except([
            'skip', 'limit', 'search', 'exclude', 'user', 'perPage', 'order', 'orderColumn', 'page', 'with',
            'has_transferred_to_client_id', 'is_expired', 'school_id'
        ]);

        // Handle special filters with custom query
        $query = Voucher::query();

        // Apply school filter if present (required by multi-tenant system)
        if ($request->has('school_id')) {
            $query->where('school_id', $request->get('school_id'));
        }

        // Apply standard filters
        foreach ($filters as $key => $value) {
            if ($value !== null && $value !== '') {
                $query->where($key, $value);
            }
        }

        // Filter by transferred vouchers
        if ($request->get('has_transferred_to_client_id') == '1') {
            $query->whereNotNull('transferred_to_client_id');
        }

        // Filter by expired vouchers
        if ($request->get('is_expired') == '1') {
            $query->where('expires_at', '<', now())
                  ->whereNotNull('expires_at');
        }

        // Apply search
        if ($request->get('search')) {
            $searchTerm = $request->get('search');
            $query->where(function($q) use ($searchTerm) {
                $q->where('code', 'like', "%{$searchTerm}%")
                  ->orWhere('name', 'like', "%{$searchTerm}%");
            });
        }

        // Apply relationships
        if ($request->get('with')) {
            $with = is_array($request->get('with')) ? $request->get('with') : [$request->get('with')];
            $query->with($with);
        }

        // Apply ordering
        $order = $request->get('order', 'desc');
        $orderColumn = $request->get('orderColumn', 'id');
        $query->orderBy($orderColumn, $order);

        // Paginate
        $perPage = $request->get('perPage', 10);
        $vouchers = $query->paginate($perPage);

        return $this->sendResponse($vouchers, 'Vouchers retrieved successfully');
    }

    /**
     * @OA\Post(
     *      path="/vouchers/{id}/restore",
     *      summary="restoreVoucher",
     *      tags={"Voucher"},
     *      description="Restore a trashed voucher",
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="ID of the voucher to restore",
     *          required=true,
     *          @OA\Schema(
     *              type="integer"
     *          )
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
     *                  ref="#/components/schemas/Voucher"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Voucher not found"
     *      )
     * )
     */
    public function restore(int $id): JsonResponse
    {
        // Buscar el voucher, incluyendo los eliminados
        $voucher = $this->voucherRepository->find($id, [], [], true); // true para incluir withTrashed

        if (!$voucher) {
            return $this->sendError('Voucher not found', 404);
        }

        // Restaurar el voucher
        $voucher->restore();

        return $this->sendResponse($voucher, 'Voucher restored successfully');
    }


    /**
     * @OA\Post(
     *      path="/vouchers",
     *      summary="createVoucher",
     *      tags={"Voucher"},
     *      description="Create Voucher",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/Voucher")
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
     *                  ref="#/components/schemas/Voucher"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function store(CreateVoucherAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        if (empty($input['client_id'])) {
            if (empty($input['buyer_name']) || empty($input['buyer_email'])) {
                return $this->sendError('Buyer name and email are required when no client is assigned', [
                    'buyer_name' => ['required'],
                    'buyer_email' => ['required']
                ], 422);
            }
        }

        if (!isset($input['remaining_balance']) && isset($input['quantity'])) {
            $input['remaining_balance'] = $input['quantity'];
        }

        $voucher = $this->voucherRepository->create($input);

        return $this->sendResponse(new VoucherResource($voucher), 'Voucher saved successfully');
    }

    /**
     * @OA\Get(
     *      path="/vouchers/{id}",
     *      summary="getVoucherItem",
     *      tags={"Voucher"},
     *      description="Get Voucher",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Voucher",
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
     *                  ref="#/components/schemas/Voucher"
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
        /** @var Voucher $voucher */
        $voucher = $this->voucherRepository->find($id, with: $request->get('with', []));

        if (empty($voucher)) {
            return $this->sendError('Voucher not found');
        }

        return $this->sendResponse($voucher, 'Voucher retrieved successfully');
    }

    /**
     * @OA\Put(
     *      path="/vouchers/{id}",
     *      summary="updateVoucher",
     *      tags={"Voucher"},
     *      description="Update Voucher",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Voucher",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/Voucher")
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
     *                  ref="#/components/schemas/Voucher"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function update($id, UpdateVoucherAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var Voucher $voucher */
        $voucher = $this->voucherRepository->find($id, with: $request->get('with', []));

        if (empty($voucher)) {
            return $this->sendError('Voucher not found');
        }

        if (array_key_exists('client_id', $input) && empty($input['client_id'])) {
            if (empty($input['buyer_name']) || empty($input['buyer_email'])) {
                return $this->sendError('Buyer name and email are required when no client is assigned', [
                    'buyer_name' => ['required'],
                    'buyer_email' => ['required']
                ], 422);
            }
        }

        $voucher = $this->voucherRepository->update($input, $id);

        return $this->sendResponse(new VoucherResource($voucher), 'Voucher updated successfully');
    }

    /**
     * @OA\Delete(
     *      path="/vouchers/{id}",
     *      summary="deleteVoucher",
     *      tags={"Voucher"},
     *      description="Delete Voucher",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Voucher",
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
        /** @var Voucher $voucher */
        $voucher = $this->voucherRepository->find($id);

        if (empty($voucher)) {
            return $this->sendError('Voucher not found');
        }

        $voucher->delete();

        return $this->sendSuccess('Voucher deleted successfully');
    }

    /**
     * Transfer voucher to another client
     * POST /api/vouchers/{id}/transfer
     */
    public function transfer(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|integer|exists:clients,id'
        ]);

        $voucher = Voucher::find($id);

        if (!$voucher) {
            return $this->sendError('Voucher not found', null, 404);
        }

        if (!$voucher->is_transferable) {
            return $this->sendError('Este bono no es transferible', null, 400);
        }

        if (!$voucher->canBeUsed()) {
            return $this->sendError('Este bono no puede ser usado (expirado o sin saldo)', null, 400);
        }

        $success = $voucher->transferTo($request->input('client_id'));

        if ($success) {
            return $this->sendResponse($voucher->load('transferredToClient'), 'Bono transferido correctamente');
        }

        return $this->sendError('Error al transferir el bono', null, 500);
    }

    /**
     * Get voucher summary with usage stats
     * GET /api/vouchers/{id}/summary
     */
    public function summary(int $id): JsonResponse
    {
        $voucher = Voucher::with(['client', 'school', 'transferredToClient'])->find($id);

        if (!$voucher) {
            return $this->sendError('Voucher not found', null, 404);
        }

        return $this->sendResponse($voucher->getSummary(), 'Voucher summary retrieved successfully');
    }

    /**
     * Get generic vouchers (no client assigned)
     * GET /api/vouchers/generic
     */
    public function generic(Request $request): JsonResponse
    {
        $query = Voucher::whereNull('client_id')
            ->where('payed', true);

        if ($request->has('school_id')) {
            $query->where('school_id', $request->input('school_id'));
        }

        if ($request->has('available_only') && $request->input('available_only')) {
            $query->where('remaining_balance', '>', 0)
                ->where(function($q) {
                    $q->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
                });
        }

        $vouchers = $query->with(['school'])->get();

        return $this->sendResponse($vouchers, 'Generic vouchers retrieved successfully');
    }

    /**
     * Check if voucher can be used by a specific client
     * POST /api/vouchers/{id}/check-availability
     */
    public function checkAvailability(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'nullable|integer|exists:clients,id',
            'course_type_id' => 'nullable|integer'
        ]);

        $voucher = Voucher::find($id);

        if (!$voucher) {
            return $this->sendError('Voucher not found', null, 404);
        }

        $clientId = $request->input('client_id');
        $courseTypeId = $request->input('course_type_id');

        $canBeUsed = $voucher->canBeUsedByClient($clientId);
        $validForCourseType = $voucher->isValidForCourseType($courseTypeId);

        $reasons = [];

        if (!$voucher->canBeUsed()) {
            if ($voucher->isExpired()) {
                $reasons[] = 'Bono expirado';
            }
            if (!$voucher->hasBalance()) {
                $reasons[] = 'Sin saldo disponible';
            }
            if ($voucher->hasReachedMaxUses()) {
                $reasons[] = 'Máximo de usos alcanzado';
            }
        }

        if (!$canBeUsed && $clientId) {
            $reasons[] = 'El bono no puede ser usado por este cliente';
        }

        if (!$validForCourseType && $courseTypeId) {
            $reasons[] = 'El bono no es válido para este tipo de curso';
        }

        $available = $canBeUsed && $validForCourseType;

        return $this->sendResponse([
            'available' => $available,
            'can_be_used' => $canBeUsed,
            'valid_for_course_type' => $validForCourseType,
            'reasons' => $reasons,
            'voucher' => [
                'id' => $voucher->id,
                'code' => $voucher->code,
                'name' => $voucher->name,
                'remaining_balance' => $voucher->remaining_balance,
                'expires_at' => $voucher->expires_at?->format('Y-m-d H:i:s'),
                'is_transferable' => $voucher->is_transferable
            ]
        ], $available ? 'Bono disponible' : 'Bono no disponible');
    }
}
