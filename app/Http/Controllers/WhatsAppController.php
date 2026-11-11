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
    private const DEFAULT_TEST_UNIT_NAME = 'الوحدة التجريبية';

    private const DATE_DISPLAY_FORMAT = 'd/m/Y';

    public function index(Request $request): Response
    {
        $workspace = $this->resolveWorkspace($request);

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
                        'rent_due_reminder_ar' => 'تذكير موعد استحقاق الإيجار',
                        'payment_receipt_instant_ar' => 'سند قبض إلكتروني فوري',
                        'overdue_notice_ar' => 'إشعار متأخرات سداد',
                        'contract_renewal_ar' => 'تنبيه قرب انتهاء العقد',
                        default => $log->template_name,
                    },
                    'status' => $log->status,
                    'error_message' => $log->error_message,
                    'status_label' => match ($log->status) {
                        'delivered' => 'تم التسليم والقراءة',
                        'sent' => 'تم الإرسال',
                        'pending' => 'مجدول',
                        'failed' => 'فشل الإرسال',
                        default => $log->status,
                    },
                    'scheduled_at' => $log->scheduled_at->format('d/m/Y H:i'),
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
                'sender_number' => config('services.whatsapp.phone_number') ?? ($hasCredentials ? 'حساب Meta الرسمي المعتمد' : 'غير مهيأ'),
                'provider' => 'WhatsApp Cloud API الرسمية (Meta)',
                'status_label' => $hasCredentials ? 'متصل ونشط' : 'بانتظار ضبط مفاتيح الربط (WHATSAPP_API_TOKEN)',
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
        $currencySymbol = $workspace->currencySymbol();

        $parameters = match ($validated['template_id']) {
            'payment_receipt_instant_ar' => [
                'renter_name' => $renter->name,
                'amount' => "1,500 {$currencySymbol}",
                'receipt_no' => 'REC-TEST-'.random_int(100, 999),
                'link' => url('/'),
            ],
            'contract_renewal_ar' => [
                'renter_name' => $renter->name,
                'unit' => self::DEFAULT_TEST_UNIT_NAME,
                'end_date' => now()->addDays(30)->format(self::DATE_DISPLAY_FORMAT),
            ],
            'overdue_notice_ar' => [
                'renter_name' => $renter->name,
                'unit' => self::DEFAULT_TEST_UNIT_NAME,
                'due_date' => now()->subDays(5)->format(self::DATE_DISPLAY_FORMAT),
                'amount' => "5,000 {$currencySymbol}",
            ],
            default => [
                'renter_name' => $renter->name,
                'unit' => self::DEFAULT_TEST_UNIT_NAME,
                'amount' => "5,000 {$currencySymbol}",
                'due_date' => now()->addDays(3)->format(self::DATE_DISPLAY_FORMAT),
            ],
        };

        try {
            $log = $whatsAppService->send(
                workspaceId: $workspace->id,
                renterId: $renter->id,
                phone: $renter->phone,
                templateName: $validated['template_id'],
                parameters: $parameters,
                deduplicationKey: 'manual_test_'.time().'_'.$renter->id
            );
        } catch (\Throwable $e) {
            return back()->with('warning', 'تعذر إتمام الإرسال: '.$e->getMessage());
        }

        if ($log && in_array($log->status, ['sent', 'delivered'], true)) {
            return back()->with('success', 'تم إرسال رسالة WhatsApp بنجاح عبر بوابة Meta Cloud API');
        }

        $errorMessage = $log->error_message ?? 'تعذر إتمام الإرسال';

        return back()->with('warning', 'فشل الإرسال: '.$errorMessage);
    }
}
