<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import DeductionForm from '@/Components/Leases/SecurityDeposit/DeductionForm.vue';
import DeductionSection from '@/Components/Leases/SecurityDeposit/DeductionSection.vue';
import SettlementSummary from '@/Components/Leases/SecurityDeposit/SettlementSummary.vue';
import {
    AlertCircle,
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    Ban,
    Building2,
    Calendar,
    Check,
    CheckCircle2,
    Clock,
    Coins,
    CreditCard,
    DollarSign,
    ExternalLink,
    FileCheck,
    FileText,
    History,
    Home,
    Info,
    MessageCircle,
    MinusCircle,
    Paperclip,
    Phone,
    Plus,
    RotateCcw,
    Shield,
    ShieldAlert,
    ShieldCheck,
    Store,
    Trash2,
    Upload,
    User,
    Wrench,
    X,
} from 'lucide-vue-next';

interface DepositPayment {
    id: number;
    amount: number;
    paid_at: string;
    payment_method: string;
    payment_method_label: string;
    reference: string;
    notes: string | null;
    recorded_by: string;
    is_reversed: boolean;
    reversal_reason: string | null;
    reversed_at: string | null;
    reversed_by: string | null;
}

interface DepositDeduction {
    id: number;
    amount: number;
    category: string;
    category_label: string;
    description: string;
    notes?: string | null;
    attachment_url?: string | null;
    status: string;
    deducted_at: string;
    recorded_by: string;
}

interface DepositRefund {
    id: number;
    amount: number;
    refunded_at: string;
    payment_method: string;
    payment_method_label: string;
    reference: string;
    notes: string | null;
    recorded_by: string;
    is_reversed: boolean;
    reversal_reason: string | null;
}

interface RentChargeItem {
    id: number;
    period: string;
    due_date: string;
    amount: number;
    paid_amount: number;
    outstanding_balance: number;
    status: string;
}

const props = defineProps<{
    lease: {
        id: number;
        contract_number: string;
        starts_on: string;
        ends_on: string;
        raw_starts_on: string;
        raw_ends_on: string;
        rent_amount: number;
        billing_interval: string;
        status: string;
        status_label: string;
        is_past_end_date?: boolean;
        is_expiring_soon?: boolean;
        notes: string | null;
        contract_image_url?: string | null;
        has_contract_image?: boolean;
        created_at: string;
    };
    unit: {
        id: number;
        name: string;
        code: string;
        unit_type: string;
        unit_type_label: string;
        property_id: number;
        property_name: string;
        property_address: string | null;
        apartment_gender?: {
            status: 'male' | 'female' | 'mixed' | 'unspecified';
            label: string;
            short_label: string;
            description: string;
            badge_class: string;
            male_count: number;
            female_count: number;
            unspecified_count: number;
            total_active_renters: number;
        } | null;
    };
    renter: {
        id: number;
        name: string;
        phone: string;
        national_id: string | null;
        email: string | null;
        gender?: 'male' | 'female' | null;
        gender_label?: string | null;
    };
    security_deposit: {
        agreed_amount: number;
        total_received: number;
        outstanding_amount: number;
        total_deductions: number;
        total_refunded: number;
        refundable_balance: number;
        status: string;
        status_label: string;
        payments: DepositPayment[];
        deductions: DepositDeduction[];
        refunds: DepositRefund[];
        settlement: {
            id: number;
            status: string;
            settlement_type?: 'full_refund' | 'partial_refund' | 'no_refund' | null;
            settlement_type_label?: string | null;
            retention_reason?: string | null;
            original_deposit?: number | null;
            inspection_category: string;
            inspection_category_label: string;
            inspection_notes: string | null;
            total_received?: number;
            total_deductions?: number;
            total_refunded?: number;
            refundable_balance?: number;
            refund_status?: 'pending' | 'refunded' | 'not_applicable';
            refund_status_label?: string;
            refund_method?: string | null;
            refund_payment_date?: string | null;
            refund_reference?: string | null;
            settled_at: string | null;
            settled_by: string | null;
            notes: string | null;
        } | null;
        actions: {
            can_record_payment: boolean;
            can_start_settlement: boolean;
            can_record_deduction: boolean;
            can_record_refund: boolean;
            can_settle: boolean;
            can_record_pending_refund?: boolean;
            can_extend?: boolean;
            can_terminate?: boolean;
        };
    };
    rent_charges: RentChargeItem[];
    currency: string;
    currency_symbol: string;
}>();

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
const activePaymentForReversal = ref<DepositPayment | null>(null);

// Forms
const todayDate = new Date().toISOString().split('T')[0];

const extendForm = useForm({
    ends_on: '',
    rent_amount: props.lease.rent_amount,
    notes: '',
});

interface DeductionDraft {
    category: string;
    amount: number;
    description: string;
    notes: string;
    attachment: File | null;
}

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

const showEditDeductionModal = ref(false);
const editingDeductionId = ref<number | null>(null);
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

const finalizeTotalDeductions = computed(() => {
    return Math.round(
        finalizeSettlementForm.deductions.reduce((sum, d) => sum + (Number(d.amount) || 0), 0) * 100
    ) / 100;
});

const finalizeRefundDue = computed(() => {
    return Math.max(0, Math.round((availableDepositForSettlement.value - finalizeTotalDeductions.value) * 100) / 100);
});

const finalizeRetainedAmount = computed(() => {
    return finalizeTotalDeductions.value;
});

const finalizeExceedsBalance = computed(() => {
    return finalizeTotalDeductions.value > availableDepositForSettlement.value;
});

const finalizeCalculatedOutcome = computed(() => {
    if (finalizeTotalDeductions.value <= 0) {
        return {
            type: 'full_refund' as const,
            title: 'رد كامل مبلغ التأمين (Full Refund)',
            description: `لا توجد خصومات؛ سيتم صرف كامل الرصيد المتاح (${availableDepositForSettlement.value.toLocaleString()} ${props.currency_symbol}) للمستأجر.`,
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

const confirmedDepositDeductions = computed(() => props.security_deposit.deductions.filter((deduction) => deduction.status === 'confirmed'));
const finalSettlementDecision = computed(() => {
    const refundDue = props.security_deposit.refundable_balance;

    if (props.security_deposit.total_deductions <= 0) {
        return refundDue > 0 ? 'رد كامل الرصيد المتبقي للمستأجر' : 'لا يوجد مبلغ متبقٍ للرد';
    }

    return refundDue > 0
        ? `خصم البنود المعتمدة ورد ${refundDue.toLocaleString()} ${props.currency_symbol} للمستأجر`
        : 'استهلاك الرصيد المتبقي في الخصومات المعتمدة، ولا يوجد مبلغ إضافي للرد';
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
        if (confirm('هل أنت متأكد من إنهاء عقد الإيجار رسمياً وتحديث حالة الوحدة لتصبح شاغرة؟')) {
            router.post(route('leases.terminate', props.lease.id));
        }
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

const deleteDeduction = (deductionId: number) => {
    if (confirm('هل أنت متأكد من إلغاء بند الخصم هذا من سجل التأمين؟')) {
        router.delete(route('leases.security-deposit.deduction.delete', [props.lease.id, deductionId]));
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
        finalizeSettlementForm.deductions = props.security_deposit.deductions.map((d) => ({
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

const terminateLease = () => {
    if (confirm('هل أنت متأكد من إنهاء هذا العقد مبكراً وبدء مرحلة تسوية التأمين؟')) {
        router.post(route('leases.terminate', props.lease.id));
    }
};

// ----------------------------------------------------
// وثيقة العقد المعتمد (Contract Document Upload / Delete)
// ----------------------------------------------------
const showContractUploadModal = ref(false);
const contractUploadForm = useForm({
    contract_image: null as File | null,
});

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

const deleteContractImage = () => {
    if (confirm('هل أنت متأكد من رغبتك بحذف وثيقة العقد المرفقة نهائياً؟')) {
        router.delete(route('leases.contract-image.destroy', props.lease.id));
    }
};

const depositPercentReceived = computed(() => {
    if (!props.security_deposit.agreed_amount || props.security_deposit.agreed_amount <= 0) return 0;
    return Math.min(100, Math.round((props.security_deposit.total_received / props.security_deposit.agreed_amount) * 100));
});

const depositBadgeClass = computed(() => {
    switch (props.security_deposit.status) {
        case 'held':
            return 'bg-emerald-50 text-emerald-800 border-emerald-200';
        case 'partially_received':
            return 'bg-amber-50 text-amber-800 border-amber-200';
        case 'due':
            return 'bg-blue-50 text-blue-800 border-blue-200';
        case 'under_review':
            return 'bg-purple-50 text-purple-800 border-purple-200';
        case 'settled':
            return 'bg-slate-100 text-slate-700 border-slate-200';
        default:
            return 'bg-slate-100 text-slate-600 border-slate-200';
    }
});
</script>

<template>
    <AppLayout
        :title="`عقد ${lease.contract_number} — ${unit.name} — Nook Mate`"
        active-tab="leases"
        :header-title="`عقد إيجار ${lease.contract_number}`"
        :header-subtitle="`${unit.name} • ${unit.property_name} • المستأجر: ${renter.name}`"
        :breadcrumb="`العقود / ${lease.contract_number}`"
    >
        <template #actions>
            <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                <Link
                    :href="route('leases.index')"
                    class="h-10 px-4 rounded-xl border border-[#D6E0E7] bg-white hover:bg-[#F8F4EA] text-xs font-bold text-[#5B6770] hover:text-[#163E3E] transition flex items-center justify-center gap-1.5 shadow-2xs flex-1 sm:flex-initial"
                >
                    <ArrowRight class="w-4 h-4" />
                    <span>العودة لقائمة العقود</span>
                </Link>

                <button
                    v-if="lease.status === 'active'"
                    @click="showExtendModal = true"
                    type="button"
                    class="h-10 px-4 rounded-xl border border-[#163E3E] bg-[#E8F0F0] hover:bg-[#163E3E] hover:text-white text-[#163E3E] text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer shadow-2xs flex-1 sm:flex-initial"
                >
                    <RotateCcw class="w-4 h-4" />
                    <span>تجديد / تمديد العقد</span>
                </button>

                <button
                    v-if="lease.status === 'active'"
                    @click="openTerminateWorkflow"
                    type="button"
                    class="h-10 px-4 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer shadow-2xs flex-1 sm:flex-initial"
                >
                    <Ban class="w-4 h-4" />
                    <span>إنهاء العقد وتسوية التأمين</span>
                </button>
            </div>
        </template>

        <!-- شريط تنبيه تجاوز موعد انتهاء العقد -->
        <div
            v-if="lease.status === 'active' && lease.is_past_end_date"
            class="p-4 sm:p-5 rounded-2xl sm:rounded-3xl bg-amber-50 border-2 border-amber-300 text-amber-950 shadow-2xs space-y-3"
        >
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-start sm:items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-amber-200 text-amber-900 flex items-center justify-center shrink-0">
                        <Clock class="w-5 h-5 stroke-[2.3]" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-black text-sm sm:text-base text-amber-950">انتهى موعد هذا العقد تاريخياً في {{ lease.ends_on }}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-200 text-amber-900 border border-amber-300">
                                يتطلب اتخاذ قرار
                            </span>
                        </div>
                        <p class="text-xs text-amber-900/80 mt-1 leading-relaxed">
                            العقد لا يزال نشطاً في النظام والوحدة تظل مشغولة لحفظ حقوق المالك والمستأجر في إجراءات الفحص والتأمين. اختر التجديد أو البدء في التسوية والإنهاء.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0 self-start sm:self-auto">
                    <button
                        type="button"
                        @click="showExtendModal = true"
                        class="h-10 px-4 rounded-xl bg-white hover:bg-amber-100 text-amber-950 border border-amber-300 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-2xs"
                    >
                        <RotateCcw class="w-4 h-4" />
                        <span>تجديد / تمديد العقد</span>
                    </button>
                    <button
                        type="button"
                        @click="openTerminateWorkflow"
                        class="h-10 px-4 rounded-xl bg-rose-700 hover:bg-rose-800 text-white text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-2xs"
                    >
                        <Ban class="w-4 h-4" />
                        <span>تسوية التأمين والإنهاء</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 1. كروت الملخص المالي السريع للعقد والتأمين (4 KPIs) - متجاوبة تماماً للموبايل -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <!-- كارت الإيجار الشهري -->
            <div class="rounded-2xl sm:rounded-3xl border border-[#D6E0E7] bg-white p-3.5 sm:p-5 shadow-2xs space-y-2 hover:border-[#163E3E]/40 transition">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] sm:text-xs font-semibold text-[#5B6770]">الإيجار الشهري</span>
                    <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg sm:rounded-xl bg-[#E8F0F0] text-[#163E3E] flex items-center justify-center">
                        <Coins class="w-3.5 h-3.5 sm:w-4 sm:h-4 stroke-[2.2]" />
                    </div>
                </div>
                <div class="flex items-baseline justify-between gap-1 flex-wrap">
                    <span class="text-xl sm:text-2xl font-black text-[#163E3E] font-mono">
                        {{ lease.rent_amount.toLocaleString() }}
                        <span class="text-[10px] sm:text-xs font-sans font-normal text-[#5B6770]">{{ currency_symbol }}</span>
                    </span>
                    <StatusBadge :status="lease.status" :label="lease.status_label" size="sm" />
                </div>
            </div>

            <!-- كارت التأمين المتفق عليه -->
            <div class="rounded-2xl sm:rounded-3xl border border-[#D6E0E7] bg-white p-3.5 sm:p-5 shadow-2xs space-y-2 hover:border-[#163E3E]/40 transition">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] sm:text-xs font-semibold text-[#5B6770]">تأمين الوحدة المتفق عليه</span>
                    <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg sm:rounded-xl bg-[#E8F0F0] text-[#163E3E] flex items-center justify-center">
                        <Shield class="w-3.5 h-3.5 sm:w-4 sm:h-4 stroke-[2.2]" />
                    </div>
                </div>
                <div class="flex items-baseline justify-between gap-1 flex-wrap">
                    <span class="text-xl sm:text-2xl font-black text-[#163E3E] font-mono">
                        {{ security_deposit.agreed_amount.toLocaleString() }}
                        <span class="text-[10px] sm:text-xs font-sans font-normal text-[#5B6770]">{{ currency_symbol }}</span>
                    </span>
                    <span class="text-[10px] sm:text-xs text-[#5B6770]">تأمين مسترد</span>
                </div>
            </div>

            <!-- كارت المحصل الفعلي قيد الحجز -->
            <div class="rounded-2xl sm:rounded-3xl border border-[#D6E0E7] bg-white p-3.5 sm:p-5 shadow-2xs space-y-2 hover:border-emerald-300 transition">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] sm:text-xs font-semibold text-[#5B6770]">المحصل قيد الحجز</span>
                    <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg sm:rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
                        <ShieldCheck class="w-3.5 h-3.5 sm:w-4 sm:h-4 stroke-[2.2]" />
                    </div>
                </div>
                <div class="flex items-baseline justify-between gap-1 flex-wrap">
                    <span class="text-xl sm:text-2xl font-black text-emerald-700 font-mono">
                        {{ security_deposit.total_received.toLocaleString() }}
                        <span class="text-[10px] sm:text-xs font-sans font-normal text-emerald-600">{{ currency_symbol }}</span>
                    </span>
                    <span
                        v-if="security_deposit.outstanding_amount > 0"
                        class="text-[10px] sm:text-xs text-amber-800 bg-amber-50 border border-amber-200 px-1.5 sm:px-2 py-0.5 rounded-md font-bold"
                    >
                        متبقي {{ security_deposit.outstanding_amount.toLocaleString() }}
                    </span>
                    <span v-else class="text-[10px] sm:text-xs text-emerald-800 bg-emerald-50 border border-emerald-200 px-1.5 sm:px-2 py-0.5 rounded-md font-bold">
                        مكتمل السداد
                    </span>
                </div>
            </div>

            <!-- كارت الرصيد القابل للرد حالياً -->
            <div class="rounded-2xl sm:rounded-3xl border border-[#D6E0E7] bg-white p-3.5 sm:p-5 shadow-2xs space-y-2 hover:border-[#163E3E]/40 transition">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] sm:text-xs font-semibold text-[#5B6770]">الرصيد القابل للرد</span>
                    <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg sm:rounded-xl bg-[#E8F0F0] text-[#163E3E] flex items-center justify-center">
                        <RotateCcw class="w-3.5 h-3.5 sm:w-4 sm:h-4 stroke-[2.2]" />
                    </div>
                </div>
                <div class="flex items-baseline justify-between gap-1 flex-wrap">
                    <span class="text-xl sm:text-2xl font-black text-[#163E3E] font-mono">
                        {{ security_deposit.refundable_balance.toLocaleString() }}
                        <span class="text-[10px] sm:text-xs font-sans font-normal text-[#5B6770]">{{ currency_symbol }}</span>
                    </span>
                    <span class="text-[10px] sm:text-xs font-bold px-2 py-0.5 rounded-md border" :class="depositBadgeClass">
                        {{ security_deposit.status_label }}
                    </span>
                </div>
            </div>
        </div>

        <!-- 2. صفحة العقد الرئيسية: عمودان متجاوبان -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 sm:gap-6">
            <!-- العمود الأيمن (4 أعمدة على الديسكتوب): بيانات الوحدة والمستأجر -->
            <div class="lg:col-span-4 space-y-5 sm:space-y-6">
                <!-- بطاقة الوحدة المؤجرة -->
                <div class="rounded-2xl sm:rounded-3xl border border-[#D6E0E7] bg-white p-4 sm:p-6 shadow-2xs space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7]">
                        <div class="flex items-center gap-2.5">
                            <div class="w-10 h-10 rounded-xl bg-[#E8F0F0] text-[#163E3E] flex items-center justify-center shrink-0">
                                <Store v-if="unit.unit_type === 'shop'" class="w-5 h-5 stroke-[2.2]" />
                                <Home v-else class="w-5 h-5 stroke-[2.2]" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-[#163E3E]">{{ unit.name }}</h3>
                                <span class="text-xs text-[#5B6770] font-mono">{{ unit.code }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span
                                v-if="unit.apartment_gender"
                                class="text-[11px] font-bold px-2 py-0.5 rounded-lg border"
                                :class="unit.apartment_gender.badge_class"
                            >
                                {{ unit.apartment_gender.short_label }}
                            </span>
                            <span class="text-xs font-bold bg-[#F8FAFC] text-[#163E3E] px-2.5 py-1 rounded-lg border border-[#D6E0E7]">
                                {{ unit.unit_type_label }}
                            </span>
                        </div>
                    </div>

                    <div class="space-y-2.5 text-xs">
                        <div class="flex items-center justify-between py-1">
                            <span class="text-[#5B6770]">العقار / المبنى:</span>
                            <span class="font-bold text-[#163E3E] flex items-center gap-1">
                                <Building2 class="w-3.5 h-3.5 text-[#5B6770]" />
                                <span>{{ unit.property_name }}</span>
                            </span>
                        </div>
                        <div class="flex items-center justify-between py-1" v-if="unit.property_address">
                            <span class="text-[#5B6770]">العنوان:</span>
                            <span class="text-[#163E3E] truncate max-w-[200px]">{{ unit.property_address }}</span>
                        </div>
                        <div class="flex items-center justify-between py-1 border-t border-[#D6E0E7]/60">
                            <span class="text-[#5B6770]">تاريخ البداية:</span>
                            <span class="font-mono font-bold text-[#163E3E]">{{ lease.starts_on }}</span>
                        </div>
                        <div class="flex items-center justify-between py-1">
                            <span class="text-[#5B6770]">تاريخ النهاية:</span>
                            <span class="font-mono font-bold text-[#163E3E]">{{ lease.ends_on }}</span>
                        </div>
                        <div class="flex items-center justify-between py-1 border-t border-[#D6E0E7]/60">
                            <span class="text-[#5B6770]">دورية الاستحقاق:</span>
                            <span class="font-bold text-[#163E3E] bg-[#E8F0F0] px-2 py-0.5 rounded-md">
                                {{ lease.billing_interval === 'monthly' ? 'شهري' : lease.billing_interval }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- بطاقة بيانات المستأجر -->
                <div class="rounded-2xl sm:rounded-3xl border border-[#D6E0E7] bg-white p-4 sm:p-6 shadow-2xs space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7]">
                        <div class="flex items-center gap-2.5">
                            <div class="w-10 h-10 rounded-xl bg-[#163E3E] text-white flex items-center justify-center font-black text-sm shrink-0">
                                {{ renter.name.charAt(0) }}
                            </div>
                            <div>
                                <Link
                                    :href="route('renters.show', renter.id)"
                                    class="text-sm font-bold text-[#163E3E] hover:text-[#163E3E] transition block"
                                >
                                    {{ renter.name }}
                                </Link>
                                <span class="text-xs text-[#5B6770] font-mono block mt-0.5" dir="ltr">{{ renter.phone }}</span>
                            </div>
                        </div>

                        <!-- اختصارات الاتصال والواتساب السريعة -->
                        <div class="flex items-center gap-1.5">
                            <a
                                v-if="renter.phone"
                                :href="`https://wa.me/20${renter.phone.replace(/^0+/, '')}`"
                                target="_blank"
                                class="p-2 rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition shadow-2xs"
                                title="محادثة WhatsApp"
                            >
                                <MessageCircle class="w-4 h-4" />
                            </a>
                            <a
                                v-if="renter.phone"
                                :href="`tel:${renter.phone}`"
                                class="p-2 rounded-xl bg-slate-100 text-[#163E3E] hover:bg-slate-200 transition shadow-2xs"
                                title="اتصال هاتفي"
                            >
                                <Phone class="w-4 h-4" />
                            </a>
                        </div>
                    </div>

                    <div class="space-y-2.5 text-xs">
                        <div class="flex items-center justify-between py-1">
                            <span class="text-[#5B6770]">الجنس:</span>
                            <span
                                v-if="renter.gender"
                                class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold border"
                                :class="renter.gender === 'male' ? 'bg-sky-50 text-sky-700 border-sky-200' : 'bg-rose-50 text-rose-700 border-rose-200'"
                            >
                                {{ renter.gender_label || (renter.gender === 'male' ? 'ذكر' : 'أنثى') }}
                            </span>
                            <span v-else class="text-[#8A9AA7] text-[11px]">غير محدد</span>
                        </div>
                        <div class="flex items-center justify-between py-1">
                            <span class="text-[#5B6770]">رقم الهوية / الإقامة:</span>
                            <span class="font-mono font-bold text-[#163E3E] bg-[#F8FAFC] px-2 py-0.5 rounded-md border border-[#D6E0E7]">
                                {{ renter.national_id || 'غير مسجل' }}
                            </span>
                        </div>
                        <div v-if="renter.email" class="flex items-center justify-between py-1">
                            <span class="text-[#5B6770]">البريد الإلكتروني:</span>
                            <span class="font-mono text-[#163E3E] truncate max-w-[180px]">{{ renter.email }}</span>
                        </div>
                        <div class="pt-2 border-t border-[#D6E0E7]/60">
                            <Link
                                :href="route('renters.show', renter.id)"
                                class="w-full h-9 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] hover:bg-white text-xs font-bold text-[#163E3E] flex items-center justify-center gap-1 transition"
                            >
                                <span>عرض السجل الكامل للمستأجر</span>
                                <ExternalLink class="w-3.5 h-3.5" />
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- بطاقة وثيقة العقد المعتمد (مشفرة ومحمية) -->
                <div class="rounded-2xl sm:rounded-3xl border border-[#D6E0E7] bg-white p-4 sm:p-6 shadow-2xs space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7]">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl bg-[#E8F0F0] text-[#163E3E] flex items-center justify-center font-bold">
                                <FileCheck class="w-4 h-4 stroke-[2.2]" />
                            </div>
                            <div>
                                <h3 class="font-bold text-sm text-[#163E3E]">وثيقة العقد المعتمد</h3>
                                <p class="text-[11px] text-[#5B6770]">صورة العقد الموقّع والمسح الضوئي</p>
                            </div>
                        </div>

                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                            <ShieldCheck class="w-3 h-3 text-emerald-600" />
                            <span>تشفير AES-256</span>
                        </span>
                    </div>

                    <!-- حالة وجود مرفق عقد محفوظ -->
                    <div v-if="lease.has_contract_image && lease.contract_image_url" class="space-y-3">
                        <div class="p-3.5 rounded-2xl bg-emerald-50/70 border border-emerald-200 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center shrink-0">
                                    <FileText class="w-5 h-5" />
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-[#163E3E] truncate">نسخة العقد المعتمدة</p>
                                    <p class="text-[11px] text-emerald-800 font-medium">مشفر ومحمي في خوادم النظام</p>
                                </div>
                            </div>

                            <a
                                :href="lease.contract_image_url"
                                target="_blank"
                                class="h-9 px-3 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold transition inline-flex items-center gap-1.5 shadow-2xs shrink-0"
                            >
                                <ExternalLink class="w-3.5 h-3.5" />
                                <span>عرض / تحميل</span>
                            </a>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                @click="showContractUploadModal = true"
                                type="button"
                                class="flex-1 h-9 rounded-xl border border-[#D6E0E7] bg-white hover:bg-slate-50 text-[#163E3E] text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-2xs cursor-pointer"
                            >
                                <Upload class="w-3.5 h-3.5 text-[#163E3E]" />
                                <span>استبدال المرفق</span>
                            </button>
                            <button
                                @click="deleteContractImage"
                                type="button"
                                class="h-9 px-3 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold transition flex items-center justify-center gap-1 shadow-2xs cursor-pointer"
                                title="حذف المرفق"
                            >
                                <Trash2 class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>

                    <!-- حالة عدم وجود مرفق حتى الآن -->
                    <div v-else class="space-y-3">
                        <div class="p-3.5 rounded-2xl bg-[#F8FAFC] border border-dashed border-[#D6E0E7] text-center space-y-1.5">
                            <p class="text-xs font-bold text-[#5B6770]">لم يتم إرفاق نسخة العقد الورقي بعد</p>
                            <p class="text-[11px] text-[#8A9AA7]">يمكنك إرفاق صورة العقد أو ملف PDF لحفظه مشفراً بخصوصية تامة.</p>
                        </div>

                        <button
                            @click="showContractUploadModal = true"
                            type="button"
                            class="w-full h-10 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-2xs cursor-pointer"
                        >
                            <Upload class="w-4 h-4" />
                            <span>إرفاق وثيقة العقد الآن</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- العمود الأيسر (8 أعمدة على الديسكتوب): قسم تأمين الوحدة الشامل + فواتير الإيجار -->
            <div class="lg:col-span-8 space-y-5 sm:space-y-6">
                <!-- الصندوق الرئيسي لتأمين الوحدة المسترد (Security Deposit Hero Box) -->
                <div class="rounded-2xl sm:rounded-3xl border-2 border-[#163E3E]/30 bg-white p-4 sm:p-7 shadow-xs space-y-6">
                    <!-- عنوان القسم وحالة دورة الحياة -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-[#D6E0E7]">
                        <div class="flex items-start sm:items-center gap-3">
                            <div class="w-11 h-11 rounded-2xl bg-[#163E3E] text-white flex items-center justify-center shadow-xs shrink-0">
                                <ShieldCheck class="w-6 h-6 stroke-[2]" />
                            </div>
                            <div>
                                <h3 class="text-base sm:text-lg font-bold text-[#163E3E] flex items-center gap-2">
                                    <span>تأمين الوحدة المسترد</span>
                                </h3>
                                <p class="text-xs text-[#5B6770] mt-0.5">
                                    مبلغ مالي لحماية الوحدة أثناء العقد، محفوظ بشكل مستقل ولا يختلط بإيراد الإيجار
                                </p>
                            </div>
                        </div>

                        <!-- شارة حالة دورة حياة التأمين -->
                        <div>
                            <span class="text-xs font-bold px-3 py-1.5 rounded-xl border inline-block" :class="depositBadgeClass">
                                الحالة: {{ security_deposit.status_label }}
                            </span>
                        </div>
                    </div>

                    <!-- شريط الإجراءات السياقية المتوافقة مع الحالة الحالية -->
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-3 sm:gap-x-2.5">
                        <!-- زر تسجيل استلام دفعة تأمين -->
                        <button
                            v-if="security_deposit.actions.can_record_payment"
                            @click="showRecordPaymentModal = true"
                            type="button"
                            class="h-10 px-4 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer"
                        >
                            <CreditCard class="w-3.5 h-3.5" />
                            <span>تسجيل استلام دفعة تأمين</span>
                        </button>

                        <!-- زر بدء فحص الوحدة والتسوية -->
                        <button
                            v-if="security_deposit.actions.can_start_settlement"
                            @click="showStartSettlementModal = true"
                            type="button"
                            class="h-10 px-4 rounded-xl border border-[#163E3E] text-[#163E3E] hover:bg-[#E8F0F0] text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-2xs"
                        >
                            <Wrench class="w-3.5 h-3.5" />
                            <span>بدء فحص الوحدة والتسوية</span>
                        </button>

                        <!-- زر إضافة خصم تعويض أضرار -->
                        <button
                            v-if="security_deposit.actions.can_record_deduction"
                            @click="showRecordDeductionModal = true"
                            type="button"
                            class="h-10 px-4 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-2xs"
                        >
                            <MinusCircle class="w-3.5 h-3.5" />
                            <span>إضافة خصم أضرار مبرر</span>
                        </button>

                        <!-- زر تسجيل رد التأمين -->
                        <button
                            v-if="security_deposit.actions.can_record_refund"
                            @click="showRecordRefundModal = true"
                            type="button"
                            class="h-10 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer"
                        >
                            <RotateCcw class="w-3.5 h-3.5" />
                            <span>تسجيل رد التأمين للمستأجر</span>
                        </button>

                        <!-- زر اعتماد التسوية النهائية -->
                        <button
                            v-if="security_deposit.actions.can_settle"
                            @click="openFinalizeSettlementModal"
                            type="button"
                            class="h-10 px-4 rounded-xl bg-[#163E3E] hover:bg-[#1E3A5F] text-white text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer"
                        >
                            <FileCheck class="w-3.5 h-3.5" />
                            <span>اعتماد التسوية النهائية</span>
                        </button>
                    </div>

                    <!-- المصفوفة المالية للتأمين + شريط نسبة التحصيل -->
                    <div class="space-y-3">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-3 text-xs">
                            <div class="p-3 sm:p-3.5 rounded-2xl bg-[#F8FAFC] border border-[#D6E0E7] space-y-1">
                                <span class="text-[#5B6770] block text-[11px]">التأمين المتفق عليه</span>
                                <span class="font-black text-[#163E3E] font-mono text-base block">
                                    {{ security_deposit.agreed_amount.toLocaleString() }}
                                    <span class="text-[10px] font-sans font-normal text-[#5B6770]">{{ currency_symbol }}</span>
                                </span>
                            </div>

                            <div class="p-3 sm:p-3.5 rounded-2xl bg-emerald-50/70 border border-emerald-200 space-y-1">
                                <span class="text-emerald-800 block text-[11px] font-semibold">المستلم الفعلي</span>
                                <span class="font-black text-emerald-700 font-mono text-base block">
                                    {{ security_deposit.total_received.toLocaleString() }}
                                    <span class="text-[10px] font-sans font-normal text-emerald-600">{{ currency_symbol }}</span>
                                </span>
                            </div>

                            <div class="p-3 sm:p-3.5 rounded-2xl bg-rose-50/70 border border-rose-200 space-y-1">
                                <span class="text-rose-800 block text-[11px] font-semibold">الخصومات المعتمدة</span>
                                <span class="font-black text-rose-700 font-mono text-base block">
                                    {{ security_deposit.total_deductions.toLocaleString() }}
                                    <span class="text-[10px] font-sans font-normal text-rose-600">{{ currency_symbol }}</span>
                                </span>
                            </div>

                            <div class="p-3 sm:p-3.5 rounded-2xl bg-[#E8F0F0] border border-[#163E3E]/30 space-y-1">
                                <span class="text-[#163E3E] block text-[11px] font-bold">الرصيد المتاح للرد</span>
                                <span class="font-black text-[#163E3E] font-mono text-base block">
                                    {{ security_deposit.refundable_balance.toLocaleString() }}
                                    <span class="text-[10px] font-sans font-normal text-[#163E3E]">{{ currency_symbol }}</span>
                                </span>
                            </div>
                        </div>

                        <!-- شريط تقدم التحصيل -->
                        <div v-if="security_deposit.agreed_amount > 0" class="p-3 rounded-xl bg-[#F8FAFC] border border-[#D6E0E7] space-y-1.5">
                            <div class="flex items-center justify-between text-[11px] text-[#5B6770] font-semibold">
                                <span>نسبة استيفاء مبلغ التأمين من المستأجر:</span>
                                <span class="font-mono font-bold text-[#163E3E]">{{ depositPercentReceived }}%</span>
                            </div>
                            <div class="w-full h-2 bg-slate-200 rounded-full overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="depositPercentReceived >= 100 ? 'bg-emerald-600' : 'bg-[#163E3E]'"
                                    :style="{ width: `${depositPercentReceived}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <SettlementSummary
                        v-if="security_deposit.settlement"
                        :settlement="security_deposit.settlement"
                        :deductions="security_deposit.deductions"
                        :currency-symbol="currency_symbol"
                        :export-url="route('leases.security-deposit.export', lease.id)"
                        :statement-url="route('leases.security-deposit.statement', lease.id)"
                    />

                    <details class="rounded-2xl border border-[#D6E0E7] bg-white">
                        <summary class="flex cursor-pointer flex-wrap items-center justify-between gap-2 px-4 py-3 text-xs font-bold text-[#163E3E]">
                            <span>سجل الحركات التفصيلي</span>
                            <span class="font-normal text-[#5B6770]">
                                {{ security_deposit.payments.length }} دفعات · {{ security_deposit.deductions.length }} خصومات · {{ security_deposit.refunds.length }} عمليات رد
                            </span>
                        </summary>
                        <div class="space-y-4 border-t border-[#D6E0E7] p-3 sm:p-4">

                    <!-- ملخص تسوية المبلغ التأميني المعتمد وسجل التدقيق الشامل -->
                    <div v-if="security_deposit.settlement" class="p-4 sm:p-6 rounded-3xl border-2 border-blue-200 bg-gradient-to-br from-blue-50/70 to-white space-y-4 text-xs shadow-2xs">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-blue-200/80">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-xl bg-blue-600 text-white flex items-center justify-center shrink-0 shadow-2xs">
                                    <FileCheck class="w-4 h-4 stroke-[2.2]" />
                                </div>
                                <div>
                                    <h4 class="font-bold text-sm text-blue-950">ملخص تسوية المبلغ التأميني المعتمد</h4>
                                    <span class="text-[11px] text-blue-800">
                                        القرار: {{ security_deposit.settlement.settlement_type_label || 'تسوية معتمدة' }}
                                    </span>
                                </div>
                            </div>
                            <span class="font-bold text-blue-900 bg-blue-100/80 px-3 py-1 rounded-xl border border-blue-200 self-start sm:self-auto">
                                فحص الوحدة: {{ security_deposit.settlement.inspection_category_label }}
                            </span>
                        </div>

                        <!-- جدول ملخص التسوية المالي المعتمد -->
                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5 text-center">
                            <div class="p-2.5 rounded-xl bg-white border border-blue-200/80 space-y-1">
                                <span class="text-[10px] text-[#5B6770] block font-semibold">التأمين الأصلي</span>
                                <span class="font-mono font-bold text-xs text-[#163E3E] block">
                                    {{ (security_deposit.settlement.original_deposit || security_deposit.agreed_amount).toLocaleString() }}
                                    <span class="text-[9px] font-sans text-[#5B6770]">{{ currency_symbol }}</span>
                                </span>
                            </div>
                            <div class="p-2.5 rounded-xl bg-white border border-rose-200/80 space-y-1">
                                <span class="text-[10px] text-rose-800 block font-semibold">إجمالي الخصومات</span>
                                <span class="font-mono font-bold text-xs text-rose-700 block">
                                    -{{ (security_deposit.settlement.total_deductions || 0).toLocaleString() }}
                                    <span class="text-[9px] font-sans text-rose-600">{{ currency_symbol }}</span>
                                </span>
                            </div>
                            <div class="p-2.5 rounded-xl bg-white border border-[#163E3E]/40 space-y-1">
                                <span class="text-[10px] text-[#163E3E] block font-semibold">المستحق رده</span>
                                <span class="font-mono font-bold text-xs text-[#163E3E] block">
                                    {{ ((security_deposit.settlement.refundable_balance || 0) + (security_deposit.settlement.total_refunded || 0)).toLocaleString() }}
                                    <span class="text-[9px] font-sans text-[#163E3E]">{{ currency_symbol }}</span>
                                </span>
                            </div>
                            <div class="p-2.5 rounded-xl bg-white border border-emerald-200/80 space-y-1">
                                <span class="text-[10px] text-emerald-800 block font-semibold">المسدد فعلياً</span>
                                <span class="font-mono font-bold text-xs text-emerald-700 block">
                                    {{ (security_deposit.settlement.total_refunded || 0).toLocaleString() }}
                                    <span class="text-[9px] font-sans text-emerald-600">{{ currency_symbol }}</span>
                                </span>
                            </div>
                            <div class="p-2.5 rounded-xl bg-white border border-blue-200/80 space-y-1 col-span-2 sm:col-span-1">
                                <span class="text-[10px] text-[#163E3E] block font-semibold">المتبقي للصرف</span>
                                <span class="font-mono font-bold text-xs text-[#163E3E] block">
                                    {{ (security_deposit.settlement.refundable_balance || 0).toLocaleString() }}
                                    <span class="text-[9px] font-sans text-[#5B6770]">{{ currency_symbol }}</span>
                                </span>
                            </div>
                        </div>

                        <!-- كارت حالة الرد والصرف الفعلي المنفصل تماماً عن الحساب -->
                        <div class="p-3.5 rounded-2xl border" :class="security_deposit.settlement.refund_status === 'refunded' ? 'bg-emerald-50/80 border-emerald-300 text-emerald-950' : (security_deposit.settlement.refund_status === 'pending' ? 'bg-amber-50/90 border-amber-300 text-amber-950' : 'bg-slate-50 border-slate-200 text-slate-800')">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0" :class="security_deposit.settlement.refund_status === 'refunded' ? 'bg-emerald-200 text-emerald-800' : (security_deposit.settlement.refund_status === 'pending' ? 'bg-amber-200 text-amber-800' : 'bg-slate-200 text-slate-700')">
                                        <CheckCircle2 v-if="security_deposit.settlement.refund_status === 'refunded'" class="w-4 h-4 stroke-[2.3]" />
                                        <Clock v-else-if="security_deposit.settlement.refund_status === 'pending'" class="w-4 h-4 stroke-[2.3]" />
                                        <Ban v-else class="w-4 h-4 stroke-[2.3]" />
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-xs sm:text-sm">
                                                حالة الرد الفعلي: {{ security_deposit.settlement.refund_status_label || 'معلق' }}
                                            </span>
                                        </div>
                                        <span v-if="security_deposit.settlement.refund_status === 'pending'" class="text-[11px] text-amber-900/80 block mt-0.5">
                                            تم اعتماد استحقاق الرد للمستأجر، ولكن المبلغ لم يتم صرفه أو تحويله بعد.
                                        </span>
                                        <span v-else-if="security_deposit.settlement.refund_status === 'refunded'" class="text-[11px] text-emerald-900/80 block mt-0.5">
                                            تم توثيق صرف المبلغ للمستأجر بتاريخ {{ security_deposit.settlement.refund_payment_date || 'اليوم' }} عبر ({{ security_deposit.settlement.refund_method || 'تحويل' }}).
                                        </span>
                                        <span v-else class="text-[11px] text-slate-600 block mt-0.5">
                                            تم الاحتفاظ بالمبلغ التأميني بالكامل وفق أسباب التسوية المعتمدة.
                                        </span>
                                    </div>
                                </div>

                                <!-- زر تسجيل صرف الرد المعلق لاحقاً -->
                                <button
                                    v-if="security_deposit.settlement.refund_status === 'pending' && (security_deposit.settlement.refundable_balance || 0) > 0"
                                    type="button"
                                    @click="showPendingRefundModal = true"
                                    class="px-4 py-2 rounded-xl bg-amber-800 hover:bg-amber-900 text-white text-xs font-bold transition flex items-center justify-center gap-1.5 shrink-0 shadow-2xs cursor-pointer self-start sm:self-auto"
                                >
                                    <RotateCcw class="w-3.5 h-3.5" />
                                    <span>تسجيل صرف التأمين للمستأجر</span>
                                </button>
                            </div>
                        </div>

                        <!-- سبب الاحتفاظ بالتأمين في حالة عدم الرد -->
                        <div v-if="security_deposit.settlement.retention_reason" class="p-3 bg-white/80 rounded-xl border border-blue-200 text-xs space-y-1">
                            <span class="font-bold text-blue-950 block">أسباب الاحتفاظ بالمبلغ التأميني:</span>
                            <p class="text-blue-900 leading-relaxed">{{ security_deposit.settlement.retention_reason }}</p>
                        </div>

                        <!-- ملاحظات فحص الوحدة والتسوية -->
                        <p v-if="security_deposit.settlement.inspection_notes" class="text-blue-950 leading-relaxed bg-white/70 p-3 rounded-xl border border-blue-200">
                            {{ security_deposit.settlement.inspection_notes }}
                        </p>

                        <div v-if="security_deposit.settlement.settled_at" class="text-[11px] text-blue-700 pt-2 border-t border-blue-200/80 flex items-center gap-1">
                            <CheckCircle2 class="w-3.5 h-3.5 text-blue-600" />
                            <span>تم اعتماد التسوية بواسطة {{ security_deposit.settlement.settled_by }} في {{ security_deposit.settlement.settled_at }}</span>
                        </div>
                    </div>

                    <!-- سجل الحركات المالية الموثقة للتأمين (Audit Trail) -->
                    <div class="space-y-6 pt-2">
                        <div class="flex items-center gap-2 pb-2 border-b border-[#D6E0E7]">
                            <History class="w-4 h-4 text-[#163E3E]" />
                            <h4 class="text-sm font-bold text-[#163E3E]">سجل الحركات المالية الموثقة للتأمين</h4>
                        </div>

                        <!-- 1. سندات استلام التأمين -->
                        <div class="space-y-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-[#5B6770]">سندات استلام التأمين ({{ security_deposit.payments.length }})</span>
                            </div>

                            <div v-if="security_deposit.payments.length > 0">
                                <!-- عرض الموبايل: بطاقات دفعات التأمين (Mobile Cards for < sm) -->
                                <div class="block sm:hidden space-y-2.5">
                                    <div
                                        v-for="p in security_deposit.payments"
                                        :key="p.id"
                                        class="p-3.5 rounded-xl border border-[#D6E0E7] bg-white space-y-2 shadow-2xs"
                                        :class="p.is_reversed ? 'opacity-60 bg-slate-50' : ''"
                                    >
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="font-mono font-bold text-[#163E3E]">{{ p.reference }}</span>
                                            <span v-if="p.is_reversed" class="text-rose-700 bg-rose-50 px-2 py-0.5 rounded font-bold text-[10px]">ملغاة</span>
                                            <span v-else class="text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded font-bold text-[10px]">سارية</span>
                                        </div>
                                        <div class="flex items-baseline justify-between">
                                            <span class="font-mono font-black text-base text-[#163E3E]">
                                                {{ p.amount.toLocaleString() }} {{ currency_symbol }}
                                            </span>
                                            <span class="text-xs text-[#5B6770]">{{ p.payment_method_label }}</span>
                                        </div>
                                        <div class="flex items-center justify-between text-[11px] text-[#5B6770] pt-1.5 border-t border-[#D6E0E7]/60">
                                            <span class="font-mono">{{ p.paid_at }}</span>
                                            <span>{{ p.recorded_by }}</span>
                                        </div>
                                        <div v-if="!p.is_reversed && security_deposit.status !== 'settled'" class="pt-1 text-left">
                                            <button
                                                @click="openReversePayment(p)"
                                                type="button"
                                                class="text-rose-600 hover:text-rose-800 text-xs font-bold cursor-pointer"
                                            >
                                                عكس الدفعة
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- عرض الديسكتوب: جدول دفعات التأمين (Desktop Table for sm+) -->
                                <div class="hidden sm:block rounded-2xl border border-[#D6E0E7] overflow-hidden">
                                    <table class="w-full text-right text-xs">
                                        <thead class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] font-bold">
                                            <tr>
                                                <th class="py-3 px-4">المرجع</th>
                                                <th class="py-3 px-4">المبلغ</th>
                                                <th class="py-3 px-4">طريقة الدفع</th>
                                                <th class="py-3 px-4">التاريخ</th>
                                                <th class="py-3 px-4">المسجل</th>
                                                <th class="py-3 px-4">الحالة</th>
                                                <th class="py-3 px-4 text-left">إجراء</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-[#D6E0E7]">
                                            <tr v-for="p in security_deposit.payments" :key="p.id" :class="p.is_reversed ? 'bg-slate-50 opacity-60 line-through' : 'hover:bg-[#F8FAFC] transition'">
                                                <td class="py-3 px-4 font-mono font-bold text-[#163E3E]">{{ p.reference }}</td>
                                                <td class="py-3 px-4 font-mono font-bold text-[#163E3E]">{{ p.amount.toLocaleString() }} {{ currency_symbol }}</td>
                                                <td class="py-3 px-4">{{ p.payment_method_label }}</td>
                                                <td class="py-3 px-4 font-mono text-[#5B6770]">{{ p.paid_at }}</td>
                                                <td class="py-3 px-4 text-[#5B6770]">{{ p.recorded_by }}</td>
                                                <td class="py-3 px-4">
                                                    <span v-if="p.is_reversed" class="text-rose-700 bg-rose-50 px-2 py-0.5 rounded font-bold">
                                                        ملغاة: {{ p.reversal_reason }}
                                                    </span>
                                                    <span v-else class="text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded font-bold">
                                                        سارية
                                                    </span>
                                                </td>
                                                <td class="py-3 px-4 text-left">
                                                    <button
                                                        v-if="!p.is_reversed && security_deposit.status !== 'settled'"
                                                        @click="openReversePayment(p)"
                                                        type="button"
                                                        class="text-rose-600 hover:text-rose-800 text-xs font-semibold cursor-pointer"
                                                    >
                                                        عكس الدفعة
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <p v-else class="text-xs text-slate-400 p-3.5 bg-[#F8FAFC] rounded-2xl border border-dashed border-[#D6E0E7] text-center">
                                لم يتم تسجيل أي دفعة تأمين حتى الآن.
                            </p>
                        </div>

                        <!-- 2. خصومات التأمين -->
                        <div class="space-y-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-[#5B6770]">خصومات التأمين ({{ security_deposit.deductions.length }})</span>
                            </div>

                            <div v-if="security_deposit.deductions.length > 0">
                                <!-- عرض الموبايل: بطاقات الخصومات (Mobile Cards for < sm) -->
                                <div class="block sm:hidden space-y-2.5">
                                    <div
                                        v-for="d in security_deposit.deductions"
                                        :key="d.id"
                                        class="p-3.5 rounded-xl border border-rose-200 bg-rose-50/40 space-y-2 shadow-2xs"
                                    >
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="font-bold text-rose-900 bg-rose-100 px-2 py-0.5 rounded">{{ d.category_label }}</span>
                                            <span class="font-mono font-black text-rose-700 text-sm">
                                                -{{ d.amount.toLocaleString() }} {{ currency_symbol }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-[#163E3E] leading-relaxed">{{ d.description }}</p>
                                        <p v-if="d.notes" class="text-[11px] text-[#5B6770] bg-white/70 p-2 rounded-lg border border-rose-200/60">{{ d.notes }}</p>
                                        <div v-if="d.attachment_url" class="pt-0.5">
                                            <a :href="d.attachment_url" target="_blank" class="inline-flex items-center gap-1 text-[11px] text-[#163E3E] hover:underline font-bold">
                                                <FileText class="w-3 h-3" />
                                                <span>عرض المرفق / الفاتورة</span>
                                            </a>
                                        </div>
                                        <div class="flex items-center justify-between text-[11px] text-[#5B6770] pt-1.5 border-t border-rose-200/60">
                                            <span class="font-mono">{{ d.deducted_at }}</span>
                                            <div v-if="security_deposit.status !== 'settled'" class="flex items-center gap-2">
                                                <button
                                                    @click="openEditDeductionModal(d)"
                                                    type="button"
                                                    class="text-[#163E3E] hover:text-[#1E3A5F] font-bold text-xs cursor-pointer"
                                                >
                                                    تعديل
                                                </button>
                                                <button
                                                    @click="deleteDeduction(d.id)"
                                                    type="button"
                                                    class="text-rose-700 hover:text-rose-900 font-bold flex items-center gap-1 cursor-pointer"
                                                >
                                                    <Trash2 class="w-3.5 h-3.5" />
                                                    <span>حذف</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- عرض الديسكتوب: جدول الخصومات (Desktop Table for sm+) -->
                                <div class="hidden sm:block rounded-2xl border border-[#D6E0E7] overflow-hidden">
                                    <table class="w-full text-right text-xs">
                                        <thead class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] font-bold">
                                            <tr>
                                                <th class="py-3 px-4">البند والتصنيف</th>
                                                <th class="py-3 px-4">المبلغ المخصوم</th>
                                                <th class="py-3 px-4">الوصف والمبرر</th>
                                                <th class="py-3 px-4">التاريخ</th>
                                                <th class="py-3 px-4">المسجل</th>
                                                <th class="py-3 px-4 text-left" v-if="security_deposit.status !== 'settled'">إجراءات</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-[#D6E0E7]">
                                            <tr v-for="d in security_deposit.deductions" :key="d.id" class="hover:bg-[#F8FAFC] transition">
                                                <td class="py-3 px-4 font-bold text-[#163E3E]">{{ d.category_label }}</td>
                                                <td class="py-3 px-4 font-mono font-bold text-rose-700">{{ d.amount.toLocaleString() }} {{ currency_symbol }}</td>
                                                <td class="py-3 px-4 text-[#5B6770]">
                                                    <div>{{ d.description }}</div>
                                                    <div v-if="d.notes" class="text-[11px] text-[#5B6770]/80 mt-0.5">{{ d.notes }}</div>
                                                    <div v-if="d.attachment_url" class="mt-1">
                                                        <a :href="d.attachment_url" target="_blank" class="inline-flex items-center gap-1 text-[#163E3E] hover:underline font-bold text-[11px]">
                                                            <FileText class="w-3 h-3" />
                                                            <span>عرض المرفق</span>
                                                        </a>
                                                    </div>
                                                </td>
                                                <td class="py-3 px-4 font-mono text-[#5B6770]">{{ d.deducted_at }}</td>
                                                <td class="py-3 px-4 text-[#5B6770]">{{ d.recorded_by }}</td>
                                                <td class="py-3 px-4 text-left" v-if="security_deposit.status !== 'settled'">
                                                    <div class="flex items-center gap-2.5 justify-end">
                                                        <button
                                                            @click="openEditDeductionModal(d)"
                                                            type="button"
                                                            class="text-[#163E3E] hover:text-[#1E3A5F] font-bold text-xs cursor-pointer"
                                                            title="تعديل بند الخصم"
                                                        >
                                                            تعديل
                                                        </button>
                                                        <button
                                                            @click="deleteDeduction(d.id)"
                                                            type="button"
                                                            class="text-rose-600 hover:text-rose-800 p-1 rounded hover:bg-rose-50 cursor-pointer"
                                                            title="إلغاء الخصم"
                                                        >
                                                            <Trash2 class="w-3.5 h-3.5" />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <p v-else class="text-xs text-slate-400 p-3.5 bg-[#F8FAFC] rounded-2xl border border-dashed border-[#D6E0E7] text-center">
                                لا توجد أي خصومات مسجلة على التأمين (الوحدة بدون أضرار).
                            </p>
                        </div>

                        <!-- 3. المبالغ المستردة للمستأجر -->
                        <div class="space-y-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-[#5B6770]">المبالغ المستردة للمستأجر ({{ security_deposit.refunds.length }})</span>
                            </div>

                            <div v-if="security_deposit.refunds.length > 0">
                                <!-- عرض الموبايل: بطاقات المبالغ المستردة (Mobile Cards for < sm) -->
                                <div class="block sm:hidden space-y-2.5">
                                    <div
                                        v-for="r in security_deposit.refunds"
                                        :key="r.id"
                                        class="p-3.5 rounded-xl border border-emerald-200 bg-emerald-50/40 space-y-2 shadow-2xs"
                                    >
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="font-mono font-bold text-emerald-800">{{ r.reference }}</span>
                                            <span class="font-mono font-black text-emerald-700 text-sm">
                                                {{ r.amount.toLocaleString() }} {{ currency_symbol }}
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between text-xs text-[#5B6770]">
                                            <span>طريقة التحويل: {{ r.payment_method_label }}</span>
                                            <span class="font-mono">{{ r.refunded_at }}</span>
                                        </div>
                                        <p v-if="r.notes" class="text-xs text-[#163E3E] pt-1 border-t border-emerald-200/60">{{ r.notes }}</p>
                                    </div>
                                </div>

                                <!-- عرض الديسكتوب: جدول المبالغ المستردة (Desktop Table for sm+) -->
                                <div class="hidden sm:block rounded-2xl border border-[#D6E0E7] overflow-hidden">
                                    <table class="w-full text-right text-xs">
                                        <thead class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] font-bold">
                                            <tr>
                                                <th class="py-3 px-4">المرجع</th>
                                                <th class="py-3 px-4">المبلغ المسترد</th>
                                                <th class="py-3 px-4">طريقة التحويل</th>
                                                <th class="py-3 px-4">تاريخ الرد</th>
                                                <th class="py-3 px-4">المسجل</th>
                                                <th class="py-3 px-4">ملاحظات</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-[#D6E0E7]">
                                            <tr v-for="r in security_deposit.refunds" :key="r.id" class="hover:bg-[#F8FAFC] transition">
                                                <td class="py-3 px-4 font-mono font-bold text-emerald-700">{{ r.reference }}</td>
                                                <td class="py-3 px-4 font-mono font-bold text-emerald-700">{{ r.amount.toLocaleString() }} {{ currency_symbol }}</td>
                                                <td class="py-3 px-4">{{ r.payment_method_label }}</td>
                                                <td class="py-3 px-4 font-mono text-[#5B6770]">{{ r.refunded_at }}</td>
                                                <td class="py-3 px-4 text-[#5B6770]">{{ r.recorded_by }}</td>
                                                <td class="py-3 px-4 text-[#5B6770]">{{ r.notes || '—' }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <p v-else class="text-xs text-slate-400 p-3.5 bg-[#F8FAFC] rounded-2xl border border-dashed border-[#D6E0E7] text-center">
                                لم يتم توثيق أي رد لمبلغ التأمين حتى الآن.
                            </p>
                        </div>
                    </div>
                        </div>
                    </details>
                </div>

                <!-- جدول استحقاقات وفواتير الإيجار (Rent Charges) للتمييز التام والواضح -->
                <div class="rounded-2xl sm:rounded-3xl border border-[#D6E0E7] bg-white p-4 sm:p-6 shadow-2xs space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7]">
                        <div class="flex items-center gap-2">
                            <FileText class="w-4 h-4 text-[#163E3E]" />
                            <h3 class="text-sm font-bold text-[#163E3E]">فواتير واستحقاقات الإيجار (منفصلة تماماً عن التأمين)</h3>
                        </div>
                        <span class="text-xs text-[#5B6770]">{{ rent_charges.length }} فترة إيجارية</span>
                    </div>

                    <!-- عرض الموبايل: بطاقات فواتير الإيجار (Mobile Cards for < sm) -->
                    <div class="block sm:hidden space-y-3">
                        <div
                            v-for="c in rent_charges"
                            :key="c.id"
                            class="p-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] space-y-2.5 shadow-2xs"
                        >
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-[#163E3E]">{{ c.period }}</span>
                                <span v-if="c.status === 'paid'" class="text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded font-bold text-[10px]">مسدد بالكامل</span>
                                <span v-else-if="c.status === 'overdue'" class="text-rose-700 bg-rose-50 px-2 py-0.5 rounded font-bold text-[10px]">متأخر</span>
                                <span v-else class="text-amber-700 bg-amber-50 px-2 py-0.5 rounded font-bold text-[10px]">مستحق</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2 text-xs">
                                <div>
                                    <span class="text-[10px] text-[#5B6770] block">المطلوب</span>
                                    <span class="font-mono font-bold text-[#163E3E]">{{ c.amount.toLocaleString() }}</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-[#5B6770] block">المسدد</span>
                                    <span class="font-mono font-bold text-emerald-700">{{ c.paid_amount.toLocaleString() }}</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-[#5B6770] block">المتبقي</span>
                                    <span class="font-mono font-bold" :class="c.outstanding_balance > 0 ? 'text-amber-700' : 'text-slate-400'">
                                        {{ c.outstanding_balance.toLocaleString() }}
                                    </span>
                                </div>
                            </div>
                            <div class="text-[11px] text-[#5B6770] font-mono pt-1.5 border-t border-[#D6E0E7]/60">
                                استحقاق: {{ c.due_date }}
                            </div>
                        </div>
                    </div>

                    <!-- عرض الديسكتوب: جدول استحقاقات الإيجار (Desktop Table for sm+) -->
                    <div class="hidden sm:block rounded-2xl border border-[#D6E0E7] overflow-hidden">
                        <table class="w-full text-right text-xs">
                            <thead class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] font-bold">
                                <tr>
                                    <th class="py-3 px-4">الفترة الإيجارية</th>
                                    <th class="py-3 px-4">تاريخ الاستحقاق</th>
                                    <th class="py-3 px-4">القيمة الإيجارية</th>
                                    <th class="py-3 px-4">المسدد</th>
                                    <th class="py-3 px-4">المتبقي</th>
                                    <th class="py-3 px-4">الحالة</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#D6E0E7]">
                                <tr v-for="c in rent_charges" :key="c.id" class="hover:bg-[#F8FAFC] transition">
                                    <td class="py-3 px-4 font-bold text-[#163E3E]">{{ c.period }}</td>
                                    <td class="py-3 px-4 font-mono text-[#5B6770]">{{ c.due_date }}</td>
                                    <td class="py-3 px-4 font-mono font-bold text-[#163E3E]">{{ c.amount.toLocaleString() }} {{ currency_symbol }}</td>
                                    <td class="py-3 px-4 font-mono text-emerald-700 font-bold">{{ c.paid_amount.toLocaleString() }} {{ currency_symbol }}</td>
                                    <td class="py-3 px-4 font-mono font-bold" :class="c.outstanding_balance > 0 ? 'text-amber-700' : 'text-slate-400'">
                                        {{ c.outstanding_balance.toLocaleString() }} {{ currency_symbol }}
                                    </td>
                                    <td class="py-3 px-4">
                                        <span v-if="c.status === 'paid'" class="text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded font-bold">مسدد بالكامل</span>
                                        <span v-else-if="c.status === 'overdue'" class="text-rose-700 bg-rose-50 px-2 py-0.5 rounded font-bold">متأخر</span>
                                        <span v-else class="text-amber-700 bg-amber-50 px-2 py-0.5 rounded font-bold">مستحق قريباً</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- مودال 1: تسجيل استلام دفعة تأمين -->
        <div v-if="showRecordPaymentModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showRecordPaymentModal = false" class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-5 sm:p-7 z-10 text-right animate-in fade-in zoom-in-95" dir="rtl">
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7] mb-4">
                    <h3 class="text-sm font-bold text-[#163E3E]">تسجيل استلام دفعة تأمين</h3>
                    <button @click="showRecordPaymentModal = false" type="button" class="p-1 text-slate-400 hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitPayment" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">المبلغ المحصل *</label>
                        <div class="relative">
                            <input
                                v-model="paymentForm.amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                required
                                class="w-full h-11 pl-12 pr-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm font-mono font-bold"
                            />
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-[#5B6770] font-semibold">
                                {{ currency_symbol }}
                            </span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">طريقة السداد *</label>
                        <select v-model="paymentForm.payment_method" class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs">
                            <option value="cash">نقدي (كاش)</option>
                            <option value="bank_transfer">تحويل بنكي</option>
                            <option value="cheque">شيك مصرفي</option>
                            <option value="other">إنستاباي / فيزا / إلكتروني</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">تاريخ التحصيل *</label>
                        <input v-model="paymentForm.paid_at" type="date" required class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-right" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">رقم المرجع / الإيصال</label>
                        <input v-model="paymentForm.reference" type="text" placeholder="مثال: REC-10293" class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-right" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">ملاحظات إضافية</label>
                        <textarea v-model="paymentForm.notes" rows="2" class="w-full p-2.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-right"></textarea>
                    </div>

                    <div class="pt-3 border-t border-[#D6E0E7] flex justify-end gap-2">
                        <button @click="showRecordPaymentModal = false" type="button" class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold">إلغاء</button>
                        <button type="submit" class="h-10 px-4 rounded-xl bg-[#163E3E] text-white text-xs font-bold">تأكيد تسجيل الدفعة</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- مودال 2: بدء فحص الوحدة والتسوية -->
        <div v-if="showStartSettlementModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showStartSettlementModal = false" class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-5 sm:p-7 z-10 text-right animate-in fade-in zoom-in-95" dir="rtl">
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7] mb-4">
                    <h3 class="text-sm font-bold text-[#163E3E]">بدء فحص حالة الوحدة والتسوية</h3>
                    <button @click="showStartSettlementModal = false" type="button" class="p-1 text-slate-400 hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitStartSettlement" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">نتيجة فحص الوحدة *</label>
                        <select v-model="settlementForm.inspection_category" class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs">
                            <option value="no_issues">لا توجد ملاحظات أو أضرار (حالة ممتازة)</option>
                            <option value="furniture_damage">تلف أثاث أو أجهزة</option>
                            <option value="fixture_damage">تلف تركيبات ومرافق</option>
                            <option value="missing_items">مفقودات ومحتويات ناقصة</option>
                            <option value="other_damage">أضرار موثقة أخرى</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">ملاحظات وتقرير الفحص الميداني</label>
                        <textarea v-model="settlementForm.inspection_notes" rows="3" placeholder="وصف حالة الوحدة عند التسليم، الجدران، الأرضيات، التركيبات..." class="w-full p-2.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-right"></textarea>
                    </div>

                    <div class="pt-3 border-t border-[#D6E0E7] flex justify-end gap-2">
                        <button @click="showStartSettlementModal = false" type="button" class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold">إلغاء</button>
                        <button type="submit" class="h-10 px-4 rounded-xl bg-[#163E3E] text-white text-xs font-bold">بدء التسوية</button>
                    </div>
                </form>
            </div>
        </div>

        <DeductionForm
            v-model:show="showRecordDeductionModal"
            :lease-id="lease.id"
            :available-balance="security_deposit.refundable_balance"
            :currency-symbol="currency_symbol"
        />

        <!-- مودال 4: تسجيل رد التأمين -->
        <div v-if="showRecordRefundModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showRecordRefundModal = false" class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-5 sm:p-7 z-10 text-right animate-in fade-in zoom-in-95" dir="rtl">
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7] mb-4">
                    <h3 class="text-sm font-bold text-[#163E3E]">تسجيل رد مبلغ التأمين للمستأجر</h3>
                    <button @click="showRecordRefundModal = false" type="button" class="p-1 text-slate-400 hover:text-slate-600">
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
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">المبلغ المردود *</label>
                        <div class="relative">
                            <input
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
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">طريقة الرد *</label>
                        <select v-model="refundForm.payment_method" class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs">
                            <option value="bank_transfer">تحويل بنكي</option>
                            <option value="cash">نقدي (كاش)</option>
                            <option value="cheque">شيك</option>
                            <option value="other">إنستاباي / محفظة إلكترونية</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">تاريخ الرد الفعلي *</label>
                        <input v-model="refundForm.refunded_at" type="date" required class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-right" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">رقم مرجع التحويل / الإيصال</label>
                        <input v-model="refundForm.reference" type="text" placeholder="مثال: TXN-54321" class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-right" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">ملاحظات الرد</label>
                        <textarea v-model="refundForm.notes" rows="2" class="w-full p-2.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-right"></textarea>
                    </div>

                    <div class="pt-3 border-t border-[#D6E0E7] flex justify-end gap-2">
                        <button @click="showRecordRefundModal = false" type="button" class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold">إلغاء</button>
                        <button type="submit" class="h-10 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">توثيق الرد الفعلي</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- مودال 5: اعتماد التسوية النهائية للتأمين -->
        <div v-if="showFinalizeSettlementModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showFinalizeSettlementModal = false" class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-4xl bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-5 sm:p-7 z-10 text-right max-h-[92vh] overflow-y-auto animate-in fade-in zoom-in-95" dir="rtl">
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
                    <button @click="showFinalizeSettlementModal = false" type="button" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-xl hover:bg-slate-100 transition">
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
                        :available-deposit="availableDepositForSettlement"
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
                                الرصيد الأصلي المتاح: {{ availableDepositForSettlement.toLocaleString() }} {{ currency_symbol }}
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
                                    'p-3 rounded-xl border cursor-pointer text-right flex items-center justify-between transition',
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
                                    'p-3 rounded-xl border cursor-pointer text-right flex items-center justify-between transition',
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
                                <label class="block text-[11px] font-bold text-emerald-950 mb-1">طريقة الرد *</label>
                                <select v-model="finalizeSettlementForm.refund_method" class="w-full h-10 px-2.5 rounded-xl border border-emerald-200 bg-white text-xs">
                                    <option value="bank_transfer">تحويل بنكي</option>
                                    <option value="cash">نقدي (كاش)</option>
                                    <option value="cheque">شيك</option>
                                    <option value="other">إنستاباي / أخرى</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-emerald-950 mb-1">تاريخ الرد الفعلي *</label>
                                <input v-model="finalizeSettlementForm.refund_payment_date" type="date" required class="w-full h-10 px-2.5 rounded-xl border border-emerald-200 bg-white text-xs text-right font-mono" />
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-emerald-950 mb-1">رقم المرجع / الإيصال</label>
                                <input v-model="finalizeSettlementForm.refund_reference" type="text" placeholder="TXN-..." class="w-full h-10 px-2.5 rounded-xl border border-emerald-200 bg-white text-xs text-right font-mono" />
                            </div>
                        </div>
                    </div>

                    <!-- تقرير فحص الوحدة وملاحظات التسوية -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-[#D6E0E7]">
                        <div>
                            <label class="block text-xs font-bold text-[#163E3E] mb-1">التقرير النهائي لفحص الوحدة *</label>
                            <select v-model="finalizeSettlementForm.inspection_category" class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs">
                                <option value="no_issues">لا توجد ملاحظات أو أضرار (حالة سليمة)</option>
                                <option value="furniture_damage">تلف أثاث أو أجهزة</option>
                                <option value="fixture_damage">تلف تركيبات ومرافق</option>
                                <option value="missing_items">مفقودات ومحتويات ناقصة</option>
                                <option value="other_damage">أضرار موثقة أخرى</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-[#163E3E] mb-1">ملاحظات الإغلاق النهائي للتسوية</label>
                            <input v-model="finalizeSettlementForm.notes" type="text" placeholder="أي ملاحظات ختامية للتسوية..." class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-right" />
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
                            class="h-10 px-5 rounded-xl bg-[#163E3E] hover:bg-[#1E3A5F] text-white text-xs font-bold transition flex items-center gap-1.5 shadow-xs disabled:opacity-50 cursor-pointer"
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
            <div class="relative w-full max-w-lg bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-5 sm:p-7 z-10 text-right animate-in fade-in zoom-in-95" dir="rtl">
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7] mb-4">
                    <h3 class="text-sm font-bold text-[#163E3E]">تعديل بند الخصم</h3>
                    <button @click="showEditDeductionModal = false" type="button" class="p-1 text-slate-400 hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div v-if="Object.keys(editDeductionForm.errors).length > 0" class="p-3 mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1">
                    <div v-for="(err, k) in editDeductionForm.errors" :key="k">{{ err }}</div>
                </div>

                <form @submit.prevent="submitEditDeduction" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">سبب / تصنيف الخصم *</label>
                        <select v-model="editDeductionForm.category" required class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs">
                            <option value="unit_damage">تلفيات في الوحدة</option>
                            <option value="maintenance">إصلاحات وصيانة</option>
                            <option value="unpaid_dues">مستحقات غير مدفوعة</option>
                            <option value="cleaning">تنظيف الوحدة</option>
                            <option value="other">أخرى</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">مبلغ الخصم (بالجنيه المصري) *</label>
                        <div class="relative">
                            <input
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
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">
                            {{ editDeductionForm.category === 'other' ? 'توضيح سبب الخصم (مطلوب عند اختيار أخرى) *' : 'بيان ومبرر الخصم *' }}
                        </label>
                        <input
                            v-model="editDeductionForm.description"
                            type="text"
                            required
                            maxlength="1000"
                            placeholder="وصف وتوضيح سبب الاستقطاع..."
                            class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-[#5B6770] mb-1">ملاحظات إضافية (اختياري)</label>
                        <textarea
                            v-model="editDeductionForm.notes"
                            rows="2"
                            maxlength="1000"
                            class="w-full p-2.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-right"
                        ></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-[#5B6770] mb-1">مرفق إثبات جديد (اختياري)</label>
                        <input
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
                        <button @click="showEditDeductionModal = false" type="button" class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold">إلغاء</button>
                        <button type="submit" :disabled="editDeductionForm.processing" class="h-10 px-5 rounded-xl bg-[#163E3E] hover:bg-[#1E3A5F] text-white text-xs font-bold">حفظ التعديلات</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- مودال 6: عكس دفعة تأمين -->
        <div v-if="showReversePaymentModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showReversePaymentModal = false" class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-5 sm:p-7 z-10 text-right animate-in fade-in zoom-in-95" dir="rtl">
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7] mb-4">
                    <h3 class="text-sm font-bold text-rose-700">عكس وإلغاء دفعة تأمين مسجلة</h3>
                    <button @click="showReversePaymentModal = false" type="button" class="p-1 text-slate-400 hover:text-slate-600">
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
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">سبب إلغاء وعكس الدفعة *</label>
                        <textarea v-model="reversePaymentForm.reversal_reason" rows="3" placeholder="اذكر سبب الإلغاء (سداد بالخطأ، شيك مرتجع، تصحيح محاسبي)..." required class="w-full p-2.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-right"></textarea>
                    </div>

                    <div class="pt-3 border-t border-[#D6E0E7] flex justify-end gap-2">
                        <button @click="showReversePaymentModal = false" type="button" class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold">إلغاء</button>
                        <button type="submit" class="h-10 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold">تأكيد عكس الدفعة</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- مودال تمديد / تجديد العقد -->
        <div v-if="showExtendModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showExtendModal = false" class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-lg bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-5 sm:p-7 z-10 text-right animate-in fade-in zoom-in-95" dir="rtl">
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
                    <button @click="showExtendModal = false" type="button" class="p-1 text-slate-400 hover:text-slate-600">
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
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">تاريخ نهاية العقد الجديد *</label>
                        <input
                            v-model="extendForm.ends_on"
                            type="date"
                            :min="lease.ends_on"
                            required
                            class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-right font-mono"
                        />
                        <p class="text-[11px] text-[#5B6770] mt-1">يجب أن يكون التاريخ بعد تاريخ النهاية الحالي للعقد.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">القيمة الإيجارية الشهرية الجديدة</label>
                        <div class="relative">
                            <input
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
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">ملاحظات التجديد</label>
                        <textarea
                            v-model="extendForm.notes"
                            rows="2"
                            placeholder="ملاحظات توثيقية حول تجديد العقد والاتفاق مع المستأجر..."
                            class="w-full p-2.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-right"
                        ></textarea>
                    </div>

                    <div class="pt-3 border-t border-[#D6E0E7] flex justify-end gap-2">
                        <button @click="showExtendModal = false" type="button" class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold hover:bg-slate-50">إلغاء</button>
                        <button
                            type="submit"
                            :disabled="extendForm.processing"
                            class="h-10 px-5 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold transition flex items-center gap-1.5 shadow-xs disabled:opacity-50"
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
            <div class="relative w-full max-w-3xl bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-5 sm:p-7 z-10 text-right max-h-[90vh] overflow-y-auto animate-in fade-in zoom-in-95" dir="rtl">
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
                    <button @click="showTerminateWithSettlementModal = false" type="button" class="p-1 text-slate-400 hover:text-slate-600">
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
                        <label class="block text-xs font-bold text-[#163E3E]">حدد نوع التسوية ومصير التأمين *</label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <label
                                :class="[
                                    'p-3.5 rounded-2xl border cursor-pointer transition text-right flex flex-col justify-between space-y-2',
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
                                    'p-3.5 rounded-2xl border cursor-pointer transition text-right flex flex-col justify-between space-y-2',
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
                                    'p-3.5 rounded-2xl border cursor-pointer transition text-right flex flex-col justify-between space-y-2',
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
                        <label class="block text-xs font-bold text-rose-900 mb-1">سبب احتجاز كامل مبلغ التأمين وعدم رده للمستأجر *</label>
                        <textarea
                            v-model="terminateForm.retention_reason"
                            rows="3"
                            required
                            placeholder="وضح بالتفصيل سبب الاحتفاظ بكامل التأمين (مثل: وجود تلفيات جسيمة تفوق قيمة التأمين، عدم سداد إيجار متأخر)..."
                            class="w-full p-2.5 rounded-xl border border-rose-200 bg-white text-xs text-right"
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
                                    'p-3 rounded-xl border cursor-pointer text-right flex items-center justify-between',
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
                                    'p-3 rounded-xl border cursor-pointer text-right flex items-center justify-between',
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
                                <label class="block text-[11px] font-bold text-emerald-950 mb-1">طريقة الرد *</label>
                                <select v-model="terminateForm.refund_method" class="w-full h-10 px-2.5 rounded-xl border border-emerald-200 bg-white text-xs">
                                    <option value="bank_transfer">تحويل بنكي</option>
                                    <option value="cash">نقدي (كاش)</option>
                                    <option value="cheque">شيك</option>
                                    <option value="other">إنستاباي / أخرى</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-emerald-950 mb-1">تاريخ الرد الفعلي *</label>
                                <input v-model="terminateForm.refund_payment_date" type="date" required class="w-full h-10 px-2.5 rounded-xl border border-emerald-200 bg-white text-xs text-right font-mono" />
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-emerald-950 mb-1">رقم المرجع / الإيصال</label>
                                <input v-model="terminateForm.refund_reference" type="text" placeholder="TXN-..." class="w-full h-10 px-2.5 rounded-xl border border-emerald-200 bg-white text-xs text-right font-mono" />
                            </div>
                        </div>
                    </div>

                    <!-- 6. حالة فحص الوحدة وملاحظات الإنهاء -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-[#D6E0E7]">
                        <div>
                            <label class="block text-xs font-bold text-[#163E3E] mb-1">حالة فحص واستلام الوحدة *</label>
                            <select v-model="terminateForm.inspection_category" class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs">
                                <option value="no_issues">سليمة بدون أضرار (حالة ممتازة)</option>
                                <option value="furniture_damage">تلف أثاث أو أجهزة</option>
                                <option value="fixture_damage">تلف تركيبات ومرافق</option>
                                <option value="missing_items">مفقودات ومحتويات ناقصة</option>
                                <option value="other_damage">أضرار موثقة أخرى</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-[#163E3E] mb-1">ملاحظات ختامية على إنهاء العقد</label>
                            <input v-model="terminateForm.notes" type="text" placeholder="أي ملاحظات نهائية..." class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-right" />
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
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-5 sm:p-7 z-10 text-right animate-in fade-in zoom-in-95" dir="rtl">
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
                    <button @click="showPendingRefundModal = false" type="button" class="p-1 text-slate-400 hover:text-slate-600">
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
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">المبلغ المصروف *</label>
                        <div class="relative">
                            <input
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
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">طريقة الصرف *</label>
                        <select v-model="pendingRefundForm.payment_method" class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs">
                            <option value="bank_transfer">تحويل بنكي</option>
                            <option value="cash">نقدي (كاش)</option>
                            <option value="cheque">شيك</option>
                            <option value="other">إنستاباي / محفظة إلكترونية</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">تاريخ الصرف والتحويل الفعلي *</label>
                        <input v-model="pendingRefundForm.refunded_at" type="date" required class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-right font-mono" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">رقم مرجع الحوالة / الإيصال</label>
                        <input v-model="pendingRefundForm.reference" type="text" placeholder="TXN-123456" class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-right font-mono" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">ملاحظات الصرف</label>
                        <textarea v-model="pendingRefundForm.notes" rows="2" class="w-full p-2.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-right"></textarea>
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
                        <label class="block text-xs font-bold text-[#163E3E] mb-2">اختر ملف العقد (صورة أو PDF) *</label>
                        <input
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
    </AppLayout>
</template>
