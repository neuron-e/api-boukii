<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Payrexx\PayrexxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Payrexx\Models\Request\Gateway as GatewayRequest;
use Payrexx\Payrexx;

class PaymentTerminalController extends Controller
{
    protected $payrexxService;

    public function __construct(PayrexxService $payrexxService)
    {
        $this->payrexxService = $payrexxService;
    }

    /**
     * Get the VPOS (Virtual Point of Sale) URL for the school
     */
    public function getVposUrl(Request $request)
    {
        try {
            $user = Auth::user();
            $school = $user->schools()->first();

            if (!$school) {
                return response()->json([
                    'success' => false,
                    'message' => 'School not found'
                ], 404);
            }

            $instance = $school->getPayrexxInstance();

            if (empty($instance)) {
                Log::warning('Payrexx instance not configured for VPOS', [
                    'school_id' => $school->id,
                    'user_id' => Auth::id()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payrexx instance is not configured'
                ], 400);
            }

            $apiBaseDomain = config('services.payrexx.base_domain', 'pay.boukii.com');
            $vposUrl = "https://{$instance}.{$apiBaseDomain}/de/vpos";

            Log::info('VPOS URL generated', [
                'school_id' => $school->id,
                'user_id' => Auth::id(),
                'vpos_url' => $vposUrl
            ]);

            return response()->json([
                'success' => true,
                'vpos_url' => $vposUrl
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating VPOS URL', [
                'school_id' => optional(Auth::user()->schools()->first())->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error generating VPOS URL: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a payment link for ad-hoc payments
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'client_email' => 'nullable|email',
            'client_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            $school = $user->schools()->first();

            if (!$school) {
                return response()->json([
                    'success' => false,
                    'message' => 'School not found'
                ], 404);
            }

            if (empty($school->getPayrexxInstance()) || empty($school->getPayrexxKey())) {
                Log::warning('Payrexx configuration incomplete for payment terminal', [
                    'school_id' => $school->id,
                    'user_id' => Auth::id()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payrexx configuration is incomplete'
                ], 400);
            }

            $amount = (float) $request->input('amount');
            $description = $request->input('description', '');
            $clientEmail = $request->input('client_email');
            $clientName = $request->input('client_name');

            // Create Payrexx gateway
            $gateway = new GatewayRequest();

            // Set amount (Payrexx uses cents)
            $gateway->setAmount($amount * 100);
            $gateway->setCurrency('CHF');

            // Set description
            if (!empty($description)) {
                $gateway->setPurpose($description);
            }

            // Set validity to 60 minutes
            $gateway->setValidity(60);

            // Set success/cancel redirect URLs (optional - can point to admin panel)
            $gateway->setSuccessRedirectUrl(config('app.frontend_url') . '/payment-terminal?status=success');
            $gateway->setCancelRedirectUrl(config('app.frontend_url') . '/payment-terminal?status=cancel');
            $gateway->setFailedRedirectUrl(config('app.frontend_url') . '/payment-terminal?status=failed');

            // Create Payrexx client
            $apiBaseDomain = config('services.payrexx.base_domain', 'pay.boukii.com');
            $payrexx = new Payrexx(
                $school->getPayrexxInstance(),
                $school->getPayrexxKey(),
                '',
                $apiBaseDomain
            );

            // Create the gateway
            $createdGateway = $payrexx->create($gateway);

            if (!$createdGateway || !$createdGateway->getLink()) {
                Log::error('Failed to create payment terminal gateway', [
                    'school_id' => $school->id,
                    'amount' => $amount
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate payment link'
                ], 500);
            }

            $paymentLink = $createdGateway->getLink();

            Log::info('Payment terminal link created', [
                'school_id' => $school->id,
                'user_id' => Auth::id(),
                'amount' => $amount,
                'link' => $paymentLink
            ]);

            return response()->json([
                'success' => true,
                'payment_link' => $paymentLink,
                'amount' => $amount,
                'currency' => 'CHF'
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating payment terminal link', [
                'school_id' => optional(Auth::user()->schools()->first())->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error generating payment link: ' . $e->getMessage()
            ], 500);
        }
    }
}
