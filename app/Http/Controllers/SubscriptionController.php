<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Unit;
use App\Models\Workspace;
use App\Models\WorkspaceSubscription;
use App\Services\Limits\WorkspaceLimitService;
use Illuminate\Http\RedirectResponse;
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

        $plans = $this->buildPlans($workspace, $currentPlan);
        $currentPlanSummary = $this->buildCurrentPlanSummary($workspace, $activeSubscription, $currentPlan, $totalBillableUnits);
        $requestedPlan = $this->buildRequestedPlanSummary($workspace);

        return Inertia::render('Subscription/Index', [
            'current_plan' => $currentPlanSummary,
            'requested_plan' => $requestedPlan,
            'plans' => $plans,
            'billing_history' => $activeSubscription ? [
                [
                    'id' => 'SUB-'.$activeSubscription->id,
                    'date' => $activeSubscription->starts_at->format('d/m/Y'),
                    'description' => 'اشتراك '.$currentPlanSummary['name'],
                    'amount' => (float) ($currentPlan->monthly_price ?? 0.0),
                    'status' => 'paid',
                    'status_label' => 'نشط ومعتمد',
                ],
            ] : [],
        ]);
    }

    /**
     * Submit a request from the user to change their plan.
     */
    public function requestPlanChange(Request $request, WorkspaceLimitService $limitService): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validated = $request->validate([
            'plan_id' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $plan = $this->resolveRequestedPlan((int) $validated['plan_id']);

        $requested = $limitService->requestPlanChange($workspace, $plan, $validated['notes'] ?? null);

        if (! $requested) {
            return back()->with('info', 'أنت مشترك بالفعل في هذه الباقة.');
        }

        return back()->with('success', 'تم إرسال طلب تغيير الباقة إلى إدارة المنصة بنجاح، وسيتم مراجعته واعتماده.');
    }

    /**
     * Resolve the requested plan by id.
     */
    protected function resolveRequestedPlan(int $planId): Plan
    {
        return Plan::where('is_active', true)->findOrFail($planId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildPlans(Workspace $workspace, ?Plan $currentPlan): array
    {
        $dbPlans = Plan::where('is_active', true)->get()->keyBy('slug');
        $resolvePlanId = function (string $slug, int $fallback) use ($dbPlans): int {
            $plan = $dbPlans->get($slug);

            return $plan instanceof Plan ? $plan->id : $fallback;
        };

        $requestedSlug = $workspace->plan_request_status === 'pending'
            ? $workspace->requestedPlan?->slug
            : null;

        return [
            [
                'id' => $resolvePlanId('trial', 1),
                'slug' => 'trial',
                'name' => 'باقة التجربة (Trial)',
                'badge' => 'تجربة مجانية',
                'price' => 0,
                'period' => 'شهرياً',
                'units_limit' => 1,
                'parent_unit_limit' => 1,
                'active_rental_limit' => 1,
                'features' => [
                    'عقار رئيسي واحد (1) مع عقد تأجير نشط',
                    self::UNLIMITED_CHILD_UNITS_FEATURE,
                    'سندات قبض إلكترونية وعقود إيجار غير محدودة',
                    'تجربة مجانية لكافة أدوات المنصة',
                ],
                'is_current' => $currentPlan?->slug === 'trial' || ($currentPlan === null),
                'is_requested' => $requestedSlug === 'trial',
            ],
            [
                'id' => $resolvePlanId('starter', 2),
                'slug' => 'starter',
                'name' => 'باقة البداية (Starter)',
                'badge' => 'اقتصادية',
                'price' => 99,
                'period' => 'شهرياً',
                'units_limit' => 3,
                'parent_unit_limit' => 3,
                'active_rental_limit' => 3,
                'features' => [
                    'حتى 3 عقارات رئيسية مع 3 عقود تأجير نشطة',
                    self::UNLIMITED_CHILD_UNITS_FEATURE,
                    'سندات قبض إلكترونية وعقود إيجار غير محدودة',
                    'تذكيرات WhatsApp تلقائية للمستأجرين',
                ],
                'is_current' => $currentPlan?->slug === 'starter',
                'is_requested' => $requestedSlug === 'starter',
            ],
            [
                'id' => $resolvePlanId('growth', 3),
                'slug' => 'growth',
                'name' => 'باقة النمو (Growth)',
                'badge' => 'الأكثر طلباً',
                'price' => 199,
                'period' => 'شهرياً',
                'units_limit' => 10,
                'parent_unit_limit' => 10,
                'active_rental_limit' => 10,
                'features' => [
                    'من 4 حتى 10 عقارات رئيسية مع 10 عقود تأجير نشطة',
                    self::UNLIMITED_CHILD_UNITS_FEATURE,
                    'تذكيرات WhatsApp تلقائية غير محدودة',
                    'كشف تعارض العقود والحجوزات وإدارة الصيانة',
                    'دعم فني سريع',
                ],
                'is_current' => $currentPlan?->slug === 'growth',
                'is_requested' => $requestedSlug === 'growth',
            ],
            [
                'id' => $resolvePlanId('pro', 4),
                'slug' => 'pro',
                'name' => 'باقة المحترفين (Pro)',
                'badge' => 'للمحترفين',
                'price' => 399,
                'period' => 'شهرياً',
                'units_limit' => 25,
                'parent_unit_limit' => 25,
                'active_rental_limit' => 25,
                'features' => [
                    'من 11 حتى 25 عقار رئيسي مع 25 عقد تأجير نشط',
                    self::UNLIMITED_CHILD_UNITS_FEATURE,
                    'كل ميزات باقة النمو وإدارة متقدمة للمحافظ',
                    'تقارير مالية ومحاسبية متقدمة مع ضريبة القيمة المضافة',
                    'دعم فني مخصص ذو أولوية',
                ],
                'is_current' => $currentPlan?->slug === 'pro',
                'is_requested' => $requestedSlug === 'pro',
            ],
            [
                'id' => $resolvePlanId('business', 5),
                'slug' => 'business',
                'name' => 'باقة الأعمال (Business)',
                'badge' => 'للشركات الناشئة',
                'price' => 699,
                'period' => 'شهرياً',
                'units_limit' => 50,
                'parent_unit_limit' => 50,
                'active_rental_limit' => 50,
                'features' => [
                    'من 26 حتى 50 عقار رئيسي مع 50 عقد تأجير نشط',
                    self::UNLIMITED_CHILD_UNITS_FEATURE,
                    'تقارير الضرائب والتحليلات المالية المتقدمة',
                    'سجلات صيانة ومصروفات مفصلة ومتعددة',
                    'دعم فني VIP مباشر',
                ],
                'is_current' => $currentPlan?->slug === 'business',
                'is_requested' => $requestedSlug === 'business',
            ],
            [
                'id' => $resolvePlanId('enterprise', 6),
                'slug' => 'enterprise',
                'name' => 'باقة الشركات اللامحدودة (Enterprise)',
                'badge' => 'سعر مخصص (> 50 وحدة)',
                'price' => 0,
                'period' => 'شهرياً',
                'units_limit' => null,
                'parent_unit_limit' => null,
                'active_rental_limit' => null,
                'features' => [
                    'أكثر من 50 عقار رئيسي وعقود تأجير نشطة (سعة غير محدودة ∞)',
                    self::UNLIMITED_CHILD_UNITS_FEATURE,
                    'عقارات وملاك ومستأجرون غير محدودين',
                    'تخصيص متكامل وتقارير خاصة للشركات',
                    'مدير حساب مخصص ودعم فني على مدار الساعة VIP',
                ],
                'is_current' => $currentPlan?->slug === 'enterprise',
                'is_requested' => $requestedSlug === 'enterprise',
            ],
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     slug: string,
     *     units_used: int,
     *     units_limit: int|null,
     *     parent_unit_limit: int|null,
     *     active_rental_limit: int|null,
     *     renewal_date: string|null
     * }
     */
    private function buildCurrentPlanSummary(
        Workspace $workspace,
        ?WorkspaceSubscription $activeSubscription,
        ?Plan $currentPlan,
        int $totalBillableUnits
    ): array {
        $parentUnitsCount = Unit::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->whereNull('parent_unit_id')
            ->count();

        $planName = $currentPlan->name ?? 'باقة التجربة (Trial)';
        $unitLimit = $activeSubscription ? $currentPlan?->unit_limit : ($workspace->parent_unit_limit ?? 1);

        return [
            'name' => $planName,
            'slug' => $currentPlan instanceof Plan ? $currentPlan->slug : 'trial',
            'units_used' => $parentUnitsCount ?: $totalBillableUnits,
            'units_limit' => $unitLimit,
            'parent_unit_limit' => $workspace->parent_unit_limit,
            'active_rental_limit' => $workspace->active_rental_limit,
            'renewal_date' => $activeSubscription?->ends_at?->format('d/m/Y'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildRequestedPlanSummary(Workspace $workspace): ?array
    {
        if (! $workspace->requestedPlan) {
            return null;
        }

        return [
            'id' => $workspace->requestedPlan->id,
            'name' => $workspace->requestedPlan->name,
            'slug' => $workspace->requestedPlan->slug,
            'monthly_price' => (float) $workspace->requestedPlan->monthly_price,
            'parent_unit_limit' => $workspace->requestedPlan->parent_unit_limit,
            'status' => $workspace->plan_request_status,
            'status_label' => match ($workspace->plan_request_status) {
                'pending' => 'قيد المراجعة والاعتماد من الإدارة',
                'approved' => 'تمت الموافقة والتفعيل',
                'rejected' => 'تم رفض الطلب',
                default => 'غير محدد',
            },
            'requested_at' => $workspace->plan_requested_at?->format('d/m/Y H:i'),
            'notes' => $workspace->plan_request_notes,
        ];
    }
}
