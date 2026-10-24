<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
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

    public function store(Request $request): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validated = $request->validate([
            'property_id' => [
                'nullable',
                Rule::exists('properties', 'id')->where('workspace_id', $workspace->id),
            ],
            'category' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0.5',
            'expense_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $workspace->expenses()->create([
            ...$validated,
            'recorded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'تم تسجيل المصروف بنجاح');
    }
}
