<?php

namespace App\Http\Controllers;

use App\Http\Requests\Expenses\StoreExpenseRequest;
use App\Models\Expense;
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
            ->with(['property', 'unit', 'recorder'])
            ->latest('expense_date')
            ->get()
            ->map(function (Expense $expense) {
                return [
                    'id' => $expense->id,
                    'category' => $expense->category,
                    'amount' => (float) $expense->amount,
                    'expense_date' => $expense->expense_date?->format('d/m/Y'),
                    'property_name' => $expense->property?->name ?? 'مصروف عام للمنشأة',
                    'unit_name' => $expense->unit?->name ?? '—',
                    'notes' => $expense->notes ?? '',
                    'recorder_name' => $expense->recorder?->name ?? 'المسؤول',
                ];
            });

        $totalExpenses = (float) $expenses->sum('amount');
        $byCategory = $expenses->groupBy('category')->map(fn ($grp) => (float) $grp->sum('amount'));

        $properties = $workspace->properties()->get(['id', 'name']);

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'properties' => $properties,
            'stats' => [
                'total_amount' => $totalExpenses,
                'count' => $expenses->count(),
                'categories' => $byCategory,
            ],
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $workspace->expenses()->create([
            ...$request->validated(),
            'recorded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'تم تسجيل المصروف بنجاح');
    }
}
