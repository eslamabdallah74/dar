<?php

namespace App\Jobs;

use App\Enums\QueueName;
use App\Services\WhatsAppService;

/**
 * SendWhatsAppMessageJob handles asynchronous delivery of WhatsApp messages
 * via the configured gateway with deduplication and overlap prevention.
 */
class SendWhatsAppMessageJob extends BaseJob
{
    /**
     * @param  array<string, mixed>  $parameters  Template variables, see WhatsAppGatewayInterface::sendTemplate()
     */
    public function __construct(
        public int $workspaceId,
        public ?int $renterId,
        public string $phone,
        public string $templateName,
        public array $parameters,
        public string $deduplicationKey,
        public ?int $leaseId = null,
        public ?int $rentChargeId = null
    ) {
        $this->onQueue(QueueName::Messaging->value);
    }

    /**
     * Unique identifier to ensure no duplicate job executes simultaneously.
     */
    public function uniqueId(): string
    {
        return "whatsapp:{$this->deduplicationKey}";
    }

    public function handle(WhatsAppService $whatsAppService): void
    {
        $whatsAppService->send(
            workspaceId: $this->workspaceId,
            renterId: $this->renterId,
            phone: $this->phone,
            templateName: $this->templateName,
            parameters: $this->parameters,
            deduplicationKey: $this->deduplicationKey,
            leaseId: $this->leaseId,
            rentChargeId: $this->rentChargeId
        );
    }
}
