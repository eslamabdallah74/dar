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

        $expectedToken = config('services.whatsapp.webhook_verify_token') ?? env('WHATSAPP_WEBHOOK_VERIFY_TOKEN');

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
        $appSecret = config('services.whatsapp.app_secret') ?? env('WHATSAPP_APP_SECRET');

        if (app()->isProduction() && empty($appSecret)) {
            Log::critical('[WhatsApp Webhook Security] WHATSAPP_APP_SECRET is not configured in production environment. Rejecting webhook.');

            return response()->json(['error' => 'Webhook verification misconfigured'], 500);
        }

        // Validate webhook signature if app secret is configured
        if (! empty($appSecret)) {
            $signatureHeader = $request->header('X-Hub-Signature-256', '');
            $expectedSignature = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);

            if (! hash_equals($expectedSignature, $signatureHeader)) {
                Log::warning('[WhatsApp Webhook Signature Mismatch] Unauthorized payload.');

                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $payload = $request->all();
        $entries = $payload['entry'] ?? [];

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                $value = $change['value'] ?? [];

                // 1. Process delivery statuses
                if (! empty($value['statuses'])) {
                    foreach ($value['statuses'] as $statusItem) {
                        $providerMsgId = $statusItem['id'] ?? null;
                        $newStatus = $statusItem['status'] ?? null;

                        if (! $providerMsgId || ! $newStatus) {
                            continue;
                        }

                        $log = MessageLog::withoutGlobalScopes()
                            ->where('provider_message_id', $providerMsgId)
                            ->first();

                        if (! $log) {
                            continue;
                        }

                        $mappedStatus = match ($newStatus) {
                            'read', 'delivered' => 'delivered',
                            'sent' => 'sent',
                            'failed' => 'failed',
                            default => $log->status,
                        };

                        $updates = ['status' => $mappedStatus];

                        if ($mappedStatus === 'delivered' && ! $log->sent_at) {
                            $updates['sent_at'] = now();
                        }

                        if ($mappedStatus === 'failed') {
                            $error = $statusItem['errors'][0] ?? null;
                            if ($error) {
                                $updates['error_code'] = (string) ($error['code'] ?? 'UNKNOWN');
                                $updates['error_message'] = (string) ($error['message'] ?? $error['title'] ?? 'Delivery failed');
                            }
                        }

                        $log->update($updates);

                        Log::info("[WhatsApp Webhook Status Updated] ID: {$providerMsgId} -> {$mappedStatus}");
                    }
                }
            }
        }

        return response()->json(['status' => 'EVENT_RECEIVED']);
    }
}
