<?php

namespace App\Http\Controllers;

use App\Models\MessageLog;
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
}
