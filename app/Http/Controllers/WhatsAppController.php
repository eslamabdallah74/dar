<?php

namespace App\Http\Controllers;

use App\Models\MessageLog;
use App\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppController extends Controller
{
    public function index(Request $request, WhatsAppService $whatsAppService): Response
    {
        $workspace = $this->resolveWorkspace($request);
        $suffix = $whatsAppService->resolveTemplateSuffix($workspace->id);

        $logs = $workspace->messageLogs()
            ->with('renter')
            ->latest('scheduled_at')
            ->get()
            ->map(function (MessageLog $log) {
                return [
                    'id' => $log->id,
                    'renter_id' => $log->renter_id,
                    'renter_name' => $log->renter->name ?? 'مستأجر',
                    'recipient_phone' => $log->recipient_phone,
                    'template_name' => match ($log->template_name) {
                        'rent_due_reminder_ar', 'rent_due_reminder_en' => __('whatsapp.templates.rent_due_reminder'),
                        'payment_receipt_instant_ar', 'payment_receipt_instant_en' => __('whatsapp.templates.payment_receipt_instant'),
                        'overdue_notice_ar', 'overdue_notice_en' => __('whatsapp.templates.overdue_notice'),
                        'contract_renewal_ar', 'contract_renewal_en' => __('whatsapp.templates.contract_renewal'),
                        default => $log->template_name,
                    },
                    'status' => $log->status,
                    'status_label' => match ($log->status) {
                        'delivered' => __('whatsapp.statuses.delivered'),
                        'sent' => __('whatsapp.statuses.sent'),
                        'pending' => __('whatsapp.statuses.pending'),
                        'failed' => __('whatsapp.statuses.failed'),
                        default => $log->status,
                    },
                    'scheduled_at' => $log->scheduled_at->format('d/m/Y H:i'),
                    'sent_at' => $log->sent_at?->format('d/m/Y H:i') ?? '—',
                ];
            });

        $templates = [
            [
                'id' => "rent_due_reminder{$suffix}",
                'title' => __('whatsapp.templates.rent_due_reminder'),
                'trigger' => __('whatsapp.templates.rent_due_reminder_trigger'),
                'sample' => __('whatsapp.templates.rent_due_reminder_sample'),
                'active' => true,
            ],
            [
                'id' => "payment_receipt_instant{$suffix}",
                'title' => __('whatsapp.templates.payment_receipt_instant'),
                'trigger' => __('whatsapp.templates.payment_receipt_instant_trigger'),
                'sample' => __('whatsapp.templates.payment_receipt_instant_sample'),
                'active' => true,
            ],
            [
                'id' => "overdue_notice{$suffix}",
                'title' => __('whatsapp.templates.overdue_notice'),
                'trigger' => __('whatsapp.templates.overdue_notice_trigger'),
                'sample' => __('whatsapp.templates.overdue_notice_sample'),
                'active' => true,
            ],
            [
                'id' => "contract_renewal{$suffix}",
                'title' => __('whatsapp.templates.contract_renewal'),
                'trigger' => __('whatsapp.templates.contract_renewal_trigger'),
                'sample' => __('whatsapp.templates.contract_renewal_sample'),
                'active' => true,
            ],
        ];

        $renters = $workspace->renters()->get(['id', 'name', 'phone']);

        $hasCredentials = ! empty(config('services.whatsapp.token')) && ! empty(config('services.whatsapp.phone_number_id'));
        $totalSentOrDelivered = $logs->whereIn('status', ['sent', 'delivered'])->count();
        $totalLogsCount = $logs->count();
        $deliveredRate = $totalLogsCount > 0 ? (int) round(($totalSentOrDelivered / $totalLogsCount) * 100) : 0;

        return Inertia::render('WhatsApp/Index', [
            'logs' => $logs,
            'templates' => $templates,
            'renters' => $renters,
            'stats' => [
                'total_sent' => $totalSentOrDelivered,
                'delivered_rate' => $deliveredRate,
                'active_templates' => 4,
            ],
            'connection' => [
                'is_connected' => $hasCredentials,
                'sender_number' => config('services.whatsapp.phone_number') ?? ($hasCredentials ? '+20 10 1234 5678' : __('whatsapp.connection.unconfigured')),
                'provider' => __('whatsapp.connection.provider'),
                'status_label' => $hasCredentials ? __('whatsapp.connection.connected') : __('whatsapp.connection.disconnected'),
            ],
        ]);
    }

    public function testSend(Request $request, WhatsAppService $whatsAppService): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validated = $request->validate([
            'renter_id' => [
                'required',
                Rule::exists('renters', 'id')->where('workspace_id', $workspace->id),
            ],
            'template_id' => ['required', 'string'],
        ]);

        $renter = $workspace->renters()->findOrFail((int) $validated['renter_id']);

        $log = $whatsAppService->send(
            workspaceId: $workspace->id,
            renterId: $renter->id,
            phone: $renter->phone,
            templateName: $validated['template_id'],
            parameters: [
                'renter_name' => $renter->name,
                'unit' => 'الوحدة',
                'amount' => '5,000',
                'due_date' => now()->addDays(3)->format('d/m/Y'),
            ],
            deduplicationKey: 'manual_test_'.time().'_'.$renter->id
        );

        if ($log && in_array($log->status, ['sent', 'delivered'], true)) {
            return back()->with('success', __('whatsapp.messages.test_sent_success'));
        }

        if ($log && $log->status === 'failed') {
            return back()->with('warning', __('whatsapp.messages.test_sent_failed', ['error' => $log->error_message ?? '']));
        }

        return back()->with('info', __('whatsapp.messages.test_sent_processed'));
    }
}
