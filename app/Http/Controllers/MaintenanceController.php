<?php

namespace App\Http\Controllers;

use App\Http\Requests\Maintenance\StoreMaintenanceAttachmentRequest;
use App\Http\Requests\Maintenance\StoreMaintenanceNoteRequest;
use App\Http\Requests\Maintenance\StoreMaintenanceRequest;
use App\Http\Requests\Maintenance\UpdateMaintenanceRequest;
use App\Http\Requests\Maintenance\UpdateMaintenanceStatusRequest;
use App\Http\Resources\MaintenanceRequestResource;
use App\Jobs\NotifyMaintenanceRequestCreatedJob;
use App\Models\MaintenanceAttachment;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\Unit;
use App\Services\MaintenanceExpenseService;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaintenanceController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $this->resolveWorkspace($request);

        $requests = $workspace->maintenanceRequests()
            ->with(['property', 'unit', 'reportedByRenter', 'expense'])
            ->withCount(['attachments', 'notes'])
            ->latest('reported_at')
            ->latest('id')
            ->get();

        $requestsData = collect(MaintenanceRequestResource::collection($requests)->resolve());

        $properties = $workspace->properties()->with('units')->get();

        $pendingCount = $requestsData->filter(fn (array $r): bool => in_array($r['status'], ['pending', 'open'], true))->count();
        $inProgressCount = $requestsData->where('status', 'in_progress')->count();
        $completedCount = $requestsData->filter(fn (array $r): bool => in_array($r['status'], ['completed', 'closed'], true))->count();

        // Operational stats for linked expenses
        $pendingCostCount = $requestsData->filter(fn (array $r): bool => ! $r['expense'] || ! $r['expense']['has_amount'])->count();
        $recordedCostCount = $requestsData->filter(fn (array $r): bool => $r['expense'] && $r['expense']['has_amount'])->count();

        return Inertia::render('Maintenance/Index', [
            'requests' => $requestsData->values()->all(),
            'properties' => $properties->map(fn (Property $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'units' => $p->units->map(fn (Unit $u) => ['id' => $u->id, 'name' => $u->name]),
            ]),
            'stats' => [
                'open' => $pendingCount,
                'pending' => $pendingCount,
                'in_progress' => $inProgressCount,
                'closed' => $completedCount,
                'completed' => $completedCount,
                'total' => $requests->count(),
                'pending_cost_count' => $pendingCostCount,
                'recorded_cost_count' => $recordedCostCount,
            ],
            'currency' => $workspace->currency ?? 'EGP',
            'initial_selected_id' => $request->query('selected') ? (int) $request->query('selected') : null,
        ]);
    }

    public function store(
        StoreMaintenanceRequest $request,
        MaintenanceExpenseService $expenseService
    ): RedirectResponse {
        $workspace = $this->resolveWorkspace($request);
        $validated = $request->validated();

        $maintenanceRequest = $expenseService->createMaintenanceWithExpense(
            $workspace,
            $validated,
            $request->user()->id
        );

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

        DB::transaction(function () use ($maintenance, $request, $expenseService) {
            $maintenance->update($request->validated());

            // Sync linked expense details without overwriting user-entered amount or payment status
            $expenseService->syncFromMaintenance($maintenance, $request->user()->id);

            if (function_exists('activity')) {
                activity('maintenance')
                    ->performedOn($maintenance)
                    ->causedBy($request->user())
                    ->withProperties([
                        'workspace_id' => $maintenance->workspace_id,
                        'changes' => $request->validated(),
                    ])
                    ->log('تم تحديث بيانات طلب الصيانة ومزامنة بند المصروف');
            }
        });

        return back()->with('success', 'تم تحديث بيانات طلب الصيانة ومزامنة بند المصروف بنجاح');
    }

    public function updateStatus(
        UpdateMaintenanceStatusRequest $request,
        MaintenanceRequest $maintenance,
        MaintenanceExpenseService $expenseService
    ): RedirectResponse {
        $workspace = $this->resolveWorkspace($request);

        abort_if($maintenance->workspace_id !== $workspace->id, 403, 'غير مصرح لك بتعديل طلب الصيانة هذا.');

        $validated = $request->validated();
        $newStatus = $validated['status'];

        try {
            $expenseService->transitionStatus($maintenance, $newStatus, $request->user()->id);
        } catch (\DomainException $e) {
            return back()->withErrors([
                'status' => $e->getMessage(),
            ])->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم تحديث حالة طلب الصيانة بنجاح');
    }

    public function show(Request $request, MaintenanceRequest $maintenance): JsonResponse|RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($maintenance->workspace_id !== $workspace->id, 403, 'غير مصرح لك بعرض طلب الصيانة هذا.');

        $maintenance->load([
            'property',
            'unit',
            'reportedByRenter',
            'expense',
            'attachments.uploader',
            'notes.creator',
        ]);

        $expense = $maintenance->expense;
        $hasAmount = $expense !== null && $expense->amount !== null && (float) $expense->amount > 0;
        $isPaid = $expense !== null && $expense->isPaid();

        $data = [
            'id' => $maintenance->id,
            'title' => $maintenance->title,
            'description' => $maintenance->description,
            'status' => match ($maintenance->status) {
                'open' => 'pending',
                'closed' => 'completed',
                default => $maintenance->status,
            },
            'status_label' => match ($maintenance->status) {
                'pending', 'open' => 'جديدة',
                'in_progress' => 'جاري الإصلاح',
                'completed', 'closed' => 'مكتملة',
                default => $maintenance->status,
            },
            'is_pending' => $maintenance->isPending(),
            'is_in_progress' => $maintenance->isInProgress(),
            'is_completed' => $maintenance->isCompleted(),
            'can_be_completed' => $hasAmount,
            'property_id' => $maintenance->property_id,
            'property_name' => $maintenance->property?->name ?? 'عقار عام',
            'unit_id' => $maintenance->unit_id,
            'unit_name' => $maintenance->unit?->name ?? 'الأماكن المشتركة',
            'is_common_area' => $maintenance->unit_id === null,
            'renter_name' => $maintenance->reportedByRenter?->name ?? 'إدارة العقار',
            'reported_at' => $maintenance->reported_at?->format('d/m/Y H:i'),
            'raw_reported_at' => $maintenance->reported_at?->toIso8601String(),
            'started_at' => $maintenance->started_at?->format('d/m/Y H:i'),
            'raw_started_at' => $maintenance->started_at?->toIso8601String(),
            'resolved_at' => $maintenance->resolved_at?->format('d/m/Y H:i'),
            'raw_resolved_at' => $maintenance->resolved_at?->toIso8601String(),
            'created_at' => $maintenance->created_at?->format('d/m/Y H:i'),
            'updated_at' => $maintenance->updated_at?->format('d/m/Y H:i'),
            'expense' => $expense ? [
                'id' => $expense->id,
                'amount' => $expense->amount !== null ? (float) $expense->amount : null,
                'has_amount' => $hasAmount,
                'status' => $expense->status,
                'payment_status' => $expense->payment_status ?? 'unpaid',
                'is_paid' => $isPaid,
                'paid_at' => $expense->paid_at?->format('d/m/Y'),
                'status_label' => ! $hasAmount
                    ? 'بانتظار تسجيل التكلفة'
                    : ($isPaid ? 'تم دفع المصروف' : 'التكلفة مسجلة — غير مدفوعة'),
                'is_pending_amount' => $expense->isPendingAmount(),
                'is_ready' => $expense->isReady(),
                'is_recorded' => $expense->isRecorded(),
            ] : null,
            'attachments' => $maintenance->attachments->map(fn ($att) => [
                'id' => $att->id,
                'original_filename' => $att->original_filename,
                'file_size' => $att->file_size,
                'formatted_size' => $att->formatted_size,
                'mime_type' => $att->mime_type,
                'is_image' => $att->isImage(),
                'is_pdf' => $att->isPdf(),
                'preview_url' => route('maintenance.attachments.preview', [$maintenance->id, $att->id]),
                'download_url' => route('maintenance.attachments.download', [$maintenance->id, $att->id]),
                'uploaded_by_name' => $att->uploader?->name ?? 'غير محدد',
                'created_at' => $att->created_at?->format('d/m/Y H:i'),
            ])->values()->all(),
            'notes' => $maintenance->notes->map(fn ($n) => [
                'id' => $n->id,
                'note' => $n->note,
                'created_by_name' => $n->creator?->name ?? 'مستخدم',
                'created_at' => $n->created_at?->format('d/m/Y H:i'),
                'raw_created_at' => $n->created_at?->toIso8601String(),
            ])->values()->all(),
            'activities' => $maintenance->activities()->with('causer')->latest('id')->get()->map(fn ($act) => [
                'id' => $act->id,
                'description' => $act->description,
                'causer_name' => $act->causer?->name ?? 'النظام',
                'properties' => $act->properties,
                'created_at' => $act->created_at?->format('d/m/Y H:i'),
                'raw_created_at' => $act->created_at?->toIso8601String(),
            ])->values()->all(),
        ];

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($data);
        }

        return redirect()->route('maintenance.index', ['selected' => $maintenance->id]);
    }

    public function storeAttachments(
        StoreMaintenanceAttachmentRequest $request,
        MaintenanceRequest $maintenance
    ): JsonResponse|RedirectResponse {
        $workspace = $this->resolveWorkspace($request);
        abort_if($maintenance->workspace_id !== $workspace->id, 403, 'غير مصرح لك بإدارة مرفقات طلب الصيانة هذا.');

        $files = $request->file('files', []);
        $uploaded = [];

        DB::transaction(function () use ($files, $maintenance, $workspace, $request, &$uploaded) {
            foreach ($files as $file) {
                $originalName = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension() ?: 'bin';
                $safeName = Str::uuid()->toString().'.'.strtolower($extension);
                $path = $file->storeAs("maintenance/{$workspace->id}/{$maintenance->id}", $safeName, 'local');

                $attachment = $maintenance->attachments()->create([
                    'workspace_id' => $workspace->id,
                    'file_path' => $path,
                    'original_filename' => $originalName,
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'file_size' => $file->getSize() ?: 0,
                    'uploaded_by' => $request->user()->id,
                ]);

                if (function_exists('activity')) {
                    activity('maintenance')
                        ->performedOn($maintenance)
                        ->causedBy($request->user())
                        ->withProperties([
                            'workspace_id' => $workspace->id,
                            'attachment_id' => $attachment->id,
                            'filename' => $originalName,
                        ])
                        ->log('تم رفع مرفق جديد: '.$originalName);
                }

                $uploaded[] = [
                    'id' => $attachment->id,
                    'original_filename' => $attachment->original_filename,
                    'file_size' => $attachment->file_size,
                    'formatted_size' => $attachment->formatted_size,
                    'mime_type' => $attachment->mime_type,
                    'is_image' => $attachment->isImage(),
                    'is_pdf' => $attachment->isPdf(),
                    'preview_url' => route('maintenance.attachments.preview', [$maintenance->id, $attachment->id]),
                    'download_url' => route('maintenance.attachments.download', [$maintenance->id, $attachment->id]),
                    'uploaded_by_name' => $request->user()->name,
                    'created_at' => $attachment->created_at?->format('d/m/Y H:i'),
                ];
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'تم رفع المرفقات بنجاح',
                'attachments' => $uploaded,
            ]);
        }

        return back()->with('success', 'تم رفع المرفقات بنجاح');
    }

    public function previewAttachment(
        Request $request,
        MaintenanceRequest $maintenance,
        MaintenanceAttachment $attachment,
        DocumentStorageService $storage
    ): SymfonyResponse {
        $workspace = $this->resolveWorkspace($request);
        abort_if($maintenance->workspace_id !== $workspace->id, 403, 'غير مصرح لك.');
        abort_if((int) $attachment->maintenance_request_id !== (int) $maintenance->id, 403, 'غير مصرح لك.');
        abort_if((int) $attachment->workspace_id !== (int) $workspace->id, 403, 'غير مصرح لك.');

        abort_if(! $storage->exists($attachment->file_path), 404, 'الملف غير موجود في التخزين.');

        return $storage->previewResponse($attachment->file_path, $attachment->mime_type, $attachment->original_filename);
    }

    public function downloadAttachment(
        Request $request,
        MaintenanceRequest $maintenance,
        MaintenanceAttachment $attachment,
        DocumentStorageService $storage
    ): StreamedResponse {
        $workspace = $this->resolveWorkspace($request);
        abort_if($maintenance->workspace_id !== $workspace->id, 403, 'غير مصرح لك.');
        abort_if((int) $attachment->maintenance_request_id !== (int) $maintenance->id, 403, 'غير مصرح لك.');
        abort_if((int) $attachment->workspace_id !== (int) $workspace->id, 403, 'غير مصرح لك.');

        abort_if(! $storage->exists($attachment->file_path), 404, 'الملف غير موجود في التخزين.');

        return $storage->downloadResponse($attachment->file_path, $attachment->original_filename);
    }

    public function destroyAttachment(
        Request $request,
        MaintenanceRequest $maintenance,
        MaintenanceAttachment $attachment,
        DocumentStorageService $storage
    ): JsonResponse|RedirectResponse {
        $workspace = $this->resolveWorkspace($request);
        abort_if($maintenance->workspace_id !== $workspace->id, 403, 'غير مصرح لك.');
        abort_if((int) $attachment->maintenance_request_id !== (int) $maintenance->id, 403, 'غير مصرح لك.');
        abort_if((int) $attachment->workspace_id !== (int) $workspace->id, 403, 'غير مصرح لك.');

        // Only delete file from disk if no other record references the same path
        $otherReferences = MaintenanceAttachment::withoutGlobalScopes()
            ->where('file_path', $attachment->file_path)
            ->where('id', '!=', $attachment->id)
            ->exists();

        if (! $otherReferences) {
            $storage->delete($attachment->file_path);
        }

        $filename = $attachment->original_filename;
        $attachment->delete();

        if (function_exists('activity')) {
            activity('maintenance')
                ->performedOn($maintenance)
                ->causedBy($request->user())
                ->withProperties([
                    'workspace_id' => $workspace->id,
                    'filename' => $filename,
                ])
                ->log('تم حذف المرفق: '.$filename);
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'تم حذف المرفق بنجاح']);
        }

        return back()->with('success', 'تم حذف المرفق بنجاح');
    }

    public function storeNote(
        StoreMaintenanceNoteRequest $request,
        MaintenanceRequest $maintenance
    ): JsonResponse|RedirectResponse {
        $workspace = $this->resolveWorkspace($request);
        abort_if($maintenance->workspace_id !== $workspace->id, 403, 'غير مصرح لك.');

        $note = $maintenance->notes()->create([
            'workspace_id' => $workspace->id,
            'note' => $request->validated('note'),
            'created_by' => $request->user()->id,
        ]);

        if (function_exists('activity')) {
            activity('maintenance')
                ->performedOn($maintenance)
                ->causedBy($request->user())
                ->withProperties([
                    'workspace_id' => $workspace->id,
                    'note_id' => $note->id,
                ])
                ->log('تمت إضافة ملاحظة تقدم جديدة');
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'تمت إضافة الملاحظة بنجاح',
                'note' => [
                    'id' => $note->id,
                    'note' => $note->note,
                    'created_by_name' => $request->user()->name,
                    'created_at' => $note->created_at?->format('d/m/Y H:i'),
                    'raw_created_at' => $note->created_at?->toIso8601String(),
                ],
            ]);
        }

        return back()->with('success', 'تمت إضافة الملاحظة بنجاح');
    }
}
