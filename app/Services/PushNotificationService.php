<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Users\User;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatRoom;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PushNotificationService
{
    private string $projectId;
    private string $serviceAccountPath;
    private string $fcmUrl;
    private ?string $accessToken = null;
    private ?int $tokenExpiry = null;

    public function __construct()
    {
        $this->projectId = config('services.fcm.project_id', '');
        $this->serviceAccountPath = config('services.fcm.service_account_path', '');
        $this->fcmUrl = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
    }

    public function sendMessageNotification(ChatMessage $message, ChatRoom $room): void
    {
        Log::info('PushNotificationService::sendMessageNotification called', [
            'message_id' => $message->getId(),
            'room_id' => $room->getId(),
            'sender_id' => $message->getUserId()
        ]);
        
        try {
            // Get all room members except the sender
            $roomMemberIds = $room->membersRelation()
                ->where('user_id', '!=', $message->getUserId())
                ->pluck('user_id')
                ->toArray();

            Log::info('Room member IDs found', [
                'room_id' => $room->getId(),
                'member_ids' => $roomMemberIds,
                'count' => count($roomMemberIds)
            ]);

            if (empty($roomMemberIds)) {
                Log::info('No room members found, skipping notifications', ['room_id' => $room->getId()]);
                return;
            }

            // Get device tokens for these users
            $deviceTokens = DeviceToken::whereIn('user_id', $roomMemberIds)
                ->active()
                ->get();

            Log::info('Device tokens found', [
                'room_id' => $room->getId(),
                'token_count' => $deviceTokens->count(),
                'tokens' => $deviceTokens->map(fn($t) => [
                    'user_id' => $t->getUserId(),
                    'device_type' => $t->getDeviceType(),
                    'token_preview' => substr($t->getToken(), 0, 20) . '...'
                ])->toArray()
            ]);

            if ($deviceTokens->isEmpty()) {
                Log::info('No device tokens found for room members', [
                    'room_id' => $room->getId(),
                    'member_count' => count($roomMemberIds)
                ]);
                return;
            }

            $sender = $message->userRelation;
            $messageText = $this->truncateMessage($message->getMessage());

            // Group tokens by device type for optimized sending
            $tokensByType = $deviceTokens->groupBy('device_type');

            foreach ($tokensByType as $deviceType => $tokens) {
                $this->sendToDeviceType($deviceType, $tokens, [
                    'title' => $room->getName(),
                    'body' => "{$sender->getName()}: {$messageText}",
                    'data' => [
                        'type' => 'chat',
                        'chat_room_id' => $room->getId(),
                        'message_id' => $message->getId(),
                        'sender_id' => $message->getUserId(),
                        'sender_name' => $sender->getName(),
                        'room_name' => $room->getName(),
                        'family_id' => $room->getFamilyId(),
                    ]
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to send push notifications for message', [
                'message_id' => $message->getId(),
                'room_id' => $room->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function sendToDeviceType(string $deviceType, $tokens, array $notificationData): void
    {
        foreach ($tokens as $tokenRecord) {
            $this->sendToDevice($tokenRecord->getToken(), $deviceType, $notificationData);
        }
    }

    private function sendToDevice(string $token, string $deviceType, array $notificationData): void
    {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                Log::error('Failed to get FCM access token');
                return;
            }

            $payload = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $notificationData['title'],
                        'body' => $notificationData['body']
                    ],
                    'data' => array_map('strval', $notificationData['data']), // FCM V1 requires string values
                ]
            ];

            // Add platform-specific configuration
            if ($deviceType === 'ios') {
                $payload['message']['apns'] = [
                    'payload' => [
                        'aps' => [
                            'alert' => [
                                'title' => $notificationData['title'],
                                'body' => $notificationData['body']
                            ],
                            'sound' => 'default',
                            'badge' => 1
                        ]
                    ]
                ];
            } elseif ($deviceType === 'android') {
                $payload['message']['android'] = [
                    'priority' => 'high',
                    'notification' => [
                        'sound' => 'default',
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                    ]
                ];
            }

            $this->sendFCMRequest($payload, $token, $deviceType);

        } catch (\Exception $e) {
            Log::error("Exception sending push notification to {$deviceType}", [
                'error' => $e->getMessage(),
                'token' => substr($token, 0, 10) . '...'
            ]);
        }
    }

    private function getAccessToken(): ?string
    {
        // Check if current token is still valid
        if ($this->accessToken && $this->tokenExpiry && time() < $this->tokenExpiry - 60) {
            return $this->accessToken;
        }

        try {
            // Handle both absolute and relative paths
            $filePath = $this->serviceAccountPath;
            if (!str_starts_with($filePath, '/')) {
                $filePath = base_path($filePath);
            }
            
            if (empty($this->serviceAccountPath) || !file_exists($filePath)) {
                Log::error('Service account file not found', [
                    'original_path' => $this->serviceAccountPath,
                    'resolved_path' => $filePath
                ]);
                return null;
            }

            $serviceAccount = json_decode(file_get_contents($filePath), true);
            
            // Create JWT for OAuth
            $now = time();
            $header = json_encode(['typ' => 'JWT', 'alg' => 'RS256']);
            $claim = json_encode([
                'iss' => $serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/cloud-platform',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => $now + 3600,
                'iat' => $now
            ]);

            $headerEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
            $claimEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($claim));
            
            $signature = '';
            openssl_sign(
                $headerEncoded . '.' . $claimEncoded,
                $signature,
                $serviceAccount['private_key'],
                OPENSSL_ALGO_SHA256
            );
            $signatureEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
            
            $jwt = $headerEncoded . '.' . $claimEncoded . '.' . $signatureEncoded;

            // Get access token
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->accessToken = $data['access_token'];
                $this->tokenExpiry = time() + $data['expires_in'];
                return $this->accessToken;
            } else {
                Log::error('Failed to get FCM access token', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return null;
            }

        } catch (\Exception $e) {
            Log::error('Exception getting FCM access token', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function sendFCMRequest(array $payload, string $token, string $platform): void
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ])->post($this->fcmUrl, $payload);

            if ($response->successful()) {
                Log::info("Push notification sent successfully to {$platform}", [
                    'token' => substr($token, 0, 10) . '...'
                ]);
            } else {
                $result = $response->json();
                Log::error("Failed to send push notification to {$platform}", [
                    'status' => $response->status(),
                    'response' => $result,
                    'token' => substr($token, 0, 10) . '...'
                ]);

                // Handle invalid tokens
                if (isset($result['error']['details'])) {
                    foreach ($result['error']['details'] as $detail) {
                        if ($detail['@type'] === 'type.googleapis.com/google.firebase.fcm.v1.FcmError') {
                            $errorCode = $detail['errorCode'] ?? '';
                            if (in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT'])) {
                                DeviceToken::where('token', $token)->delete();
                                Log::info('Removed invalid device token', [
                                    'token' => substr($token, 0, 10) . '...',
                                    'error' => $errorCode
                                ]);
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Exception sending FCM request to {$platform}", [
                'error' => $e->getMessage(),
                'token' => substr($token, 0, 10) . '...'
            ]);
        }
    }

    private function truncateMessage(string $message, int $maxLength = 100): string
    {
        if (strlen($message) <= $maxLength) {
            return $message;
        }

        return substr($message, 0, $maxLength - 3) . '...';
    }

    public function sendInvitationNotification(User $user, string $familyName, string $inviterName): void
    {
        try {
            $deviceTokens = DeviceToken::forUser($user->getId())
                ->active()
                ->get();

            if ($deviceTokens->isEmpty()) {
                return;
            }

            $tokensByType = $deviceTokens->groupBy('device_type');

            foreach ($tokensByType as $deviceType => $tokens) {
                $this->sendToDeviceType($deviceType, $tokens, [
                    'title' => 'Family Invitation',
                    'body' => "{$inviterName} invited you to join {$familyName}",
                    'data' => [
                        'type' => 'invitation',
                        'family_name' => $familyName,
                        'inviter_name' => $inviterName,
                    ]
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send invitation notification', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }
}