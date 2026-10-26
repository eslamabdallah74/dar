<?php

declare(strict_types=1);

namespace App\Services\Leases;

use App\Enums\SecurityDepositDeductionCategory;
use App\Models\Lease;
use App\Models\RentCharge;
use App\Models\SecurityDepositDeduction;
use App\Models\SecurityDepositPayment;
use App\Models\SecurityDepositRefund;
use App\Models\SecurityDepositSettlement;
use App\Models\Workspace;
use App\Services\Storage\DocumentStorageService;

/**
 * Data of the lease page: the contract, unit and renter, the rent ledger and the security deposit
 * ledger with the deposit actions currently allowed.
 */
class LeaseDetailsService
{
    public function __construct(
        private readonly DocumentStorageService $storage
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Lease $lease, Workspace $workspace): array
    {
        $lease->syncSecurityDepositStatus();

        $lease->load([
            'unit.property',
            'renter',
            'rentCharges.allocations.payment',
            'securityDepositPayments.recordedByUser',
            'securityDepositPayments.reversedByUser',
            'securityDepositSettlement.settledByUser',
            'securityDepositDeductions.recordedByUser',
            'securityDepositRefunds.recordedByUser',
            'securityDepositRefunds.reversedByUser',
        ]);

        return [
            'lease' => $this->leaseSummary($lease),
            'unit' => [
                'id' => $lease->unit?->id,
                'name' => $lease->unit?->name,
                'code' => $lease->unit?->code,
                'unit_type' => $lease->unit?->unit_type,
                'unit_type_label' => match ($lease->unit?->unit_type) {
                    'apartment' => 'شقة',
                    'room' => 'غرفة',
                    'bed' => 'سرير',
                    'shop' => 'محل',
                    default => 'وحدة',
                },
                'property_id' => $lease->unit?->property_id,
                'property_name' => $lease->unit?->property?->name,
                'property_address' => $lease->unit?->property?->address,
                'apartment_gender' => $lease->unit?->getApartmentGenderInfo(),
            ],
            'renter' => [
                'id' => $lease->renter?->id,
                'name' => $lease->renter?->name,
                'phone' => $lease->renter?->phone,
                'national_id' => $lease->renter?->national_id,
                'email' => $lease->renter?->email,
                'gender' => $lease->renter?->gender?->value,
                'gender_label' => $lease->renter?->gender?->label(),
            ],
            'security_deposit' => $this->securityDeposit($lease),
            'rent_charges' => $lease->rentCharges->map(function (RentCharge $charge): array {
                $paidAmount = $charge->paid_amount;
                $outstanding = max(0, (float) $charge->amount - $paidAmount);

                return [
                    'id' => $charge->id,
                    'period' => $charge->period_start->format('m/Y'),
                    'due_date' => $charge->due_date->format('d/m/Y'),
                    'amount' => (float) $charge->amount,
                    'paid_amount' => $paidAmount,
                    'outstanding_balance' => $outstanding,
                    'status' => $outstanding <= 0 ? 'paid' : ($charge->due_date < today() ? 'overdue' : 'pending'),
                ];
            }),
            'currency' => $workspace->currency ?? 'EGP',
            'currency_symbol' => $workspace->currencySymbol(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function leaseSummary(Lease $lease): array
    {
        $isPastEndDate = $lease->is_past_end_date;
        $isExpiringSoon = $lease->is_expiring_soon;

        return [
            'id' => $lease->id,
            'contract_number' => $lease->contract_number,
            'starts_on' => $lease->starts_on->format('d/m/Y'),
            'ends_on' => $lease->ends_on->format('d/m/Y'),
            'raw_starts_on' => $lease->starts_on->toDateString(),
            'raw_ends_on' => $lease->ends_on->toDateString(),
            'rent_amount' => (float) $lease->rent_amount,
            'billing_interval' => match ($lease->billing_interval) {
                'monthly' => 'شهري',
            },
            'status' => $lease->status,
            'status_label' => match ($lease->status) {
                'active' => $isPastEndDate ? 'انتهى موعده (يتطلب إجراء)' : ($isExpiringSoon ? 'ينتهي قريباً' : 'سارٍ'),
                'expired' => 'منتهي',
                'draft' => 'مسودة',
                'terminated' => 'منتهي ومسوّى',
                'cancelled' => 'ملغي',
            },
            'is_past_end_date' => $isPastEndDate,
            'is_expiring_soon' => $isExpiringSoon,
            'notes' => $lease->notes,
            'contract_image_url' => $lease->contract_image ? route('leases.contract-image', $lease) : null,
            'has_contract_image' => ! empty($lease->contract_image),
            'created_at' => $lease->created_at?->format('d/m/Y'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function securityDeposit(Lease $lease): array
    {
        $totalReceived = $lease->total_deposit_received;
        $outstandingCollection = $lease->outstanding_deposit_amount;
        $refundableBalance = $lease->refundable_deposit_balance;
        $settlement = $lease->securityDepositSettlement;
        $depositStatus = $lease->security_deposit_status;

        return [
            'original_deposit' => (float) ($lease->security_deposit_amount > 0 ? $lease->security_deposit_amount : $totalReceived),
            'agreed_amount' => (float) $lease->security_deposit_amount,
            'total_received' => $totalReceived,
            'outstanding_amount' => $outstandingCollection,
            'total_deductions' => $lease->total_deposit_deductions,
            'total_refunded' => $lease->total_deposit_refunded,
            'refundable_balance' => $refundableBalance,
            'categories' => SecurityDepositDeductionCategory::options(),
            'status' => $depositStatus,
            'status_label' => match ($depositStatus) {
                'not_required' => 'غير مطلوب (٠ ج.م)',
                'due' => 'مستحق السداد',
                'partially_received' => 'مدفوع جزئياً',
                'held' => 'محتجز قيد العقد',
                'under_review' => 'قيد فحص الوحدة والتسوية',
                'settled' => 'تمت التسوية نهائياً',
            },
            'payments' => $lease->securityDepositPayments->map(fn (SecurityDepositPayment $payment): array => [
                'id' => $payment->id,
                'amount' => (float) $payment->amount,
                'paid_at' => $payment->paid_at->format('d/m/Y H:i'),
                'payment_method' => $payment->payment_method,
                'payment_method_label' => $this->paymentMethodLabel($payment->payment_method),
                'reference' => $payment->reference ?? 'DEP-'.$payment->id,
                'notes' => $payment->notes,
                'recorded_by' => $payment->recordedByUser->name ?? 'مستخدم النظام',
                'is_reversed' => (bool) $payment->is_reversed,
                'reversal_reason' => $payment->reversal_reason,
                'reversed_at' => $payment->reversed_at?->format('d/m/Y H:i'),
                'reversed_by' => $payment->reversedByUser?->name,
            ]),
            'deductions' => $lease->securityDepositDeductions->map(fn (SecurityDepositDeduction $deduction): array => [
                'id' => $deduction->id,
                'amount' => (float) $deduction->amount,
                'category' => $deduction->category,
                'category_label' => $deduction->category_label,
                'description' => $deduction->description,
                'notes' => $deduction->notes,
                'attachment_url' => $this->storage->publicUrl($deduction->attachment_path),
                'status' => $deduction->status,
                'deducted_at' => $deduction->deducted_at->format('d/m/Y'),
                'recorded_by' => $deduction->recordedByUser->name ?? 'مستخدم النظام',
            ]),
            'refunds' => $lease->securityDepositRefunds->map(fn (SecurityDepositRefund $refund): array => [
                'id' => $refund->id,
                'amount' => (float) $refund->amount,
                'refunded_at' => $refund->refunded_at->format('d/m/Y H:i'),
                'payment_method' => $refund->payment_method,
                'payment_method_label' => $this->paymentMethodLabel($refund->payment_method),
                'reference' => $refund->reference ?? 'REF-'.$refund->id,
                'notes' => $refund->notes,
                'recorded_by' => $refund->recordedByUser->name ?? 'مستخدم النظام',
                'is_reversed' => (bool) $refund->is_reversed,
                'reversal_reason' => $refund->reversal_reason,
            ]),
            'settlement' => $settlement ? $this->settlementSummary($settlement, $lease) : null,
            'actions' => [
                'can_record_payment' => $outstandingCollection > 0 && $depositStatus !== 'settled',
                'can_start_settlement' => $depositStatus !== 'not_required' && $depositStatus !== 'settled',
                'can_record_deduction' => $totalReceived > 0 && $depositStatus !== 'settled' && $refundableBalance > 0,
                'can_record_refund' => $refundableBalance > 0 && in_array($depositStatus, ['under_review', 'held', 'due', 'partially_received'], true),
                'can_settle' => in_array($depositStatus, ['under_review', 'held', 'partially_received', 'due'], true),
                'can_record_pending_refund' => $settlement && $settlement->refund_status === 'pending' && $refundableBalance > 0,
                'can_extend' => $lease->status === 'active',
                'can_terminate' => $lease->status === 'active',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function settlementSummary(SecurityDepositSettlement $settlement, Lease $lease): array
    {
        return [
            'id' => $settlement->id,
            'status' => $settlement->status,
            'settlement_type' => $settlement->settlement_type,
            'settlement_type_label' => $settlement->settlement_type_label,
            'retention_reason' => $settlement->retention_reason,
            'original_deposit' => (float) ($settlement->original_deposit ?? $lease->security_deposit_amount),
            'inspection_category' => $settlement->inspection_category,
            'inspection_category_label' => $settlement->inspection_category_label,
            'inspection_notes' => $settlement->inspection_notes,
            'total_received' => (float) $settlement->total_received,
            'total_deductions' => (float) $settlement->total_deductions,
            'total_refunded' => (float) $settlement->total_refunded,
            'refundable_balance' => (float) $settlement->refundable_balance,
            'refund_status' => $settlement->refund_status ?? 'not_applicable',
            'refund_status_label' => $settlement->refund_status_label,
            'refund_method' => $settlement->refund_method,
            'refund_payment_date' => $settlement->refund_payment_date?->format('d/m/Y'),
            'refund_reference' => $settlement->refund_reference,
            'settled_at' => $settlement->settled_at?->format('d/m/Y H:i'),
            'settled_by' => $settlement->settledByUser?->name,
            'notes' => $settlement->notes,
        ];
    }

    private function paymentMethodLabel(?string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'cash' => 'نقدي',
            'bank_transfer' => 'تحويل بنكي',
            'cheque' => 'شيك',
            default => 'إلكتروني / إنستاباي',
        };
    }
}
