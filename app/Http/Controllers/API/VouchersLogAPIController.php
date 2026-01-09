<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\API\CreateVouchersLogAPIRequest;
use App\Http\Requests\API\UpdateVouchersLogAPIRequest;
use App\Http\Resources\API\VouchersLogResource;
use App\Models\VouchersLog;
use App\Repositories\VouchersLogRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class VouchersLogController
 */

class VouchersLogAPIController extends AppBaseController
{
    /** @var  VouchersLogRepository */
    private $vouchersLogRepository;

    public function __construct(VouchersLogRepository $vouchersLogRepo)
    {
        $this->vouchersLogRepository = $vouchersLogRepo;
    }

    /**
     * @OA\Get(
     *      path="/vouchers-logs",
     *      summary="getVouchersLogList",
     *      tags={"VouchersLog"},
     *      description="Get all VouchersLogs",
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
     *                  @OA\Items(ref="#/components/schemas/VouchersLog")
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
        $vouchersLogs = $this->vouchersLogRepository->all(
            $request->except(['skip', 'limit', 'search', 'exclude', 'user', 'perPage', 'order', 'orderColumn', 'page', 'with']),
            $request->get('search'),
            $request->get('skip'),
            $request->get('limit'),
            $request->perPage,
            $request->get('with', []),
            $request->get('order', 'desc'),
            $request->get('orderColumn', 'id')
        );

        return $this->sendResponse($vouchersLogs, 'Vouchers Logs retrieved successfully');
    }

    /**
     * @OA\Post(
     *      path="/vouchers-logs",
     *      summary="createVouchersLog",
     *      tags={"VouchersLog"},
     *      description="Create VouchersLog",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/VouchersLog")
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
     *                  ref="#/components/schemas/VouchersLog"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function store(CreateVouchersLogAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $vouchersLog = $this->vouchersLogRepository->create($input);

        if (
            isset($input['voucher_id'], $input['booking_id'], $input['amount'])
            && (float) $input['amount'] < 0
        ) {
            $voucher = \App\Models\Voucher::find($input['voucher_id']);

            if (
                $voucher
                && empty($voucher->origin_booking_id)
                && (empty($voucher->origin_type) || $voucher->origin_type === 'unknown')
            ) {
                $voucher->origin_type = 'refund_credit';
                $voucher->origin_booking_id = (int) $input['booking_id'];

                $refundLog = \App\Models\BookingLog::query()
                    ->where('booking_id', $input['booking_id'])
                    ->where('action', 'LIKE', '%refund%')
                    ->latest()
                    ->first();

                if (!$refundLog) {
                    $refundLog = \App\Models\BookingLog::query()
                        ->where('booking_id', $input['booking_id'])
                        ->where('action', 'LIKE', '%cancel%')
                        ->latest()
                        ->first();
                }

                if ($refundLog) {
                    $voucher->origin_booking_log_id = $refundLog->id;
                }

                $voucher->save();
            }
        }

        return $this->sendResponse($vouchersLog, 'Vouchers Log saved successfully');
    }

    /**
     * @OA\Get(
     *      path="/vouchers-logs/{id}",
     *      summary="getVouchersLogItem",
     *      tags={"VouchersLog"},
     *      description="Get VouchersLog",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of VouchersLog",
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
     *                  ref="#/components/schemas/VouchersLog"
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
        /** @var VouchersLog $vouchersLog */
        $vouchersLog = $this->vouchersLogRepository->find($id, with: $request->get('with', []));

        if (empty($vouchersLog)) {
            return $this->sendError('Vouchers Log not found');
        }

        return $this->sendResponse($vouchersLog, 'Vouchers Log retrieved successfully');
    }

    /**
     * @OA\Put(
     *      path="/vouchers-logs/{id}",
     *      summary="updateVouchersLog",
     *      tags={"VouchersLog"},
     *      description="Update VouchersLog",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of VouchersLog",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/VouchersLog")
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
     *                  ref="#/components/schemas/VouchersLog"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function update($id, UpdateVouchersLogAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var VouchersLog $vouchersLog */
        $vouchersLog = $this->vouchersLogRepository->find($id, with: $request->get('with', []));

        if (empty($vouchersLog)) {
            return $this->sendError('Vouchers Log not found');
        }

        $vouchersLog = $this->vouchersLogRepository->update($input, $id);

        return $this->sendResponse(new VouchersLogResource($vouchersLog), 'VouchersLog updated successfully');
    }

    /**
     * @OA\Delete(
     *      path="/vouchers-logs/{id}",
     *      summary="deleteVouchersLog",
     *      tags={"VouchersLog"},
     *      description="Delete VouchersLog",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of VouchersLog",
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
        /** @var VouchersLog $vouchersLog */
        $vouchersLog = $this->vouchersLogRepository->find($id);

        if (empty($vouchersLog)) {
            return $this->sendError('Vouchers Log not found');
        }

        $vouchersLog->delete();

        return $this->sendSuccess('Vouchers Log deleted successfully');
    }
}
