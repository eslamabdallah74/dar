<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GenericNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $mailSubject,
        public string $contentBody,
        public array $details = []
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject,
        );
    }

    public function content(): Content
    {
        $escapedSubject = htmlspecialchars($this->mailSubject);
        $escapedBody = nl2br(htmlspecialchars($this->contentBody));

        return new Content(
            htmlString: <<<HTML
            <div dir="rtl" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #1e293b; padding: 24px; max-width: 600px; margin: 20px auto; border: 1px solid #e2e8f0; border-radius: 12px; background-color: #ffffff;">
                <div style="border-bottom: 2px solid #0284c7; padding-bottom: 12px; margin-bottom: 16px;">
                    <h2 style="color: #0f172a; margin: 0; font-size: 20px;">{$escapedSubject}</h2>
                </div>
                <div style="font-size: 15px; color: #334155;">
                    {$escapedBody}
                </div>
                <div style="margin-top: 24px; pt: 16px; border-top: 1px solid #f1f5f9; font-size: 12px; color: #94a3b8; text-align: center;">
                    نظام Nook Mate لإدارة العقارات والإيجارات
                </div>
            </div>
            HTML
        );
    }
}
