<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
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
    Phone,
    Plus,
    RotateCcw,
    Shield,
    ShieldAlert,
    ShieldCheck,
    Store,
    Trash2,
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
        notes: string | null;
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
    };
    renter: {
        id: number;
        name: string;
        phone: string;
        national_id: string | null;
        email: string | null;
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
            inspection_category: string;
            inspection_category_label: string;
            inspection_notes: string | null;
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
const activePaymentForReversal = ref<DepositPayment | null>(null);

// Forms
const todayDate = new Date().toISOString().split('T')[0];

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

const deductionForm = useForm({
    category: 'furniture_damage',
    description: '',
    amount: 0,
    deducted_at: todayDate,
});

const refundForm = useForm({
    amount: props.security_deposit.refundable_balance,
    payment_method: 'bank_transfer',
    refunded_at: todayDate,
    reference: '',
    notes: '',
});

const finalizeSettlementForm = useForm({
    inspection_category: props.security_deposit.settlement?.inspection_category || 'no_issues',
    inspection_notes: props.security_deposit.settlement?.inspection_notes || '',
    notes: '',
});

const reversePaymentForm = useForm({
    reversal_reason: '',
});

// Handlers
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

const submitDeduction = () => {
    deductionForm.post(route('leases.security-deposit.deduction', props.lease.id), {
        onSuccess: () => {
            deductionForm.reset();
            showRecordDeductionModal.value = false;
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

const submitFinalizeSettlement = () => {
    finalizeSettlementForm.post(route('leases.security-deposit.settle', props.lease.id), {
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
        :title="`عقد ${lease.contract_number} — ${unit.name} — دار`"
        active-tab="leases"
        :header-title="`عقد إيجار ${lease.contract_number}`"
        :header-subtitle="`${unit.name} • ${unit.property_name} • المستأجر: ${renter.name}`"
        :breadcrumb="`العقود / ${lease.contract_number}`"
    >
        <template #actions>
            <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                <Link
                    :href="route('leases.index')"
                    class="h-10 px-4 rounded-xl border border-[#D6E0E7] bg-white hover:bg-[#F3F7FA] text-xs font-bold text-[#5B6770] hover:text-[#0F2747] transition flex items-center justify-center gap-1.5 shadow-2xs flex-1 sm:flex-initial"
                >
                    <ArrowRight class="w-4 h-4" />
                    <span>العودة لقائمة العقود</span>
                </Link>

                <button
                    v-if="lease.status === 'active'"
                    @click="terminateLease"
                    type="button"
                    class="h-10 px-4 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer shadow-2xs flex-1 sm:flex-initial"
                >
                    <Ban class="w-4 h-4" />
                    <span>إنهاء العقد وتسوية التأمين</span>
                </button>
            </div>
        </template>

        <!-- 1. كروت الملخص المالي السريع للعقد والتأمين (4 KPIs) - متجاوبة تماماً للموبايل -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <!-- كارت الإيجار الشهري -->
            <div class="rounded-2xl sm:rounded-3xl border border-[#D6E0E7] bg-white p-3.5 sm:p-5 shadow-2xs space-y-2 hover:border-[#0E6E7E]/40 transition">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] sm:text-xs font-semibold text-[#5B6770]">الإيجار الشهري</span>
                    <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg sm:rounded-xl bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center">
                        <Coins class="w-3.5 h-3.5 sm:w-4 sm:h-4 stroke-[2.2]" />
                    </div>
                </div>
                <div class="flex items-baseline justify-between gap-1 flex-wrap">
                    <span class="text-xl sm:text-2xl font-black text-[#0F2747] font-mono">
                        {{ lease.rent_amount.toLocaleString() }}
                        <span class="text-[10px] sm:text-xs font-sans font-normal text-[#5B6770]">{{ currency_symbol }}</span>
                    </span>
                    <StatusBadge :status="lease.status" :label="lease.status_label" size="sm" />
                </div>
            </div>

            <!-- كارت التأمين المتفق عليه -->
            <div class="rounded-2xl sm:rounded-3xl border border-[#D6E0E7] bg-white p-3.5 sm:p-5 shadow-2xs space-y-2 hover:border-[#0E6E7E]/40 transition">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] sm:text-xs font-semibold text-[#5B6770]">تأمين الوحدة المتفق عليه</span>
                    <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg sm:rounded-xl bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center">
                        <Shield class="w-3.5 h-3.5 sm:w-4 sm:h-4 stroke-[2.2]" />
                    </div>
                </div>
                <div class="flex items-baseline justify-between gap-1 flex-wrap">
                    <span class="text-xl sm:text-2xl font-black text-[#0F2747] font-mono">
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
            <div class="rounded-2xl sm:rounded-3xl border border-[#D6E0E7] bg-white p-3.5 sm:p-5 shadow-2xs space-y-2 hover:border-[#0E6E7E]/40 transition">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] sm:text-xs font-semibold text-[#5B6770]">الرصيد القابل للرد</span>
                    <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg sm:rounded-xl bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center">
                        <RotateCcw class="w-3.5 h-3.5 sm:w-4 sm:h-4 stroke-[2.2]" />
                    </div>
                </div>
                <div class="flex items-baseline justify-between gap-1 flex-wrap">
                    <span class="text-xl sm:text-2xl font-black text-[#0E6E7E] font-mono">
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
                            <div class="w-10 h-10 rounded-xl bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center shrink-0">
                                <Store v-if="unit.unit_type === 'shop'" class="w-5 h-5 stroke-[2.2]" />
                                <Home v-else class="w-5 h-5 stroke-[2.2]" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-[#0F2747]">{{ unit.name }}</h3>
                                <span class="text-xs text-[#5B6770] font-mono">{{ unit.code }}</span>
                            </div>
                        </div>
                        <span class="text-xs font-bold bg-[#F8FAFC] text-[#0E6E7E] px-2.5 py-1 rounded-lg border border-[#D6E0E7]">
                            {{ unit.unit_type_label }}
                        </span>
                    </div>

                    <div class="space-y-2.5 text-xs">
                        <div class="flex items-center justify-between py-1">
                            <span class="text-[#5B6770]">العقار / المبنى:</span>
                            <span class="font-bold text-[#0F2747] flex items-center gap-1">
                                <Building2 class="w-3.5 h-3.5 text-[#5B6770]" />
                                <span>{{ unit.property_name }}</span>
                            </span>
                        </div>
                        <div class="flex items-center justify-between py-1" v-if="unit.property_address">
                            <span class="text-[#5B6770]">العنوان:</span>
                            <span class="text-[#0F2747] truncate max-w-[200px]">{{ unit.property_address }}</span>
                        </div>
                        <div class="flex items-center justify-between py-1 border-t border-[#D6E0E7]/60">
                            <span class="text-[#5B6770]">تاريخ البداية:</span>
                            <span class="font-mono font-bold text-[#0F2747]">{{ lease.starts_on }}</span>
                        </div>
                        <div class="flex items-center justify-between py-1">
                            <span class="text-[#5B6770]">تاريخ النهاية:</span>
                            <span class="font-mono font-bold text-[#0F2747]">{{ lease.ends_on }}</span>
                        </div>
                        <div class="flex items-center justify-between py-1 border-t border-[#D6E0E7]/60">
                            <span class="text-[#5B6770]">دورية الاستحقاق:</span>
                            <span class="font-bold text-[#0E6E7E] bg-[#E3F1F4] px-2 py-0.5 rounded-md">
                                {{ lease.billing_interval === 'monthly' ? 'شهري' : lease.billing_interval }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- بطاقة بيانات المستأجر -->
                <div class="rounded-2xl sm:rounded-3xl border border-[#D6E0E7] bg-white p-4 sm:p-6 shadow-2xs space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7]">
                        <div class="flex items-center gap-2.5">
                            <div class="w-10 h-10 rounded-xl bg-[#0E6E7E] text-white flex items-center justify-center font-black text-sm shrink-0">
                                {{ renter.name.charAt(0) }}
                            </div>
                            <div>
                                <Link
                                    :href="route('renters.show', renter.id)"
                                    class="text-sm font-bold text-[#0F2747] hover:text-[#0E6E7E] transition block"
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
                                class="p-2 rounded-xl bg-slate-100 text-[#0F2747] hover:bg-slate-200 transition shadow-2xs"
                                title="اتصال هاتفي"
                            >
                                <Phone class="w-4 h-4" />
                            </a>
                        </div>
                    </div>

                    <div class="space-y-2.5 text-xs">
                        <div class="flex items-center justify-between py-1">
                            <span class="text-[#5B6770]">رقم الهوية / الإقامة:</span>
                            <span class="font-mono font-bold text-[#0F2747] bg-[#F8FAFC] px-2 py-0.5 rounded-md border border-[#D6E0E7]">
                                {{ renter.national_id || 'غير مسجل' }}
                            </span>
                        </div>
                        <div v-if="renter.email" class="flex items-center justify-between py-1">
                            <span class="text-[#5B6770]">البريد الإلكتروني:</span>
                            <span class="font-mono text-[#0F2747] truncate max-w-[180px]">{{ renter.email }}</span>
                        </div>
                        <div class="pt-2 border-t border-[#D6E0E7]/60">
                            <Link
                                :href="route('renters.show', renter.id)"
                                class="w-full h-9 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] hover:bg-white text-xs font-bold text-[#0E6E7E] flex items-center justify-center gap-1 transition"
                            >
                                <span>عرض السجل الكامل للمستأجر</span>
                                <ExternalLink class="w-3.5 h-3.5" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- العمود الأيسر (8 أعمدة على الديسكتوب): قسم تأمين الوحدة الشامل + فواتير الإيجار -->
            <div class="lg:col-span-8 space-y-5 sm:space-y-6">
                <!-- الصندوق الرئيسي لتأمين الوحدة المسترد (Security Deposit Hero Box) -->
                <div class="rounded-2xl sm:rounded-3xl border-2 border-[#0E6E7E]/30 bg-white p-4 sm:p-7 shadow-xs space-y-6">
                    <!-- عنوان القسم وحالة دورة الحياة -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-[#D6E0E7]">
                        <div class="flex items-start sm:items-center gap-3">
                            <div class="w-11 h-11 rounded-2xl bg-[#0E6E7E] text-white flex items-center justify-center shadow-xs shrink-0">
                                <ShieldCheck class="w-6 h-6 stroke-[2]" />
                            </div>
                            <div>
                                <h3 class="text-base sm:text-lg font-bold text-[#0F2747] flex items-center gap-2">
                                    <span>تأمين الوحدة المسترد</span>
                                    <span class="text-xs font-semibold text-[#5B6770] font-sans">Security Deposit</span>
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
                            class="h-10 px-4 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer"
                        >
                            <CreditCard class="w-3.5 h-3.5" />
                            <span>تسجيل استلام دفعة تأمين</span>
                        </button>

                        <!-- زر بدء فحص الوحدة والتسوية -->
                        <button
                            v-if="security_deposit.actions.can_start_settlement"
                            @click="showStartSettlementModal = true"
                            type="button"
                            class="h-10 px-4 rounded-xl border border-[#0E6E7E] text-[#0E6E7E] hover:bg-[#E3F1F4] text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-2xs"
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
                            @click="showFinalizeSettlementModal = true"
                            type="button"
                            class="h-10 px-4 rounded-xl bg-[#0F2747] hover:bg-[#1E3A5F] text-white text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer"
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
                                <span class="font-black text-[#0F2747] font-mono text-base block">
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

                            <div class="p-3 sm:p-3.5 rounded-2xl bg-[#E3F1F4] border border-[#0E6E7E]/30 space-y-1">
                                <span class="text-[#0E6E7E] block text-[11px] font-bold">الرصيد المتاح للرد</span>
                                <span class="font-black text-[#0E6E7E] font-mono text-base block">
                                    {{ security_deposit.refundable_balance.toLocaleString() }}
                                    <span class="text-[10px] font-sans font-normal text-[#0E6E7E]">{{ currency_symbol }}</span>
                                </span>
                            </div>
                        </div>

                        <!-- شريط تقدم التحصيل -->
                        <div v-if="security_deposit.agreed_amount > 0" class="p-3 rounded-xl bg-[#F8FAFC] border border-[#D6E0E7] space-y-1.5">
                            <div class="flex items-center justify-between text-[11px] text-[#5B6770] font-semibold">
                                <span>نسبة استيفاء مبلغ التأمين من المستأجر:</span>
                                <span class="font-mono font-bold text-[#0F2747]">{{ depositPercentReceived }}%</span>
                            </div>
                            <div class="w-full h-2 bg-slate-200 rounded-full overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="depositPercentReceived >= 100 ? 'bg-emerald-600' : 'bg-[#0E6E7E]'"
                                    :style="{ width: `${depositPercentReceived}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <!-- تفاصيل محضر فحص الوحدة والتسوية الحالية إن وُجدت -->
                    <div v-if="security_deposit.settlement" class="p-4 sm:p-5 rounded-2xl border border-blue-200 bg-blue-50/50 space-y-2.5 text-xs">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <span class="font-bold text-blue-900 flex items-center gap-1.5 text-sm">
                                <FileCheck class="w-4 h-4 text-blue-700" />
                                <span>محضر فحص حالة الوحدة والتسوية المعتمد</span>
                            </span>
                            <span class="font-bold text-blue-800 bg-blue-100 px-2.5 py-1 rounded-lg border border-blue-200">
                                تصنيف الفحص: {{ security_deposit.settlement.inspection_category_label }}
                            </span>
                        </div>
                        <p v-if="security_deposit.settlement.inspection_notes" class="text-blue-950 leading-relaxed bg-white/70 p-3 rounded-xl border border-blue-200">
                            {{ security_deposit.settlement.inspection_notes }}
                        </p>
                        <div v-if="security_deposit.settlement.settled_at" class="text-[11px] text-blue-700 pt-1.5 border-t border-blue-200 flex items-center gap-1">
                            <CheckCircle2 class="w-3.5 h-3.5 text-blue-600" />
                            <span>تم اعتماد التسوية بواسطة {{ security_deposit.settlement.settled_by }} في {{ security_deposit.settlement.settled_at }}</span>
                        </div>
                    </div>

                    <!-- سجل الحركات المالية الموثقة للتأمين (Audit Trail) -->
                    <div class="space-y-6 pt-2">
                        <div class="flex items-center gap-2 pb-2 border-b border-[#D6E0E7]">
                            <History class="w-4 h-4 text-[#0E6E7E]" />
                            <h4 class="text-sm font-bold text-[#0F2747]">سجل الحركات المالية الموثقة للتأمين</h4>
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
                                            <span class="font-mono font-bold text-[#0E6E7E]">{{ p.reference }}</span>
                                            <span v-if="p.is_reversed" class="text-rose-700 bg-rose-50 px-2 py-0.5 rounded font-bold text-[10px]">ملغاة</span>
                                            <span v-else class="text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded font-bold text-[10px]">سارية</span>
                                        </div>
                                        <div class="flex items-baseline justify-between">
                                            <span class="font-mono font-black text-base text-[#0F2747]">
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
                                                <td class="py-3 px-4 font-mono font-bold text-[#0E6E7E]">{{ p.reference }}</td>
                                                <td class="py-3 px-4 font-mono font-bold text-[#0F2747]">{{ p.amount.toLocaleString() }} {{ currency_symbol }}</td>
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

                        <!-- 2. الخصومات المعتمدة للأضرار -->
                        <div class="space-y-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-[#5B6770]">الخصومات المبررة للأضرار ({{ security_deposit.deductions.length }})</span>
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
                                        <p class="text-xs text-[#0F2747] leading-relaxed">{{ d.description }}</p>
                                        <div class="flex items-center justify-between text-[11px] text-[#5B6770] pt-1.5 border-t border-rose-200/60">
                                            <span class="font-mono">{{ d.deducted_at }}</span>
                                            <button
                                                v-if="security_deposit.status !== 'settled'"
                                                @click="deleteDeduction(d.id)"
                                                type="button"
                                                class="text-rose-700 hover:text-rose-900 font-bold flex items-center gap-1 cursor-pointer"
                                            >
                                                <Trash2 class="w-3.5 h-3.5" />
                                                <span>حذف البند</span>
                                            </button>
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
                                                <th class="py-3 px-4 text-left" v-if="security_deposit.status !== 'settled'">إلغاء</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-[#D6E0E7]">
                                            <tr v-for="d in security_deposit.deductions" :key="d.id" class="hover:bg-[#F8FAFC] transition">
                                                <td class="py-3 px-4 font-bold text-[#0F2747]">{{ d.category_label }}</td>
                                                <td class="py-3 px-4 font-mono font-bold text-rose-700">{{ d.amount.toLocaleString() }} {{ currency_symbol }}</td>
                                                <td class="py-3 px-4 text-[#5B6770]">{{ d.description }}</td>
                                                <td class="py-3 px-4 font-mono text-[#5B6770]">{{ d.deducted_at }}</td>
                                                <td class="py-3 px-4 text-[#5B6770]">{{ d.recorded_by }}</td>
                                                <td class="py-3 px-4 text-left" v-if="security_deposit.status !== 'settled'">
                                                    <button
                                                        @click="deleteDeduction(d.id)"
                                                        type="button"
                                                        class="text-rose-600 hover:text-rose-800 p-1 rounded hover:bg-rose-50 cursor-pointer"
                                                        title="إلغاء الخصم"
                                                    >
                                                        <Trash2 class="w-3.5 h-3.5" />
                                                    </button>
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
                                        <p v-if="r.notes" class="text-xs text-[#0F2747] pt-1 border-t border-emerald-200/60">{{ r.notes }}</p>
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

                <!-- جدول استحقاقات وفواتير الإيجار (Rent Charges) للتمييز التام والواضح -->
                <div class="rounded-2xl sm:rounded-3xl border border-[#D6E0E7] bg-white p-4 sm:p-6 shadow-2xs space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7]">
                        <div class="flex items-center gap-2">
                            <FileText class="w-4 h-4 text-[#0E6E7E]" />
                            <h3 class="text-sm font-bold text-[#0F2747]">فواتير واستحقاقات الإيجار (منفصلة تماماً عن التأمين)</h3>
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
                                <span class="font-bold text-[#0F2747]">{{ c.period }}</span>
                                <span v-if="c.status === 'paid'" class="text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded font-bold text-[10px]">مسدد بالكامل</span>
                                <span v-else-if="c.status === 'overdue'" class="text-rose-700 bg-rose-50 px-2 py-0.5 rounded font-bold text-[10px]">متأخر</span>
                                <span v-else class="text-amber-700 bg-amber-50 px-2 py-0.5 rounded font-bold text-[10px]">مستحق</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2 text-xs">
                                <div>
                                    <span class="text-[10px] text-[#5B6770] block">المطلوب</span>
                                    <span class="font-mono font-bold text-[#0F2747]">{{ c.amount.toLocaleString() }}</span>
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
                                    <td class="py-3 px-4 font-bold text-[#0F2747]">{{ c.period }}</td>
                                    <td class="py-3 px-4 font-mono text-[#5B6770]">{{ c.due_date }}</td>
                                    <td class="py-3 px-4 font-mono font-bold text-[#0F2747]">{{ c.amount.toLocaleString() }} {{ currency_symbol }}</td>
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
            <div @click="showRecordPaymentModal = false" class="fixed inset-0 bg-[#0F2747]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-5 sm:p-7 z-10 text-right animate-in fade-in zoom-in-95" dir="rtl">
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7] mb-4">
                    <h3 class="text-sm font-bold text-[#0F2747]">تسجيل استلام دفعة تأمين</h3>
                    <button @click="showRecordPaymentModal = false" type="button" class="p-1 text-slate-400 hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitPayment" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1">المبلغ المحصل *</label>
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
                        <label class="block text-xs font-bold text-[#0F2747] mb-1">طريقة السداد *</label>
                        <select v-model="paymentForm.payment_method" class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs">
                            <option value="cash">نقدي (كاش)</option>
                            <option value="bank_transfer">تحويل بنكي</option>
                            <option value="cheque">شيك مصرفي</option>
                            <option value="other">إنستاباي / فيزا / إلكتروني</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1">تاريخ التحصيل *</label>
                        <input v-model="paymentForm.paid_at" type="date" required class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-right" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1">رقم المرجع / الإيصال</label>
                        <input v-model="paymentForm.reference" type="text" placeholder="مثال: REC-10293" class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-right" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1">ملاحظات إضافية</label>
                        <textarea v-model="paymentForm.notes" rows="2" class="w-full p-2.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-right"></textarea>
                    </div>

                    <div class="pt-3 border-t border-[#D6E0E7] flex justify-end gap-2">
                        <button @click="showRecordPaymentModal = false" type="button" class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold">إلغاء</button>
                        <button type="submit" class="h-10 px-4 rounded-xl bg-[#0E6E7E] text-white text-xs font-bold">تأكيد تسجيل الدفعة</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- مودال 2: بدء فحص الوحدة والتسوية -->
        <div v-if="showStartSettlementModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showStartSettlementModal = false" class="fixed inset-0 bg-[#0F2747]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-5 sm:p-7 z-10 text-right animate-in fade-in zoom-in-95" dir="rtl">
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7] mb-4">
                    <h3 class="text-sm font-bold text-[#0F2747]">بدء فحص حالة الوحدة والتسوية</h3>
                    <button @click="showStartSettlementModal = false" type="button" class="p-1 text-slate-400 hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitStartSettlement" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1">نتيجة فحص الوحدة *</label>
                        <select v-model="settlementForm.inspection_category" class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs">
                            <option value="no_issues">لا توجد ملاحظات أو أضرار (حالة ممتازة)</option>
                            <option value="furniture_damage">تلف أثاث أو أجهزة</option>
                            <option value="fixture_damage">تلف تركيبات ومرافق</option>
                            <option value="missing_items">مفقودات ومحتويات ناقصة</option>
                            <option value="other_damage">أضرار موثقة أخرى</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1">ملاحظات وتقرير الفحص الميداني</label>
                        <textarea v-model="settlementForm.inspection_notes" rows="3" placeholder="وصف حالة الوحدة عند التسليم، الجدران، الأرضيات، التركيبات..." class="w-full p-2.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-right"></textarea>
                    </div>

                    <div class="pt-3 border-t border-[#D6E0E7] flex justify-end gap-2">
                        <button @click="showStartSettlementModal = false" type="button" class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold">إلغاء</button>
                        <button type="submit" class="h-10 px-4 rounded-xl bg-[#0E6E7E] text-white text-xs font-bold">بدء التسوية</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- مودال 3: إضافة خصم تعويض أضرار -->
        <div v-if="showRecordDeductionModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showRecordDeductionModal = false" class="fixed inset-0 bg-[#0F2747]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-5 sm:p-7 z-10 text-right animate-in fade-in zoom-in-95" dir="rtl">
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7] mb-4">
                    <h3 class="text-sm font-bold text-[#0F2747]">إضافة خصم مبرر من مبلغ التأمين</h3>
                    <button @click="showRecordDeductionModal = false" type="button" class="p-1 text-slate-400 hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div v-if="deductionForm.errors.amount" class="p-3 mb-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs">
                    {{ deductionForm.errors.amount }}
                </div>

                <form @submit.prevent="submitDeduction" class="space-y-4">
                    <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-900">
                        الحد الأقصى المتاح للخصم حالياً: <strong>{{ security_deposit.refundable_balance.toLocaleString() }} {{ currency_symbol }}</strong>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1">تصنيف الضرر / الخصم *</label>
                        <select v-model="deductionForm.category" class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs">
                            <option value="furniture_damage">تلف أثاث أو أجهزة</option>
                            <option value="fixture_damage">تلف تركيبات ومرافق</option>
                            <option value="missing_items">مفقودات ومحتويات ناقصة</option>
                            <option value="other_damage">أضرار موثقة أخرى</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1">وصف ومبرر الخصم *</label>
                        <input v-model="deductionForm.description" type="text" placeholder="مثال: إصلاح باب الحمام المكسور، استبدال مفاتيح الإنارة" required class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-right" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1">مبلغ الخصم *</label>
                        <div class="relative">
                            <input
                                v-model="deductionForm.amount"
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
                        <label class="block text-xs font-bold text-[#0F2747] mb-1">تاريخ الخصم *</label>
                        <input v-model="deductionForm.deducted_at" type="date" required class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-right" />
                    </div>

                    <div class="pt-3 border-t border-[#D6E0E7] flex justify-end gap-2">
                        <button @click="showRecordDeductionModal = false" type="button" class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold">إلغاء</button>
                        <button type="submit" class="h-10 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold">اعتماد الخصم</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- مودال 4: تسجيل رد التأمين -->
        <div v-if="showRecordRefundModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showRecordRefundModal = false" class="fixed inset-0 bg-[#0F2747]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-5 sm:p-7 z-10 text-right animate-in fade-in zoom-in-95" dir="rtl">
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7] mb-4">
                    <h3 class="text-sm font-bold text-[#0F2747]">تسجيل رد مبلغ التأمين للمستأجر</h3>
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
                        <label class="block text-xs font-bold text-[#0F2747] mb-1">المبلغ المردود *</label>
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
                        <label class="block text-xs font-bold text-[#0F2747] mb-1">طريقة الرد *</label>
                        <select v-model="refundForm.payment_method" class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs">
                            <option value="bank_transfer">تحويل بنكي</option>
                            <option value="cash">نقدي (كاش)</option>
                            <option value="cheque">شيك</option>
                            <option value="other">إنستاباي / محفظة إلكترونية</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1">تاريخ الرد الفعلي *</label>
                        <input v-model="refundForm.refunded_at" type="date" required class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-right" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1">رقم مرجع التحويل / الإيصال</label>
                        <input v-model="refundForm.reference" type="text" placeholder="مثال: TXN-54321" class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-right" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1">ملاحظات الرد</label>
                        <textarea v-model="refundForm.notes" rows="2" class="w-full p-2.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-right"></textarea>
                    </div>

                    <div class="pt-3 border-t border-[#D6E0E7] flex justify-end gap-2">
                        <button @click="showRecordRefundModal = false" type="button" class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold">إلغاء</button>
                        <button type="submit" class="h-10 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">توثيق الرد الفعلي</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- مودال 5: اعتماد التسوية النهائية -->
        <div v-if="showFinalizeSettlementModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showFinalizeSettlementModal = false" class="fixed inset-0 bg-[#0F2747]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-5 sm:p-7 z-10 text-right animate-in fade-in zoom-in-95" dir="rtl">
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7] mb-4">
                    <h3 class="text-sm font-bold text-[#0F2747]">اعتماد وإغلاق التسوية النهائية</h3>
                    <button @click="showFinalizeSettlementModal = false" type="button" class="p-1 text-slate-400 hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitFinalizeSettlement" class="space-y-4">
                    <div class="p-3.5 bg-slate-50 rounded-2xl border border-[#D6E0E7] space-y-1.5 text-xs">
                        <div class="flex justify-between">
                            <span class="text-[#5B6770]">المحصل من التأمين:</span>
                            <span class="font-bold font-mono">{{ security_deposit.total_received.toLocaleString() }} {{ currency_symbol }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#5B6770]">إجمالي الخصومات:</span>
                            <span class="font-bold font-mono text-rose-700">{{ security_deposit.total_deductions.toLocaleString() }} {{ currency_symbol }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#5B6770]">إجمالي المردود:</span>
                            <span class="font-bold font-mono text-emerald-700">{{ security_deposit.total_refunded.toLocaleString() }} {{ currency_symbol }}</span>
                        </div>
                        <div class="flex justify-between pt-1.5 border-t border-[#D6E0E7]">
                            <span class="font-bold text-[#0F2747]">المتبقي القابل للرد:</span>
                            <span class="font-bold font-mono text-[#0E6E7E]">{{ security_deposit.refundable_balance.toLocaleString() }} {{ currency_symbol }}</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1">التقرير النهائي لفحص الوحدة *</label>
                        <select v-model="finalizeSettlementForm.inspection_category" class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs">
                            <option value="no_issues">لا توجد ملاحظات أو أضرار</option>
                            <option value="furniture_damage">تلف أثاث أو أجهزة</option>
                            <option value="fixture_damage">تلف تركيبات ومرافق</option>
                            <option value="missing_items">مفقودات ومحتويات ناقصة</option>
                            <option value="other_damage">أضرار موثقة أخرى</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1">ملاحظات الإغلاق النهائي للتسوية</label>
                        <textarea v-model="finalizeSettlementForm.notes" rows="2" class="w-full p-2.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-right"></textarea>
                    </div>

                    <div class="pt-3 border-t border-[#D6E0E7] flex justify-end gap-2">
                        <button @click="showFinalizeSettlementModal = false" type="button" class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold">إلغاء</button>
                        <button type="submit" class="h-10 px-4 rounded-xl bg-[#0F2747] hover:bg-[#1E3A5F] text-white text-xs font-bold">إغلاق واعتماد التسوية</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- مودال 6: عكس دفعة تأمين -->
        <div v-if="showReversePaymentModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showReversePaymentModal = false" class="fixed inset-0 bg-[#0F2747]/60 backdrop-blur-xs"></div>
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
                        <div>المرجع: <strong class="font-mono text-[#0E6E7E]">{{ activePaymentForReversal.reference }}</strong></div>
                        <div>المبلغ: <strong class="font-mono">{{ activePaymentForReversal.amount.toLocaleString() }} {{ currency_symbol }}</strong></div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1">سبب إلغاء وعكس الدفعة *</label>
                        <textarea v-model="reversePaymentForm.reversal_reason" rows="3" placeholder="اذكر سبب الإلغاء (سداد بالخطأ، شيك مرتجع، تصحيح محاسبي)..." required class="w-full p-2.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-right"></textarea>
                    </div>

                    <div class="pt-3 border-t border-[#D6E0E7] flex justify-end gap-2">
                        <button @click="showReversePaymentModal = false" type="button" class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold">إلغاء</button>
                        <button type="submit" class="h-10 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold">تأكيد عكس الدفعة</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
