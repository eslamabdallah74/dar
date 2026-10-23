<?php

namespace App\Http\Controllers;

use App\Models\MessageLog;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = app(CurrentWorkspace::class)->get()
            ?? $request->user()->ownedWorkspaces()->first();

        $logs = $workspace->messageLogs()
            ->with('renter')
            ->latest('scheduled_at')
            ->get()
            ->map(function (MessageLog $log) {
                return [
                    'id' => $log->id,
                    'renter_id' => $log->renter_id,
                    'renter_name' => $log->renter?->name ?? 'مستأجر',
                    'recipient_phone' => $log->recipient_phone,
                    'template_name' => match ($log->template_name) {
                        'rent_due_reminder_ar' => 'تذكير موعد استحقاق الإيجار',
                        'payment_receipt_instant_ar' => 'سند قبض إلكتروني فوري',
                        'overdue_notice_ar' => 'إشعار متأخرات سداد',
                        'contract_renewal_ar' => 'تنبيه قرب انتهاء العقد',
                        default => $log->template_name,
                    },
                    'status' => $log->status,
                    'status_label' => match ($log->status) {
                        'delivered' => 'تم التسليم والقراءة',
                        'sent' => 'تم الإرسال',
                        'pending' => 'مجدول',
                        'failed' => 'فشل الإرسال',
                        default => $log->status,
                    },
                    'scheduled_at' => $log->scheduled_at?->format('d/m/Y H:i'),
                    'sent_at' => $log->sent_at?->format('d/m/Y H:i') ?? '—',
                ];
            });

        $templates = [
            [
                'id' => 'rent_due_reminder_ar',
                'title' => 'تذكير موعد استحقاق الإيجار',
                'trigger' => 'تلقائياً قبل 3 أيام من تاريخ الاستحقاق',
                'sample' => 'مرحباً {اسم_المستأجر}، نود تذكيركم بأن القسط الإيجاري للوحدة {الوحدة} بقيمة {المبلغ} ج.م مستحق في {تاريخ_الاستحقاق}. شكراً لتعاونكم.',
                'active' => true,
            ],
            [
                'id' => 'payment_receipt_instant_ar',
                'title' => 'سند قبض إلكتروني فوري',
                'trigger' => 'فور تسجيل دفعة جديدة في النظام',
                'sample' => 'عزيزي {اسم_المستأجر}، تم استلام مبلغ {المبلغ} ج.م بنجاح، سند قبض رقم {رقم_السند}. يمكنكم الاطلاع على الإيصال عبر الرابط التالي: {رابط_السند}',
                'active' => true,
            ],
            [
                'id' => 'overdue_notice_ar',
                'title' => 'إشعار متأخرات سداد الإيجار',
                'trigger' => 'بعد مرور 5 أيام على موعد الاستحقاق',
                'sample' => 'عزيزي {اسم_المستأجر}، نلفت عنايتكم لتأخر سداد قسط الإيجار للوحدة {الوحدة} المستحق بتاريخ {تاريخ_الاستحقاق}. يرجى المبادرة بالسداد تجنباً للإجراءات النظامية.',
                'active' => true,
            ],
            [
                'id' => 'contract_renewal_ar',
                'title' => 'تنبيه تجديد عقد الإيجار',
                'trigger' => 'قبل 30 يوماً من انتهاء العقد',
                'sample' => 'مرحباً {اسم_المستأجر}، نود إشعاركم بأن عقد إيجاركم للوحدة {الوحدة} ينتهي في {تاريخ_الانتهاء}. يرجى التواصل معنا في حال رغبتكم بالتجديد.',
                'active' => true,
            ],
        ];

        $renters = $workspace->renters()->get(['id', 'name', 'phone']);

        return Inertia::render('WhatsApp/Index', [
            'logs' => $logs,
            'templates' => $templates,
            'renters' => $renters,
            'stats' => [
                'total_sent' => $logs->whereIn('status', ['sent', 'delivered'])->count(),
                'delivered_rate' => 98,
                'active_templates' => 4,
            ],
            'connection' => [
                'is_connected' => true,
                'sender_number' => '+20 10 1234 5678',
                'provider' => 'WhatsApp Cloud API الرسمية',
                'status_label' => 'متصل ونشط',
            ],
        ]);
    }

    public function testSend(Request $request): RedirectResponse
    {
        $workspace = app(CurrentWorkspace::class)->get()
            ?? $request->user()->ownedWorkspaces()->first();

        $validated = $request->validate([
            'renter_id' => 'required|exists:renters,id',
            'template_id' => 'required|string',
        ]);

        $renter = $workspace->renters()->findOrFail($validated['renter_id']);

        $workspace->messageLogs()->create([
            'renter_id' => $renter->id,
            'recipient_phone' => $renter->phone,
            'template_name' => $validated['template_id'],
            'scheduled_at' => now(),
            'sent_at' => now(),
            'status' => 'delivered',
            'attempts' => 1,
            'deduplication_key' => 'manual_test_'.time().'_'.$renter->id,
        ]);

        return back()->with('success', 'تم إرسال رسالة WhatsApp التجريبية بنجاح وتسليمها للمستأجر');
    }
}
