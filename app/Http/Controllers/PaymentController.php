<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\NotificationTrait;
use App\Models\Billing;
use App\Models\Guest_Spot_Log;
use App\Models\Location;
use App\Models\Mqtt_Spot_Log;
use App\Models\Public_Spot;
use App\Models\Reservable_Spot;
use App\Services\MqttService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    use NotificationTrait;

    // MQTT Topic Templates (consistent with SpotLogController)
    private const EXIT_GATE = 'garage/%s/exit_gate';
    private const EXIT_DISPLAY = 'garage/%s/exit/display/message';
    private const EXIT_QR = 'garage/%s/exit/display/qrcode';
    private const AVAILABLE_SPOTS = 'garage/%s/available_spots';
    private const PUBLIC_SPOT = 'Public Spot';

    public function getPaymobToken()
    {
        try {
            $response = Http::post('https://accept.paymob.com/api/auth/tokens', [
                'api_key' => env('PAYMOB_API_KEY'),
            ]);

            if (!$response->successful()) {
                Log::error('Paymob Auth Failed', ['response' => $response->body()]);
                return null;
            }

            return $response['token'];
        } catch (\Exception $e) {
            Log::error('Paymob Token Error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function addBalance($authToken, $amountCents)
    {
        try {
            $response = Http::post('https://accept.paymob.com/api/ecommerce/orders', [
                'auth_token' => $authToken,
                'delivery_needed' => false,
                'amount_cents' => $amountCents,
                'currency' => 'EGP',
                'merchant_order_id' => uniqid(),
                'items' => [],
            ]);

            if (!$response->successful()) {
                Log::error('Paymob Order Failed', ['response' => $response->body()]);
                return null;
            }

            return $response['id'];
        } catch (\Exception $e) {
            Log::error('Paymob Order Error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function generatePaymentKey($authToken, $orderId, $amountCents, $billingData, $integrationId)
    {
        try {
            $response = Http::post('https://accept.paymob.com/api/acceptance/payment_keys', [
                'auth_token' => $authToken,
                'amount_cents' => $amountCents,
                'expiration' => 3600,
                'order_id' => $orderId,
                'billing_data' => $billingData,
                'currency' => 'EGP',
                'integration_id' => $integrationId,
            ]);

            if (!$response->successful()) {
                Log::error('Paymob Payment Key Failed', [
                    'response' => $response->body(),
                    'request' => [
                        'order_id' => $orderId,
                        'amount' => $amountCents
                    ]
                ]);
                return null;
            }

            return $response['token'];
        } catch (\Exception $e) {
            Log::error('Paymob Payment Key Error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function checkLocationExistence($branch)
    {
        return Location::where('name', $branch)->value('id');
    }

    public function guestPayment($amount, $plate, $branch = 'default')
    {
        if ($amount < 1) {
            $amount = 1;
        } else {
            $amount = floor($amount);
        }
        $amountCents = $amount * 100;
        $method = 'card';

        // Create billing record with branch information
        $billing = Billing::create([
            'amount' => $amount,
            'currency' => 'EGP',
            'status' => 'pending',
            'method' => $method,
            'license_plate' => $plate,
            'branch' => $branch,  // Store branch for callback processing
        ]);

        // Step 1: Get Authentication Token
        $authToken = $this->getPaymobToken();
        if (!$authToken) {
            $billing->update(['status' => 'failed']);
            return false;
        }

        // Step 2: Create Order - include billing ID in merchant_order_id
        $orderId = $this->addBalance($authToken, $amountCents);
        if (!$orderId) {
            $billing->update(['status' => 'failed']);
            return false;
        }

        // Update billing with Paymob order ID
        $billing->update(['paymob_order_id' => $orderId]);

        // Step 3: Generate Payment Key
        $integrationIds = [
            'card' => env('PAYMOB_CARD_INTEGRATION_ID'),
            'wallet' => env('PAYMOB_WALLET_INTEGRATION_ID'),
        ];
        $billingData = [
            "apartment" => "NA",
            "email" => "guest@mail.com",
            "floor" => "NA",
            "first_name" => "Guest",
            "street" => "NA",
            "building" => "NA",
            "phone_number" => '+201000000000',
            "shipping_method" => "NA",
            "postal_code" => "NA",
            "city" => "NA",
            "country" => "EG",
            "last_name" => "NA",
            "state" => "NA"
        ];

        // Include billing ID in the callback URL
        $callbackUrl = route('paymob.callback');

        $paymentKey = $this->generatePaymentKey(
            $authToken,
            $orderId,
            $amountCents,
            array_merge($billingData, ['callback_url' => $callbackUrl]),
            $integrationIds[$method]
        );
        if (!$paymentKey) {
            $billing->update(['status' => 'failed']);
            return false;
        }

        // Step 4: Generate Payment URL
        $iframeIds = [
            'card' => env('PAYMOB_CARD_IFRAME_ID'),
            'wallet' => env('PAYMOB_WALLET_IFRAME_ID'),
        ];

        $paymentUrl = "https://accept.paymob.com/api/acceptance/iframes/{$iframeIds[$method]}?payment_token=$paymentKey";
        return response($paymentUrl);
    }

    public function initiatePayment(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'method' => 'required|in:card,wallet'
        ]);

        $user = auth('api')->user();
        $amountCents = $request->amount * 100;

        // Create billing record first
        $billing = Billing::create([
            'user_id' => $user->id,
            'amount' => $request->amount,
            'currency' => 'EGP',
            'status' => 'pending',
            'method' => $request->input('method'),
            'message' => 'Transaction is being processed.',
            'completed_at' => now(),
            'branch' => $request->input('branch', 'default'), // Add branch support for user payments too
        ]);

        // Step 1: Get Authentication Token
        $authToken = $this->getPaymobToken();
        if (!$authToken) {
            $billing->update(['status' => 'failed' ,'message' => "Something went wrong.\nPlease contact application support"]);
            return response()->json(['error' => 'Payment gateway authentication failed'], 500);
        }

        // Step 2: Create Order - include billing ID in merchant_order_id
        $orderId = $this->addBalance($authToken, $amountCents);
        if (!$orderId) {
            $billing->update(['status' => 'failed','message' => "Something went wrong.\nPlease contact application support"]);
            return response()->json(['error' => 'Order creation failed'], 500);
        }

        // Update billing with Paymob order ID
        $billing->update(['paymob_order_id' => $orderId]);

        // Step 3: Generate Payment Key
        $integrationIds = [
            'card' => env('PAYMOB_CARD_INTEGRATION_ID'),
            'wallet' => env('PAYMOB_WALLET_INTEGRATION_ID'),
        ];

        $billingData = [
            "apartment" => "NA",
            "email" => $user->email,
            "floor" => "NA",
            "first_name" => $user->name,
            "street" => "NA",
            "building" => "NA",
            "phone_number" => $user->userData->phone ?? '+201000000000',
            "shipping_method" => "NA",
            "postal_code" => "NA",
            "city" => "NA",
            "country" => "EG",
            "last_name" => "NA",
            "state" => "NA"
        ];

        // Include billing ID in the callback URL
        $callbackUrl = route('paymob.callback');

        $paymentKey = $this->generatePaymentKey(
            $authToken,
            $orderId,
            $amountCents,
            array_merge($billingData, ['callback_url' => $callbackUrl]),
            $integrationIds[$request->input('method')]
        );

        if (!$paymentKey) {
            $billing->update(['status' => 'failed','message' => "Something went wrong.\nPlease contact application support"]);
            return response()->json(['error' => 'Payment key generation failed'], 500);
        }

        // Step 4: Generate Payment URL
        $iframeIds = [
            'card' => env('PAYMOB_CARD_IFRAME_ID'),
            'wallet' => env('PAYMOB_WALLET_IFRAME_ID'),
        ];

        $paymentUrl = "https://accept.paymob.com/api/acceptance/iframes/{$iframeIds[$request->input('method')]}?payment_token=$paymentKey";

        return response()->json([
            'success' => true,
            'payment_url' => $paymentUrl,
        ]);
    }

    public function paymobCallback(Request $request)
    {
        try {
            $paymobOrderId = $request->obj['order']['id'] ?? null;
            if (!$paymobOrderId) {
                throw new \Exception('Paymob order ID not found in callback');
            }

            $billing = Billing::where('paymob_order_id', $paymobOrderId)->firstOrFail();
            $chargeAmount = $billing->amount;

            // Get branch context from billing record
            $branch = $billing->branch ?? 'default';
            $branchID = $this->checkLocationExistence($branch);

            if (!$branchID) {
                Log::error('Invalid branch in billing record', ['branch' => $branch, 'billing_id' => $billing->id]);
                // Continue with default behavior but log the error
            }

            // Check multiple success indicators
            $isSuccess = (
                ($request->has('success') && $request->success === 'true') ||
                (isset($request->obj['success']) && $request->obj['success'] === true)
            );

            // Check capture status from multiple locations
            $isCaptured = isset($request->obj['data']['migs_order']['status']) &&
                $request->obj['data']['migs_order']['status'] === 'CAPTURED';

            // Prepare update data
            $updateData = [
                'status' => ($isSuccess && $isCaptured) ? 'completed' : 'failed',
                'completed_at' => ($isSuccess && $isCaptured) ? now() : null,
            ];

            // Get transaction ID from multiple possible locations
            if (isset($request->obj['id'])) {
                $updateData['transaction_id'] = $request->obj['id'];
            } elseif (isset($request->obj['data']['txn_response_code'])) {
                $updateData['transaction_id'] = $request->obj['data']['txn_response_code'];
            }

            $billing->update($updateData);

            if ($isSuccess && $isCaptured) {
                if ($billing->user_id) {
                    // Handle user balance recharge
                    $user = $billing->user;
                    $user->userData()->increment('balance', $chargeAmount);
                    $message = "Your account has been recharged with EGP " . $chargeAmount . ". Your new balance is EGP " . $user->userData->balance . ".";
                    $billing->update(['message' => $message]);
                    // $this->sendSms($message, $user->userData->phone);
                } else {
                    // Handle guest payment - consistent with main code logic
                    return $this->handleGuestPaymentCompletion($billing, $branch, $branchID);
                }
                return response()->json(['success' => true, 'message' => 'Payment completed successfully']);
            }

            $billing->update(['message' => 'Transaction failed, please make sure your card has enough credits or contact your bank.']);
            return response()->json(['success' => false, 'message' => 'Payment not completed'], 400);

        } catch (\Exception $e) {
            Log::error('Paymob Callback Error', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error processing callback: ' . $e->getMessage()
            ], 500);
        }
    }

    private function handleGuestPaymentCompletion($billing, $branch, $branchID)
    {
        return DB::transaction(function () use ($billing, $branch, $branchID) {
            $plate = $billing->license_plate;

            // Find the correct unpaid guest log entry - consistent with main code
            $currentLog = Guest_Spot_Log::where([
                ['license_plate', $plate],
                ['is_payed', 0]
            ]);

            // Add location filter if branchID exists
            if ($branchID) {
                $currentLog->where('location_id', $branchID);
            }

            $currentLog = $currentLog->whereDate('entered_at', Carbon::today())
                ->orderBy('entered_at', 'desc')
                ->first();

            if (!$currentLog) {
                throw new \Exception('No unpaid guest log found for plate: ' . $plate);
            }

            // Update the log entry
            $currentLog->update([
                'is_payed' => 1,
                'exited_at' => now()
            ]);

            // Remove from MQTT tracking - consistent with main code
            Mqtt_Spot_Log::where([
                ['license_plate', $plate],
                ['location', $branch]
            ])->delete();

            // Update available spots using consistent logic
            $this->updateAvailableSpots($branch, $branchID);

            // Open gate and clear display - using consistent MQTT topics
            $mqttService = new MqttService();
            $mqttService->publish(sprintf(self::EXIT_QR, $branch), '');
            $mqttService->publish(sprintf(self::EXIT_GATE, $branch), 'open');

            $message = "Plate: $plate\nPayment completed\nGoodbye :)";
            $mqttService->publish(sprintf(self::EXIT_DISPLAY, $branch), $message);

            return response()->json(['success' => true, 'message' => 'Guest payment completed successfully']);
        });
    }

    private function updateAvailableSpots($branch, $branchID)
    {
        // Use the same counting logic as main code
        $publicSpotCount = Mqtt_Spot_Log::LocationCount(self::PUBLIC_SPOT, $branch);

        if ($branchID) {
            $publicSpots = Public_Spot::where('location_id', $branchID)->count();
            $reservableSpots = Reservable_Spot::where([
                ['is_occupied', 0],
                ['location_id', $branchID]
            ])->count();
        } else {
            // Fallback for backward compatibility
            $publicSpots = Public_Spot::count();
            $reservableSpots = 0;
        }

        $mqttService = new MqttService();
        $mqttService->publish(
            sprintf(self::AVAILABLE_SPOTS, $branch),
            ($publicSpots - $publicSpotCount) . ' ' . $reservableSpots
        );
    }
}
