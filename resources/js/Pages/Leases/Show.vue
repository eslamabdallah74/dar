<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import DeductionForm from '@/Components/Leases/SecurityDeposit/DeductionForm.vue';
import DeductionSection from '@/Components/Leases/SecurityDeposit/DeductionSection.vue';
import SettlementSummary from '@/Components/Leases/SecurityDeposit/SettlementSummary.vue';
import ModalRecordPayment from '@/Components/Common/ModalRecordPayment.vue';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import {
    ArrowLeft,
    ArrowRight,
    Ban,
    Clock,
    Coins,
    CreditCard,
    FileCheck,
    FileText,
    MessageCircle,
    Paperclip,
    Phone,
    RotateCcw,
    Shield,
    ShieldAlert,
    ShieldCheck,
    Trash2,
    Upload,
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
        terminateLeaseWithoutDeposit();
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

const depositPercentReceived = computed(() => {
    if (!props.security_deposit.agreed_amount || props.security_deposit.agreed_amount <= 0) return 0;
    return Math.min(100, Math.round((props.security_deposit.total_received / props.security_deposit.agreed_amount) * 100));
});

const buttonBase =
    'inline-flex h-10 cursor-pointer items-center gap-2 rounded-xl px-4 text-sm font-semibold transition active:translate-y-px focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#163E3E]/30 focus-visible:ring-offset-2';
const buttonPrimary = `${buttonBase} bg-[#163E3E] text-white hover:bg-[#102F2F]`;
const buttonSecondary = `${buttonBase} border border-[#D6E0E7] bg-white text-[#163E3E] hover:bg-[#F8F4EA]`;
const buttonGhost = `${buttonBase} text-[#5B6770] hover:bg-[#163E3E]/5 hover:text-[#163E3E]`;
const buttonDanger = `${buttonBase} border border-rose-200 bg-white text-rose-700 hover:bg-rose-50`;
const iconButton =
    'inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#D6E0E7] text-[#163E3E] transition hover:bg-[#F8F4EA] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#163E3E]/30';
const iconButtonDanger =
    'inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-xl border border-rose-200 text-rose-700 transition hover:bg-rose-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-300';
const linkButton = 'cursor-pointer text-xs font-semibold text-[#163E3E] underline-offset-4 hover:underline focus-visible:underline focus-visible:outline-none';
const linkButtonDanger = 'cursor-pointer text-xs font-semibold text-rose-700 underline-offset-4 hover:underline focus-visible:underline focus-visible:outline-none';

const formatMoney = (amount: number): string => Number(amount).toLocaleString();

const isLeaseActive = computed(() => props.lease.status === 'active');

const leaseBadgeStatus = computed(() => {
    if (!isLeaseActive.value) {
        return props.lease.status;
    }
    if (props.lease.is_past_end_date) {
        return 'past_due';
    }

    return props.lease.is_expiring_soon ? 'expiring' : 'active';
});

/**
 * Arabic counted noun: "يوم" (1), "يومان" (2), "5 أيام" (3-10), "15 يوماً" (11+).
 */
const arabicCount = (count: number, forms: { one: string; two: string; few: string; many: string }): string => {
    if (count === 1) {
        return forms.one;
    }
    if (count === 2) {
        return forms.two;
    }

    return `${count} ${count <= 10 ? forms.few : forms.many}`;
};

const formatDuration = (days: number): string => {
    if (days < 60) {
        return arabicCount(days, { one: 'يوم', two: 'يومان', few: 'أيام', many: 'يوماً' });
    }
    const months = Math.round(days / 30.44);
    if (months < 24) {
        return arabicCount(months, { one: 'شهر', two: 'شهران', few: 'أشهر', many: 'شهراً' });
    }

    return arabicCount(Math.round(days / 365.25), { one: 'سنة', two: 'سنتان', few: 'سنوات', many: 'سنة' });
};

const parseIsoDate = (isoDate: string): Date => {
    const [year, month, day] = isoDate.split('-').map(Number);

    return new Date(year, month - 1, day);
};

const millisecondsPerDay = 86_400_000;

const contractTimeline = computed(() => {
    const startsOn = parseIsoDate(props.lease.raw_starts_on).getTime();
    const endsOn = parseIsoDate(props.lease.raw_ends_on).getTime();
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const todayTime = today.getTime();

    const totalDays = Math.max(1, Math.round((endsOn - startsOn) / millisecondsPerDay));
    const elapsedDays = Math.round((todayTime - startsOn) / millisecondsPerDay);
    const remainingDays = Math.round((endsOn - todayTime) / millisecondsPerDay);
    const elapsedPercent = Math.min(100, Math.max(0, Math.round((elapsedDays / totalDays) * 100)));

    let caption = `المدة ${formatDuration(totalDays)}`;
    if (isLeaseActive.value) {
        if (elapsedDays < 0) {
            caption = `يبدأ بعد ${formatDuration(-elapsedDays)}`;
        } else if (remainingDays < 0) {
            caption = `انتهت مدته منذ ${formatDuration(-remainingDays)}`;
        } else if (remainingDays === 0) {
            caption = 'ينتهي اليوم';
        } else {
            caption = `متبقٍ ${formatDuration(remainingDays)}`;
        }
    }

    return { elapsedPercent, caption };
});

const roundMoney = (amount: number): number => Math.round(amount * 100) / 100;

const rentTotals = computed(() => {
    const totals = props.rent_charges.reduce(
        (sums, charge) => {
            sums.billed += charge.amount;
            sums.paid += charge.paid_amount;
            sums.outstanding += charge.outstanding_balance;
            if (charge.status === 'overdue') {
                sums.overdue += charge.outstanding_balance;
                sums.overdueCount += 1;
            }

            return sums;
        },
        { billed: 0, paid: 0, outstanding: 0, overdue: 0, overdueCount: 0 }
    );

    return {
        ...totals,
        billed: roundMoney(totals.billed),
        paid: roundMoney(totals.paid),
        outstanding: roundMoney(totals.outstanding),
        overdue: roundMoney(totals.overdue),
    };
});

const formatPeriodCount = (count: number): string => arabicCount(count, { one: 'فترة واحدة', two: 'فترتان', few: 'فترات', many: 'فترة' });

const rentChargesCaption = computed(() => {
    const { billed, paid } = rentTotals.value;
    const periods = formatPeriodCount(props.rent_charges.length);

    return paid > 0
        ? `سُدد ${formatMoney(paid)} من ${formatMoney(billed)} ${props.currency_symbol} · ${periods}`
        : `لم يُسدد شيء بعد من ${formatMoney(billed)} ${props.currency_symbol} · ${periods}`;
});

const rentOutstandingCaption = computed(() => {
    const { outstanding, overdue, overdueCount } = rentTotals.value;
    const periods = formatPeriodCount(overdueCount);

    if (overdue > 0) {
        return overdue === outstanding
            ? `كلها متأخرة عن موعدها (${periods})`
            : `منها ${formatMoney(overdue)} ${props.currency_symbol} متأخرة (${periods})`;
    }
    if (outstanding > 0) {
        return 'لم يحن موعد سدادها بعد';
    }

    return props.rent_charges.length > 0 ? 'كل الفواتير الصادرة مسددة' : 'لم تصدر فواتير بعد';
});

const rentChargeBadge = (charge: RentChargeItem): { status: string; label: string } => {
    if (charge.status === 'paid') {
        return { status: 'paid', label: 'مسدد' };
    }
    if (charge.status === 'overdue') {
        return { status: 'overdue', label: 'متأخر' };
    }

    return charge.paid_amount > 0 ? { status: 'partial', label: 'مسدد جزئياً' } : { status: 'pending', label: 'مستحق' };
};

const showRentPaymentModal = ref(false);
const rentPaymentCharge = ref<RentChargeItem | null>(null);

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

const hasDeposit = computed(() => {
    const deposit = props.security_deposit;

    return deposit.agreed_amount > 0 || deposit.total_received > 0 || deposit.payments.length > 0 || deposit.settlement !== null;
});

const depositBadgeStatuses: Record<string, string> = {
    held: 'paid',
    partially_received: 'partial',
    due: 'pending',
    under_review: 'in_progress',
    settled: 'closed',
};

const depositBadgeStatus = computed(() => depositBadgeStatuses[props.security_deposit.status] ?? 'inactive');

interface DepositAction {
    key: string;
    label: string;
    run: () => void;
}

interface DepositStep {
    message: string;
    needsAttention: boolean;
    action: DepositAction | null;
}

const recordDepositPaymentAction: DepositAction = {
    key: 'record-payment',
    label: 'تسجيل استلام دفعة',
    run: () => {
        showRecordPaymentModal.value = true;
    },
};

const finalizeSettlementAction: DepositAction = {
    key: 'finalize-settlement',
    label: 'اعتماد التسوية النهائية',
    run: openFinalizeSettlementModal,
};

const depositNextStep = computed<DepositStep | null>(() => {
    const deposit = props.security_deposit;

    if (deposit.actions.can_record_pending_refund) {
        return {
            needsAttention: true,
            message: `اعتُمد رد ${formatMoney(deposit.refundable_balance)} ${props.currency_symbol} للمستأجر ولم يُصرف بعد.`,
            action: {
                key: 'pending-refund',
                label: 'تسجيل صرف المبلغ',
                run: () => {
                    showPendingRefundModal.value = true;
                },
            },
        };
    }
    if (deposit.status === 'under_review' && deposit.actions.can_settle) {
        return {
            needsAttention: true,
            message: 'الوحدة قيد الفحص. راجع الخصومات ثم اعتمد التسوية لإغلاق التأمين.',
            action: finalizeSettlementAction,
        };
    }
    if (deposit.actions.can_record_payment) {
        return {
            needsAttention: true,
            message: `لم يُستلم ${formatMoney(deposit.outstanding_amount)} ${props.currency_symbol} من التأمين بعد.`,
            action: recordDepositPaymentAction,
        };
    }
    if (deposit.status === 'settled') {
        return { needsAttention: false, message: 'تمت تسوية التأمين وإغلاقه.', action: null };
    }
    if (deposit.status === 'held') {
        return { needsAttention: false, message: 'التأمين مستلم بالكامل ومحفوظ حتى نهاية العقد.', action: null };
    }

    return null;
});

const depositOtherActions = computed<DepositAction[]>(() => {
    const deposit = props.security_deposit;
    const actions: DepositAction[] = [];

    if (deposit.actions.can_record_payment) {
        actions.push(recordDepositPaymentAction);
    }
    if (deposit.actions.can_start_settlement) {
        actions.push({
            key: 'start-settlement',
            label: deposit.status === 'under_review' ? 'تحديث نتيجة الفحص' : 'بدء فحص الوحدة',
            run: () => {
                showStartSettlementModal.value = true;
            },
        });
    }
    if (deposit.actions.can_record_deduction) {
        actions.push({
            key: 'record-deduction',
            label: 'إضافة خصم',
            run: () => {
                showRecordDeductionModal.value = true;
            },
        });
    }
    if (deposit.actions.can_record_refund) {
        actions.push({
            key: 'record-refund',
            label: 'تسجيل رد للمستأجر',
            run: () => {
                showRecordRefundModal.value = true;
            },
        });
    }
    if (deposit.actions.can_settle) {
        actions.push(finalizeSettlementAction);
    }

    const primaryActionKey = depositNextStep.value?.action?.key;

    return actions.filter((action) => action.key !== primaryActionKey);
});

const canChangeDepositRecords = computed(() => props.security_deposit.status !== 'settled');

type DepositLedgerKind = 'payment' | 'deduction' | 'refund';

interface DepositLedgerEntry {
    key: string;
    kind: DepositLedgerKind;
    title: string;
    occurredAt: string;
    amount: number;
    method: string | null;
    reference: string | null;
    recordedBy: string;
    description: string | null;
    notes: string | null;
    attachmentUrl: string | null;
    isReversed: boolean;
    reversalReason: string | null;
    payment: DepositPayment | null;
    deduction: DepositDeduction | null;
}

/**
 * Turns "d/m/Y" or "d/m/Y H:i" into "Y-m-d H:i" so entries sort chronologically.
 */
const toSortableDate = (displayDate: string): string => {
    const [datePart = '', timePart = '00:00'] = displayDate.split(' ');
    const [day = '', month = '', year = ''] = datePart.split('/');

    return `${year}-${month}-${day} ${timePart}`;
};

const depositLedgerKindOrder: Record<DepositLedgerKind, number> = { payment: 0, deduction: 1, refund: 2 };

const depositLedger = computed<DepositLedgerEntry[]>(() => {
    const deposit = props.security_deposit;
    const entries: DepositLedgerEntry[] = [
        ...deposit.payments.map((payment) => ({
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
            .filter((deduction) => deduction.status === 'confirmed')
            .map((deduction) => ({
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
        ...deposit.refunds.map((refund) => ({
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

const depositLedgerStyles: Record<DepositLedgerKind, { dot: string; amount: string; sign: string }> = {
    payment: { dot: 'bg-emerald-500', amount: 'text-emerald-700', sign: '+' },
    deduction: { dot: 'bg-rose-500', amount: 'text-rose-700', sign: '−' },
    refund: { dot: 'bg-[#163E3E]', amount: 'text-[#163E3E]', sign: '−' },
};
</script>

<template>
    <AppLayout
        :title="`عقد ${lease.contract_number} — ${unit.name} — Nook Mate`"
        active-tab="leases"
        :header-title="`عقد ${lease.contract_number}`"
        :header-subtitle="`${renter.name} · ${unit.name} · ${unit.property_name}`"
        :breadcrumb="`العقود / ${lease.contract_number}`"
    >
        <template #actions>
            <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                <Link :href="route('leases.index')" :class="buttonGhost">
                    <ArrowRight class="h-4 w-4" />
                    <span>كل العقود</span>
                </Link>
                <template v-if="isLeaseActive">
                    <button type="button" :class="buttonSecondary" @click="showExtendModal = true">
                        <RotateCcw class="h-4 w-4" />
                        <span>تجديد العقد</span>
                    </button>
                    <button type="button" :class="buttonDanger" @click="openTerminateWorkflow">
                        <Ban class="h-4 w-4" />
                        <span>إنهاء العقد</span>
                    </button>
                    <button type="button" :class="buttonPrimary" @click="openRentPayment()">
                        <CreditCard class="h-4 w-4" />
                        <span>تسجيل دفعة إيجار</span>
                    </button>
                </template>
            </div>
        </template>

        <div
            v-if="isLeaseActive && lease.is_past_end_date"
            role="status"
            class="flex flex-col gap-4 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-start gap-3">
                <Clock class="mt-0.5 h-5 w-5 shrink-0 text-amber-700" />
                <div>
                    <p class="text-sm font-bold text-amber-950">انتهت مدة العقد في {{ lease.ends_on }} ولم يُغلق بعد</p>
                    <p class="mt-1 text-xs leading-5 text-amber-900/80">تبقى الوحدة مشغولة حتى تجدد العقد أو تنهيه وتسوي التأمين.</p>
                </div>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <button type="button" :class="buttonSecondary" @click="showExtendModal = true">تجديد العقد</button>
                <button type="button" :class="buttonDanger" @click="openTerminateWorkflow">إنهاء العقد</button>
            </div>
        </div>

        <section aria-label="ملخص العقد" class="grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-[#D6E0E7] bg-[#E6ECF0] lg:grid-cols-4">
            <div class="col-span-2 bg-white p-5 lg:col-span-1">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-medium text-[#5B6770]">مدة العقد</span>
                    <StatusBadge :status="leaseBadgeStatus" :label="lease.status_label" size="sm" />
                </div>
                <div class="mt-3 flex items-center justify-between gap-2 font-mono text-sm tabular-nums text-[#163E3E]">
                    <span>{{ lease.starts_on }}</span>
                    <span class="h-px flex-1 bg-[#D6E0E7]" aria-hidden="true"></span>
                    <span>{{ lease.ends_on }}</span>
                </div>
                <div
                    class="mt-3 h-1.5 overflow-hidden rounded-full bg-[#EEF2F4]"
                    role="progressbar"
                    aria-label="المنقضي من مدة العقد"
                    :aria-valuenow="contractTimeline.elapsedPercent"
                    aria-valuemin="0"
                    aria-valuemax="100"
                >
                    <div class="h-full rounded-full bg-[#163E3E]" :style="{ width: `${contractTimeline.elapsedPercent}%` }"></div>
                </div>
                <p class="mt-2 text-xs text-[#5B6770]">{{ contractTimeline.caption }}</p>
            </div>

            <div class="bg-white p-5">
                <span class="text-xs font-medium text-[#5B6770]">الإيجار</span>
                <p class="mt-2 font-mono text-2xl font-semibold tabular-nums text-[#163E3E]">
                    {{ formatMoney(lease.rent_amount) }}
                    <span class="font-sans text-xs font-medium text-[#5B6770]">{{ currency_symbol }}</span>
                </p>
                <p class="mt-1 text-xs text-[#5B6770]">دورية الدفع: {{ lease.billing_interval }}</p>
            </div>

            <div class="bg-white p-5">
                <span class="text-xs font-medium text-[#5B6770]">إيجار غير مسدد</span>
                <p
                    class="mt-2 font-mono text-2xl font-semibold tabular-nums"
                    :class="rentTotals.overdue > 0 ? 'text-rose-700' : 'text-[#163E3E]'"
                >
                    {{ formatMoney(rentTotals.outstanding) }}
                    <span class="font-sans text-xs font-medium text-[#5B6770]">{{ currency_symbol }}</span>
                </p>
                <p class="mt-1 text-xs" :class="rentTotals.overdue > 0 ? 'font-semibold text-rose-700' : 'text-[#5B6770]'">
                    {{ rentOutstandingCaption }}
                </p>
            </div>

            <div class="col-span-2 bg-white p-5 lg:col-span-1">
                <span class="text-xs font-medium text-[#5B6770]">التأمين المحفوظ لديك</span>
                <template v-if="hasDeposit">
                    <p class="mt-2 font-mono text-2xl font-semibold tabular-nums text-[#163E3E]">
                        {{ formatMoney(security_deposit.refundable_balance) }}
                        <span class="font-sans text-xs font-medium text-[#5B6770]">{{ currency_symbol }}</span>
                    </p>
                    <p class="mt-1 text-xs text-[#5B6770]">
                        {{ security_deposit.status_label }}
                        <span v-if="security_deposit.outstanding_amount > 0" class="font-semibold text-amber-800">
                            · متبقٍ تحصيل {{ formatMoney(security_deposit.outstanding_amount) }}
                        </span>
                    </p>
                </template>
                <template v-else>
                    <p class="mt-2 text-lg font-semibold text-[#8A9AA7]">بدون تأمين</p>
                    <p class="mt-1 text-xs text-[#5B6770]">لم يُشترط تأمين في هذا العقد</p>
                </template>
            </div>
        </section>

        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <section aria-labelledby="rent-heading" class="overflow-hidden rounded-2xl border border-[#D6E0E7] bg-white">
                    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-[#E6ECF0] px-5 py-4">
                        <div>
                            <h2 id="rent-heading" class="text-base font-bold text-[#163E3E]">فواتير الإيجار</h2>
                            <p v-if="rent_charges.length" class="mt-0.5 text-xs text-[#5B6770]">{{ rentChargesCaption }}</p>
                        </div>
                    </header>

                    <div v-if="rent_charges.length === 0" class="px-5 py-10 text-center">
                        <p class="text-sm font-semibold text-[#163E3E]">لم تصدر فواتير إيجار بعد</p>
                        <p class="mt-1 text-xs text-[#5B6770]">تُنشأ فاتورة لكل فترة تلقائياً حسب دورية الدفع.</p>
                    </div>

                    <template v-else>
                        <ul class="divide-y divide-[#E6ECF0] sm:hidden">
                            <li v-for="charge in rent_charges" :key="charge.id" class="space-y-2.5 px-5 py-4">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-mono text-sm font-semibold tabular-nums text-[#163E3E]">{{ charge.period }}</span>
                                    <StatusBadge :status="rentChargeBadge(charge).status" :label="rentChargeBadge(charge).label" size="sm" />
                                </div>
                                <dl class="grid grid-cols-3 gap-2 text-xs">
                                    <div>
                                        <dt class="text-[#5B6770]">القيمة</dt>
                                        <dd class="mt-0.5 font-mono font-semibold tabular-nums text-[#163E3E]">{{ formatMoney(charge.amount) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[#5B6770]">المسدد</dt>
                                        <dd class="mt-0.5 font-mono font-semibold tabular-nums text-emerald-700">{{ formatMoney(charge.paid_amount) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[#5B6770]">المتبقي</dt>
                                        <dd
                                            class="mt-0.5 font-mono font-semibold tabular-nums"
                                            :class="charge.outstanding_balance > 0 ? 'text-[#163E3E]' : 'text-[#8A9AA7]'"
                                        >
                                            {{ formatMoney(charge.outstanding_balance) }}
                                        </dd>
                                    </div>
                                </dl>
                                <div class="flex items-center justify-between text-xs text-[#5B6770]">
                                    <span>الاستحقاق <span class="font-mono tabular-nums">{{ charge.due_date }}</span></span>
                                    <button
                                        v-if="isLeaseActive && charge.outstanding_balance > 0"
                                        type="button"
                                        :class="linkButton"
                                        @click="openRentPayment(charge)"
                                    >
                                        سداد هذه الفترة
                                    </button>
                                </div>
                            </li>
                        </ul>

                        <table class="hidden w-full text-right text-sm sm:table">
                            <thead class="bg-[#FBFAF6] text-xs text-[#5B6770]">
                                <tr>
                                    <th scope="col" class="px-5 py-3 font-medium">الفترة</th>
                                    <th scope="col" class="px-3 py-3 font-medium">الاستحقاق</th>
                                    <th scope="col" class="px-3 py-3 font-medium">القيمة</th>
                                    <th scope="col" class="px-3 py-3 font-medium">المسدد</th>
                                    <th scope="col" class="px-3 py-3 font-medium">المتبقي</th>
                                    <th scope="col" class="px-3 py-3 font-medium">الحالة</th>
                                    <th scope="col" class="px-5 py-3"><span class="sr-only">إجراء</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E6ECF0]">
                                <tr v-for="charge in rent_charges" :key="charge.id" class="transition-colors hover:bg-[#FBFAF6]">
                                    <td class="px-5 py-3.5 font-mono font-semibold tabular-nums text-[#163E3E]">{{ charge.period }}</td>
                                    <td class="px-3 py-3.5 font-mono tabular-nums text-[#5B6770]">{{ charge.due_date }}</td>
                                    <td class="px-3 py-3.5 font-mono tabular-nums text-[#163E3E]">{{ formatMoney(charge.amount) }}</td>
                                    <td class="px-3 py-3.5 font-mono tabular-nums text-emerald-700">{{ formatMoney(charge.paid_amount) }}</td>
                                    <td
                                        class="px-3 py-3.5 font-mono font-semibold tabular-nums"
                                        :class="charge.outstanding_balance > 0 ? 'text-[#163E3E]' : 'text-[#8A9AA7]'"
                                    >
                                        {{ formatMoney(charge.outstanding_balance) }}
                                    </td>
                                    <td class="px-3 py-3.5">
                                        <StatusBadge :status="rentChargeBadge(charge).status" :label="rentChargeBadge(charge).label" size="sm" />
                                    </td>
                                    <td class="px-5 py-3.5 text-left">
                                        <button
                                            v-if="isLeaseActive && charge.outstanding_balance > 0"
                                            type="button"
                                            :class="linkButton"
                                            @click="openRentPayment(charge)"
                                        >
                                            سداد
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </template>
                </section>

                <section v-if="hasDeposit" aria-labelledby="deposit-heading" class="overflow-hidden rounded-2xl border border-[#D6E0E7] bg-white">
                    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-[#E6ECF0] px-5 py-4">
                        <div>
                            <h2 id="deposit-heading" class="text-base font-bold text-[#163E3E]">التأمين</h2>
                            <p class="mt-0.5 text-xs text-[#5B6770]">مبلغ مسترد يُحفظ منفصلاً عن الإيجار ويُسوّى عند نهاية العقد.</p>
                        </div>
                        <StatusBadge :status="depositBadgeStatus" :label="security_deposit.status_label" />
                    </header>

                    <div class="space-y-4 px-5 py-5">
                        <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-[#E6ECF0] bg-[#E6ECF0] sm:grid-cols-4">
                            <div class="bg-[#FBFAF6] p-3.5">
                                <dt class="text-xs text-[#5B6770]">المستلم من المستأجر</dt>
                                <dd class="mt-1 font-mono text-lg font-semibold tabular-nums text-[#163E3E]">{{ formatMoney(security_deposit.total_received) }}</dd>
                                <dd class="text-xs text-[#5B6770]">من {{ formatMoney(security_deposit.agreed_amount) }} متفق عليه</dd>
                            </div>
                            <div class="bg-[#FBFAF6] p-3.5">
                                <dt class="text-xs text-[#5B6770]"><span aria-hidden="true">− </span>الخصومات</dt>
                                <dd class="mt-1 font-mono text-lg font-semibold tabular-nums" :class="security_deposit.total_deductions > 0 ? 'text-rose-700' : 'text-[#163E3E]'">
                                    {{ formatMoney(security_deposit.total_deductions) }}
                                </dd>
                                <dd class="text-xs text-[#5B6770]">للمالك مقابل أضرار أو مستحقات</dd>
                            </div>
                            <div class="bg-[#FBFAF6] p-3.5">
                                <dt class="text-xs text-[#5B6770]"><span aria-hidden="true">− </span>المردود للمستأجر</dt>
                                <dd class="mt-1 font-mono text-lg font-semibold tabular-nums text-[#163E3E]">{{ formatMoney(security_deposit.total_refunded) }}</dd>
                                <dd class="text-xs text-[#5B6770]">صُرف فعلياً</dd>
                            </div>
                            <div class="bg-[#E8F0F0] p-3.5">
                                <dt class="text-xs font-semibold text-[#163E3E]"><span aria-hidden="true">= </span>المحفوظ لديك الآن</dt>
                                <dd class="mt-1 font-mono text-lg font-bold tabular-nums text-[#163E3E]">{{ formatMoney(security_deposit.refundable_balance) }}</dd>
                                <dd class="text-xs text-[#163E3E]/70">{{ currency_symbol }} قابلة للرد</dd>
                            </div>
                        </dl>

                        <div v-if="security_deposit.outstanding_amount > 0" class="space-y-1.5">
                            <div class="flex items-center justify-between text-xs text-[#5B6770]">
                                <span>استُلم {{ depositPercentReceived }}% من التأمين المتفق عليه</span>
                                <span class="font-mono tabular-nums">متبقٍ {{ formatMoney(security_deposit.outstanding_amount) }} {{ currency_symbol }}</span>
                            </div>
                            <div
                                class="h-1.5 overflow-hidden rounded-full bg-[#EEF2F4]"
                                role="progressbar"
                                aria-label="نسبة تحصيل التأمين"
                                :aria-valuenow="depositPercentReceived"
                                aria-valuemin="0"
                                aria-valuemax="100"
                            >
                                <div class="h-full rounded-full bg-amber-500" :style="{ width: `${depositPercentReceived}%` }"></div>
                            </div>
                        </div>

                        <div
                            v-if="depositNextStep || depositOtherActions.length"
                            class="rounded-xl border px-4 py-3.5"
                            :class="depositNextStep?.needsAttention ? 'border-amber-200 bg-amber-50/60' : 'border-[#E6ECF0] bg-[#FBFAF6]'"
                        >
                            <div v-if="depositNextStep" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-sm leading-6 text-[#163E3E]">
                                    <span class="font-semibold">{{ depositNextStep.needsAttention ? 'الخطوة التالية:' : 'الوضع الحالي:' }}</span>
                                    {{ depositNextStep.message }}
                                </p>
                                <button v-if="depositNextStep.action" type="button" :class="buttonPrimary" class="shrink-0" @click="depositNextStep.action.run">
                                    {{ depositNextStep.action.label }}
                                </button>
                            </div>
                            <div
                                v-if="depositOtherActions.length"
                                class="flex flex-wrap items-center gap-x-4 gap-y-2"
                                :class="depositNextStep ? 'mt-3 border-t border-black/5 pt-3' : ''"
                            >
                                <span class="text-xs text-[#5B6770]">{{ depositNextStep ? 'إجراءات أخرى:' : 'إجراءات التأمين:' }}</span>
                                <button
                                    v-for="depositAction in depositOtherActions"
                                    :key="depositAction.label"
                                    type="button"
                                    :class="linkButton"
                                    @click="depositAction.run"
                                >
                                    {{ depositAction.label }}
                                </button>
                            </div>
                        </div>

                        <SettlementSummary
                            v-if="security_deposit.settlement"
                            :settlement="security_deposit.settlement"
                            :currency-symbol="currency_symbol"
                            :export-url="route('leases.security-deposit.export', lease.id)"
                            :statement-url="route('leases.security-deposit.statement', lease.id)"
                        />
                    </div>

                    <div class="border-t border-[#E6ECF0]">
                        <div class="flex items-center justify-between gap-3 px-5 pt-4">
                            <h3 class="text-sm font-bold text-[#163E3E]">حركة التأمين</h3>
                            <span v-if="depositLedger.length" class="text-xs text-[#5B6770]">من الأقدم للأحدث</span>
                        </div>
                        <p v-if="depositLedger.length === 0" class="px-5 pb-6 pt-3 text-center text-xs text-[#5B6770]">
                            لم تُسجل أي حركة على التأمين بعد.
                        </p>
                        <ol v-else class="divide-y divide-[#E6ECF0] px-5 pb-2">
                            <li v-for="entry in depositLedger" :key="entry.key" class="flex items-start gap-3 py-3.5">
                                <span class="mt-2 h-2 w-2 shrink-0 rounded-full" :class="depositLedgerStyles[entry.kind].dot" aria-hidden="true"></span>
                                <div class="min-w-0 flex-1">
                                    <div :class="entry.isReversed ? 'opacity-60' : ''">
                                        <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                            <p class="text-sm font-semibold text-[#163E3E]">
                                                {{ entry.title }}
                                                <span v-if="entry.isReversed" class="text-xs font-semibold text-rose-700">· ملغاة</span>
                                            </p>
                                            <p
                                                class="text-sm font-semibold"
                                                :class="[depositLedgerStyles[entry.kind].amount, entry.isReversed ? 'line-through' : '']"
                                            >
                                                <span class="font-mono tabular-nums" dir="ltr">{{ depositLedgerStyles[entry.kind].sign }}{{ formatMoney(entry.amount) }}</span>
                                                <span class="ms-1 text-xs font-medium">{{ currency_symbol }}</span>
                                            </p>
                                        </div>
                                        <p class="mt-0.5 text-xs text-[#5B6770]">
                                            <span class="font-mono tabular-nums">{{ entry.occurredAt }}</span>
                                            <span v-if="entry.method"> · {{ entry.method }}</span>
                                            <span v-if="entry.reference"> · <span class="font-mono">{{ entry.reference }}</span></span>
                                            · بواسطة {{ entry.recordedBy }}
                                        </p>
                                        <p v-if="entry.description" class="mt-1 text-xs leading-5 text-[#334155]">{{ entry.description }}</p>
                                        <p v-if="entry.notes" class="mt-1 text-xs leading-5 text-[#5B6770]">{{ entry.notes }}</p>
                                        <p v-if="entry.isReversed && entry.reversalReason" class="mt-1 text-xs text-rose-700">سبب الإلغاء: {{ entry.reversalReason }}</p>
                                    </div>
                                    <div
                                        v-if="entry.attachmentUrl || (canChangeDepositRecords && !entry.isReversed && (entry.payment || entry.deduction))"
                                        class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1"
                                    >
                                        <a
                                            v-if="entry.attachmentUrl"
                                            :href="entry.attachmentUrl"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            :class="linkButton"
                                            class="inline-flex items-center gap-1"
                                        >
                                            <Paperclip class="h-3.5 w-3.5" />
                                            <span>عرض المرفق</span>
                                        </a>
                                        <template v-if="canChangeDepositRecords && !entry.isReversed">
                                            <button v-if="entry.payment" type="button" :class="linkButtonDanger" @click="openReversePayment(entry.payment)">
                                                عكس الدفعة
                                            </button>
                                            <template v-if="entry.deduction">
                                                <button type="button" :class="linkButton" @click="openEditDeductionModal(entry.deduction)">تعديل الخصم</button>
                                                <button type="button" :class="linkButtonDanger" class="inline-flex items-center gap-1" @click="deleteDeduction(entry.deduction)">
                                                    <Trash2 class="h-3.5 w-3.5" />
                                                    <span>حذف</span>
                                                </button>
                                            </template>
                                        </template>
                                    </div>
                                </div>
                            </li>
                        </ol>
                    </div>
                </section>

                <section v-else class="flex items-start gap-3 rounded-2xl border border-dashed border-[#D6E0E7] bg-white/60 px-5 py-4">
                    <Shield class="mt-0.5 h-5 w-5 shrink-0 text-[#8A9AA7]" />
                    <div>
                        <h2 class="text-sm font-bold text-[#163E3E]">لا يوجد تأمين على هذا العقد</h2>
                        <p class="mt-0.5 text-xs leading-5 text-[#5B6770]">أُنشئ العقد بدون مبلغ تأمين، لذلك لا توجد تسوية تأمين عند إنهائه.</p>
                    </div>
                </section>
            </div>

            <aside class="space-y-6">
                <section aria-labelledby="renter-heading" class="rounded-2xl border border-[#D6E0E7] bg-white">
                    <header class="flex items-start justify-between gap-3 px-5 pt-5">
                        <div class="min-w-0">
                            <p id="renter-heading" class="text-xs font-medium text-[#5B6770]">المستأجر</p>
                            <Link
                                :href="route('renters.show', renter.id)"
                                class="mt-0.5 block truncate text-base font-bold text-[#163E3E] underline-offset-4 hover:underline"
                            >
                                {{ renter.name }}
                            </Link>
                        </div>
                        <div v-if="renter.phone" class="flex shrink-0 items-center gap-1.5">
                            <a
                                :href="`https://wa.me/20${renter.phone.replace(/^0+/, '')}`"
                                target="_blank"
                                rel="noopener noreferrer"
                                :class="iconButton"
                                aria-label="محادثة واتساب"
                                title="محادثة واتساب"
                            >
                                <MessageCircle class="h-4 w-4" />
                            </a>
                            <a :href="`tel:${renter.phone}`" :class="iconButton" aria-label="اتصال هاتفي" title="اتصال هاتفي">
                                <Phone class="h-4 w-4" />
                            </a>
                        </div>
                    </header>
                    <dl class="divide-y divide-[#EEF2F4] px-5 py-2 text-sm">
                        <div class="flex items-center justify-between gap-4 py-2.5">
                            <dt class="text-[#5B6770]">الجوال</dt>
                            <dd class="font-mono tabular-nums text-[#163E3E]" dir="ltr">{{ renter.phone || '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 py-2.5">
                            <dt class="text-[#5B6770]">رقم الهوية</dt>
                            <dd class="font-mono tabular-nums text-[#163E3E]">{{ renter.national_id || 'غير مسجل' }}</dd>
                        </div>
                        <div v-if="renter.email" class="flex items-center justify-between gap-4 py-2.5">
                            <dt class="text-[#5B6770]">البريد</dt>
                            <dd class="truncate text-[#163E3E]" dir="ltr">{{ renter.email }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 py-2.5">
                            <dt class="text-[#5B6770]">الجنس</dt>
                            <dd class="text-[#163E3E]">{{ renter.gender_label || 'غير محدد' }}</dd>
                        </div>
                    </dl>
                    <div class="border-t border-[#EEF2F4] px-5 py-3">
                        <Link :href="route('renters.show', renter.id)" :class="linkButton" class="inline-flex items-center gap-1">
                            <span>ملف المستأجر وسجل مدفوعاته</span>
                            <ArrowLeft class="h-3.5 w-3.5" />
                        </Link>
                    </div>
                </section>

                <section aria-labelledby="unit-heading" class="rounded-2xl border border-[#D6E0E7] bg-white">
                    <header class="px-5 pt-5">
                        <p id="unit-heading" class="text-xs font-medium text-[#5B6770]">الوحدة</p>
                        <p class="mt-0.5 text-base font-bold text-[#163E3E]">{{ unit.name }}</p>
                    </header>
                    <dl class="divide-y divide-[#EEF2F4] px-5 py-2 text-sm">
                        <div class="flex items-center justify-between gap-4 py-2.5">
                            <dt class="text-[#5B6770]">النوع</dt>
                            <dd class="flex items-center gap-1.5 text-[#163E3E]">
                                <span>{{ unit.unit_type_label }}</span>
                                <span
                                    v-if="unit.apartment_gender"
                                    class="rounded-md border px-1.5 py-0.5 text-[11px] font-semibold"
                                    :class="unit.apartment_gender.badge_class"
                                >
                                    {{ unit.apartment_gender.short_label }}
                                </span>
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 py-2.5">
                            <dt class="text-[#5B6770]">الرمز</dt>
                            <dd class="font-mono text-[#163E3E]">{{ unit.code || '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 py-2.5">
                            <dt class="text-[#5B6770]">العقار</dt>
                            <dd class="min-w-0 truncate">
                                <Link :href="route('properties.show', unit.property_id)" :class="linkButton">{{ unit.property_name }}</Link>
                            </dd>
                        </div>
                        <div v-if="unit.property_address" class="flex items-start justify-between gap-4 py-2.5">
                            <dt class="shrink-0 text-[#5B6770]">العنوان</dt>
                            <dd class="text-left text-[#163E3E]">{{ unit.property_address }}</dd>
                        </div>
                    </dl>
                </section>

                <section aria-labelledby="contract-heading" class="rounded-2xl border border-[#D6E0E7] bg-white">
                    <header class="px-5 pt-5">
                        <p id="contract-heading" class="text-xs font-medium text-[#5B6770]">بيانات العقد</p>
                        <p class="mt-0.5 font-mono text-base font-bold text-[#163E3E]">{{ lease.contract_number }}</p>
                    </header>
                    <dl class="divide-y divide-[#EEF2F4] px-5 py-2 text-sm">
                        <div class="flex items-center justify-between gap-4 py-2.5">
                            <dt class="text-[#5B6770]">تاريخ البداية</dt>
                            <dd class="font-mono tabular-nums text-[#163E3E]">{{ lease.starts_on }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 py-2.5">
                            <dt class="text-[#5B6770]">تاريخ النهاية</dt>
                            <dd class="font-mono tabular-nums text-[#163E3E]">{{ lease.ends_on }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 py-2.5">
                            <dt class="text-[#5B6770]">أُنشئ في</dt>
                            <dd class="font-mono tabular-nums text-[#163E3E]">{{ lease.created_at }}</dd>
                        </div>
                    </dl>
                    <div v-if="lease.notes" class="mx-5 mb-4 rounded-xl bg-[#FBFAF6] px-3.5 py-3">
                        <p class="text-xs font-medium text-[#5B6770]">ملاحظات العقد</p>
                        <p class="mt-1 whitespace-pre-line text-sm leading-6 text-[#334155]">{{ lease.notes }}</p>
                    </div>

                    <div class="border-t border-[#EEF2F4] px-5 py-4">
                        <p class="text-xs font-medium text-[#5B6770]">نسخة العقد الموقّعة</p>
                        <div v-if="lease.has_contract_image && lease.contract_image_url" class="mt-2 flex flex-wrap items-center gap-2">
                            <a :href="lease.contract_image_url" target="_blank" rel="noopener noreferrer" :class="buttonSecondary" class="flex-1 justify-center">
                                <FileText class="h-4 w-4" />
                                <span>عرض العقد</span>
                            </a>
                            <button type="button" :class="buttonGhost" @click="showContractUploadModal = true">استبدال</button>
                            <button type="button" :class="iconButtonDanger" aria-label="حذف نسخة العقد" title="حذف نسخة العقد" @click="deleteContractImage">
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                        <div v-else class="mt-2 space-y-2">
                            <p class="text-xs leading-5 text-[#5B6770]">لم تُرفق نسخة بعد. ارفع صورة أو ملف PDF للعقد الموقّع، ويُحفظ مشفّراً.</p>
                            <button type="button" :class="buttonSecondary" class="w-full justify-center" @click="showContractUploadModal = true">
                                <Upload class="h-4 w-4" />
                                <span>رفع نسخة العقد</span>
                            </button>
                        </div>
                    </div>
                </section>
            </aside>
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
