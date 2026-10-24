<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
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

        if (! $isAuthorized) {
            abort(403, 'غير مصرح لك بالوصول إلى مساحة العمل هذه.');
        }

        return $workspace;
    }

    public function index(Request $request): Response
    {
        $workspace = $this->resolveWorkspace($request);

        return Inertia::render('Settings/Index', [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'currency' => $workspace->currency ?? 'EGP',
                'cr_number' => $workspace->cr_number ?? '',
                'tax_number' => $workspace->tax_number ?? '',
                'phone' => $workspace->phone ?? '',
                'address' => $workspace->address ?? '',
            ],
            'preferences' => [
                'auto_whatsapp_reminders' => (bool) $workspace->auto_whatsapp_reminders,
                'auto_instant_receipts' => (bool) $workspace->auto_instant_receipts,
                'due_reminder_days_before' => (int) ($workspace->rent_reminder_days_before ?? 2),
                'overdue_warning_days_after' => (int) ($workspace->overdue_warning_days_after ?? 5),
                'weekly_summary_report' => (bool) $workspace->weekly_summary_report,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'currency' => 'nullable|string|max:10',
            'cr_number' => 'nullable|string|max:50',
            'tax_number' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:500',
            'auto_whatsapp_reminders' => 'nullable|boolean',
            'auto_instant_receipts' => 'nullable|boolean',
            'due_reminder_days_before' => 'nullable|integer|min:0|max:30',
            'overdue_warning_days_after' => 'nullable|integer|min:0|max:60',
            'weekly_summary_report' => 'nullable|boolean',
        ]);

        $updates = array_filter($validated, fn ($val) => $val !== null);

        if (isset($validated['due_reminder_days_before'])) {
            $updates['rent_reminder_days_before'] = $validated['due_reminder_days_before'];
            unset($updates['due_reminder_days_before']);
        }

        $workspace->update($updates);

        return back()->with('success', 'تم حفظ إعدادات وتفضيلات المنشأة بنجاح');
    }
}
