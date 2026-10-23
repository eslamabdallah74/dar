<?php

namespace App\Http\Controllers;

use App\Support\CurrentWorkspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = app(CurrentWorkspace::class)->get()
            ?? $request->user()->ownedWorkspaces()->first();

        $totalUnits = $workspace->units()->count();
        if ($totalUnits === 0) {
            $totalUnits = 12;
        }

        $plans = [
            [
                'id' => 'starter',
                'name' => 'باقة الانطلاق',
                'badge' => 'مجانية',
                'price' => 0,
                'period' => 'شهرياً',
                'units_limit' => 5,
                'features' => [
                    'إدارة حتى 5 وحدات تأجيرية',
                    'سندات قبض إلكترونية غير محدودة',
                    'عقود إيجار غير محدودة',
                    'تذكيرات WhatsApp محدودة (50 رسالة/شهر)',
                ],
                'is_current' => false,
            ],
            [
                'id' => 'growth',
                'name' => 'باقة النمو (Growth)',
                'badge' => 'الأكثر طلباً',
                'price' => 199,
                'period' => 'شهرياً',
                'units_limit' => 25,
                'features' => [
                    'إدارة حتى 25 وحدة تأجيرية',
                    'تذكيرات WhatsApp تلقائية غير محدودة',
                    'كشف تعارض العقود والحجوزات',
                    'إدارة الصيانة والمصروفات التشغيلية',
                    'دعم فني مخصص ذو أولوية',
                ],
                'is_current' => true,
            ],
            [
                'id' => 'pro',
                'name' => 'باقة المحترفين (Pro)',
                'badge' => 'للمحافظ الكبيرة',
                'price' => 499,
                'period' => 'شهرياً',
                'units_limit' => 100,
                'features' => [
                    'إدارة حتى 100 وحدة تأجيرية',
                    'كل ميزات باقة النمو',
                    'صلاحيات متقدمة لفريق العمل (أدوار متعددة)',
                    'تقارير مالية ومحاسبية متقدمة مع ضريبة القيمة المضافة',
                    'ربط مباشر مع منصة إيجار (قريباً)',
                ],
                'is_current' => false,
            ],
        ];

        return Inertia::render('Subscription/Index', [
            'current_plan' => [
                'name' => 'باقة النمو • تجريبية',
                'trial_days_remaining' => 10,
                'units_used' => $totalUnits,
                'units_limit' => 25,
                'renewal_date' => now()->addDays(10)->format('d/m/Y'),
                'payment_card' => '•••• 4242 (Visa)',
            ],
            'plans' => $plans,
            'billing_history' => [
                [
                    'id' => 'INV-2026-001',
                    'date' => '01/10/2026',
                    'description' => 'اشتراك باقة النمو - شهر أكتوبر 2026',
                    'amount' => 199.00,
                    'status' => 'paid',
                    'status_label' => 'مدفوعة',
                ],
                [
                    'id' => 'INV-2026-002',
                    'date' => '01/09/2026',
                    'description' => 'اشتراك باقة النمو - شهر سبتمبر 2026',
                    'amount' => 199.00,
                    'status' => 'paid',
                    'status_label' => 'مدفوعة',
                ],
            ],
        ]);
    }
}
