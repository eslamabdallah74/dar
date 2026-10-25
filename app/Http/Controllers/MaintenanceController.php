<?php

namespace App\Http\Controllers;

use App\Http\Requests\Maintenance\StoreMaintenanceRequest;
use App\Http\Requests\Maintenance\UpdateMaintenanceRequest;
use App\Http\Requests\Maintenance\UpdateMaintenanceStatusRequest;
use App\Jobs\NotifyMaintenanceRequestCreatedJob;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\Unit;
use App\Services\MaintenanceExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $this->resolveWorkspace($request);

        $requests = $workspace->maintenanceRequests()
            ->with(['property', 'unit', 'reportedByRenter', 'expense'])
            ->latest('reported_at')
            ->latest('id')
            ->get()
            ->map(function (MaintenanceRequest $mr) {
                $expense = $mr->expense;

                return [
                    'id' => $mr->id,
                    'title' => $mr->title,
                    'description' => $mr->description,
                    'status' => $mr->status,
                    'status_label' => match ($mr->status) {
                        'open' => 'جديد',
                        'in_progress' => 'قيد المعالجة',
                        'closed' => 'مكتمل',
                        default => $mr->status,
                    },
                    'property_id' => $mr->property_id,
                    'property_name' => $mr->property?->name ?? 'عقار عام',
                    'unit_id' => $mr->unit_id,
                    'unit_name' => $mr->unit?->name ?? 'مرافق مشتركة',
                    'renter_name' => $mr->reportedByRenter?->name ?? 'إدارة العقار',
                    'reported_at' => $mr->reported_at?->format('d/m/Y'),
                    'raw_reported_at' => $mr->reported_at?->toDateString(),
                    'resolved_at' => $mr->resolved_at?->format('d/m/Y'),
                    'expense' => $expense ? [
                        'id' => $expense->id,
                        'amount' => $expense->amount !== null ? (float) $expense->amount : null,
                        'status' => $expense->status,
                        'status_label' => match ($expense->status) {
                            'pending_amount' => 'بانتظار تحديد التكلفة',
                            'ready_to_apply' => 'جاهز للتطبيق',
                            'applied' => 'معتمد ومطبق',
                            default => $expense->status,
                        },
                        'is_applied' => $expense->isApplied(),
                        'is_ready_to_apply' => $expense->isReadyToApply(),
                        'is_pending_amount' => $expense->isPendingAmount(),
                    ] : null,
                ];
            });

        $properties = $workspace->properties()->with('units')->get();

        $openCount = $requests->where('status', 'open')->count();
        $inProgressCount = $requests->where('status', 'in_progress')->count();
        $closedCount = $requests->where('status', 'closed')->count();

        // Expense workflow stats
        $readyToApplyExpensesCount = $requests->filter(fn ($r) => $r['expense'] && $r['expense']['status'] === 'ready_to_apply')->count();
        $pendingAmountExpensesCount = $requests->filter(fn ($r) => $r['expense'] && $r['expense']['status'] === 'pending_amount')->count();
        $appliedExpensesCount = $requests->filter(fn ($r) => $r['expense'] && $r['expense']['status'] === 'applied')->count();

        return Inertia::render('Maintenance/Index', [
            'requests' => $requests->values()->all(),
            'properties' => $properties->map(fn (Property $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'units' => $p->units->map(fn (Unit $u) => ['id' => $u->id, 'name' => $u->name]),
            ]),
            'stats' => [
                'open' => $openCount,
                'in_progress' => $inProgressCount,
                'closed' => $closedCount,
                'total' => $requests->count(),
                'ready_to_apply_expenses_count' => $readyToApplyExpensesCount,
                'pending_amount_expenses_count' => $pendingAmountExpensesCount,
                'applied_expenses_count' => $appliedExpensesCount,
            ],
            'currency' => $workspace->currency ?? 'EGP',
        ]);
    }

    public function store(
        StoreMaintenanceRequest $request,
        MaintenanceExpenseService $expenseService
    ): RedirectResponse {
        $workspace = $this->resolveWorkspace($request);
        $validated = $request->validated();

        $maintenanceRequest = $workspace->maintenanceRequests()->create([
            ...$validated,
            'description' => $validated['description'] ?? '',
            'status' => 'open',
            'reported_at' => now(),
        ]);

        // Automatically create corresponding pending expense record
        $expenseService->syncFromMaintenance($maintenanceRequest, $request->user()->id);

        dispatch(new NotifyMaintenanceRequestCreatedJob($maintenanceRequest->id));

        return back()->with('success', 'تم تسجيل طلب الصيانة وإنشاء بند المصروف المرتبط به بنجاح');
    }

    public function update(
        UpdateMaintenanceRequest $request,
        MaintenanceRequest $maintenance,
        MaintenanceExpenseService $expenseService
    ): RedirectResponse {
        $workspace = $this->resolveWorkspace($request);

        abort_if($maintenance->workspace_id !== $workspace->id, 403, 'غير مصرح لك بتعديل طلب الصيانة هذا.');

        $maintenance->update($request->validated());

        // Sync linked expense details without overwriting user-entered amount
        $expenseService->syncFromMaintenance($maintenance, $request->user()->id);

        return back()->with('success', 'تم تحديث بيانات طلب الصيانة ومزامنة بند المصروف بنجاح');
    }

    public function updateStatus(
        UpdateMaintenanceStatusRequest $request,
        MaintenanceRequest $maintenance
    ): RedirectResponse {
        $workspace = $this->resolveWorkspace($request);

        abort_if($maintenance->workspace_id !== $workspace->id, 403, 'غير مصرح لك بتعديل طلب الصيانة هذا.');

        $validated = $request->validated();

        $maintenance->update([
            'status' => $validated['status'],
            'resolved_at' => $validated['status'] === 'closed' ? now() : null,
        ]);

        return back()->with('success', 'تم تحديث حالة طلب الصيانة بنجاح');
    }

    /**
     * Apply maintenance expenses that have actual amounts entered from the Expenses page.
     * Enforces the requirement that amounts cannot be bypassed without entering them in Expenses first.
     */
    public function applyExpenses(
        Request $request,
        MaintenanceExpenseService $expenseService
    ): RedirectResponse {
        $workspace = $this->resolveWorkspace($request);
        $maintenanceRequestId = $request->input('maintenance_request_id');

        $result = $expenseService->applyExpenses(
            $workspace,
            $request->user()->id,
            $maintenanceRequestId ? (int) $maintenanceRequestId : null
        );

        $messages = [];
        $currency = $workspace->currency ?? 'EGP';

        if ($result['applied_count'] > 0) {
            $messages[] = "تم اعتماد وتطبيق {$result['applied_count']} مصروف صيانة بقيمة إجمالية ".number_format($result['applied_total'], 2)." {$currency} وإدراجها في الحسابات المالية.";
        }

        if ($result['skipped_missing_amount'] > 0) {
            $pendingList = ! empty($result['pending_titles'])
                ? ' ('.implode('، ', array_slice($result['pending_titles'], 0, 3)).(count($result['pending_titles']) > 3 ? '...' : '').')'
                : '';
            $messages[] = "يوجد {$result['skipped_missing_amount']} طلب صيانة لم يتم تطبيقها لأن تكلفتها غير محددة بعد{$pendingList}. يرجى إدخال التكلفة الفعلية أولاً من صفحة المصروفات.";
        }

        if ($result['applied_count'] === 0 && $result['skipped_missing_amount'] === 0) {
            if ($result['already_applied'] > 0) {
                return back()->with('info', 'جميع مصروفات الصيانة تم تطبيقها واعتمادها مسبقاً.');
            }

            return back()->with('info', 'لا توجد طلبات صيانة تتطلب اعتماد مصروفات حالياً.');
        }

        $flashType = $result['applied_count'] > 0 ? 'success' : 'warning';

        return back()->with($flashType, implode(' ', $messages));
    }
}
