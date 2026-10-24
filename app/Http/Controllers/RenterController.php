<?php

namespace App\Http\Controllers;

use App\Http\Requests\Renters\StoreRenterDocumentRequest;
use App\Http\Requests\Renters\StoreRenterRequest;
use App\Http\Requests\Renters\UpdateRenterRequest;
use App\Models\Renter;
use App\Models\RenterDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RenterController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $this->resolveWorkspace($request);

        $renters = $workspace->renters()
            ->with([
                'leases' => fn ($q) => $q->with(['unit.property', 'rentCharges.allocations'])->latest(),
                'payments',
            ])
            ->get()
            ->map(function (Renter $renter) {
                $activeLease = $renter->leases->firstWhere('status', 'active');
                $totalPaid = (float) $renter->payments->sum('amount');

                // Determine payment status
                $hasOverdue = false;
                if ($activeLease) {
                    $hasOverdue = $activeLease->rentCharges
                        ->where('due_date', '<', today())
                        ->contains(fn ($c) => $c->outstanding_balance > 0);
                }

                return [
                    'id' => $renter->id,
                    'name' => $renter->name,
                    'phone' => $renter->phone,
                    'email' => $renter->email,
                    'national_id' => $renter->national_id ?? '—',
                    'active_unit' => $activeLease ? [
                        'unit_name' => $activeLease->unit?->name,
                        'property_name' => $activeLease->unit?->property?->name,
                        'rent_amount' => (float) $activeLease->rent_amount,
                        'ends_on' => $activeLease->ends_on?->format('d/m/Y'),
                    ] : null,
                    'status' => $hasOverdue ? 'overdue' : ($activeLease ? 'regular' : 'inactive'),
                    'status_label' => $hasOverdue ? 'متأخر في السداد' : ($activeLease ? 'منتظم' : 'بدون عقد نشط'),
                    'total_paid' => $totalPaid,
                    'whatsapp_opted_in' => (bool) $renter->whatsapp_opt_in_at,
                    'created_at' => $renter->created_at?->format('d/m/Y'),
                ];
            });

        $totalRenters = $renters->count();
        $activeCount = $renters->whereNotNull('active_unit')->count();
        $overdueCount = $renters->where('status', 'overdue')->count();
        $whatsappCount = $renters->where('whatsapp_opted_in', true)->count();

        return Inertia::render('Renters/Index', [
            'renters' => $renters,
            'stats' => [
                'total_renters' => $totalRenters,
                'active_contracts' => $activeCount,
                'overdue_count' => $overdueCount,
                'whatsapp_opted_in' => $whatsappCount,
            ],
        ]);
    }

    public function show(Request $request, Renter $renter): Response
    {
        $workspace = $this->resolveWorkspace($request);

        abort_if($renter->workspace_id !== $workspace->id, 403);

        $renter->load([
            'leases' => fn ($q) => $q->with(['unit.property', 'rentCharges.allocations.payment'])->latest(),
            'payments' => fn ($q) => $q->latest(),
            'messageLogs' => fn ($q) => $q->latest()->take(10),
            'documents',
        ]);

        $activeLease = $renter->leases->firstWhere('status', 'active') ?? $renter->leases->first();

        $charges = collect();
        if ($activeLease) {
            $charges = $activeLease->rentCharges->map(function ($charge) {
                $allocated = (float) $charge->allocations->sum('amount');
                $outstanding = max(0, (float) $charge->amount - $allocated);
                $isPaid = $outstanding <= 0;
                $isOverdue = ! $isPaid && $charge->due_date < today();

                return [
                    'id' => $charge->id,
                    'period' => $charge->period_start?->format('m/Y'),
                    'period_start' => $charge->period_start?->format('d/m/Y'),
                    'period_end' => $charge->period_end?->format('d/m/Y'),
                    'due_date' => $charge->due_date?->format('d/m/Y'),
                    'amount' => (float) $charge->amount,
                    'paid_amount' => $allocated,
                    'outstanding_balance' => $outstanding,
                    'status' => $isPaid ? 'paid' : ($isOverdue ? 'overdue' : 'pending'),
                    'status_label' => $isPaid ? 'مسدد بالكامل' : ($isOverdue ? 'متأخر' : 'مستحق قريباً'),
                ];
            });
        }

        $paymentsList = $renter->payments->map(fn ($p) => [
            'id' => $p->id,
            'reference' => $p->reference ?? 'REC-'.$p->id,
            'amount' => (float) $p->amount,
            'paid_at' => $p->paid_at?->format('d/m/Y H:i') ?? $p->created_at?->format('d/m/Y'),
            'payment_method' => match ($p->payment_method) {
                'bank_transfer' => 'تحويل بنكي',
                'cash' => 'نقدي',
                'cheque' => 'شيك',
                default => 'إنستاباي / فيزا / إلكتروني',
            },
            'notes' => $p->notes,
        ]);

        $messageLogsList = $renter->messageLogs->map(fn ($m) => [
            'id' => $m->id,
            'template' => match ($m->template_name) {
                'rent_due_reminder_ar' => 'تذكير استحقاق الإيجار',
                'payment_receipt_instant_ar' => 'إشعار استلام دفعة وسند قبض',
                default => 'إشعار عام',
            },
            'status' => $m->status,
            'status_label' => match ($m->status) {
                'delivered' => 'تم التسليم',
                'sent' => 'تم الإرسال',
                'pending' => 'معلق',
                'failed' => 'فشل الإرسال',
                default => $m->status,
            },
            'sent_at' => $m->sent_at?->format('d/m/Y H:i') ?? '—',
            'phone' => $m->recipient_phone,
        ]);

        return Inertia::render('Renters/Show', [
            'renter' => [
                'id' => $renter->id,
                'name' => $renter->name,
                'phone' => $renter->phone,
                'email' => $renter->email,
                'national_id' => $renter->national_id ?? '—',
                'id_card_front_url' => $renter->id_card_front_url,
                'id_card_back_url' => $renter->id_card_back_url,
                'whatsapp_opted_in' => (bool) $renter->whatsapp_opt_in_at,
                'whatsapp_opt_in_date' => $renter->whatsapp_opt_in_at?->format('d/m/Y'),
                'notes' => $renter->notes,
                'created_at' => $renter->created_at?->format('d/m/Y'),
            ],
            'active_lease' => $activeLease ? [
                'id' => $activeLease->id,
                'contract_number' => 'C-02'.($activeLease->id + 4),
                'unit_name' => $activeLease->unit?->name,
                'unit_code' => $activeLease->unit?->code,
                'unit_type' => $activeLease->unit?->unit_type,
                'unit_type_label' => match ($activeLease->unit?->unit_type) {
                    'apartment' => 'شقة',
                    'room' => 'غرفة',
                    'bed' => 'سرير',
                    'shop' => 'محل',
                    default => 'وحدة',
                },
                'property_name' => $activeLease->unit?->property?->name,
                'starts_on' => $activeLease->starts_on?->format('d/m/Y'),
                'ends_on' => $activeLease->ends_on?->format('d/m/Y'),
                'rent_amount' => (float) $activeLease->rent_amount,
                'billing_interval' => 'شهري',
                'deposit_amount' => (float) $activeLease->security_deposit_amount,
                'deposit_received' => (float) $activeLease->total_deposit_received,
                'deposit_status' => $activeLease->security_deposit_status,
                'deposit_status_label' => match ($activeLease->security_deposit_status) {
                    'not_required' => 'غير مطلوب',
                    'due' => 'مستحق السداد',
                    'partially_received' => 'مدفوع جزئياً',
                    'held' => 'محتجز قيد العقد',
                    'under_review' => 'قيد التسوية',
                    'settled' => 'تمت التسوية',
                    default => $activeLease->security_deposit_status,
                },
                'status' => $activeLease->status,
            ] : null,
            'security_deposits' => $renter->leases->map(fn ($l) => [
                'lease_id' => $l->id,
                'contract_number' => 'C-02'.($l->id + 4),
                'unit_name' => $l->unit?->name,
                'unit_type' => $l->unit?->unit_type,
                'unit_type_label' => match ($l->unit?->unit_type) {
                    'apartment' => 'شقة',
                    'room' => 'غرفة',
                    'bed' => 'سرير',
                    'shop' => 'محل',
                    default => 'وحدة',
                },
                'agreed_amount' => (float) $l->security_deposit_amount,
                'total_received' => (float) $l->total_deposit_received,
                'total_deductions' => (float) $l->total_deposit_deductions,
                'total_refunded' => (float) $l->total_deposit_refunded,
                'refundable_balance' => (float) $l->refundable_deposit_balance,
                'status' => $l->security_deposit_status,
                'status_label' => match ($l->security_deposit_status) {
                    'not_required' => 'غير مطلوب',
                    'due' => 'مستحق السداد',
                    'partially_received' => 'مدفوع جزئياً',
                    'held' => 'محتجز قيد العقد',
                    'under_review' => 'قيد فحص الوحدة والتسوية',
                    'settled' => 'تمت التسوية',
                    default => $l->security_deposit_status,
                },
            ])->filter(fn ($d) => $d['agreed_amount'] > 0 || $d['total_received'] > 0)->values(),
            'ledger' => $charges,
            'payments' => $paymentsList,
            'whatsapp_logs' => $messageLogsList,
            'documents' => $renter->documents->map(fn ($doc) => [
                'id' => $doc->id,
                'title' => $doc->title ?: ($doc->original_filename ?: 'مستند'),
                'original_filename' => $doc->original_filename ?? 'document',
                'document_type' => $doc->document_type,
                'document_type_label' => match ($doc->document_type) {
                    'national_id' => 'بطاقة هوية وطنية',
                    'passport' => 'جواز سفر',
                    'employment_letter' => 'خطاب تعريف بالعمل / الراتب',
                    'other' => 'عقد / مستند آخر',
                    default => $doc->document_type,
                },
                'file_size' => $doc->file_size ? round($doc->file_size / 1024, 1).' كيلوبايت' : null,
                'created_at' => $doc->created_at?->format('d/m/Y H:i') ?? '',
                'download_url' => route('renters.documents.download', [$renter->id, $doc->id]),
            ]),
            'summary' => [
                'total_billed' => (float) $charges->sum('amount'),
                'total_paid' => (float) $paymentsList->sum('amount'),
                'total_outstanding' => (float) $charges->sum('outstanding_balance'),
            ],
        ]);
    }

    public function checkPhone(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $request->validate([
            'phone' => 'required|string',
            'exclude_id' => 'nullable|integer',
        ]);

        $phone = trim($request->input('phone'));
        $digits = preg_replace('/\D+/', '', $phone);

        $query = $workspace->renters();
        if ($request->filled('exclude_id')) {
            $query->where('id', '!=', $request->input('exclude_id'));
        }

        $existing = $query->where(function ($q) use ($phone, $digits) {
            $q->where('phone', $phone);
            if (! empty($digits)) {
                $q->orWhere('phone', $digits);
                if (str_starts_with($digits, '20') && strlen($digits) > 10) {
                    $q->orWhere('phone', '0'.substr($digits, 2));
                    $q->orWhere('phone', '+'.$digits);
                } elseif (str_starts_with($digits, '0') && strlen($digits) === 11) {
                    $q->orWhere('phone', '+2'.$digits);
                    $q->orWhere('phone', '2'.$digits);
                }
                $q->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') = ?", [$digits]);
            }
        })->first();

        if ($existing) {
            return response()->json([
                'exists' => true,
                'renter' => [
                    'id' => $existing->id,
                    'name' => $existing->name,
                    'phone' => $existing->phone,
                    'national_id' => $existing->national_id,
                    'email' => $existing->email,
                ],
                'message' => 'هذا الرقم مسجل بالفعل للمستأجر: '.$existing->name,
            ]);
        }

        return response()->json([
            'exists' => false,
            'renter' => null,
            'message' => 'رقم الهاتف متاح',
        ]);
    }

    public function store(StoreRenterRequest $request): RedirectResponse|JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $validated = $request->validated();

        $renter = $workspace->renters()->create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'national_id' => $validated['national_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'whatsapp_opt_in_at' => ($validated['whatsapp_consent'] ?? true) ? now() : null,
            'whatsapp_opt_in_source' => 'manual_entry',
        ]);

        $updates = [];

        if ($request->hasFile('id_card_front')) {
            $file = $request->file('id_card_front');
            $extension = $file->getClientOriginalExtension() ?: 'jpg';
            $filename = Str::random(40).'.'.strtolower($extension);
            $updates['id_card_front'] = $file->storeAs("ids/{$renter->id}", $filename, 'public');
        }

        if ($request->hasFile('id_card_back')) {
            $file = $request->file('id_card_back');
            $extension = $file->getClientOriginalExtension() ?: 'jpg';
            $filename = Str::random(40).'.'.strtolower($extension);
            $updates['id_card_back'] = $file->storeAs("ids/{$renter->id}", $filename, 'public');
        }

        if (! empty($updates)) {
            $renter->update($updates);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تمت إضافة المستأجر بنجاح',
                'renter' => [
                    'id' => $renter->id,
                    'name' => $renter->name,
                    'phone' => $renter->phone,
                    'national_id' => $renter->national_id,
                ],
            ], 201);
        }

        return back()->with('success', 'تمت إضافة المستأجر بنجاح');
    }

    public function update(UpdateRenterRequest $request, Renter $renter): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);

        abort_if($renter->workspace_id !== $workspace->id, 403);

        $validated = $request->validated();

        if ($request->hasFile('id_card_front')) {
            if ($renter->id_card_front && Storage::disk('public')->exists($renter->id_card_front)) {
                Storage::disk('public')->delete($renter->id_card_front);
            }
            $file = $request->file('id_card_front');
            $extension = $file->getClientOriginalExtension() ?: 'jpg';
            $filename = Str::random(40).'.'.strtolower($extension);
            $validated['id_card_front'] = $file->storeAs("ids/{$renter->id}", $filename, 'public');
        } else {
            unset($validated['id_card_front']);
        }

        if ($request->hasFile('id_card_back')) {
            if ($renter->id_card_back && Storage::disk('public')->exists($renter->id_card_back)) {
                Storage::disk('public')->delete($renter->id_card_back);
            }
            $file = $request->file('id_card_back');
            $extension = $file->getClientOriginalExtension() ?: 'jpg';
            $filename = Str::random(40).'.'.strtolower($extension);
            $validated['id_card_back'] = $file->storeAs("ids/{$renter->id}", $filename, 'public');
        } else {
            unset($validated['id_card_back']);
        }

        $renter->update($validated);

        return back()->with('success', 'تم تحديث بيانات المستأجر بنجاح');
    }

    public function storeDocument(StoreRenterDocumentRequest $request, Renter $renter): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($renter->workspace_id !== $workspace->id, 403, 'غير مصرح لك بإدارة مستندات هذا المستأجر.');

        $validated = $request->validated();

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $fileSize = $file->getSize();
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $safeName = Str::random(40).'.'.strtolower($extension);

        $path = $file->storeAs("documents/{$workspace->id}/{$renter->id}", $safeName, 'local');

        $renter->documents()->create([
            'workspace_id' => $workspace->id,
            'document_type' => $validated['document_type'],
            'title' => $validated['title'] ?: pathinfo($originalName, PATHINFO_FILENAME),
            'original_filename' => $originalName,
            'storage_path' => $path,
            'file_size' => $fileSize,
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'تم رفع المستند بنجاح وحفظه في التخزين الآمن');
    }

    public function downloadDocument(Request $request, Renter $renter, RenterDocument $document): StreamedResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($renter->workspace_id !== $workspace->id, 403, 'غير مصرح لك بالوصول لهذا المستأجر.');
        abort_if($document->workspace_id !== $workspace->id || $document->renter_id !== $renter->id, 403, 'غير مصرح لك بتحميل هذا المستند.');

        if (! Storage::disk('local')->exists($document->storage_path)) {
            abort(404, 'الملف غير موجود في وحدة التخزين الآمن.');
        }

        return Storage::disk('local')->download($document->storage_path, $document->original_filename ?: 'document');
    }

    public function destroyDocument(Request $request, Renter $renter, RenterDocument $document): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($renter->workspace_id !== $workspace->id, 403, 'غير مصرح لك بالوصول لهذا المستأجر.');
        abort_if($document->workspace_id !== $workspace->id || $document->renter_id !== $renter->id, 403, 'غير مصرح لك بحذف هذا المستند.');

        if (Storage::disk('local')->exists($document->storage_path)) {
            Storage::disk('local')->delete($document->storage_path);
        }

        $document->delete();

        return back()->with('success', 'تم حذف المستند بنجاح من النظام والتخزين');
    }
}
