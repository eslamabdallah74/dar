<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    private const UNLIMITED_CHILD_UNITS_FEATURE = 'تقسيمات داخلية غير محدودة (غرف وأسرّة)';

    public function index(Request $request): Response
    {
        $workspace = $this->resolveWorkspace($request);

        $totalBillableUnits = Unit::billableCountForWorkspace($workspace->id);
        $activeSubscription = $workspace->activeSubscription()->with('plan')->first();
        $currentPlan = $activeSubscription?->plan;

        $plans = [
            [
                'id' => 'starter',
                'name' => 'باقة الانطلاق',
                'badge' => 'مجانية',
                'price' => 0,
                'period' => 'شهرياً',
                'units_limit' => 5,
                'parent_unit_limit' => 1,
                'active_rental_limit' => 1,
                'features' => [
                    'عقار رئيسي واحد (1) مع عقد تأجير نشط',
                    self::UNLIMITED_CHILD_UNITS_FEATURE,
                    'سندات قبض إلكترونية وعقود إيجار غير محدودة',
                    'تذكيرات WhatsApp محدودة (50 رسالة/شهر)',
                ],
                'is_current' => $currentPlan?->slug === 'starter' || ($currentPlan === null),
            ],
            [
                'id' => 'growth',
                'name' => 'باقة النمو (Growth)',
                'badge' => 'الأكثر طلباً',
                'price' => 199,
                'period' => 'شهرياً',
                'units_limit' => 25,
                'parent_unit_limit' => 10,
                'active_rental_limit' => 10,
                'features' => [
                    'حتى 10 عقارات رئيسية مع 10 عقود تأجير نشطة',
                    self::UNLIMITED_CHILD_UNITS_FEATURE,
                    'تذكيرات WhatsApp تلقائية غير محدودة',
                    'كشف تعارض العقود والحجوزات وإدارة الصيانة',
                    'دعم فني مخصص ذو أولوية',
                ],
                'is_current' => $currentPlan?->slug === 'growth',
            ],
            [
                'id' => 'pro',
                'name' => 'باقة المحترفين (Pro)',
                'badge' => 'للمحافظ الكبيرة',
                'price' => 499,
                'period' => 'شهرياً',
                'units_limit' => 100,
                'parent_unit_limit' => 50,
                'active_rental_limit' => 50,
                'features' => [
                    'حتى 50 عقار رئيسي مع 50 عقد تأجير نشط',
                    self::UNLIMITED_CHILD_UNITS_FEATURE,
                    'كل ميزات باقة النمو وإدارة متقدمة للمحافظ',
                    'تقارير مالية ومحاسبية متقدمة مع ضريبة القيمة المضافة',
                    'ربط مباشر مع منصة إيجار (قريباً)',
                ],
                'is_current' => $currentPlan?->slug === 'pro',
            ],
            [
                'id' => 'enterprise',
                'name' => 'باقة الشركات اللامحدودة (Enterprise)',
                'badge' => 'شاملة كل شيء',
                'price' => 999,
                'period' => 'شهرياً',
                'units_limit' => null,
                'parent_unit_limit' => null,
                'active_rental_limit' => null,
                'features' => [
                    'عقارات رئيسية وعقود تأجير نشطة غير محدودة (∞)',
                    self::UNLIMITED_CHILD_UNITS_FEATURE,
                    'عقارات وملاك ومستأجرون غير محدودين',
                    'كافة التقارير المالية والتحليلات والضرائب',
                    'مدير حساب مخصص ودعم فني على مدار الساعة VIP',
                ],
                'is_current' => $currentPlan?->slug === 'enterprise',
            ],
        ];

        $planName = $currentPlan->name ?? 'باقة الانطلاق (Starter)';
        $unitLimit = $activeSubscription ? $currentPlan?->unit_limit : ($workspace->parent_unit_limit ?? 5);
        $parentUnitsCount = Unit::withoutGlobalScopes()->where('workspace_id', $workspace->id)->whereNull('parent_unit_id')->count();

        return Inertia::render('Subscription/Index', [
            'current_plan' => [
                'name' => $planName,
                'units_used' => $parentUnitsCount ?: $totalBillableUnits,
                'units_limit' => $unitLimit,
                'parent_unit_limit' => $workspace->parent_unit_limit,
                'active_rental_limit' => $workspace->active_rental_limit,
                'renewal_date' => $activeSubscription?->ends_at?->format('d/m/Y'),
                'payment_card' => $activeSubscription ? '•••• 4242 (مدى / بطاقة بنكية)' : 'غير محدد',
            ],
            'plans' => $plans,
            'billing_history' => $activeSubscription ? [
                [
                    'id' => 'SUB-'.$activeSubscription->id,
                    'date' => $activeSubscription->starts_at->format('d/m/Y'),
                    'description' => 'اشتراك '.$planName,
                    'amount' => (float) ($currentPlan->monthly_price ?? 0.0),
                    'status' => 'paid',
                    'status_label' => 'نشط ومعتمد',
                ],
            ] : [],
        ]);
    }
}
