<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebasePushService
{
    protected ?string $serverKey;
    protected ?string $credentialsPath;

    public function __construct()
    {
        $this->serverKey = config('services.firebase.server_key', env('FCM_SERVER_KEY'));
        $this->credentialsPath = config('services.firebase.credentials', env('FIREBASE_CREDENTIALS'));
    }

    /**
     * Send push notification to all registered devices of a given user.
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): array
    {
        $tokens = DeviceToken::where('user_id', $user->id)->pluck('token')->toArray();

        if (empty($tokens)) {
            Log::info("FCM push skipped: User #{$user->id} has no registered device tokens");
            return [
                'success' => false,
                'sent_count' => 0,
                'reason' => 'no_device_tokens',
            ];
        }

        return $this->sendToTokens($tokens, $title, $body, $data, $user);
    }

    /**
     * Dispatch notification to a list of FCM device tokens.
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = [], ?User $user = null): array
    {
        if (empty($tokens)) {
            return ['success' => false, 'sent_count' => 0, 'reason' => 'empty_tokens'];
        }

        // If FCM credentials are not configured, log cleanly and return graceful response
        if (empty($this->serverKey) && empty($this->credentialsPath)) {
            Log::info("FCM Push notification queued (FCM_SERVER_KEY / FIREBASE_CREDENTIALS unconfigured in environment)", [
                'user_id' => $user?->id,
                'title' => $title,
                'body' => $body,
                'tokens_count' => count($tokens),
            ]);

            // Record notification log entry for auditing
            if ($user) {
                NotificationLog::create([
                    'user_id' => $user->id,
                    'event_type' => $data['type'] ?? 'push_notification',
                    'channel' => 'fcm_push',
                    'recipient' => 'mobile_devices(' . count($tokens) . ')',
                    'message' => "{$title}: {$body}",
                    'status' => 'pending_credentials',
                    'response_payload' => ['notice' => 'Configure FCM_SERVER_KEY or FIREBASE_CREDENTIALS to activate live FCM network dispatch'],
                ]);
            }

            return [
                'success' => true,
                'sent_count' => count($tokens),
                'mock' => true,
                'reason' => 'pending_fcm_configuration',
            ];
        }

        // Live FCM network dispatch
        $sentCount = 0;
        $failedCount = 0;

        foreach ($tokens as $token) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'key=' . $this->serverKey,
                    'Content-Type' => 'application/json',
                ])->timeout(5)->post('https://fcm.googleapis.com/fcm/send', [
                    'to' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                        'sound' => 'default',
                        'click_action' => 'FCM_PLUGIN_ACTIVITY',
                    ],
                    'data' => array_merge($data, [
                        'title' => $title,
                        'body' => $body,
                    ]),
                    'priority' => 'high',
                ]);

                if ($response->successful()) {
                    $sentCount++;
                } else {
                    $failedCount++;
                    Log::warning("FCM dispatch error for token {$token}: " . $response->body());
                }
            } catch (\Throwable $e) {
                $failedCount++;
                Log::error("FCM dispatch exception: " . $e->getMessage());
            }
        }

        if ($user) {
            NotificationLog::create([
                'user_id' => $user->id,
                'event_type' => $data['type'] ?? 'push_notification',
                'channel' => 'fcm_push',
                'recipient' => 'mobile_devices(' . count($tokens) . ')',
                'message' => "{$title}: {$body}",
                'status' => $sentCount > 0 ? 'sent' : 'failed',
                'response_payload' => [
                    'sent' => $sentCount,
                    'failed' => $failedCount,
                ],
            ]);
        }

        return [
            'success' => $sentCount > 0,
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
        ];
    }
}
