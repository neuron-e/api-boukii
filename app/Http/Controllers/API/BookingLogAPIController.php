<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\API\CreateBookingLogAPIRequest;
use App\Http\Requests\API\UpdateBookingLogAPIRequest;
use App\Http\Resources\API\BookingLogResource;
use App\Models\BookingLog;
use App\Repositories\BookingLogRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class BookingLogController
 */

class BookingLogAPIController extends AppBaseController
{
    /** @var  BookingLogRepository */
    private $bookingLogRepository;

    public function __construct(BookingLogRepository $bookingLogRepo)
    {
        $this->bookingLogRepository = $bookingLogRepo;
    }

    /**
     * @OA\Get(
     *      path="/booking-logs",
     *      summary="getBookingLogList",
     *      tags={"BookingLog"},
     *      description="Get all BookingLogs",
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
     *                  @OA\Items(ref="#/components/schemas/BookingLog")
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
        $bookingLogs = $this->bookingLogRepository->all(
            $request->except(['skip', 'limit', 'search', 'exclude', 'user', 'perPage', 'order', 'orderColumn', 'page', 'with']),
            $request->get('search'),
            $request->get('skip'),
            $request->get('limit'),
            $request->perPage,
            $request->get('with', []),
            $request->get('order', 'desc'),
            $request->get('orderColumn', 'id')
        );

        return $this->sendResponse($bookingLogs, 'Booking Logs retrieved successfully');
    }

    /**
     * @OA\Post(
     *      path="/booking-logs",
     *      summary="createBookingLog",
     *      tags={"BookingLog"},
     *      description="Create BookingLog",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/BookingLog")
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
     *                  ref="#/components/schemas/BookingLog"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function store(CreateBookingLogAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $bookingLog = $this->bookingLogRepository->create($input);

        return $this->sendResponse($bookingLog, 'Booking Log saved successfully');
    }

    /**
     * @OA\Get(
     *      path="/booking-logs/{id}",
     *      summary="getBookingLogItem",
     *      tags={"BookingLog"},
     *      description="Get BookingLog",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of BookingLog",
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
     *                  ref="#/components/schemas/BookingLog"
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
        /** @var BookingLog $bookingLog */
        $bookingLog = $this->bookingLogRepository->find($id, with: $request->get('with', []));

        if (empty($bookingLog)) {
            return $this->sendError('Booking Log not found');
        }

        return $this->sendResponse($bookingLog, 'Booking Log retrieved successfully');
    }

    /**
     * @OA\Put(
     *      path="/booking-logs/{id}",
     *      summary="updateBookingLog",
     *      tags={"BookingLog"},
     *      description="Update BookingLog",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of BookingLog",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/BookingLog")
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
     *                  ref="#/components/schemas/BookingLog"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function update($id, UpdateBookingLogAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var BookingLog $bookingLog */
        $bookingLog = $this->bookingLogRepository->find($id, with: $request->get('with', []));

        if (empty($bookingLog)) {
            return $this->sendError('Booking Log not found');
        }

        $bookingLog = $this->bookingLogRepository->update($input, $id);

        return $this->sendResponse(new BookingLogResource($bookingLog), 'BookingLog updated successfully');
    }

    /**
     * @OA\Delete(
     *      path="/booking-logs/{id}",
     *      summary="deleteBookingLog",
     *      tags={"BookingLog"},
     *      description="Delete BookingLog",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of BookingLog",
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
        /** @var BookingLog $bookingLog */
        $bookingLog = $this->bookingLogRepository->find($id);

        if (empty($bookingLog)) {
            return $this->sendError('Booking Log not found');
        }

        $bookingLog->delete();

        return $this->sendSuccess('Booking Log deleted successfully');
    }
}
