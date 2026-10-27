<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;

class MockWhatsAppService implements WhatsAppServiceInterface
{
    protected static bool $simulateFailure = false;
    protected static string $failureMessage = 'Simulated WhatsApp Gateway Connection Timeout (HTTP 504)';

    public function sendMessage(string $recipientPhone, string $message, array $metadata = []): array
    {
        if (self::$simulateFailure) {
            Log::warning("[MOCK WHATSAPP FAILURE] To: {$recipientPhone} | Error: " . self::$failureMessage);

            return [
                'success' => false,
                'message_id' => null,
                'provider' => 'mock',
                'status' => 'failed',
                'error' => self::$failureMessage,
            ];
        }

        $mockId = 'mock_wa_' . uniqid();
        Log::info("[MOCK WHATSAPP DISPATCH] To: {$recipientPhone} | ID: {$mockId} | Message: " . mb_strimwidth($message, 0, 100, '...'));

        return [
            'success' => true,
            'message_id' => $mockId,
            'provider' => 'mock',
            'status' => 'sent',
            'error' => null,
            'note' => 'Simulated dispatch. WhatsApp Business API credentials not configured in environment.',
        ];
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function getProviderName(): string
    {
        return 'mock';
    }

    public static function setSimulateFailure(bool $shouldFail, string $errorMessage = 'Simulated WhatsApp Gateway Connection Timeout (HTTP 504)'): void
    {
        self::$simulateFailure = $shouldFail;
        self::$failureMessage = $errorMessage;
    }

    public static function resetSimulation(): void
    {
        self::$simulateFailure = false;
        self::$failureMessage = 'Simulated WhatsApp Gateway Connection Timeout (HTTP 504)';
    }
}
