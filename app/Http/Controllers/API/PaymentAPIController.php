<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\API\CreatePaymentAPIRequest;
use App\Http\Requests\API\UpdatePaymentAPIRequest;
use App\Models\Booking;
use App\Models\Payment;
use App\Repositories\PaymentRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class PaymentController
 */

class PaymentAPIController extends AppBaseController
{
    /** @var  PaymentRepository */
    private $paymentRepository;

    public function __construct(PaymentRepository $paymentRepo)
    {
        $this->paymentRepository = $paymentRepo;
    }

    /**
     * @OA\Get(
     *      path="/payments",
     *      summary="getPaymentList",
     *      tags={"Payment"},
     *      description="Get all Payments",
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
     *                  @OA\Items(ref="#/components/schemas/Payment")
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
        $payments = $this->paymentRepository->all(
            searchArray: $request->except(['skip', 'limit', 'search', 'exclude', 'user', 'perPage', 'order', 'orderColumn', 'page', 'with', 'isMultiple', 'courseType', 'finished']),
            search: $request->get('search'),
            skip: $request->get('skip'),
            limit: $request->get('limit'),
            with: $request->get('with', []),
            order: $request->get('order', 'desc'),
            orderColumn: $request->get('orderColumn', 'id')
        );

        return $this->sendResponse($payments, 'Payments retrieved successfully');
    }

    /**
     * @OA\Post(
     *      path="/payments",
     *      summary="createPayment",
     *      tags={"Payment"},
     *      description="Create Payment",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/Payment")
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
     *                  ref="#/components/schemas/Payment"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function store(CreatePaymentAPIRequest $request): JsonResponse
    {
        $input = $request->all();
        $bookingId = $input['booking_id'] ?? null;
        if ($bookingId) {
            $booking = Booking::find($bookingId);
            if ($booking && (int) $booking->payment_method_id === Booking::ID_ONLINE) {
                $payrexxReference = trim((string) ($input['payrexx_reference'] ?? ''));
                if ($payrexxReference === '') {
                    return $this->sendError('Payrexx reference required for online link payments');
                }
            }
        }

        $payment = $this->paymentRepository->create($input);

        return $this->sendResponse($payment, 'Payment saved successfully');
    }

    /**
     * @OA\Get(
     *      path="/payments/{id}",
     *      summary="getPaymentItem",
     *      tags={"Payment"},
     *      description="Get Payment",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Payment",
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
     *                  ref="#/components/schemas/Payment"
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
        /** @var Payment $payment */
        $payment = $this->paymentRepository->find($id, with: $request->get('with', []));

        if (empty($payment)) {
            return $this->sendError('Payment not found');
        }

        return $this->sendResponse($payment, 'Payment retrieved successfully');
    }

    /**
     * @OA\Put(
     *      path="/payments/{id}",
     *      summary="updatePayment",
     *      tags={"Payment"},
     *      description="Update Payment",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Payment",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/Payment")
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
     *                  ref="#/components/schemas/Payment"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function update($id, UpdatePaymentAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var Payment $payment */
        $payment = $this->paymentRepository->find($id, with: $request->get('with', []));

        if (empty($payment)) {
            return $this->sendError('Payment not found');
        }

        $booking = $payment->booking;
        if ($booking && (int) $booking->payment_method_id === Booking::ID_ONLINE) {
            $payrexxReference = array_key_exists('payrexx_reference', $input)
                ? trim((string) $input['payrexx_reference'])
                : trim((string) ($payment->payrexx_reference ?? ''));
            if ($payrexxReference === '') {
                return $this->sendError('Payrexx reference required for online link payments');
            }
        }

        $payment = $this->paymentRepository->update($input, $id);

        return $this->sendResponse($payment, 'Payment updated successfully');
    }

    /**
     * @OA\Delete(
     *      path="/payments/{id}",
     *      summary="deletePayment",
     *      tags={"Payment"},
     *      description="Delete Payment",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Payment",
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
        /** @var Payment $payment */
        $payment = $this->paymentRepository->find($id);

        if (empty($payment)) {
            return $this->sendError('Payment not found');
        }

        $payment->delete();

        return $this->sendSuccess('Payment deleted successfully');
    }
}
