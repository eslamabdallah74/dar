<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Unit;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    /**
     * Resolve and authorize the active workspace for the authenticated user.
     */
    protected function resolveWorkspace(Request $request): Workspace
    {
        $workspace = app(CurrentWorkspace::class)->get();

        if (! $workspace) {
            abort(403, 'لا توجد مساحة عمل نشطة أو مصرح بها.');
        }

        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $isAuthorized = $workspace->owner_user_id === $user->id
            || $workspace->members()->where('user_id', $user->id)->exists();

        abort_if(! $isAuthorized, 403, 'غير مصرح لك بالوصول لمساحة العمل هذه.');

        return $workspace;
    }

    public function index(Request $request): Response
    {
        $workspace = $this->resolveWorkspace($request);

        $totalBillableUnits = Unit::billableCountForWorkspace($workspace->id);
        $activeSubscription = $workspace->activeSubscription()->with('plan')->first();
        $currentPlan = $activeSubscription?->plan;

        $dbPlans = Plan::where('is_active', true)->get();

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
                'is_current' => $currentPlan?->slug === 'starter' || ($currentPlan === null),
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
                'is_current' => $currentPlan?->slug === 'growth',
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
                    'إدارة متقدمة لجميع العقارات والأملاك',
                    'تقارير مالية ومحاسبية متقدمة مع ضريبة القيمة المضافة',
                    'ربط مباشر مع منصة إيجار (قريباً)',
                ],
                'is_current' => $currentPlan?->slug === 'pro',
            ],
        ];

        $planName = $currentPlan?->name ?? 'باقة الانطلاق (المجانية)';
        $unitLimit = $currentPlan?->unit_limit ?? 5;
        $daysRemaining = $activeSubscription?->ends_at
            ? max(0, (int) now()->diffInDays($activeSubscription->ends_at, false))
            : 30;

        return Inertia::render('Subscription/Index', [
            'current_plan' => [
                'name' => $planName,
                'trial_days_remaining' => $daysRemaining,
                'units_used' => $totalBillableUnits,
                'units_limit' => $unitLimit,
                'renewal_date' => $activeSubscription?->ends_at?->format('d/m/Y') ?? now()->addDays(30)->format('d/m/Y'),
                'payment_card' => $activeSubscription ? '•••• 4242 (مدى / بطاقة بنكية)' : 'غير محدد',
            ],
            'plans' => $plans,
            'billing_history' => $activeSubscription ? [
                [
                    'id' => 'SUB-'.$activeSubscription->id,
                    'date' => $activeSubscription->starts_at?->format('d/m/Y') ?? now()->format('d/m/Y'),
                    'description' => 'اشتراك '.$planName,
                    'amount' => (float) ($currentPlan?->monthly_price ?? 0.0),
                    'status' => 'paid',
                    'status_label' => 'نشط ومعتمد',
                ],
            ] : [],
        ]);
    }
}
