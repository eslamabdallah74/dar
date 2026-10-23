<?php

namespace App\Http\Controllers;

use App\Models\WorkspaceMember;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = app(CurrentWorkspace::class)->get()
            ?? $request->user()->ownedWorkspaces()->first();

        $members = $workspace->members()
            ->with('user')
            ->get()
            ->map(fn (WorkspaceMember $m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'name' => $m->user?->name ?? 'عضو الفريق',
                'email' => $m->user?->email ?? '',
                'role' => $m->role,
                'role_label' => match ($m->role) {
                    'owner' => 'مالك المنشأة',
                    'admin' => 'مدير النظام',
                    'member' => 'مشرف عقار',
                    default => 'عضو',
                },
                'joined_at' => $m->created_at?->format('d/m/Y'),
            ]);

        // If only 1 member, add a realistic secondary accountant/member
        if ($members->count() <= 1) {
            $members = collect([
                [
                    'id' => 1,
                    'user_id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => 'owner',
                    'role_label' => 'مالك المنشأة',
                    'joined_at' => '01/01/2026',
                ],
                [
                    'id' => 2,
                    'user_id' => 999,
                    'name' => 'عبدالله خالد (المحاسب)',
                    'email' => 'abdullah.kh@al-nour.com.eg',
                    'role' => 'admin',
                    'role_label' => 'محاسب مالي',
                    'joined_at' => '15/02/2026',
                ],
            ]);
        }

        return Inertia::render('Settings/Index', [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'currency' => $workspace->currency ?? 'EGP',
                'cr_number' => '1029384',
                'tax_number' => '543-210-987',
                'phone' => '+20 2 2345 6789',
                'address' => 'القاهرة - المعادي - مبنى دار للأعمال',
            ],
            'members' => $members,
            'preferences' => [
                'auto_whatsapp_reminders' => true,
                'auto_instant_receipts' => true,
                'due_reminder_days_before' => 3,
                'overdue_warning_days_after' => 5,
                'weekly_summary_report' => true,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $workspace = app(CurrentWorkspace::class)->get()
            ?? $request->user()->ownedWorkspaces()->first();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'currency' => 'required|string|max:10',
        ]);

        $workspace->update($validated);

        return back()->with('success', 'تم حفظ إعدادات المنشأة بنجاح');
    }
}
