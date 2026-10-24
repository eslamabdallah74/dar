<?php

namespace App\Jobs;

use App\Contracts\WhatsAppGatewayInterface;
use App\Enums\QueueName;

/**
 * Direct WhatsApp text message job skeleton.
 * Employs the WhatsAppGatewayInterface contract via dependency injection.
 */
class WhatsAppMessageJob extends BaseJob
{
    public function __construct(
        public string $toPhone,
        public string $message
    ) {
        $this->onQueue(QueueName::Messaging->value);
    }

    public function uniqueId(): string
    {
        return 'whatsapp_direct:'.$this->toPhone.':'.md5($this->message);
    }

    public function handle(WhatsAppGatewayInterface $gateway): void
    {
        $gateway->sendText($this->toPhone, $this->message);
    }
}
