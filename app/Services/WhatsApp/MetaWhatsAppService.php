<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWhatsAppService implements WhatsAppServiceInterface
{
    protected ?string $token;
    protected ?string $phoneNumberId;

    public function __construct()
    {
        $this->token = config('services.whatsapp.token');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
    }

    public function sendMessage(string $recipientPhone, string $message, array $metadata = []): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message_id' => null,
                'provider' => 'meta_cloud_api',
                'status' => 'failed',
                'error' => 'Meta WhatsApp Business API credentials (token/phone_number_id) are not configured in environment.',
            ];
        }

        // Clean recipient digits
        $recipientClean = preg_replace('/\D+/', '', $recipientPhone);

        try {
            $response = Http::withToken($this->token)
                ->timeout(10)
                ->post("https://graph.facebook.com/v19.0/{$this->phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $recipientClean,
                    'type' => 'text',
                    'text' => ['body' => $message],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $messageId = $data['messages'][0]['id'] ?? ('meta_' . uniqid());

                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'provider' => 'meta_cloud_api',
                    'status' => 'sent',
                    'error' => null,
                ];
            }

            Log::error('[META WHATSAPP ERROR] HTTP ' . $response->status() . ': ' . $response->body());

            return [
                'success' => false,
                'message_id' => null,
                'provider' => 'meta_cloud_api',
                'status' => 'failed',
                'error' => 'WhatsApp API Error: ' . ($response->json('error.message') ?? $response->body()),
            ];
        } catch (\Throwable $e) {
            Log::error('[META WHATSAPP EXCEPTION] ' . $e->getMessage());

            return [
                'success' => false,
                'message_id' => null,
                'provider' => 'meta_cloud_api',
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->token) && !empty($this->phoneNumberId);
    }

    public function getProviderName(): string
    {
        return 'meta_cloud_api';
    }
}
