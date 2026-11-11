<?php

namespace App\Http\Controllers;

use App\Models\MessageLog;
use App\Models\Renter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Handle the Meta WhatsApp Cloud API webhook verification challenge (GET).
     */
    public function verify(Request $request): Response|JsonResponse
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        $expectedToken = config('services.whatsapp.webhook_verify_token');

        if ($mode === 'subscribe' && ! empty($expectedToken) && hash_equals($expectedToken, (string) $token)) {
            Log::info('[WhatsApp Webhook Verified] Verification challenge succeeded.');

            return response((string) $challenge, 200, ['Content-Type' => 'text/plain']);
        }

        Log::warning('[WhatsApp Webhook Verification Failed] Invalid verify token or mode.', [
            'mode' => $mode,
            'provided_token' => $token,
        ]);

        return response()->json(['error' => 'Forbidden'], 403);
    }

    /**
     * Process incoming Meta WhatsApp Cloud API event notifications (POST).
     */
    public function handle(Request $request): JsonResponse
    {
        $appSecret = config('services.whatsapp.app_secret');

        if (app()->isProduction() && empty($appSecret)) {
            Log::critical('[WhatsApp Webhook Security] WHATSAPP_APP_SECRET is not configured in production environment. Rejecting webhook.');

            return response()->json(['error' => 'Webhook verification misconfigured'], 500);
        }

        if (! empty($appSecret) && ! $this->hasValidSignature($request, (string) $appSecret)) {
            Log::warning('[WhatsApp Webhook Signature Mismatch] Unauthorized payload.');

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        foreach ($this->deliveryStatuses($request->all()) as $statusItem) {
            $this->applyDeliveryStatus($statusItem);
        }

        foreach ($this->inboundMessages($request->all()) as $messageItem) {
            $this->applyInboundMessage($messageItem);
        }

        return response()->json(['status' => 'EVENT_RECEIVED']);
    }

    private function hasValidSignature(Request $request, string $appSecret): bool
    {
        $expectedSignature = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expectedSignature, (string) $request->header('X-Hub-Signature-256', ''));
    }

    /**
     * Meta nests delivery statuses as entry[].changes[].value.statuses[].
     *
     * @param  array<mixed>  $payload
     * @return iterable<mixed>
     */
    private function deliveryStatuses(array $payload): iterable
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                yield from $change['value']['statuses'] ?? [];
            }
        }
    }

    /**
     * Mirror a delivery status reported by Meta onto the matching message log.
     */
    private function applyDeliveryStatus(mixed $statusItem): void
    {
        $providerMessageId = $statusItem['id'] ?? null;
        $reportedStatus = $statusItem['status'] ?? null;

        if (! $providerMessageId || ! $reportedStatus) {
            return;
        }

        $log = MessageLog::withoutGlobalScopes()
            ->where('provider_message_id', $providerMessageId)
            ->first();

        if (! $log) {
            return;
        }

        $mappedStatus = match ($reportedStatus) {
            'read', 'delivered' => 'delivered',
            'sent' => 'sent',
            'failed' => 'failed',
            default => $log->status,
        };

        $updates = ['status' => $mappedStatus];

        if ($mappedStatus === 'delivered' && ! $log->sent_at) {
            $updates['sent_at'] = now();
        }

        $error = $statusItem['errors'][0] ?? null;
        if ($mappedStatus === 'failed' && $error) {
            $updates['error_code'] = (string) ($error['code'] ?? 'UNKNOWN');
            $updates['error_message'] = (string) ($error['message'] ?? $error['title'] ?? 'Delivery failed');
        }

        $log->update($updates);

        Log::info("[WhatsApp Webhook Status Updated] ID: {$providerMessageId} -> {$mappedStatus}");
    }

    /**
     * Meta nests inbound messages as entry[].changes[].value.messages[].
     *
     * @param  array<string, mixed>  $payload
     * @return iterable<int, array<string, mixed>>
     */
    private function inboundMessages(array $payload): iterable
    {
        $entries = is_array($payload['entry'] ?? null) ? $payload['entry'] : [];

        foreach ($entries as $entry) {
            if (is_array($entry)) {
                yield from $this->extractEntryMessages($entry);
            }
        }
    }

    /**
     * @param  array<mixed, mixed>  $entry
     * @return iterable<int, array<string, mixed>>
     */
    private function extractEntryMessages(array $entry): iterable
    {
        $changes = is_array($entry['changes'] ?? null) ? $entry['changes'] : [];

        foreach ($changes as $change) {
            if (! is_array($change) || ! is_array($change['value'] ?? null)) {
                continue;
            }

            $messages = is_array($change['value']['messages'] ?? null) ? $change['value']['messages'] : [];
            foreach ($messages as $message) {
                if (is_array($message)) {
                    /** @var array<string, mixed> $typedMessage */
                    $typedMessage = $message;
                    yield $typedMessage;
                }
            }
        }
    }

    /**
     * Extract plain text content from a Meta inbound message item.
     *
     * @param  array<string, mixed>  $messageItem
     */
    private function extractInboundText(array $messageItem): string
    {
        $messageType = is_string($messageItem['type'] ?? null) ? $messageItem['type'] : '';

        if ($messageType === 'text' && is_array($messageItem['text'] ?? null)) {
            $body = $messageItem['text']['body'] ?? '';

            return is_string($body) ? trim($body) : '';
        }

        if ($messageType === 'button' && is_array($messageItem['button'] ?? null)) {
            $text = $messageItem['button']['text'] ?? '';

            return is_string($text) ? trim($text) : '';
        }

        return '';
    }

    /**
     * Handle incoming renter replies such as STOP / UNSUBSCRIBE for compliance.
     *
     * @param  array<string, mixed>  $messageItem
     */
    private function applyInboundMessage(array $messageItem): void
    {
        $senderPhone = is_string($messageItem['from'] ?? null) ? $messageItem['from'] : '';
        $textBody = $this->extractInboundText($messageItem);

        if (empty($senderPhone) || empty($textBody)) {
            return;
        }

        $upperText = mb_strtoupper($textBody, 'UTF-8');

        $optOutKeywords = [
            'STOP', 'UNSUBSCRIBE', 'CANCEL', 'QUIT', 'END',
            'توقف', 'الغاء', 'إلغاء', 'خروج', 'ايقاف', 'إيقاف',
        ];

        $optInKeywords = [
            'START', 'UNSTOP', 'YES',
            'بدء', 'تفعيل', 'اشتراك', 'استمرار',
        ];

        if (in_array($upperText, $optOutKeywords, true)) {
            $this->applyRenterOptOut($senderPhone);
        } elseif (in_array($upperText, $optInKeywords, true)) {
            $this->applyRenterOptIn($senderPhone);
        }
    }

    /**
     * Resolve national, local, and international variants of a sender's phone number.
     *
     * @return array<int, string>
     */
    private function resolvePhoneVariants(string $senderPhone): array
    {
        $cleaned = (string) preg_replace('/\D+/', '', $senderPhone);
        if ($cleaned === '') {
            return $senderPhone !== '' ? [$senderPhone] : [];
        }

        $variants = [$senderPhone, $cleaned, '+'.$cleaned];

        // Egyptian international: 201xxxxxxxxx (12 digits) -> local 01xxxxxxxxx
        if (str_starts_with($cleaned, '201') && strlen($cleaned) === 12) {
            $variants[] = '0'.substr($cleaned, 2);
            $variants[] = substr($cleaned, 2);
        }

        // Saudi international: 9665xxxxxxxx (12 digits) -> local 05xxxxxxxx
        if (str_starts_with($cleaned, '9665') && strlen($cleaned) === 12) {
            $variants[] = '0'.substr($cleaned, 3);
            $variants[] = substr($cleaned, 3);
        }

        if (str_starts_with($senderPhone, '+')) {
            $variants[] = substr($senderPhone, 1);
        }

        $filtered = array_filter($variants, fn (string $v): bool => $v !== '');

        return array_values(array_unique($filtered));
    }

    private function applyRenterOptOut(string $senderPhone): void
    {
        $variants = $this->resolvePhoneVariants($senderPhone);

        $affected = Renter::withoutGlobalScopes()
            ->where(function ($query) use ($variants) {
                foreach ($variants as $variant) {
                    $query->orWhere('phone', 'like', "%{$variant}%");
                }
            })
            ->whereNull('whatsapp_opted_out_at')
            ->update([
                'whatsapp_opted_out_at' => now(),
            ]);

        Log::info("[WhatsApp Inbound Opt-Out] Processed opt-out for sender: {$senderPhone} (Updated: {$affected} renters)");
    }

    private function applyRenterOptIn(string $senderPhone): void
    {
        $variants = $this->resolvePhoneVariants($senderPhone);

        $affected = Renter::withoutGlobalScopes()
            ->where(function ($query) use ($variants) {
                foreach ($variants as $variant) {
                    $query->orWhere('phone', 'like', "%{$variant}%");
                }
            })
            ->whereNotNull('whatsapp_opted_out_at')
            ->update([
                'whatsapp_opted_out_at' => null,
                'whatsapp_opt_in_at' => now(),
            ]);

        Log::info("[WhatsApp Inbound Opt-In] Processed re-opt-in for sender: {$senderPhone} (Updated: {$affected} renters)");
    }
}
