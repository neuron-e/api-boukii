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
        $bookingUsers = $this->bookingUserRepository->all(
            $request->except(['skip', 'limit', 'search', 'exclude', 'user', 'perPage', 'order', 'orderColumn', 'page', 'with']),
            $request->get('search'),
            $request->get('skip'),
            $request->get('limit'),
            $request->perPage,
            $request->get('with', []),
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

        /** @var BookingUser $bookingUser */
        $bookingUser = $this->bookingUserRepository->find($id, with: $request->get('with', []));

        if (empty($bookingUser)) {
            return $this->sendError('Booking User not found');
        }

        $bookingUser = $this->bookingUserRepository->update($input, $id);

        return $this->sendResponse(new BookingUserResource($bookingUser), 'BookingUser updated successfully');
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
