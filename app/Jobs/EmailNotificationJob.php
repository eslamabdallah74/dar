<?php

namespace App\Jobs;

use App\Enums\QueueName;
use App\Mail\GenericNotificationMail;
use Illuminate\Support\Facades\Mail;

/**
 * Dispatches transactional email notifications asynchronously
 * with duplicate suppression and error handling.
 */
class EmailNotificationJob extends BaseJob
{
    public function __construct(
        public string $recipient,
        public string $mailSubject,
        public string $contentBody,
    ) {
        $this->onQueue(QueueName::Notifications->value);
    }

    public function uniqueId(): string
    {
        return 'email:'.$this->recipient.':'.md5($this->mailSubject.$this->contentBody);
    }

    public function handle(): void
    {
        Mail::to($this->recipient)->send(
            new GenericNotificationMail($this->mailSubject, $this->contentBody)
        );
    }
}
