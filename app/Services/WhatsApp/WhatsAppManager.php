<?php

namespace App\Services\WhatsApp;

class WhatsAppManager
{
    protected WhatsAppServiceInterface $provider;

    public function __construct()
    {
        $meta = new MetaWhatsAppService();
        if ($meta->isConfigured()) {
            $this->provider = $meta;
        } else {
            $this->provider = new MockWhatsAppService();
        }
    }

    public function provider(): WhatsAppServiceInterface
    {
        return $this->provider;
    }

    public function setProvider(WhatsAppServiceInterface $provider): void
    {
        $this->provider = $provider;
    }

    public function sendMessage(string $recipientPhone, string $message, array $metadata = []): array
    {
        return $this->provider->sendMessage($recipientPhone, $message, $metadata);
    }

    public function getStatus(): array
    {
        $isConfigured = $this->provider->isConfigured();
        $name = $this->provider->getProviderName();

        return [
            'provider' => $name,
            'driver' => $name,
            'is_configured' => $isConfigured,
            'configured' => $isConfigured,
            'status' => $isConfigured ? 'Production Live' : 'Simulated Mock Mode',
            'phone_number_id' => config('services.whatsapp.phone_number_id'),
            'advisory' => $isConfigured
                ? 'Meta Cloud WhatsApp Business API credentials are active.'
                : 'WHATSAPP_API_TOKEN / WHATSAPP_PHONE_NUMBER_ID are not configured in environment. Running safely in mock mode (simulated dispatch, zero external cost, no real message delivered).',
        ];
    }

    public function getStatusSummary(): array
    {
        return $this->getStatus();
    }
}
