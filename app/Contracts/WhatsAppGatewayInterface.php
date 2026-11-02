<?php

namespace App\Contracts;

/**
 * WhatsApp Gateway contract implementing the Strategy/Adapter pattern.
 * Allows transparent switching between Meta Cloud API, Twilio, MessageBird, or Mock/Log drivers.
 */
interface WhatsAppGatewayInterface
{
    /**
     * Send an official pre-approved WhatsApp template message.
     *
     * @param  string  $toPhone  International format
     * @param  string  $templateName  Template identifier registered on Meta
     * @param  array<string, mixed>  $parameters  Key-value replacements for template body variables
     * @return array{success: bool, provider_message_id: ?string, status: string, error_message: ?string}
     */
    public function sendTemplate(string $toPhone, string $templateName, array $parameters = []): array;
}
