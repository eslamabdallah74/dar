<?php

namespace App\Http\Controllers;

use App\Http\Requests\Renters\StoreRenterDocumentRequest;
use App\Http\Requests\Renters\StoreRenterRequest;
use App\Http\Requests\Renters\UpdateRenterRequest;
use App\Http\Resources\RenterResource;
use App\Models\Lease;
use App\Models\MessageLog;
use App\Models\Payment;
use App\Models\RentCharge;
use App\Models\Renter;
use App\Models\RenterDocument;
use App\Rules\EgyptianPhoneNumber;
use App\Services\EncryptedFileService;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->get();

        $rentersData = collect(RenterResource::collection($renters)->resolve());

        $totalRenters = $rentersData->count();
        $activeCount = $rentersData->whereNotNull('active_unit')->count();
        $overdueCount = $rentersData->where('status', 'overdue')->count();
        $whatsappCount = $rentersData->where('whatsapp_opted_in', true)->count();

        return Inertia::render('Renters/Index', [
            'renters' => $rentersData->values()->all(),
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
        $ledger = $activeLease ? $this->ledgerRows($activeLease) : [];
        $payments = $this->paymentRows($renter);

        return Inertia::render('Renters/Show', [
            'renter' => [
                'id' => $renter->id,
                'name' => $renter->name,
                'phone' => $renter->phone,
                'email' => $renter->email,
                'national_id' => $renter->national_id ?? '—',
                'gender' => $renter->gender?->value,
                'gender_label' => $renter->gender?->label(),
                'id_card_front_url' => $renter->id_card_front_url,
                'id_card_back_url' => $renter->id_card_back_url,
                'whatsapp_opted_in' => (bool) $renter->whatsapp_opt_in_at,
                'whatsapp_opt_in_date' => $renter->whatsapp_opt_in_at?->format('d/m/Y'),
                'notes' => $renter->notes,
                'created_at' => $renter->created_at?->format('d/m/Y'),
            ],
            'active_lease' => $activeLease ? $this->activeLeaseSummary($activeLease) : null,
            'security_deposits' => $this->securityDepositRows($renter),
            'ledger' => $ledger,
            'payments' => $payments,
            'whatsapp_logs' => $this->messageLogRows($renter),
            'documents' => $this->documentRows($renter),
            'summary' => [
                'total_billed' => (float) array_sum(array_column($ledger, 'amount')),
                'total_paid' => (float) array_sum(array_column($payments, 'amount')),
                'total_outstanding' => (float) array_sum(array_column($ledger, 'outstanding_balance')),
            ],
        ]);
    }

    public function checkPhone(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $request->validate([
            'phone' => ['required', 'string', new EgyptianPhoneNumber],
            'exclude_id' => 'nullable|integer',
        ], [
            'phone.required' => 'يرجى إدخال رقم الهاتف.',
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
                    'gender' => $existing->gender?->value,
                    'gender_label' => $existing->gender?->label(),
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

    public function store(StoreRenterRequest $request, DocumentStorageService $storage): RedirectResponse|JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $validated = $request->validated();

        $renter = $workspace->renters()->create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'national_id' => $validated['national_id'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'whatsapp_opt_in_at' => ($validated['whatsapp_consent'] ?? true) ? now() : null,
            'whatsapp_opt_in_source' => 'manual_entry',
        ]);

        $updates = [];

        if ($request->hasFile('id_card_front')) {
            $file = $request->file('id_card_front');
            $path = $storage->storeEncrypted($file, "ids/{$renter->id}");
            $updates['id_card_front'] = $path;
        }

        if ($request->hasFile('id_card_back')) {
            $file = $request->file('id_card_back');
            $path = $storage->storeEncrypted($file, "ids/{$renter->id}");
            $updates['id_card_back'] = $path;
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
                    'gender' => $renter->gender?->value,
                    'gender_label' => $renter->gender?->label(),
                ],
            ], 201);
        }

        return back()->with('success', 'تمت إضافة المستأجر بنجاح');
    }

    public function update(
        UpdateRenterRequest $request,
        Renter $renter,
        DocumentStorageService $storage
    ): RedirectResponse {
        $workspace = $this->resolveWorkspace($request);

        abort_if($renter->workspace_id !== $workspace->id, 403);

        $validated = $request->validated();

        if ($request->hasFile('id_card_front')) {
            if ($renter->id_card_front) {
                EncryptedFileService::delete($renter->id_card_front, $storage->getPrivateDisk());
                $storage->delete($renter->id_card_front, 'public');
            }
            $file = $request->file('id_card_front');
            $path = $storage->storeEncrypted($file, "ids/{$renter->id}");
            $validated['id_card_front'] = $path;
        } else {
            unset($validated['id_card_front']);
        }

        if ($request->hasFile('id_card_back')) {
            if ($renter->id_card_back) {
                EncryptedFileService::delete($renter->id_card_back, $storage->getPrivateDisk());
                $storage->delete($renter->id_card_back, 'public');
            }
            $file = $request->file('id_card_back');
            $path = $storage->storeEncrypted($file, "ids/{$renter->id}");
            $validated['id_card_back'] = $path;
        } else {
            unset($validated['id_card_back']);
        }

        $renter->update($validated);

        return back()->with('success', 'تم تحديث بيانات المستأجر بنجاح');
    }

    public function storeDocument(
        StoreRenterDocumentRequest $request,
        Renter $renter,
        DocumentStorageService $storage
    ): RedirectResponse {
        $workspace = $this->resolveWorkspace($request);
        abort_if($renter->workspace_id !== $workspace->id, 403, 'غير مصرح لك بإدارة مستندات هذا المستأجر.');

        $validated = $request->validated();

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $fileSize = $file->getSize();

        $path = $storage->storePrivate($file, "documents/{$workspace->id}/{$renter->id}");

        $renter->documents()->create([
            'workspace_id' => $workspace->id,
            'document_type' => $validated['document_type'],
            'title' => $validated['title'] ?: pathinfo($originalName, PATHINFO_FILENAME),
            'original_filename' => $originalName,
            'storage_path' => $path,
            'file_size' => $fileSize,
            'uploaded_by' => $this->authenticatedUser($request)->id,
        ]);

        return back()->with('success', 'تم رفع المستند بنجاح وحفظه في التخزين الآمن');
    }

    public function downloadDocument(
        Request $request,
        Renter $renter,
        RenterDocument $document,
        DocumentStorageService $storage
    ): StreamedResponse {
        $workspace = $this->resolveWorkspace($request);
        abort_if($renter->workspace_id !== $workspace->id, 403, 'غير مصرح لك بالوصول لهذا المستأجر.');
        abort_if($document->workspace_id !== $workspace->id || $document->renter_id !== $renter->id, 403, 'غير مصرح لك بتحميل هذا المستند.');

        abort_if(! $storage->exists($document->storage_path), 404, 'الملف غير موجود في وحدة التخزين الآمن.');

        return $storage->downloadResponse($document->storage_path, $document->original_filename ?: 'document');
    }

    public function viewIdCard(
        Request $request,
        Renter $renter,
        string $side,
        DocumentStorageService $storage
    ): \Symfony\Component\HttpFoundation\Response {
        $workspace = $this->resolveWorkspace($request);
        abort_if($renter->workspace_id !== $workspace->id, 403, 'غير مصرح لك بعرض هذا المستند.');

        $path = $side === 'front' ? $renter->id_card_front : $renter->id_card_back;

        if (! $path) {
            abort(404, 'لم يتم إرفاق صورة بطاقة الهوية.');
        }

        if ($storage->exists($path, $storage->getPrivateDisk())) {
            return EncryptedFileService::response($path, $storage->getPrivateDisk(), "renter-{$renter->id}-id-{$side}");
        }

        if ($storage->exists($path, 'public')) {
            return EncryptedFileService::response($path, 'public', "renter-{$renter->id}-id-{$side}");
        }

        abort(404, 'ملف بطاقة الهوية غير موجود.');
    }

    public function destroyDocument(
        Request $request,
        Renter $renter,
        RenterDocument $document,
        DocumentStorageService $storage
    ): RedirectResponse {
        $workspace = $this->resolveWorkspace($request);
        abort_if($renter->workspace_id !== $workspace->id, 403, 'غير مصرح لك بالوصول لهذا المستأجر.');
        abort_if($document->workspace_id !== $workspace->id || $document->renter_id !== $renter->id, 403, 'غير مصرح لك بحذف هذا المستند.');

        $storage->delete($document->storage_path);

        $document->delete();

        return back()->with('success', 'تم حذف المستند بنجاح من النظام والتخزين');
    }

    /**
     * The rent ledger of a lease as shown on the renter page.
     *
     * @return array<int, array<string, mixed>>
     */
    private function ledgerRows(Lease $lease): array
    {
        return $lease->rentCharges->map(function (RentCharge $charge): array {
            $allocated = (float) $charge->allocations->sum('amount');
            $outstanding = max(0, (float) $charge->amount - $allocated);
            $isPaid = $outstanding <= 0;
            $isOverdue = ! $isPaid && $charge->due_date < today();

            return [
                'id' => $charge->id,
                'period' => $charge->period_start->format('m/Y'),
                'period_start' => $charge->period_start->format('d/m/Y'),
                'period_end' => $charge->period_end->format('d/m/Y'),
                'due_date' => $charge->due_date->format('d/m/Y'),
                'amount' => (float) $charge->amount,
                'paid_amount' => $allocated,
                'outstanding_balance' => $outstanding,
                'status' => $isPaid ? 'paid' : ($isOverdue ? 'overdue' : 'pending'),
                'status_label' => $isPaid ? 'مسدد بالكامل' : ($isOverdue ? 'متأخر' : 'مستحق قريباً'),
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function paymentRows(Renter $renter): array
    {
        return $renter->payments->map(fn (Payment $payment): array => [
            'id' => $payment->id,
            'reference' => $payment->reference ?? 'REC-'.$payment->id,
            'amount' => (float) $payment->amount,
            'paid_at' => $payment->paid_at->format('d/m/Y H:i'),
            'payment_method' => match ($payment->payment_method) {
                'bank_transfer' => 'تحويل بنكي',
                'cash' => 'نقدي',
                'cheque' => 'شيك',
                default => 'إنستاباي / فيزا / إلكتروني',
            },
            'notes' => $payment->notes,
            'has_receipt' => ! empty($payment->receipt_path),
            'receipt_url' => ! empty($payment->receipt_path) ? route('payments.attachment', $payment) : null,
        ])->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function messageLogRows(Renter $renter): array
    {
        return $renter->messageLogs->map(fn (MessageLog $messageLog): array => [
            'id' => $messageLog->id,
            'template' => match ($messageLog->template_name) {
                'rent_due_reminder_ar' => 'تذكير استحقاق الإيجار',
                'payment_receipt_instant_ar' => 'إشعار استلام دفعة وسند قبض',
                default => 'إشعار عام',
            },
            'status' => $messageLog->status,
            'status_label' => match ($messageLog->status) {
                'delivered' => 'تم التسليم',
                'sent' => 'تم الإرسال',
                'pending' => 'معلق',
                'failed' => 'فشل الإرسال',
                default => $messageLog->status,
            },
            'sent_at' => $messageLog->sent_at?->format('d/m/Y H:i') ?? '—',
            'phone' => $messageLog->recipient_phone,
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function activeLeaseSummary(Lease $lease): array
    {
        return [
            'id' => $lease->id,
            'contract_number' => $lease->contract_number,
            'unit_name' => $lease->unit?->name,
            'unit_code' => $lease->unit?->code,
            'unit_type' => $lease->unit?->unit_type,
            'unit_type_label' => $this->unitTypeLabel($lease->unit?->unit_type),
            'property_name' => $lease->unit?->property?->name,
            'apartment_gender' => $lease->unit?->getApartmentGenderInfo(),
            'starts_on' => $lease->starts_on->format('d/m/Y'),
            'ends_on' => $lease->ends_on->format('d/m/Y'),
            'rent_amount' => (float) $lease->rent_amount,
            'billing_interval' => 'شهري',
            'deposit_amount' => (float) $lease->security_deposit_amount,
            'deposit_received' => $lease->total_deposit_received,
            'deposit_status' => $lease->security_deposit_status,
            'deposit_status_label' => match ($lease->security_deposit_status) {
                'not_required' => 'غير مطلوب',
                'due' => 'مستحق السداد',
                'partially_received' => 'مدفوع جزئياً',
                'held' => 'محتجز قيد العقد',
                'under_review' => 'قيد التسوية',
                'settled' => 'تمت التسوية',
            },
            'status' => $lease->status,
        ];
    }

    /**
     * Deposit position of every lease of the renter that involves a deposit.
     *
     * @return array<int, array<string, mixed>>
     */
    private function securityDepositRows(Renter $renter): array
    {
        return $renter->leases->map(fn (Lease $lease): array => [
            'lease_id' => $lease->id,
            'contract_number' => $lease->contract_number,
            'unit_name' => $lease->unit?->name,
            'unit_type' => $lease->unit?->unit_type,
            'unit_type_label' => $this->unitTypeLabel($lease->unit?->unit_type),
            'agreed_amount' => (float) $lease->security_deposit_amount,
            'total_received' => $lease->total_deposit_received,
            'total_deductions' => $lease->total_deposit_deductions,
            'total_refunded' => $lease->total_deposit_refunded,
            'refundable_balance' => $lease->refundable_deposit_balance,
            'status' => $lease->security_deposit_status,
            'status_label' => match ($lease->security_deposit_status) {
                'not_required' => 'غير مطلوب',
                'due' => 'مستحق السداد',
                'partially_received' => 'مدفوع جزئياً',
                'held' => 'محتجز قيد العقد',
                'under_review' => 'قيد فحص الوحدة والتسوية',
                'settled' => 'تمت التسوية',
            },
        ])->filter(fn (array $deposit) => $deposit['agreed_amount'] > 0 || $deposit['total_received'] > 0)->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function documentRows(Renter $renter): array
    {
        return $renter->documents->map(fn (RenterDocument $document): array => [
            'id' => $document->id,
            'title' => $document->title ?: ($document->original_filename ?: 'مستند'),
            'original_filename' => $document->original_filename ?? 'document',
            'document_type' => $document->document_type,
            'document_type_label' => match ($document->document_type) {
                'national_id' => 'بطاقة هوية وطنية',
                'passport' => 'جواز سفر',
                'employment_letter' => 'خطاب تعريف بالعمل / الراتب',
                'other' => 'عقد / مستند آخر',
                default => $document->document_type,
            },
            'file_size' => $document->file_size ? round($document->file_size / 1024, 1).' كيلوبايت' : null,
            'created_at' => $document->created_at?->format('d/m/Y H:i') ?? '',
            'download_url' => route('renters.documents.download', [$renter->id, $document->id]),
        ])->values()->all();
    }

    private function unitTypeLabel(?string $unitType): string
    {
        return match ($unitType) {
            'apartment' => 'شقة',
            'room' => 'غرفة',
            'bed' => 'سرير',
            'shop' => 'محل',
            default => 'وحدة',
        };
    }
}
