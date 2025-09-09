<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TestNotificationController extends Controller
{
    public function __construct(
        private PushNotificationService $pushNotificationService
    ) {}

    public function testNotification(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Get user's device tokens
            $deviceTokens = DeviceToken::forUser($user->getId())
                ->active()
                ->get();

            if ($deviceTokens->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No device tokens found for user. Make sure push notifications are enabled in the app.'
                ], 400);
            }

            // Send test notification to each device
            $sent = 0;
            foreach ($deviceTokens as $token) {
                try {
                    $this->sendTestNotification($token->getToken(), $token->getDeviceType(), $user->getName());
                    $sent++;
                } catch (\Exception $e) {
                    Log::error('Failed to send test notification', [
                        'token' => substr($token->getToken(), 0, 10) . '...',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Test notification sent to {$sent} device(s)",
                'data' => [
                    'total_tokens' => $deviceTokens->count(),
                    'sent_count' => $sent,
                    'tokens' => $deviceTokens->map(fn($t) => [
                        'device_type' => $t->getDeviceType(),
                        'token_preview' => substr($t->getToken(), 0, 20) . '...',
                        'last_used' => $t->last_used_at?->diffForHumans()
                    ])
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Test notification failed', [
                'user_id' => $request->user()?->getId(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send test notification: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getDeviceTokens(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $tokens = DeviceToken::forUser($user->getId())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($token) => [
                'id' => $token->id,
                'device_type' => $token->getDeviceType(),
                'token_preview' => substr($token->getToken(), 0, 30) . '...',
                'is_active' => $token->isActive(),
                'platform_data' => $token->getPlatformData(),
                'created_at' => $token->created_at?->diffForHumans(),
                'last_used_at' => $token->last_used_at?->diffForHumans()
            ]);

        return response()->json([
            'success' => true,
            'data' => $tokens,
            'total' => $tokens->count()
        ]);
    }

    public function checkConfig(): JsonResponse
    {
        $config = [
            'fcm_project_id' => config('services.fcm.project_id'),
            'service_account_path' => config('services.fcm.service_account_path'),
            'service_account_exists' => file_exists(config('services.fcm.service_account_path', '')),
        ];

        if ($config['service_account_exists']) {
            try {
                $serviceAccount = json_decode(file_get_contents($config['service_account_path']), true);
                $config['service_account_project_id'] = $serviceAccount['project_id'] ?? 'missing';
                $config['service_account_client_email'] = $serviceAccount['client_email'] ?? 'missing';
            } catch (\Exception $e) {
                $config['service_account_error'] = $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'config' => $config
        ]);
    }

    private function sendTestNotification(string $token, string $deviceType, string $userName): void
    {
        // Create a mock notification data structure
        $notificationData = [
            'title' => '🔔 Test Notification',
            'body' => "Hello {$userName}! Your push notifications are working perfectly!",
            'data' => [
                'type' => 'test',
                'user_name' => $userName,
                'timestamp' => now()->toISOString(),
                'test_id' => uniqid()
            ]
        ];

        // Use the same method as real notifications but send individual test
        $mockTokenRecord = new class($token, $deviceType) {
            public function __construct(
                private string $token, 
                private string $deviceType
            ) {}
            
            public function getToken(): string { return $this->token; }
            public function getDeviceType(): string { return $this->deviceType; }
        };

        // Send using the same service method
        $reflection = new \ReflectionClass($this->pushNotificationService);
        $method = $reflection->getMethod('sendToDevice');
        $method->setAccessible(true);
        $method->invoke($this->pushNotificationService, $token, $deviceType, $notificationData);
    }
}