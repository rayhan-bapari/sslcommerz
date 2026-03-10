<?php

namespace RayhanBapari\SslcommerzPayment\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use RayhanBapari\SslcommerzPayment\Exceptions\SslcommerzIpnException;
use RayhanBapari\SslcommerzPayment\Services\SslcommerzIpnService;

/**
 * Handles SSLCommerz IPN (Instant Payment Notification) POST requests.
 *
 * SSLCommerz sends a POST to this endpoint after every payment event,
 * including when the customer never returns to your site. This is more
 * reliable than relying on success/cancel/fail redirects alone.
 *
 * Flow:
 *  1. Verify the IPN hash (MD5 signature)
 *  2. Cross-validate with SSLCommerz order validation API
 *  3. Fire `sslcommerz.ipn` Laravel event for application-level handling
 *  4. Always respond 200 OK
 */
class SslcommerzIpnController extends Controller
{
    public function __construct(protected SslcommerzIpnService $ipnService) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $result = $this->ipnService->handle($request);

            // Fire event — application code listens to this
            event('sslcommerz.ipn', $result);

            return response()->json([
                'success' => true,
                'status'  => $result['status'],
                'tran_id' => $result['tran_id'],
                'verified'=> $result['verified'],
            ], 200);

        } catch (SslcommerzIpnException $e) {
            Log::warning('[SSLCommerz IPN] ' . $e->getMessage());

            // Return 200 to prevent SSLCommerz from retrying indefinitely
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 200);

        } catch (\Throwable $e) {
            Log::critical('[SSLCommerz IPN] Unexpected error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal server error.',
            ], 200);
        }
    }
}
