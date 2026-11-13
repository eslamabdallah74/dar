<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DeductionForm from '@/Components/Leases/SecurityDeposit/DeductionForm.vue';
import DeductionSection from '@/Components/Leases/SecurityDeposit/DeductionSection.vue';
import ModalRecordPayment from '@/Components/Common/ModalRecordPayment.vue';
import { useConfirmDialog } from '@/composables/useConfirmDialog';

import LeaseHeader from '@/Components/Leases/Show/LeaseHeader.vue';
import LeaseActionCenter from '@/Components/Leases/Show/LeaseActionCenter.vue';
import LeaseLifecycleStepper from '@/Components/Leases/Show/LeaseLifecycleStepper.vue';
import LeaseKpis from '@/Components/Leases/Show/LeaseKpis.vue';
import LeaseInvoicesSection from '@/Components/Leases/Show/LeaseInvoicesSection.vue';
import LeaseDepositSection from '@/Components/Leases/Show/LeaseDepositSection.vue';
import LeaseSidebar from '@/Components/Leases/Show/LeaseSidebar.vue';

import type {
    DeductionDraft,
    DepositDeduction,
    DepositLedgerEntry,
    DepositPayment,
    DepositRefund,
    LeaseData,
    RentChargeItem,
    RenterData,
    SecurityDepositData,
    UnitData,
} from '@/Components/Leases/Show/types';
import {
    buildActionCenterItems,
    calculateRentTotals,
    calculateTimeline,
} from '@/Components/Leases/Show/useLeaseUtils';

import {
    Ban,
    Clock,
    Coins,
    FileCheck,
    Loader2,
    RotateCcw,
    ShieldAlert,
    ShieldCheck,
    Trash2,
    Upload,
    X,
} from 'lucide-vue-next';

const props = defineProps<{
    lease: LeaseData;
    unit: UnitData;
    renter: RenterData;
    security_deposit: SecurityDepositData;
    rent_charges: RentChargeItem[];
    currency: string;
    currency_symbol: string;
}>();

const confirmDialog = useConfirmDialog();

// Modals State
const showRecordPaymentModal = ref(false);
const showStartSettlementModal = ref(false);
const showRecordDeductionModal = ref(false);
const showRecordRefundModal = ref(false);
const showFinalizeSettlementModal = ref(false);
const showReversePaymentModal = ref(false);
const showExtendModal = ref(false);
const showTerminateWithSettlementModal = ref(false);
const showPendingRefundModal = ref(false);
const showContractUploadModal = ref(false);
const showRentPaymentModal = ref(false);
const rentPaymentCharge = ref<RentChargeItem | null>(null);
const activePaymentForReversal = ref<DepositPayment | null>(null);
const showEditDeductionModal = ref(false);
const editingDeductionId = ref<number | null>(null);

// Forms
const todayDate = new Date().toISOString().split('T')[0];

const extendForm = useForm({
    ends_on: '',
    rent_amount: props.lease.rent_amount,
    notes: '',
});

const terminateForm = useForm<{
    settlement_type: 'full_refund' | 'partial_refund' | 'no_refund';
    inspection_category: string;
    inspection_notes: string;
    retention_reason: string;
    deductions: DeductionDraft[];
    refund_status: 'pending' | 'refunded' | 'not_applicable';
    refund_method: string;
    refund_payment_date: string;
    refund_reference: string;
    notes: string;
}>({
    settlement_type: 'full_refund',
    inspection_category: 'no_issues',
    inspection_notes: '',
    retention_reason: '',
    deductions: [],
    refund_status: 'pending',
    refund_method: 'bank_transfer',
    refund_payment_date: todayDate,
    refund_reference: '',
    notes: '',
});

const pendingRefundForm = useForm({
    amount: props.security_deposit.refundable_balance,
    payment_method: 'bank_transfer',
    refunded_at: todayDate,
    reference: '',
    notes: '',
});

const paymentForm = useForm({
    amount: props.security_deposit.outstanding_amount > 0 ? props.security_deposit.outstanding_amount : props.security_deposit.agreed_amount,
    payment_method: 'cash',
    paid_at: todayDate,
    reference: '',
    notes: '',
});

const settlementForm = useForm({
    inspection_category: 'no_issues',
    inspection_notes: '',
});

const refundForm = useForm({
    amount: props.security_deposit.refundable_balance,
    payment_method: 'bank_transfer',
    refunded_at: todayDate,
    reference: '',
    notes: '',
});

const finalizeSettlementForm = useForm<{
    inspection_category: string;
    inspection_notes: string;
    notes: string;
    deductions: DeductionDraft[];
    refund_status: 'pending' | 'refunded' | 'not_applicable';
    refund_method: string;
    refund_payment_date: string;
    refund_reference: string;
}>({
    inspection_category: props.security_deposit.settlement?.inspection_category || 'no_issues',
    inspection_notes: props.security_deposit.settlement?.inspection_notes || '',
    notes: '',
    deductions: [],
    refund_status: 'pending',
    refund_method: 'bank_transfer',
    refund_payment_date: todayDate,
    refund_reference: '',
});

const reversePaymentForm = useForm({
    reversal_reason: '',
});

const editDeductionForm = useForm<{
    category: string;
    amount: number | string;
    description: string;
    notes: string;
    attachment: File | null;
}>({
    category: 'unit_damage',
    amount: '',
    description: '',
    notes: '',
    attachment: null,
});

const contractUploadForm = useForm({
    contract_image: null as File | null,
});

// Calculations
const isLeaseActive = computed(() => props.lease.status === 'active');

const timeline = computed(() => {
    return calculateTimeline(props.lease.raw_starts_on, props.lease.raw_ends_on, isLeaseActive.value);
});

const rentTotals = computed(() => {
    return calculateRentTotals(props.rent_charges);
});

const actionCenterItems = computed(() => {
    return buildActionCenterItems(
        props.lease,
        rentTotals.value,
        props.rent_charges,
        props.security_deposit,
        timeline.value.remainingDays,
        props.currency_symbol
    );
});

// Rent payment helpers
const rentPaymentRenters = computed(() => [
    {
        id: props.renter.id,
        name: props.renter.name,
        phone: props.renter.phone,
        active_lease: { id: props.lease.id, unit_name: props.unit.name, rent_amount: props.lease.rent_amount },
    },
]);

const rentPaymentInitialAmount = computed(() => {
    if (rentPaymentCharge.value) {
        return rentPaymentCharge.value.outstanding_balance;
    }
    return rentTotals.value.overdue > 0 ? rentTotals.value.overdue : props.lease.rent_amount;
});

const openRentPayment = (charge: RentChargeItem | null = null) => {
    rentPaymentCharge.value = charge;
    showRentPaymentModal.value = true;
};

const closeRentPayment = () => {
    showRentPaymentModal.value = false;
    rentPaymentCharge.value = null;
};

// Settlement calculations
const totalNewDeductions = computed(() => {
    if (terminateForm.settlement_type !== 'partial_refund') return 0;
    return terminateForm.deductions.reduce((sum, d) => sum + (Number(d.amount) || 0), 0);
});

const availableDepositForSettlement = computed(() => {
    const received = Number(props.security_deposit.total_received) || 0;
    const previouslyRefunded = Number(props.security_deposit.total_refunded) || 0;
    const previouslyDeducted = Number(props.security_deposit.total_deductions) || 0;
    return Math.max(0, received - previouslyRefunded - previouslyDeducted);
});

const finalizeAvailableDeposit = computed(() => {
    const received = Number(props.security_deposit.total_received) || 0;
    const previouslyRefunded = Number(props.security_deposit.total_refunded) || 0;
    return Math.max(0, Math.round((received - previouslyRefunded) * 100) / 100);
});

const finalizeTotalDeductions = computed(() => {
    return Math.round(
        finalizeSettlementForm.deductions.reduce((sum, d) => sum + (Number(d.amount) || 0), 0) * 100
    ) / 100;
});

const finalizeRefundDue = computed(() => {
    return Math.max(0, Math.round((finalizeAvailableDeposit.value - finalizeTotalDeductions.value) * 100) / 100);
});

const finalizeRetainedAmount = computed(() => {
    return finalizeTotalDeductions.value;
});

const finalizeExceedsBalance = computed(() => {
    return finalizeTotalDeductions.value > finalizeAvailableDeposit.value;
});

const finalizeCalculatedOutcome = computed(() => {
    if (finalizeTotalDeductions.value <= 0) {
        return {
            type: 'full_refund' as const,
            title: 'رد كامل مبلغ التأمين (Full Refund)',
            description: `لا توجد خصومات؛ سيتم صرف كامل الرصيد المتاح (${finalizeAvailableDeposit.value.toLocaleString()} ${props.currency_symbol}) للمستأجر.`,
            badgeClass: 'bg-emerald-50 text-emerald-800 border-emerald-300',
        };
    }
    if (finalizeRefundDue.value > 0) {
        return {
            type: 'partial_refund' as const,
            title: 'رد جزئي مع تطبيق خصومات (Partial Refund)',
            description: `سيتم احتجاز ${finalizeTotalDeductions.value.toLocaleString()} ${props.currency_symbol} للمالك، ورد ${finalizeRefundDue.value.toLocaleString()} ${props.currency_symbol} للمستأجر.`,
            badgeClass: 'bg-amber-50 text-amber-800 border-amber-300',
        };
    }
    return {
        type: 'no_refund' as const,
        title: 'احتجاز كامل التأمين (No Refund)',
        description: `تغطي الخصومات كامل رصيد التأمين (${finalizeTotalDeductions.value.toLocaleString()} ${props.currency_symbol})؛ لن يتم رد أي مبالغ للمستأجر.`,
        badgeClass: 'bg-rose-50 text-rose-800 border-rose-300',
    };
});

const calculatedRefundDue = computed(() => {
    if (terminateForm.settlement_type === 'no_refund') {
        return 0;
    }
    if (terminateForm.settlement_type === 'full_refund') {
        return availableDepositForSettlement.value;
    }
    return Math.max(0, availableDepositForSettlement.value - totalNewDeductions.value);
});

// Deposit Ledger Construction
const toSortableDate = (displayDate: string): string => {
    const [datePart = '', timePart = '00:00'] = displayDate.split(' ');
    const [day = '', month = '', year = ''] = datePart.split('/');
    return `${year}-${month}-${day} ${timePart}`;
};

const depositLedgerKindOrder: Record<'payment' | 'deduction' | 'refund', number> = { payment: 0, deduction: 1, refund: 2 };

const depositLedger = computed<DepositLedgerEntry[]>(() => {
    const deposit = props.security_deposit;
    const entries: DepositLedgerEntry[] = [
        ...deposit.payments.map((payment: DepositPayment) => ({
            key: `payment-${payment.id}`,
            kind: 'payment' as const,
            title: 'استلام دفعة تأمين',
            occurredAt: payment.paid_at,
            amount: payment.amount,
            method: payment.payment_method_label,
            reference: payment.reference,
            recordedBy: payment.recorded_by,
            description: null,
            notes: payment.notes,
            attachmentUrl: null,
            isReversed: payment.is_reversed,
            reversalReason: payment.reversal_reason,
            payment,
            deduction: null,
        })),
        ...deposit.deductions
            .filter((deduction: DepositDeduction) => deduction.status === 'confirmed')
            .map((deduction: DepositDeduction) => ({
                key: `deduction-${deduction.id}`,
                kind: 'deduction' as const,
                title: `خصم: ${deduction.category_label}`,
                occurredAt: deduction.deducted_at,
                amount: deduction.amount,
                method: null,
                reference: null,
                recordedBy: deduction.recorded_by,
                description: deduction.description,
                notes: deduction.notes ?? null,
                attachmentUrl: deduction.attachment_url ?? null,
                isReversed: false,
                reversalReason: null,
                payment: null,
                deduction,
            })),
        ...deposit.refunds.map((refund: DepositRefund) => ({
            key: `refund-${refund.id}`,
            kind: 'refund' as const,
            title: 'رد للمستأجر',
            occurredAt: refund.refunded_at,
            amount: refund.amount,
            method: refund.payment_method_label,
            reference: refund.reference,
            recordedBy: refund.recorded_by,
            description: null,
            notes: refund.notes,
            attachmentUrl: null,
            isReversed: refund.is_reversed,
            reversalReason: refund.reversal_reason,
            payment: null,
            deduction: null,
        })),
    ];

    return entries.sort(
        (first, second) =>
            toSortableDate(first.occurredAt).localeCompare(toSortableDate(second.occurredAt)) ||
            depositLedgerKindOrder[first.kind] - depositLedgerKindOrder[second.kind]
    );
});

// Handlers
const openTerminateWorkflow = () => {
    const hasDeposit = (props.security_deposit.agreed_amount > 0 || props.security_deposit.total_received > 0);
    if (hasDeposit) {
        terminateForm.reset();
        terminateForm.settlement_type = 'full_refund';
        terminateForm.refund_status = 'pending';
        terminateForm.deductions = [];
        showTerminateWithSettlementModal.value = true;
    } else {
        void terminateLeaseWithoutDeposit();
    }
};

const terminateLeaseWithoutDeposit = async () => {
    const confirmed = await confirmDialog.confirm({
        tone: 'danger',
        icon: Ban,
        title: 'إنهاء هذا العقد؟',
        message: 'سيُغلق العقد رسمياً وتصبح الوحدة شاغرة ومتاحة للتأجير من جديد.',
        details: [
            { label: 'العقد', value: props.lease.contract_number },
            { label: 'المستأجر', value: props.renter.name },
            { label: 'الوحدة', value: props.unit.name },
        ],
        confirmLabel: 'إنهاء العقد',
    });

    if (confirmed) {
        router.post(route('leases.terminate', props.lease.id));
    }
};

const submitTerminateWithSettlement = () => {
    terminateForm.post(route('leases.terminate', props.lease.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showTerminateWithSettlementModal.value = false;
        },
    });
};

const submitExtend = () => {
    extendForm.post(route('leases.extend', props.lease.id), {
        onSuccess: () => {
            showExtendModal.value = false;
            extendForm.reset();
        },
    });
};

const submitPendingRefund = () => {
    pendingRefundForm.post(route('leases.security-deposit.pending-refund', props.lease.id), {
        onSuccess: () => {
            showPendingRefundModal.value = false;
            pendingRefundForm.reset();
        },
    });
};

const submitPayment = () => {
    paymentForm.post(route('leases.security-deposit.payment', props.lease.id), {
        onSuccess: () => {
            paymentForm.reset();
            showRecordPaymentModal.value = false;
        },
    });
};

const submitStartSettlement = () => {
    settlementForm.post(route('leases.security-deposit.start-settlement', props.lease.id), {
        onSuccess: () => {
            showStartSettlementModal.value = false;
        },
    });
};

const deleteDeduction = async (deduction: DepositDeduction) => {
    const confirmed = await confirmDialog.confirm({
        tone: 'danger',
        icon: Trash2,
        title: 'حذف هذا الخصم؟',
        message: 'يُحذف الخصم من سجل التأمين ويعود مبلغه إلى الرصيد القابل للرد للمستأجر.',
        details: [
            { label: 'البند', value: deduction.category_label },
            { label: 'المبلغ', value: `${deduction.amount.toLocaleString()} ${props.currency_symbol}` },
        ],
        confirmLabel: 'حذف الخصم',
    });

    if (confirmed) {
        router.delete(route('leases.security-deposit.deduction.delete', [props.lease.id, deduction.id]));
    }
};

const submitRefund = () => {
    refundForm.post(route('leases.security-deposit.refund', props.lease.id), {
        onSuccess: () => {
            refundForm.reset();
            showRecordRefundModal.value = false;
        },
    });
};

const openFinalizeSettlementModal = () => {
    finalizeSettlementForm.reset();
    finalizeSettlementForm.inspection_category = props.security_deposit.settlement?.inspection_category || 'no_issues';
    finalizeSettlementForm.inspection_notes = props.security_deposit.settlement?.inspection_notes || '';
    finalizeSettlementForm.notes = '';
    finalizeSettlementForm.refund_status = 'pending';
    finalizeSettlementForm.refund_method = 'bank_transfer';
    finalizeSettlementForm.refund_payment_date = todayDate;
    finalizeSettlementForm.refund_reference = '';

    if (props.security_deposit.deductions && props.security_deposit.deductions.length > 0) {
        finalizeSettlementForm.deductions = props.security_deposit.deductions.map((d: DepositDeduction) => ({
            id: d.id,
            category: d.category,
            description: d.description,
            amount: d.amount,
            notes: d.notes || '',
            attachment: null,
            attachment_url: d.attachment_url || null,
        }));
    } else {
        finalizeSettlementForm.deductions = [];
    }

    showFinalizeSettlementModal.value = true;
};

const submitFinalizeSettlement = () => {
    finalizeSettlementForm.post(route('leases.security-deposit.settle', props.lease.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showFinalizeSettlementModal.value = false;
        },
    });
};

const openReversePayment = (payment: DepositPayment) => {
    activePaymentForReversal.value = payment;
    reversePaymentForm.reset();
    showReversePaymentModal.value = true;
};

const submitReversePayment = () => {
    if (!activePaymentForReversal.value) return;
    reversePaymentForm.post(route('leases.security-deposit.payment.reverse', [props.lease.id, activePaymentForReversal.value.id]), {
        onSuccess: () => {
            showReversePaymentModal.value = false;
            activePaymentForReversal.value = null;
        },
    });
};

const openEditDeductionModal = (deduction: DepositDeduction) => {
    editingDeductionId.value = deduction.id;
    editDeductionForm.category = deduction.category;
    editDeductionForm.amount = deduction.amount;
    editDeductionForm.description = deduction.description;
    editDeductionForm.notes = deduction.notes || '';
    editDeductionForm.attachment = null;
    showEditDeductionModal.value = true;
};

const submitEditDeduction = () => {
    if (!editingDeductionId.value) return;
    editDeductionForm.post(route('leases.security-deposit.deductions.update', [props.lease.id, editingDeductionId.value]), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showEditDeductionModal.value = false;
            editingDeductionId.value = null;
        },
    });
};

const onShowContractFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        contractUploadForm.contract_image = target.files[0];
    }
};

const submitContractUpload = () => {
    if (!contractUploadForm.contract_image) return;
    contractUploadForm.post(route('leases.contract-image.store', props.lease.id), {
        onSuccess: () => {
            contractUploadForm.reset();
            showContractUploadModal.value = false;
        },
    });
};

const deleteContractImage = async () => {
    const confirmed = await confirmDialog.confirm({
        tone: 'danger',
        icon: Trash2,
        title: 'حذف نسخة العقد الموقّعة؟',
        message: 'يُحذف الملف المرفوع نهائياً، ويمكنك رفع نسخة جديدة في أي وقت.',
        confirmLabel: 'حذف النسخة',
    });

    if (confirmed) {
        router.delete(route('leases.contract-image.destroy', props.lease.id));
    }
};

const handleActionCenterAction = (actionKey: string, meta?: any) => {
    switch (actionKey) {
        case 'record-rent-payment':
            openRentPayment(null);
            break;
        case 'record-charge-payment':
            openRentPayment(meta?.charge || null);
            break;
        case 'extend-lease':
            showExtendModal.value = true;
            break;
        case 'finalize-settlement':
            openFinalizeSettlementModal();
            break;
        case 'pending-refund':
            showPendingRefundModal.value = true;
            break;
        case 'record-deposit-payment':
            paymentForm.amount = props.security_deposit.outstanding_amount > 0 ? props.security_deposit.outstanding_amount : props.security_deposit.agreed_amount;
            showRecordPaymentModal.value = true;
            break;
        default:
            break;
    }
};
</script>

<template>
    <AppLayout
        :title="`عقد ${lease.contract_number} — ${unit.name} — Nook Mate`"
        active-tab="leases"
        :breadcrumb="`العقود / ${lease.contract_number}`"
    >
        <div class="space-y-6">
            <!-- 1. الـ Header الذكي الموحد -->
            <LeaseHeader
                :lease="lease"
                :unit="unit"
                :renter="renter"
                :rent-totals="rentTotals"
                :security-deposit="security_deposit"
                :currency-symbol="currency_symbol"
                @open-rent-payment="openRentPayment"
                @open-extend="showExtendModal = true"
                @open-terminate="openTerminateWorkflow"
                @open-upload-contract="showContractUploadModal = true"
                @delete-contract-image="deleteContractImage"
                @open-finalize-settlement="openFinalizeSettlementModal"
                @open-pending-refund="showPendingRefundModal = true"
            />

            <!-- 2. مركز الإجراءات (محتاج انتباهك) -->
            <LeaseActionCenter
                :items="actionCenterItems"
                @action="handleActionCenterAction"
            />

            <!-- 3. مسار دورة حياة العقد (Lifecycle Stepper) -->
            <LeaseLifecycleStepper
                :lease="lease"
                :security-deposit="security_deposit"
                :remaining-days="timeline.remainingDays"
            />

            <!-- 4. مؤشرات الأداء الرئيسية (3 كروت KPIs بدل 4) -->
            <LeaseKpis
                :lease="lease"
                :rent-totals="rentTotals"
                :rent-charges="rent_charges"
                :security-deposit="security_deposit"
                :currency-symbol="currency_symbol"
            />

            <!-- 5. التخطيط الرئيسي: الفواتير والتأمين على اليمين، والبيانات الجانبية على اليسار -->
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <!-- الفواتير والدفعات (مستحقة وقادمة مفتوحة / مدفوعة مطوية) -->
                    <LeaseInvoicesSection
                        :charges="rent_charges"
                        :currency-symbol="currency_symbol"
                        :is-lease-active="isLeaseActive"
                        @record-payment="openRentPayment"
                    />

                    <!-- التأمين وتسوية الوحدة (Accordion متدرج) -->
                    <LeaseDepositSection
                        :lease="lease"
                        :security-deposit="security_deposit"
                        :currency-symbol="currency_symbol"
                        :deposit-ledger="depositLedger"
                        @record-payment="showRecordPaymentModal = true"
                        @start-settlement="showStartSettlementModal = true"
                        @record-deduction="showRecordDeductionModal = true"
                        @record-refund="showRecordRefundModal = true"
                        @finalize-settlement="openFinalizeSettlementModal"
                        @pending-refund="showPendingRefundModal = true"
                        @reverse-payment="openReversePayment"
                        @edit-deduction="openEditDeductionModal"
                        @delete-deduction="deleteDeduction"
                    />
                </div>

                <!-- الشريط الجانبي (المستأجر، الوحدة، المستندات الموقعة) -->
                <LeaseSidebar
                    :lease="lease"
                    :renter="renter"
                    :unit="unit"
                    :currency-symbol="currency_symbol"
                    @open-upload-contract="showContractUploadModal = true"
                    @delete-contract-image="deleteContractImage"
                />
            </div>
        </div>

        <!-- مودال 1: تسجيل استلام دفعة تأمين -->
        <div v-if="showRecordPaymentModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showRecordPaymentModal = false" class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-5 sm:p-7 z-10 text-start animate-in fade-in zoom-in-95" >
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7] mb-4">
                    <h3 class="text-sm font-bold text-[#163E3E]">تسجيل استلام دفعة تأمين</h3>
                    <button @click="showRecordPaymentModal = false" type="button" aria-label="إغلاق النافذة" class="p-1 text-slate-400 hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitPayment" class="space-y-4">
                    <div>
                        <label for="deposit_payment_amount" class="block text-xs font-bold text-[#163E3E] mb-1">المبلغ المحصل *</label>
                        <div class="relative">
                            <input
                                id="deposit_payment_amount"
                                v-model="paymentForm.amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                :max="security_deposit.outstanding_amount"
                                required
                                aria-label="المبلغ المحصل للتأمين"
                                class="w-full h-11 pl-12 pr-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm font-mono font-bold"
                            />
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-[#5B6770] font-semibold">
                                {{ currency_symbol }}
                            </span>
                        </div>
                        <p class="text-[11px] text-[#5B6770] mt-1">
                            الحد الأقصى المسموح بسداده: {{ security_deposit.outstanding_amount.toLocaleString() }} {{ currency_symbol }}
                        </p>
                        <p v-if="paymentForm.errors.amount" class="text-xs text-rose-600 font-semibold mt-1">
                            {{ paymentForm.errors.amount }}
                        </p>
                    </div>

                    <div>
                        <label for="deposit_payment_method" class="block text-xs font-bold text-[#163E3E] mb-1">طريقة السداد *</label>
                        <select id="deposit_payment_method" aria-label="طريقة السداد" v-model="paymentForm.payment_method" class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs">
                            <option value="cash">نقدي (كاش)</option>
                            <option value="bank_transfer">تحويل بنكي</option>
                            <option value="cheque">شيك مصرفي</option>
                            <option value="other">إنستاباي / محفظة إلكترونية / أخرى</option>
                        </select>
                    </div>

                    <div>
                        <label for="deposit_paid_at" class="block text-xs font-bold text-[#163E3E] mb-1">تاريخ التحصيل *</label>
                        <input id="deposit_paid_at" aria-label="تاريخ التحصيل" v-model="paymentForm.paid_at" type="date" required class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-start" />
                    </div>

                    <div>
                        <label for="deposit_reference" class="block text-xs font-bold text-[#163E3E] mb-1">رقم المرجع / الإيصال</label>
                        <input id="deposit_reference" aria-label="رقم المرجع أو الإيصال" v-model="paymentForm.reference" type="text" placeholder="مثال: REC-10293" class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-start" />
                    </div>

                    <div>
                        <label for="deposit_notes" class="block text-xs font-bold text-[#163E3E] mb-1">ملاحظات إضافية</label>
                        <textarea id="deposit_notes" aria-label="ملاحظات إضافية" v-model="paymentForm.notes" rows="2" class="w-full p-2.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-start"></textarea>
                    </div>

                    <div class="pt-3 border-t border-[#D6E0E7] flex justify-end gap-2">
                        <button :disabled="paymentForm.processing" @click="showRecordPaymentModal = false" type="button" class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold disabled:opacity-50 cursor-pointer">إلغاء</button>
                        <button :disabled="paymentForm.processing" type="submit" class="h-10 px-4 rounded-xl bg-[#163E3E] text-white text-xs font-bold disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5 cursor-pointer">
                            <Loader2 v-if="paymentForm.processing" class="w-3.5 h-3.5 animate-spin" />
                            <span>{{ paymentForm.processing ? 'جارٍ التسجيل...' : 'تأكيد تسجيل الدفعة' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- مودال 2: بدء فحص الوحدة والتسوية -->
        <div v-if="showStartSettlementModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showStartSettlementModal = false" class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-5 sm:p-7 z-10 text-start animate-in fade-in zoom-in-95" >
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7] mb-4">
                    <h3 class="text-sm font-bold text-[#163E3E]">بدء فحص حالة الوحدة والتسوية</h3>
                    <button @click="showStartSettlementModal = false" type="button" aria-label="إغلاق النافذة" class="p-1 text-slate-400 hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitStartSettlement" class="space-y-4">
                    <div>
                        <label for="settlement_inspection_category" class="block text-xs font-bold text-[#163E3E] mb-1">نتيجة فحص الوحدة *</label>
                        <select id="settlement_inspection_category" aria-label="نتيجة فحص الوحدة" v-model="settlementForm.inspection_category" class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs">
                            <option value="no_issues">لا توجد ملاحظات أو أضرار (حالة ممتازة)</option>
                            <option value="furniture_damage">تلف أثاث أو أجهزة</option>
                            <option value="fixture_damage">تلف تركيبات ومرافق</option>
                            <option value="missing_items">مفقودات ومحتويات ناقصة</option>
                            <option value="other_damage">أضرار موثقة أخرى</option>
                        </select>
                    </div>

                    <div>
                        <label for="settlement_inspection_notes" class="block text-xs font-bold text-[#163E3E] mb-1">ملاحظات وتقرير الفحص الميداني</label>
                        <textarea id="settlement_inspection_notes" aria-label="ملاحظات وتقرير الفحص الميداني" v-model="settlementForm.inspection_notes" rows="3" placeholder="وصف حالة الوحدة عند التسليم، الجدران، الأرضيات، التركيبات..." class="w-full p-2.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-start"></textarea>
                    </div>

                    <div class="pt-3 border-t border-[#D6E0E7] flex justify-end gap-2">
                        <button :disabled="settlementForm.processing" @click="showStartSettlementModal = false" type="button" class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold disabled:opacity-50 cursor-pointer">إلغاء</button>
                        <button :disabled="settlementForm.processing" type="submit" class="h-10 px-4 rounded-xl bg-[#163E3E] text-white text-xs font-bold disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5 cursor-pointer">
                            <Loader2 v-if="settlementForm.processing" class="w-3.5 h-3.5 animate-spin" />
                            <span>{{ settlementForm.processing ? 'جارٍ البدء...' : 'بدء التسوية' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- DeductionForm Component -->
        <DeductionForm
            v-model:show="showRecordDeductionModal"
            :lease-id="lease.id"
            :available-balance="security_deposit.refundable_balance"
            :currency-symbol="currency_symbol"
        />

        <!-- مودال 4: تسجيل رد التأمين -->
        <div v-if="showRecordRefundModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showRecordRefundModal = false" class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-5 sm:p-7 z-10 text-start animate-in fade-in zoom-in-95" >
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7] mb-4">
                    <h3 class="text-sm font-bold text-[#163E3E]">تسجيل رد مبلغ التأمين للمستأجر</h3>
                    <button @click="showRecordRefundModal = false" type="button" aria-label="إغلاق النافذة" class="p-1 text-slate-400 hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div v-if="refundForm.errors.amount" class="p-3 mb-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs">
                    {{ refundForm.errors.amount }}
                </div>

                <form @submit.prevent="submitRefund" class="space-y-4">
                    <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-200 text-xs text-emerald-900">
                        الرصيد المتاح للرد: <strong>{{ security_deposit.refundable_balance.toLocaleString() }} {{ currency_symbol }}</strong>
                        <p class="text-[11px] text-emerald-800 mt-1">
                            ملاحظة: تسجيل الرد هنا لتوثيق العملية في النظام بعد قيامك بالتحويل الفعلي للمستأجر.
                        </p>
                    </div>

                    <div>
                        <label for="refund_amount" class="block text-xs font-bold text-[#163E3E] mb-1">المبلغ المردود *</label>
                        <div class="relative">
                            <input
                                id="refund_amount"
                                aria-label="المبلغ المردود"
                                v-model="refundForm.amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                :max="security_deposit.refundable_balance"
                                required
                                class="w-full h-11 pl-12 pr-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm font-mono font-bold"
                            />
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-[#5B6770] font-semibold">
                                {{ currency_symbol }}
                            </span>
                        </div>
                    </div>

                    <div>
                        <label for="refund_payment_method" class="block text-xs font-bold text-[#163E3E] mb-1">طريقة الرد *</label>
                        <select id="refund_payment_method" aria-label="طريقة الرد" v-model="refundForm.payment_method" class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs">
                            <option value="bank_transfer">تحويل بنكي</option>
                            <option value="cash">نقدي (كاش)</option>
                            <option value="cheque">شيك</option>
                            <option value="other">إنستاباي / محفظة إلكترونية</option>
                        </select>
                    </div>

                    <div>
                        <label for="refund_refunded_at" class="block text-xs font-bold text-[#163E3E] mb-1">تاريخ الرد الفعلي *</label>
                        <input id="refund_refunded_at" aria-label="تاريخ الرد الفعلي" v-model="refundForm.refunded_at" type="date" required class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-start" />
                    </div>

                    <div>
                        <label for="refund_reference" class="block text-xs font-bold text-[#163E3E] mb-1">رقم مرجع التحويل / الإيصال</label>
                        <input id="refund_reference" aria-label="رقم مرجع التحويل أو الإيصال" v-model="refundForm.reference" type="text" placeholder="مثال: TXN-54321" class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-start" />
                    </div>

                    <div>
                        <label for="refund_notes" class="block text-xs font-bold text-[#163E3E] mb-1">ملاحظات الرد</label>
                        <textarea id="refund_notes" aria-label="ملاحظات الرد" v-model="refundForm.notes" rows="2" class="w-full p-2.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-start"></textarea>
                    </div>

                    <div class="pt-3 border-t border-[#D6E0E7] flex justify-end gap-2">
                        <button :disabled="refundForm.processing" @click="showRecordRefundModal = false" type="button" class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold disabled:opacity-50 cursor-pointer">إلغاء</button>
                        <button :disabled="refundForm.processing" type="submit" class="h-10 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5 cursor-pointer">
                            <Loader2 v-if="refundForm.processing" class="w-3.5 h-3.5 animate-spin" />
                            <span>{{ refundForm.processing ? 'جارٍ التوثيق...' : 'توثيق الرد الفعلي' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- مودال 5: اعتماد التسوية النهائية للتأمين -->
        <div v-if="showFinalizeSettlementModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showFinalizeSettlementModal = false" class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-4xl bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-5 sm:p-7 z-10 text-start max-h-[92vh] overflow-y-auto animate-in fade-in zoom-in-95" >
                <!-- Header -->
                <div class="flex items-center justify-between pb-3.5 border-b border-[#D6E0E7] mb-5">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-[#E8F0F0] text-[#163E3E] flex items-center justify-center">
                            <FileCheck class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-[#163E3E]">اعتماد وإغلاق تسوية التأمين</h3>
                            <p class="text-xs text-[#5B6770]">مراجعة الخصومات، احتساب المستحق رده تلقائياً، وتأكيد التسوية</p>
                        </div>
                    </div>
                    <button @click="showFinalizeSettlementModal = false" type="button" aria-label="إغلاق النافذة" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-xl hover:bg-slate-100 transition">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Errors -->
                <div v-if="Object.keys(finalizeSettlementForm.errors).length > 0" class="p-3.5 mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1">
                    <div v-for="(err, k) in finalizeSettlementForm.errors" :key="k">{{ err }}</div>
                </div>

                <form @submit.prevent="submitFinalizeSettlement" class="space-y-5">
                    <!-- قسم خصومات التأمين -->
                    <DeductionSection
                        v-model="finalizeSettlementForm.deductions"
                        :available-deposit="finalizeAvailableDeposit"
                        :currency-symbol="currency_symbol"
                        :errors="finalizeSettlementForm.errors"
                    />

                    <!-- ملخص الحساب المالي والقرار النهائي -->
                    <div class="rounded-2xl border p-4 text-xs space-y-3" :class="finalizeCalculatedOutcome.badgeClass">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-sm text-[#163E3E] flex items-center gap-2">
                                <Coins class="w-4 h-4" />
                                <span>{{ finalizeCalculatedOutcome.title }}</span>
                            </span>
                            <span class="font-mono font-bold text-xs">
                                الرصيد الأصلي المتاح: {{ finalizeAvailableDeposit.toLocaleString() }} {{ currency_symbol }}
                            </span>
                        </div>
                        <p class="text-xs leading-relaxed text-[#163E3E]">
                            {{ finalizeCalculatedOutcome.description }}
                        </p>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-2 border-t border-black/10">
                            <div class="p-2.5 bg-white/80 rounded-xl">
                                <span class="text-[10px] text-[#5B6770] block">التأمين المستلم:</span>
                                <strong class="text-xs font-mono font-bold text-[#163E3E] block mt-0.5">
                                    {{ security_deposit.total_received.toLocaleString() }} {{ currency_symbol }}
                                </strong>
                            </div>
                            <div class="p-2.5 bg-white/80 rounded-xl">
                                <span class="text-[10px] text-rose-700 block font-semibold">إجمالي الخصومات:</span>
                                <strong class="text-xs font-mono font-bold text-rose-700 block mt-0.5">
                                    {{ finalizeTotalDeductions.toLocaleString() }} {{ currency_symbol }}
                                </strong>
                            </div>
                            <div class="p-2.5 bg-white/80 rounded-xl">
                                <span class="text-[10px] text-amber-800 block font-semibold">المحتفظ به للمالك:</span>
                                <strong class="text-xs font-mono font-bold text-amber-700 block mt-0.5">
                                    {{ finalizeRetainedAmount.toLocaleString() }} {{ currency_symbol }}
                                </strong>
                            </div>
                            <div class="p-2.5 bg-white/80 rounded-xl">
                                <span class="text-[10px] text-emerald-800 block font-bold">المستحق رده:</span>
                                <strong class="text-xs font-mono font-bold text-emerald-700 block mt-0.5">
                                    {{ finalizeRefundDue.toLocaleString() }} {{ currency_symbol }}
                                </strong>
                            </div>
                        </div>
                    </div>

                    <!-- حالة وتوثيق الرد الفعلي إذا كان هناك مبلغ مستحق رده -->
                    <div v-if="finalizeRefundDue > 0" class="space-y-3 p-4 bg-emerald-50/50 rounded-2xl border border-emerald-200">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-emerald-950">
                                توثيق الرد الفعلي للمبلغ المستحق ({{ finalizeRefundDue.toLocaleString() }} {{ currency_symbol }})
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <label
                                :class="[
                                    'p-3 rounded-xl border cursor-pointer text-start flex items-center justify-between transition',
                                    finalizeSettlementForm.refund_status === 'pending'
                                        ? 'border-amber-500 bg-white ring-2 ring-amber-500/20'
                                        : 'border-emerald-200 bg-white/70 hover:bg-white'
                                ]"
                            >
                                <div class="space-y-0.5">
                                    <span class="text-xs font-bold text-[#163E3E] block">لم يُرد بعد (معلق)</span>
                                    <span class="text-[10px] text-[#5B6770] block">حفظ التسوية وتوثيق التحويل لاحقاً بعد التسليم الفعلي</span>
                                </div>
                                <input
                                    type="radio"
                                    value="pending"
                                    v-model="finalizeSettlementForm.refund_status"
                                    class="text-amber-600 focus:ring-amber-500"
                                />
                            </label>

                            <label
                                :class="[
                                    'p-3 rounded-xl border cursor-pointer text-start flex items-center justify-between transition',
                                    finalizeSettlementForm.refund_status === 'refunded'
                                        ? 'border-emerald-600 bg-white ring-2 ring-emerald-600/20'
                                        : 'border-emerald-200 bg-white/70 hover:bg-white'
                                ]"
                            >
                                <div class="space-y-0.5">
                                    <span class="text-xs font-bold text-emerald-900 block">تم الرد الفعلي الآن</span>
                                    <span class="text-[10px] text-[#5B6770] block">تم تحويل أو تسليم المبلغ بالفعل للمستأجر</span>
                                </div>
                                <input
                                    type="radio"
                                    value="refunded"
                                    v-model="finalizeSettlementForm.refund_status"
                                    class="text-emerald-600 focus:ring-emerald-500"
                                />
                            </label>
                        </div>

                        <!-- تفاصيل الدفع الفعلي -->
                        <div v-if="finalizeSettlementForm.refund_status === 'refunded'" class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 pt-2 border-t border-emerald-200/60">
                            <div>
                                <label for="finalize_refund_method" class="block text-[11px] font-bold text-emerald-950 mb-1">طريقة الرد *</label>
                                <select id="finalize_refund_method" aria-label="طريقة الرد" v-model="finalizeSettlementForm.refund_method" class="w-full h-10 px-2.5 rounded-xl border border-emerald-200 bg-white text-xs">
                                    <option value="bank_transfer">تحويل بنكي</option>
                                    <option value="cash">نقدي (كاش)</option>
                                    <option value="cheque">شيك</option>
                                    <option value="other">إنستاباي / أخرى</option>
                                </select>
                            </div>
                            <div>
                                <label for="finalize_refund_payment_date" class="block text-[11px] font-bold text-emerald-950 mb-1">تاريخ الرد الفعلي *</label>
                                <input id="finalize_refund_payment_date" aria-label="تاريخ الرد الفعلي" v-model="finalizeSettlementForm.refund_payment_date" type="date" required class="w-full h-10 px-2.5 rounded-xl border border-emerald-200 bg-white text-xs text-start font-mono" />
                            </div>
                            <div>
                                <label for="finalize_refund_reference" class="block text-[11px] font-semibold text-emerald-950 mb-1">رقم المرجع / الإيصال</label>
                                <input id="finalize_refund_reference" aria-label="رقم المرجع أو الإيصال" v-model="finalizeSettlementForm.refund_reference" type="text" placeholder="TXN-..." class="w-full h-10 px-2.5 rounded-xl border border-emerald-200 bg-white text-xs text-start font-mono" />
                            </div>
                        </div>
                    </div>

                    <!-- تقرير فحص الوحدة وملاحظات التسوية -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-[#D6E0E7]">
                        <div>
                            <label for="finalize_inspection_category" class="block text-xs font-bold text-[#163E3E] mb-1">التقرير النهائي لفحص الوحدة *</label>
                            <select id="finalize_inspection_category" aria-label="التقرير النهائي لفحص الوحدة" v-model="finalizeSettlementForm.inspection_category" class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs">
                                <option value="no_issues">لا توجد ملاحظات أو أضرار (حالة سليمة)</option>
                                <option value="furniture_damage">تلف أثاث أو أجهزة</option>
                                <option value="fixture_damage">تلف تركيبات ومرافق</option>
                                <option value="missing_items">مفقودات ومحتويات ناقصة</option>
                                <option value="other_damage">أضرار موثقة أخرى</option>
                            </select>
                        </div>
                        <div>
                            <label for="finalize_notes" class="block text-xs font-bold text-[#163E3E] mb-1">ملاحظات الإغلاق النهائي للتسوية</label>
                            <input id="finalize_notes" aria-label="ملاحظات الإغلاق النهائي للتسوية" v-model="finalizeSettlementForm.notes" type="text" placeholder="أي ملاحظات ختامية للتسوية..." class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-start" />
                        </div>
                    </div>

                    <!-- أزرار الإجراءات -->
                    <div class="pt-4 border-t border-[#D6E0E7] flex justify-end gap-2.5">
                        <button @click="showFinalizeSettlementModal = false" type="button" class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold hover:bg-slate-50 cursor-pointer">
                            إلغاء
                        </button>
                        <button
                            type="submit"
                            :disabled="finalizeSettlementForm.processing || finalizeExceedsBalance"
                            class="h-10 px-5 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold transition flex items-center gap-1.5 shadow-xs disabled:opacity-50 cursor-pointer"
                        >
                            <FileCheck class="w-4 h-4" />
                            <span>تأكيد واعتماد التسوية النهائية</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- مودال تعديل بند خصم -->
        <div v-if="showEditDeductionModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showEditDeductionModal = false" class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-lg bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-5 sm:p-7 z-10 text-start animate-in fade-in zoom-in-95" >
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7] mb-4">
                    <h3 class="text-sm font-bold text-[#163E3E]">تعديل بند الخصم</h3>
                    <button @click="showEditDeductionModal = false" type="button" aria-label="إغلاق النافذة" class="p-1 text-slate-400 hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div v-if="Object.keys(editDeductionForm.errors).length > 0" class="p-3 mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1">
                    <div v-for="(err, k) in editDeductionForm.errors" :key="k">{{ err }}</div>
                </div>

                <form @submit.prevent="submitEditDeduction" class="space-y-4">
                    <div>
                        <label for="edit_deduction_category" class="block text-xs font-bold text-[#163E3E] mb-1">سبب / تصنيف الخصم *</label>
                        <select id="edit_deduction_category" aria-label="سبب وتصنيف الخصم" v-model="editDeductionForm.category" required class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs">
                            <option value="unit_damage">تلفيات في الوحدة</option>
                            <option value="maintenance">إصلاحات وصيانة</option>
                            <option value="unpaid_dues">مستحقات غير مدفوعة</option>
                            <option value="cleaning">تنظيف الوحدة</option>
                            <option value="other">أخرى</option>
                        </select>
                    </div>

                    <div>
                        <label for="edit_deduction_amount" class="block text-xs font-bold text-[#163E3E] mb-1">مبلغ الخصم (بالجنيه المصري) *</label>
                        <div class="relative">
                            <input
                                id="edit_deduction_amount"
                                aria-label="مبلغ الخصم"
                                v-model="editDeductionForm.amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                required
                                class="w-full h-11 pl-12 pr-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm font-mono font-bold"
                            />
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-[#5B6770] font-semibold">{{ currency_symbol }}</span>
                        </div>
                    </div>

                    <div>
                        <label for="edit_deduction_description" class="block text-xs font-bold text-[#163E3E] mb-1">
                            {{ editDeductionForm.category === 'other' ? 'توضيح سبب الخصم (مطلوب عند اختيار أخرى) *' : 'بيان ومبرر الخصم *' }}
                        </label>
                        <input
                            id="edit_deduction_description"
                            aria-label="بيان ومبرر الخصم"
                            v-model="editDeductionForm.description"
                            type="text"
                            required
                            maxlength="1000"
                            placeholder="وصف وتوضيح سبب الاستقطاع..."
                            class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs"
                        />
                    </div>

                    <div>
                        <label for="edit_deduction_notes" class="block text-xs font-semibold text-[#5B6770] mb-1">ملاحظات إضافية (اختياري)</label>
                        <textarea
                            id="edit_deduction_notes"
                            aria-label="ملاحظات إضافية"
                            v-model="editDeductionForm.notes"
                            rows="2"
                            maxlength="1000"
                            class="w-full p-2.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-start"
                        ></textarea>
                    </div>

                    <div>
                        <label for="edit_deduction_attachment" class="block text-xs font-semibold text-[#5B6770] mb-1">مرفق إثبات جديد (اختياري)</label>
                        <input
                            id="edit_deduction_attachment"
                            aria-label="مرفق إثبات جديد"
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp,.pdf"
                            @change="(e: Event) => {
                                const target = e.target as HTMLInputElement;
                                editDeductionForm.attachment = target.files?.[0] || null;
                            }"
                            class="w-full text-xs text-slate-500 file:ml-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#E8F0F0] file:text-[#163E3E]"
                        />
                    </div>

                    <div class="pt-3 border-t border-[#D6E0E7] flex justify-end gap-2">
                        <button @click="showEditDeductionModal = false" type="button" class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold cursor-pointer">إلغاء</button>
                        <button type="submit" :disabled="editDeductionForm.processing" class="h-10 px-5 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold cursor-pointer">حفظ التعديلات</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- مودال 6: عكس دفعة تأمين -->
        <div v-if="showReversePaymentModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showReversePaymentModal = false" class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-5 sm:p-7 z-10 text-start animate-in fade-in zoom-in-95" >
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7] mb-4">
                    <h3 class="text-sm font-bold text-rose-700">عكس وإلغاء دفعة تأمين مسجلة</h3>
                    <button @click="showReversePaymentModal = false" type="button" aria-label="إغلاق النافذة" class="p-1 text-slate-400 hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div v-if="reversePaymentForm.errors.reversal_reason" class="p-3 mb-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs">
                    {{ reversePaymentForm.errors.reversal_reason }}
                </div>

                <form @submit.prevent="submitReversePayment" class="space-y-4">
                    <div v-if="activePaymentForReversal" class="p-3 bg-slate-50 rounded-xl border border-[#D6E0E7] text-xs space-y-1">
                        <div>المرجع: <strong class="font-mono text-[#163E3E]">{{ activePaymentForReversal.reference }}</strong></div>
                        <div>المبلغ: <strong class="font-mono">{{ activePaymentForReversal.amount.toLocaleString() }} {{ currency_symbol }}</strong></div>
                    </div>

                    <div>
                        <label for="reverse_payment_reason" class="block text-xs font-bold text-[#163E3E] mb-1">سبب إلغاء وعكس الدفعة *</label>
                        <textarea id="reverse_payment_reason" aria-label="سبب إلغاء وعكس الدفعة" v-model="reversePaymentForm.reversal_reason" rows="3" placeholder="اذكر سبب الإلغاء (سداد بالخطأ، شيك مرتجع، تصحيح محاسبي)..." required class="w-full p-2.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-start"></textarea>
                    </div>

                    <div class="pt-3 border-t border-[#D6E0E7] flex justify-end gap-2">
                        <button :disabled="reversePaymentForm.processing" @click="showReversePaymentModal = false" type="button" class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold disabled:opacity-50 cursor-pointer">إلغاء</button>
                        <button :disabled="reversePaymentForm.processing" type="submit" class="h-10 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5 cursor-pointer">
                            <Loader2 v-if="reversePaymentForm.processing" class="w-3.5 h-3.5 animate-spin" />
                            <span>{{ reversePaymentForm.processing ? 'جارٍ الإلغاء...' : 'تأكيد عكس الدفعة' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- مودال تمديد / تجديد العقد -->
        <div v-if="showExtendModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showExtendModal = false" class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-lg bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-5 sm:p-7 z-10 text-start animate-in fade-in zoom-in-95" >
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7] mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-[#E8F0F0] text-[#163E3E] flex items-center justify-center">
                            <Clock class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-[#163E3E]">تجديد وتمديد عقد الإيجار</h3>
                            <p class="text-[11px] text-[#5B6770]">تمديد فترة العقد وتحديث القيمة الإيجارية الشهرية إن لزم الأمر</p>
                        </div>
                    </div>
                    <button @click="showExtendModal = false" type="button" aria-label="إغلاق النافذة" class="p-1 text-slate-400 hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div v-if="Object.keys(extendForm.errors).length > 0" class="p-3 mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1">
                    <div v-for="(err, k) in extendForm.errors" :key="k">{{ err }}</div>
                </div>

                <form @submit.prevent="submitExtend" class="space-y-4">
                    <div class="p-3 bg-slate-50 rounded-2xl border border-[#D6E0E7] grid grid-cols-2 gap-3 text-xs">
                        <div>
                            <span class="text-[#5B6770] block text-[11px]">نهاية العقد الحالية:</span>
                            <span class="font-bold text-[#163E3E] font-mono">{{ lease.ends_on }}</span>
                        </div>
                        <div>
                            <span class="text-[#5B6770] block text-[11px]">الإيجار الشهري الحالي:</span>
                            <span class="font-bold text-[#163E3E] font-mono">{{ lease.rent_amount.toLocaleString() }} {{ currency_symbol }}</span>
                        </div>
                    </div>

                    <div>
                        <label for="extend_ends_on" class="block text-xs font-bold text-[#163E3E] mb-1">تاريخ نهاية العقد الجديد *</label>
                        <input
                            id="extend_ends_on"
                            aria-label="تاريخ نهاية العقد الجديد"
                            v-model="extendForm.ends_on"
                            type="date"
                            :min="lease.raw_ends_on"
                            required
                            class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-start font-mono"
                        />
                        <p class="text-[11px] text-[#5B6770] mt-1">يجب أن يكون التاريخ بعد تاريخ النهاية الحالي للعقد.</p>
                    </div>

                    <div>
                        <label for="extend_rent_amount" class="block text-xs font-bold text-[#163E3E] mb-1">القيمة الإيجارية الشهرية الجديدة</label>
                        <div class="relative">
                            <input
                                id="extend_rent_amount"
                                aria-label="القيمة الإيجارية الشهرية الجديدة"
                                v-model="extendForm.rent_amount"
                                type="number"
                                step="0.01"
                                min="0"
                                class="w-full h-11 pl-12 pr-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm font-mono font-bold"
                            />
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-[#5B6770] font-semibold">
                                {{ currency_symbol }}
                            </span>
                        </div>
                        <p class="text-[11px] text-[#5B6770] mt-1">اتركها كما هي إذا لم يتغير الإيجار الشهري.</p>
                    </div>

                    <div>
                        <label for="extend_notes" class="block text-xs font-bold text-[#163E3E] mb-1">ملاحظات التجديد</label>
                        <textarea
                            id="extend_notes"
                            aria-label="ملاحظات التجديد"
                            v-model="extendForm.notes"
                            rows="2"
                            placeholder="ملاحظات توثيقية حول تجديد العقد والاتفاق مع المستأجر..."
                            class="w-full p-2.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-start"
                        ></textarea>
                    </div>

                    <div class="pt-3 border-t border-[#D6E0E7] flex justify-end gap-2">
                        <button @click="showExtendModal = false" type="button" class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold hover:bg-slate-50 cursor-pointer">إلغاء</button>
                        <button
                            type="submit"
                            :disabled="extendForm.processing"
                            class="h-10 px-5 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold transition flex items-center gap-1.5 shadow-xs disabled:opacity-50 cursor-pointer"
                        >
                            <Clock class="w-3.5 h-3.5" />
                            <span>تأكيد التجديد والتمديد</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- مودال إنهاء العقد مع تسوية التأمين الشاملة -->
        <div v-if="showTerminateWithSettlementModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showTerminateWithSettlementModal = false" class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-3xl bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-5 sm:p-7 z-10 text-start max-h-[90vh] overflow-y-auto animate-in fade-in zoom-in-95" >
                <!-- Header -->
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7] mb-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-2xl bg-rose-50 text-rose-700 flex items-center justify-center">
                            <ShieldAlert class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-[#163E3E]">تسوية مبلغ التأمين وإنهاء العقد</h3>
                            <p class="text-xs text-[#5B6770]">تحديد مصير مبلغ التأمين واعتماد الإخلاء وتغيير حالة العقد لمنتهٍ</p>
                        </div>
                    </div>
                    <button @click="showTerminateWithSettlementModal = false" type="button" aria-label="إغلاق النافذة" class="p-1 text-slate-400 hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Errors -->
                <div v-if="Object.keys(terminateForm.errors).length > 0" class="p-3 mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1">
                    <div v-for="(err, k) in terminateForm.errors" :key="k">{{ err }}</div>
                </div>

                <form @submit.prevent="submitTerminateWithSettlement" class="space-y-5">
                    <!-- 1. ملخص التأمين المالي -->
                    <div class="p-4 bg-slate-50 rounded-2xl border border-[#D6E0E7] space-y-3">
                        <span class="text-xs font-bold text-[#163E3E] block">ملخص حساب التأمين:</span>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-xs">
                            <div class="p-2.5 bg-white rounded-xl border border-[#D6E0E7]">
                                <span class="text-[#5B6770] block text-[11px]">التأمين المستلم:</span>
                                <span class="font-bold text-[#163E3E] font-mono text-sm block">
                                    {{ security_deposit.total_received.toLocaleString() }} {{ currency_symbol }}
                                </span>
                            </div>
                            <div class="p-2.5 bg-white rounded-xl border border-[#D6E0E7]">
                                <span class="text-rose-700 block text-[11px] font-semibold">إجمالي الخصومات:</span>
                                <span class="font-bold text-rose-700 font-mono text-sm block">
                                    {{ totalNewDeductions.toLocaleString() }} {{ currency_symbol }}
                                </span>
                            </div>
                            <div class="p-2.5 bg-white rounded-xl border border-[#D6E0E7]">
                                <span class="text-[#5B6770] block text-[11px]">مردود سابقاً:</span>
                                <span class="font-bold text-[#5B6770] font-mono text-sm block">
                                    {{ security_deposit.total_refunded.toLocaleString() }} {{ currency_symbol }}
                                </span>
                            </div>
                            <div class="p-2.5 bg-emerald-50 rounded-xl border border-emerald-200">
                                <span class="text-emerald-800 block text-[11px] font-bold">المستحق رده:</span>
                                <span class="font-bold text-emerald-700 font-mono text-sm block">
                                    {{ calculatedRefundDue.toLocaleString() }} {{ currency_symbol }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- 2. خيارات مصير التأمين الثلاثة -->
                    <div class="space-y-2">
                        <span class="block text-xs font-bold text-[#163E3E]">حدد نوع التسوية ومصير التأمين *</span>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <label
                                :class="[
                                    'p-3.5 rounded-2xl border cursor-pointer transition text-start flex flex-col justify-between space-y-2',
                                    terminateForm.settlement_type === 'full_refund'
                                        ? 'border-[#163E3E] bg-[#E8F0F0]/40 ring-2 ring-[#163E3E]/20'
                                        : 'border-[#D6E0E7] bg-white hover:bg-slate-50'
                                ]"
                            >
                                <div class="flex items-start justify-between">
                                    <input
                                        type="radio"
                                        value="full_refund"
                                        v-model="terminateForm.settlement_type"
                                        class="text-[#163E3E] focus:ring-[#163E3E] mt-1"
                                    />
                                    <span class="text-xs font-bold text-[#163E3E]">رد المبلغ بالكامل</span>
                                </div>
                                <p class="text-[11px] text-[#5B6770]">
                                    لا توجد أي أضرار؛ رد كامل الرصيد المتاح ({{ availableDepositForSettlement.toLocaleString() }} {{ currency_symbol }}) للمستأجر.
                                </p>
                            </label>

                            <label
                                :class="[
                                    'p-3.5 rounded-2xl border cursor-pointer transition text-start flex flex-col justify-between space-y-2',
                                    terminateForm.settlement_type === 'partial_refund'
                                        ? 'border-amber-500 bg-amber-50/40 ring-2 ring-amber-500/20'
                                        : 'border-[#D6E0E7] bg-white hover:bg-slate-50'
                                ]"
                            >
                                <div class="flex items-start justify-between">
                                    <input
                                        type="radio"
                                        value="partial_refund"
                                        v-model="terminateForm.settlement_type"
                                        class="text-amber-600 focus:ring-amber-500 mt-1"
                                    />
                                    <span class="text-xs font-bold text-[#163E3E]">رد جزء مع خصومات</span>
                                </div>
                                <p class="text-[11px] text-[#5B6770]">
                                    تحديد خصومات معتمدة (تلفيات أو مستحقات غير مدفوعة) ورد المبلغ المتبقي للمستأجر.
                                </p>
                            </label>

                            <label
                                :class="[
                                    'p-3.5 rounded-2xl border cursor-pointer transition text-start flex flex-col justify-between space-y-2',
                                    terminateForm.settlement_type === 'no_refund'
                                        ? 'border-rose-500 bg-rose-50/40 ring-2 ring-rose-500/20'
                                        : 'border-[#D6E0E7] bg-white hover:bg-slate-50'
                                ]"
                            >
                                <div class="flex items-start justify-between">
                                    <input
                                        type="radio"
                                        value="no_refund"
                                        v-model="terminateForm.settlement_type"
                                        class="text-rose-600 focus:ring-rose-500 mt-1"
                                    />
                                    <span class="text-xs font-bold text-rose-700">عدم رد المبلغ بالكامل</span>
                                </div>
                                <p class="text-[11px] text-[#5B6770]">
                                    احتجاز كامل التأمين للمالك مع تسجيل وتوثيق الأسباب الإجبارية.
                                </p>
                            </label>
                        </div>
                    </div>

                    <!-- 3. بنود الخصومات في حالة رد جزء من المبلغ -->
                    <div v-if="terminateForm.settlement_type === 'partial_refund'" class="space-y-3">
                        <DeductionSection
                            v-model="terminateForm.deductions"
                            :available-deposit="availableDepositForSettlement"
                            :currency-symbol="currency_symbol"
                            :errors="terminateForm.errors"
                        />
                    </div>

                    <!-- 4. سبب احتجاز المبلغ بالكامل في حالة عدم الرد -->
                    <div v-if="terminateForm.settlement_type === 'no_refund'" class="space-y-1.5 p-4 bg-rose-50/50 rounded-2xl border border-rose-200">
                        <label for="terminate_retention_reason" class="block text-xs font-bold text-rose-900 mb-1">سبب احتجاز كامل مبلغ التأمين وعدم رده للمستأجر *</label>
                        <textarea
                            id="terminate_retention_reason"
                            aria-label="سبب احتجاز كامل مبلغ التأمين وعدم رده للمستأجر"
                            v-model="terminateForm.retention_reason"
                            rows="3"
                            required
                            placeholder="وضح بالتفصيل سبب الاحتفاظ بكامل التأمين (مثل: وجود تلفيات جسيمة تفوق قيمة التأمين، عدم سداد إيجار متأخر)..."
                            class="w-full p-2.5 rounded-xl border border-rose-200 bg-white text-xs text-start"
                        ></textarea>
                    </div>

                    <!-- 5. حالة وتوثيق الرد الفعلي إذا كان هناك مبلغ مستحق رده -->
                    <div v-if="calculatedRefundDue > 0" class="space-y-3 p-4 bg-emerald-50/60 rounded-2xl border border-emerald-200">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-emerald-950">
                                توثيق الرد الفعلي للمبلغ المستحق ({{ calculatedRefundDue.toLocaleString() }} {{ currency_symbol }})
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <label
                                :class="[
                                    'p-3 rounded-xl border cursor-pointer text-start flex items-center justify-between',
                                    terminateForm.refund_status === 'pending'
                                        ? 'border-amber-500 bg-white ring-2 ring-amber-500/20'
                                        : 'border-emerald-200 bg-white/70 hover:bg-white'
                                ]"
                            >
                                <div class="space-y-0.5">
                                    <span class="text-xs font-bold text-[#163E3E] block">لم يُرد بعد (معلق)</span>
                                    <span class="text-[10px] text-[#5B6770] block">سيتم التحويل الفعلي لاحقاً للمستأجر</span>
                                </div>
                                <input
                                    type="radio"
                                    value="pending"
                                    v-model="terminateForm.refund_status"
                                    class="text-amber-600 focus:ring-amber-500"
                                />
                            </label>

                            <label
                                :class="[
                                    'p-3 rounded-xl border cursor-pointer text-start flex items-center justify-between',
                                    terminateForm.refund_status === 'refunded'
                                        ? 'border-emerald-600 bg-white ring-2 ring-emerald-600/20'
                                        : 'border-emerald-200 bg-white/70 hover:bg-white'
                                ]"
                            >
                                <div class="space-y-0.5">
                                    <span class="text-xs font-bold text-emerald-900 block">تم الرد الفعلي الآن</span>
                                    <span class="text-[10px] text-[#5B6770] block">تم تحويل أو تسليم المبلغ بالفعل</span>
                                </div>
                                <input
                                    type="radio"
                                    value="refunded"
                                    v-model="terminateForm.refund_status"
                                    class="text-emerald-600 focus:ring-emerald-500"
                                />
                            </label>
                        </div>

                        <!-- تفاصيل الدفع إذا تم اختيار تم الرد الفعلي -->
                        <div v-if="terminateForm.refund_status === 'refunded'" class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 pt-2 border-t border-emerald-200/60">
                            <div>
                                <label for="terminate_refund_method" class="block text-[11px] font-bold text-emerald-950 mb-1">طريقة الرد *</label>
                                <select id="terminate_refund_method" aria-label="طريقة الرد" v-model="terminateForm.refund_method" class="w-full h-10 px-2.5 rounded-xl border border-emerald-200 bg-white text-xs">
                                    <option value="bank_transfer">تحويل بنكي</option>
                                    <option value="cash">نقدي (كاش)</option>
                                    <option value="cheque">شيك</option>
                                    <option value="other">إنستاباي / أخرى</option>
                                </select>
                            </div>
                            <div>
                                <label for="terminate_refund_payment_date" class="block text-[11px] font-bold text-emerald-950 mb-1">تاريخ الرد الفعلي *</label>
                                <input id="terminate_refund_payment_date" aria-label="تاريخ الرد الفعلي" v-model="terminateForm.refund_payment_date" type="date" required class="w-full h-10 px-2.5 rounded-xl border border-emerald-200 bg-white text-xs text-start font-mono" />
                            </div>
                            <div>
                                <label for="terminate_refund_reference" class="block text-[11px] font-semibold text-emerald-950 mb-1">رقم المرجع / الإيصال</label>
                                <input id="terminate_refund_reference" aria-label="رقم المرجع أو الإيصال" v-model="terminateForm.refund_reference" type="text" placeholder="TXN-..." class="w-full h-10 px-2.5 rounded-xl border border-emerald-200 bg-white text-xs text-start font-mono" />
                            </div>
                        </div>
                    </div>

                    <!-- 6. حالة فحص الوحدة وملاحظات الإنهاء -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-[#D6E0E7]">
                        <div>
                            <label for="terminate_inspection_category" class="block text-xs font-bold text-[#163E3E] mb-1">حالة فحص واستلام الوحدة *</label>
                            <select id="terminate_inspection_category" aria-label="حالة فحص واستلام الوحدة" v-model="terminateForm.inspection_category" class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs">
                                <option value="no_issues">سليمة بدون أضرار (حالة ممتازة)</option>
                                <option value="furniture_damage">تلف أثاث أو أجهزة</option>
                                <option value="fixture_damage">تلف تركيبات ومرافق</option>
                                <option value="missing_items">مفقودات ومحتويات ناقصة</option>
                                <option value="other_damage">أضرار موثقة أخرى</option>
                            </select>
                        </div>
                        <div>
                            <label for="terminate_notes" class="block text-xs font-bold text-[#163E3E] mb-1">ملاحظات ختامية على إنهاء العقد</label>
                            <input id="terminate_notes" aria-label="ملاحظات ختامية على إنهاء العقد" v-model="terminateForm.notes" type="text" placeholder="أي ملاحظات نهائية..." class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-start" />
                        </div>
                    </div>

                    <!-- Action buttons -->
                    <div class="pt-4 border-t border-[#D6E0E7] flex justify-end gap-2.5">
                        <button @click="showTerminateWithSettlementModal = false" type="button" class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold hover:bg-slate-50 cursor-pointer">
                            إلغاء
                        </button>
                        <button
                            type="submit"
                            :disabled="terminateForm.processing || (terminateForm.settlement_type === 'partial_refund' && totalNewDeductions > availableDepositForSettlement)"
                            class="h-10 px-5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-xs disabled:opacity-50 cursor-pointer"
                        >
                            <ShieldAlert class="w-4 h-4" />
                            <span>تأكيد التسوية وإنهاء العقد رسميًا</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- مودال تسجيل صرف المبلغ المتبقي المعلق -->
        <div v-if="showPendingRefundModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showPendingRefundModal = false" class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-5 sm:p-7 z-10 text-start animate-in fade-in zoom-in-95" >
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7] mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
                            <RotateCcw class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-[#163E3E]">صرف التأمين المعلق للمستأجر</h3>
                            <p class="text-[11px] text-[#5B6770]">توثيق تسليم أو تحويل المبلغ المتبقي المستحق للمستأجر</p>
                        </div>
                    </div>
                    <button @click="showPendingRefundModal = false" type="button" aria-label="إغلاق النافذة" class="p-1 text-slate-400 hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div v-if="Object.keys(pendingRefundForm.errors).length > 0" class="p-3 mb-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs">
                    <div v-for="(err, k) in pendingRefundForm.errors" :key="k">{{ err }}</div>
                </div>

                <form @submit.prevent="submitPendingRefund" class="space-y-4">
                    <div class="p-3 bg-emerald-50 rounded-2xl border border-emerald-200 text-xs text-emerald-950 space-y-1">
                        <div class="flex justify-between">
                            <span>الرصيد المعلق القابل للصرف:</span>
                            <span class="font-bold font-mono">{{ security_deposit.refundable_balance.toLocaleString() }} {{ currency_symbol }}</span>
                        </div>
                    </div>

                    <div>
                        <label for="pending_refund_amount" class="block text-xs font-bold text-[#163E3E] mb-1">المبلغ المصروف *</label>
                        <div class="relative">
                            <input
                                id="pending_refund_amount"
                                aria-label="المبلغ المصروف"
                                v-model="pendingRefundForm.amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                :max="security_deposit.refundable_balance"
                                required
                                class="w-full h-11 pl-12 pr-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm font-mono font-bold"
                            />
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-[#5B6770] font-semibold">
                                {{ currency_symbol }}
                            </span>
                        </div>
                    </div>

                    <div>
                        <label for="pending_refund_method" class="block text-xs font-bold text-[#163E3E] mb-1">طريقة الصرف *</label>
                        <select id="pending_refund_method" aria-label="طريقة الصرف" v-model="pendingRefundForm.payment_method" class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs">
                            <option value="bank_transfer">تحويل بنكي</option>
                            <option value="cash">نقدي (كاش)</option>
                            <option value="cheque">شيك</option>
                            <option value="other">إنستاباي / محفظة إلكترونية</option>
                        </select>
                    </div>

                    <div>
                        <label for="pending_refund_refunded_at" class="block text-xs font-bold text-[#163E3E] mb-1">تاريخ الصرف والتحويل الفعلي *</label>
                        <input id="pending_refund_refunded_at" aria-label="تاريخ الصرف والتحويل الفعلي" v-model="pendingRefundForm.refunded_at" type="date" required class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-start font-mono" />
                    </div>

                    <div>
                        <label for="pending_refund_reference" class="block text-xs font-bold text-[#163E3E] mb-1">رقم مرجع الحوالة / الإيصال</label>
                        <input id="pending_refund_reference" aria-label="رقم مرجع الحوالة أو الإيصال" v-model="pendingRefundForm.reference" type="text" placeholder="TXN-123456" class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-start font-mono" />
                    </div>

                    <div>
                        <label for="pending_refund_notes" class="block text-xs font-bold text-[#163E3E] mb-1">ملاحظات الصرف</label>
                        <textarea id="pending_refund_notes" aria-label="ملاحظات الصرف" v-model="pendingRefundForm.notes" rows="2" class="w-full p-2.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-start"></textarea>
                    </div>

                    <div class="pt-3 border-t border-[#D6E0E7] flex justify-end gap-2">
                        <button @click="showPendingRefundModal = false" type="button" class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold hover:bg-slate-50 cursor-pointer">إلغاء</button>
                        <button type="submit" :disabled="pendingRefundForm.processing" class="h-10 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs disabled:opacity-50 cursor-pointer">توثيق الصرف الفعلي</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- نافذة رفع / تحديث وثيقة العقد المعتمد -->
        <div
            v-if="showContractUploadModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
        >
            <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7]">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-[#E8F0F0] text-[#163E3E] flex items-center justify-center font-bold">
                            <Upload class="w-4 h-4" />
                        </div>
                        <h3 class="font-bold text-sm text-[#163E3E]">
                            {{ lease.has_contract_image ? 'استبدال وثيقة العقد' : 'إرفاق وثيقة العقد المعتمد' }}
                        </h3>
                    </div>
                    <button
                        @click="showContractUploadModal = false"
                        type="button"
                        aria-label="إغلاق النافذة"
                        class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition cursor-pointer"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div v-if="Object.keys(contractUploadForm.errors).length > 0" class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs">
                    <div v-for="(err, k) in contractUploadForm.errors" :key="k">{{ err }}</div>
                </div>

                <form @submit.prevent="submitContractUpload" class="space-y-4">
                    <div class="p-3 bg-emerald-50 rounded-2xl border border-emerald-200 text-xs text-emerald-950 space-y-1">
                        <div class="flex items-center gap-1.5 font-bold">
                            <ShieldCheck class="w-4 h-4 text-emerald-700" />
                            <span>تشفير أمان تلقائي</span>
                        </div>
                        <p class="text-[11px] text-emerald-900/80 leading-relaxed">
                            يتم تشفير الملف فور رفعه بخوارزمية AES ولا يمكن لأي طرف خارجي الوصول إليه إلا بصلاحيات معتمدة.
                        </p>
                    </div>

                    <div>
                        <label for="contract_image_upload" class="block text-xs font-bold text-[#163E3E] mb-2">اختر ملف العقد (صورة أو PDF) *</label>
                        <input
                            id="contract_image_upload"
                            aria-label="ملف العقد المعتمد"
                            type="file"
                            accept="image/jpeg,image/png,image/jpg,image/webp,application/pdf"
                            required
                            @change="onShowContractFileChange"
                            class="w-full text-xs text-slate-600 file:ml-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-[#E8F0F0] file:text-[#163E3E] hover:file:bg-[#163E3E] hover:file:text-white file:transition cursor-pointer"
                        />
                        <p class="text-[11px] text-[#8A9AA7] mt-1.5">الحد الأقصى للملف: 10 ميجابايت (JPEG, PNG, WEBP, PDF)</p>
                    </div>

                    <div class="pt-3 border-t border-[#D6E0E7] flex justify-end gap-2">
                        <button
                            @click="showContractUploadModal = false"
                            type="button"
                            class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold hover:bg-slate-50 cursor-pointer"
                        >
                            إلغاء
                        </button>
                        <button
                            type="submit"
                            :disabled="contractUploadForm.processing || !contractUploadForm.contract_image"
                            class="h-10 px-5 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold transition flex items-center gap-1.5 shadow-xs disabled:opacity-50 cursor-pointer"
                        >
                            <Upload class="w-3.5 h-3.5" />
                            <span>{{ contractUploadForm.processing ? 'جاري التشفير والرفع...' : 'حفظ وتشفير الوثيقة' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Rent Payment Modal -->
        <ModalRecordPayment
            v-if="showRentPaymentModal"
            :show="true"
            :renters="rentPaymentRenters"
            :initial-renter-id="renter.id"
            :initial-amount="rentPaymentInitialAmount"
            :initial-rent-charge-id="rentPaymentCharge?.id"
            @close="closeRentPayment"
        />
    </AppLayout>
</template>
