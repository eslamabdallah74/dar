<?php

namespace App\Http\Controllers;

use App\Http\Requests\Expenses\StoreExpenseRequest;
use App\Http\Requests\Expenses\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Services\MaintenanceExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            ->get();

        $expensesData = collect(ExpenseResource::collection($expenses)->resolve());

        // Financial totals MUST only count expenses that have valid amounts
        $recordedExpenses = $expensesData->filter(fn (array $e): bool => ! $e['is_pending_amount']);
        $pendingExpenses = $expensesData->filter(fn (array $e): bool => $e['is_pending_amount']);

        $totalRecordedAmount = (float) $recordedExpenses->sum('amount');
        $maintenanceExpenses = $recordedExpenses->where('origin', 'maintenance');
        $totalMaintenanceAmount = (float) $maintenanceExpenses->sum('amount');

        $byCategory = $recordedExpenses->groupBy('category')->map(fn ($grp): float => (float) $grp->sum('amount'));
        $properties = $workspace->properties()->get(['id', 'name']);

        return Inertia::render('Expenses/Index', [
            'expenses' => $expensesData->values()->all(),
            'properties' => $properties,
            'stats' => [
                'total_amount' => $totalRecordedAmount,
                'count' => $expenses->count(),
                'recorded_count' => $recordedExpenses->count(),
                'pending_cost_count' => $pendingExpenses->count(),
                'maintenance_count' => $expenses->where('origin', 'maintenance')->count(),
                'maintenance_expenses_total' => $totalMaintenanceAmount,
                // Backward compatibility keys for existing views
                'applied_count' => $recordedExpenses->count(),
                'pending_count' => $pendingExpenses->count(),
                'pending_amount' => 0.0,
                'ready_to_apply_count' => $recordedExpenses->where('origin', 'maintenance')->count(),
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
            'recorded_by' => $this->authenticatedUser($request)->id,
            'origin' => 'manual',
            'status' => 'recorded',
        ]);

        return back()->with('success', 'تم تسجيل المصروف بنجاح');
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

    public function recordPayment(Request $request, Expense $expense): RedirectResponse|JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($expense->workspace_id !== $workspace->id, 403, 'غير مصرح لك بسداد هذا المصروف.');

        if ($expense->amount === null || (float) $expense->amount <= 0) {
            return back()->withErrors(['amount' => 'لا يمكن تسجيل سداد مصروف لم تُسجل قيمته بعد.'])->with('error', 'لا يمكن تسجيل سداد مصروف لم تُسجل قيمته بعد.');
        }

        DB::transaction(function () use ($expense, $request, $workspace) {
            $expense = Expense::where('id', $expense->id)->lockForUpdate()->firstOrFail();

            $expense->update([
                'payment_status' => 'paid',
                'paid_at' => now(),
            ]);

            $maintenance = $expense->maintenanceRequest()->first();
            if ($maintenance) {
                activity('maintenance')
                    ->performedOn($maintenance)
                    ->causedBy($request->user())
                    ->withProperties([
                        'workspace_id' => $workspace->id,
                        'expense_id' => $expense->id,
                        'amount' => $expense->amount,
                        'paid_at' => $expense->paid_at?->toIso8601String(),
                    ])
                    ->log('تم تسجيل سداد تكلفة الصيانة بمبلغ: '.number_format((float) $expense->amount, 2));
            }
        });

        if ($request->wantsJson()) {
            return response()->json(['message' => 'تم تسجيل سداد المصروف بنجاح']);
        }

        return back()->with('success', 'تم تسجيل سداد المصروف بنجاح');
    }

    public function reversePayment(Request $request, Expense $expense): RedirectResponse|JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($expense->workspace_id !== $workspace->id, 403, 'غير مصرح لك بتعديل سداد هذا المصروف.');

        DB::transaction(function () use ($expense, $request, $workspace) {
            $expense = Expense::where('id', $expense->id)->lockForUpdate()->firstOrFail();

            $expense->update([
                'payment_status' => 'unpaid',
                'paid_at' => null,
            ]);

            $maintenance = $expense->maintenanceRequest()->first();
            if ($maintenance) {
                activity('maintenance')
                    ->performedOn($maintenance)
                    ->causedBy($request->user())
                    ->withProperties([
                        'workspace_id' => $workspace->id,
                        'expense_id' => $expense->id,
                    ])
                    ->log('تم إلغاء تسجيل سداد المصروف وإعادته كغير مدفوع');
            }
        });

        if ($request->wantsJson()) {
            return response()->json(['message' => 'تم إلغاء سداد المصروف بنجاح']);
        }

        return back()->with('success', 'تم إلغاء سداد المصروف بنجاح');
    }

    public function destroy(Request $request, Expense $expense): RedirectResponse|JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($expense->workspace_id !== $workspace->id, 403, 'غير مصرح لك بحذف هذا المصروف.');

        if ($expense->maintenance_request_id !== null) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'لا يمكن حذف مصروف مرتبط بطلب صيانة نشط لمنع كسر العلاقة المالية للطلب.',
                ], 422);
            }

            return back()->withErrors([
                'expense' => 'لا يمكن حذف مصروف مرتبط بطلب صيانة نشط لمنع كسر العلاقة المالية للطلب.',
            ])->with('error', 'لا يمكن حذف مصروف مرتبط بطلب صيانة نشط لمنع كسر العلاقة المالية للطلب.');
        }

        $expense->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'تم حذف المصروف بنجاح']);
        }

        return back()->with('success', 'تم حذف المصروف بنجاح');
    }
}
