<?php

namespace App\Http\Controllers;

use App\Http\Requests\Expenses\StoreExpenseRequest;
use App\Http\Requests\Expenses\UpdateExpenseRequest;
use App\Models\Expense;
use App\Services\MaintenanceExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $this->resolveWorkspace($request);

        $expenses = $workspace->expenses()
            ->with(['property', 'unit', 'recorder', 'maintenanceRequest'])
            ->latest('expense_date')
            ->latest('id')
            ->get()
            ->map(function (Expense $expense) {
                return [
                    'id' => $expense->id,
                    'title' => $expense->title ?: ($expense->maintenanceRequest?->title ?? ''),
                    'category' => $expense->category,
                    'amount' => $expense->amount !== null ? (float) $expense->amount : null,
                    'expense_date' => $expense->expense_date?->format('d/m/Y'),
                    'raw_date' => $expense->expense_date?->toDateString(),
                    'property_id' => $expense->property_id,
                    'property_name' => $expense->property?->name ?? 'مصروف عام للمنشأة',
                    'unit_id' => $expense->unit_id,
                    'unit_name' => $expense->unit?->name ?? '—',
                    'notes' => $expense->notes ?? '',
                    'recorder_name' => $expense->recorder?->name ?? 'المسؤول',
                    'origin' => $expense->origin,
                    'origin_label' => $expense->origin === 'maintenance' ? 'طلب صيانة' : 'يدوي مباشر',
                    'status' => $expense->status,
                    'status_label' => match ($expense->status) {
                        'pending_amount' => 'بانتظار تحديد التكلفة',
                        'ready_to_apply' => 'جاهز للتطبيق',
                        'applied' => 'معتمد ومطبق',
                        default => $expense->status,
                    },
                    'maintenance_request_id' => $expense->maintenance_request_id,
                    'applied_at' => $expense->applied_at?->format('d/m/Y H:i'),
                ];
            });

        // Financial totals MUST only count applied expenses
        $appliedExpenses = $expenses->where('status', 'applied');
        $pendingExpenses = $expenses->whereIn('status', ['pending_amount', 'ready_to_apply']);

        $totalAppliedAmount = (float) $appliedExpenses->whereNotNull('amount')->sum('amount');
        $totalPendingAmount = (float) $pendingExpenses->whereNotNull('amount')->sum('amount');

        $byCategory = $appliedExpenses->groupBy('category')->map(fn ($grp) => (float) $grp->sum('amount'));
        $properties = $workspace->properties()->get(['id', 'name']);

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses->values()->all(),
            'properties' => $properties,
            'stats' => [
                'total_amount' => $totalAppliedAmount,
                'count' => $expenses->count(),
                'applied_count' => $appliedExpenses->count(),
                'pending_count' => $pendingExpenses->count(),
                'pending_amount' => $totalPendingAmount,
                'ready_to_apply_count' => $expenses->where('status', 'ready_to_apply')->count(),
                'pending_cost_count' => $expenses->where('status', 'pending_amount')->count(),
                'categories' => $byCategory,
            ],
            'currency' => $workspace->currency ?? 'EGP',
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $workspace->expenses()->create([
            ...$request->validated(),
            'recorded_by' => $request->user()->id,
            'origin' => 'manual',
            'status' => 'applied',
            'applied_at' => now(),
            'applied_by' => $request->user()->id,
        ]);

        return back()->with('success', 'تم تسجيل المصروف واعتماده بنجاح');
    }

    public function update(
        UpdateExpenseRequest $request,
        Expense $expense,
        MaintenanceExpenseService $expenseService
    ): RedirectResponse {
        $workspace = $this->resolveWorkspace($request);

        abort_if($expense->workspace_id !== $workspace->id, 403, 'غير مصرح لك بتعديل هذا المصروف.');

        $expenseService->updateExpense($expense, $request->validated());

        return back()->with('success', 'تم تحديث بيانات المصروف بنجاح');
    }
}
