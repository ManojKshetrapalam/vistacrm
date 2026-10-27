<?php

namespace App\Services\WhatsApp;

interface WhatsAppServiceInterface
{
    /**
     * Send a WhatsApp message to recipient.
     *
     * @param string $recipientPhone Phone number with country code
     * @param string $message Text content or template message
     * @param array $metadata Additional context metadata
     * @return array Result array ['success' => bool, 'message_id' => string, 'provider' => string, 'status' => string, 'error' => ?string]
     */
    public function sendMessage(string $recipientPhone, string $message, array $metadata = []): array;

    /**
     * Check if the provider is fully configured with production credentials.
     */
    public function isConfigured(): bool;

    /**
     * Provider identification name (e.g. 'mock', 'meta_cloud_api').
     */
    public function getProviderName(): string;
}
