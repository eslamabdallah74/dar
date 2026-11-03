<script setup lang="ts">
import { computed, ref } from 'vue';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import SettlementSummary from '@/Components/Leases/SecurityDeposit/SettlementSummary.vue';
import type {
    DepositDeduction,
    DepositLedgerEntry,
    DepositPayment,
    LeaseData,
    SecurityDepositData,
} from './types';
import {
    formatMoney,
    getDepositHumanSummary,
} from './useLeaseUtils';
import {
    ChevronDown,
    ChevronUp,
    FileCheck,
    Paperclip,
    Plus,
    RotateCcw,
    Shield,
    ShieldCheck,
    Trash2,
} from 'lucide-vue-next';

const props = defineProps<{
    lease: LeaseData;
    securityDeposit: SecurityDepositData;
    currencySymbol: string;
    depositLedger: DepositLedgerEntry[];
}>();

const emit = defineEmits<{
    (e: 'recordPayment'): void;
    (e: 'startSettlement'): void;
    (e: 'recordDeduction'): void;
    (e: 'recordRefund'): void;
    (e: 'finalizeSettlement'): void;
    (e: 'pendingRefund'): void;
    (e: 'reversePayment', payment: DepositPayment): void;
    (e: 'editDeduction', deduction: DepositDeduction): void;
    (e: 'deleteDeduction', deduction: DepositDeduction): void;
}>();

const hasDeposit = computed(() => {
    const deposit = props.securityDeposit;
    return deposit.agreed_amount > 0 || deposit.total_received > 0 || deposit.payments.length > 0 || deposit.settlement !== null;
});

// Accordion is open by default if an action is pending or in progress
const isActionRequired = computed(() => {
    const dep = props.securityDeposit;
    return dep.status === 'under_review' ||
        Boolean(dep.actions.can_record_pending_refund) ||
        (dep.actions.can_record_payment && dep.outstanding_amount > 0);
});

const isAccordionOpen = ref(isActionRequired.value || hasDeposit.value);
const isLedgerOpen = ref(false);
const isMoreActionsOpen = ref(false);

const humanSummary = computed(() => {
    return getDepositHumanSummary(props.securityDeposit, props.currencySymbol);
});

// Stepper states for Settlement:
// 1. استلام التأمين (Received)
// 2. فحص الوحدة (Inspected)
// 3. مراجعة الخصومات (Deductions Reviewed)
// 4. اعتماد التسوية (Settled)
// 5. رد المبلغ (Refunded)
const settlementStep = computed(() => {
    const dep = props.securityDeposit;
    if (dep.status === 'settled') {
        return dep.refundable_balance === 0 || dep.settlement?.refund_status === 'refunded' ? 5 : 4;
    }
    if (dep.status === 'under_review') {
        return dep.deductions.length > 0 ? 3 : 2;
    }
    if (dep.total_received > 0) {
        return 1;
    }
    return 0;
});

const depositBadgeStatus = computed(() => {
    const map: Record<string, string> = {
        held: 'paid',
        partially_received: 'partial',
        due: 'pending',
        under_review: 'in_progress',
        settled: 'closed',
    };
    return map[props.securityDeposit.status] ?? 'inactive';
});

// Can finalize settlement logic
const isLeaseActiveAndNotEnded = computed(() => {
    return props.lease.status === 'active' && !props.lease.is_past_end_date;
});

const canFinalizeNow = computed(() => {
    return props.securityDeposit.actions.can_settle && !isLeaseActiveAndNotEnded.value;
});

const finalizeDisabledReason = computed(() => {
    if (isLeaseActiveAndNotEnded.value) {
        return `متاح بعد انتهاء العقد في ${props.lease.ends_on}، أو عبر زر إنهاء العقد مبكراً.`;
    }
    return '';
});

const depositLedgerStyles: Record<string, { dot: string; amount: string; sign: string }> = {
    payment: { dot: 'bg-emerald-500', amount: 'text-emerald-700', sign: '+' },
    deduction: { dot: 'bg-rose-500', amount: 'text-rose-700', sign: '−' },
    refund: { dot: 'bg-[#163E3E]', amount: 'text-[#163E3E]', sign: '−' },
};
</script>

<template>
    <section aria-labelledby="deposit-section-heading" class="rounded-3xl border border-[#D6E0E7] bg-white shadow-xs overflow-hidden">
        <!-- If no deposit required on this lease -->
        <div v-if="!hasDeposit" class="p-6 text-center text-xs text-[#5B6770] space-y-2">
            <Shield class="mx-auto h-8 w-8 text-[#8A9AA7]" />
            <h2 id="deposit-section-heading" class="text-sm font-bold text-[#163E3E]">لا يوجد مبلغ تأمين على هذا العقد</h2>
            <p>أُنشئ هذا العقد دون اشتراط تأمين نقدي، ولا توجد تسوية تأمين مطلوبة عند إنهائه.</p>
        </div>

        <template v-else>
            <!-- Header (Accordion Trigger) -->
            <header
                class="flex flex-wrap items-center justify-between gap-3 border-b border-[#EEF2F4] p-5 sm:p-6 cursor-pointer bg-white transition hover:bg-[#FBFAF6]"
                @click="isAccordionOpen = !isAccordionOpen"
            >
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <div class="flex h-7 w-7 items-center justify-center rounded-xl bg-[#E8F0F0] text-[#163E3E]">
                            <ShieldCheck class="h-4 w-4" />
                        </div>
                        <h2 id="deposit-section-heading" class="text-base sm:text-lg font-bold text-[#163E3E]">
                            التأمين وتسوية الوحدة
                        </h2>
                        <StatusBadge
                            :status="depositBadgeStatus"
                            :label="securityDeposit.status_label"
                            size="sm"
                        />
                    </div>
                    <p class="text-xs text-[#5B6770]">
                        الرصيد المحفوظ القابل للرد:
                        <strong class="font-mono text-[#163E3E]">{{ formatMoney(securityDeposit.refundable_balance) }} {{ currencySymbol }}</strong>
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-[#5B6770]">
                        {{ isAccordionOpen ? 'طي القسم' : 'عرض التفاصيل والتسوية' }}
                    </span>
                    <ChevronUp v-if="isAccordionOpen" class="h-4 w-4 text-[#5B6770]" />
                    <ChevronDown v-else class="h-4 w-4 text-[#5B6770]" />
                </div>
            </header>

            <!-- Collapsible Body -->
            <div v-show="isAccordionOpen" class="p-5 sm:p-6 space-y-6">
                <!-- 1. Human-language Summary Sentence in Callout Box -->
                <div class="rounded-2xl border border-[#163E3E]/15 bg-[#E8F0F0]/40 p-4 sm:p-5 text-xs sm:text-sm text-[#163E3E] leading-relaxed flex items-start gap-3">
                    <Shield class="h-5 w-5 shrink-0 text-[#163E3E] mt-0.5" />
                    <div class="space-y-1">
                        <p class="font-bold">ملخص حالة التأمين:</p>
                        <p class="font-medium text-[#163E3E]/90">
                            {{ humanSummary }}
                        </p>
                    </div>
                </div>

                <!-- 2. Settlement Stepper (استلام ← فحص ← مراجعة خصومات ← اعتماد ← رد) -->
                <div class="rounded-2xl bg-[#FBFAF6] p-4 border border-[#EEF2F4] space-y-3">
                    <h3 class="text-xs font-bold text-[#5B6770]">مسار تسوية التأمين:</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 text-center text-xs">
                        <div
                            class="p-2.5 rounded-xl border"
                            :class="settlementStep >= 1 ? 'border-emerald-300 bg-emerald-50 text-emerald-900 font-bold' : 'border-[#D6E0E7] bg-white text-[#8A9AA7]'"
                        >
                            <span class="block text-[10px] text-[#5B6770]">١. الاستلام</span>
                            <span class="mt-0.5 block">استلام التأمين {{ settlementStep >= 1 ? '✓' : '' }}</span>
                        </div>

                        <div
                            class="p-2.5 rounded-xl border"
                            :class="settlementStep >= 2 ? 'border-emerald-300 bg-emerald-50 text-emerald-900 font-bold' : 'border-[#D6E0E7] bg-white text-[#8A9AA7]'"
                        >
                            <span class="block text-[10px] text-[#5B6770]">٢. المعاينة</span>
                            <span class="mt-0.5 block">فحص الوحدة {{ settlementStep >= 2 ? '✓' : '' }}</span>
                        </div>

                        <div
                            class="p-2.5 rounded-xl border"
                            :class="settlementStep >= 3 ? 'border-emerald-300 bg-emerald-50 text-emerald-900 font-bold' : 'border-[#D6E0E7] bg-white text-[#8A9AA7]'"
                        >
                            <span class="block text-[10px] text-[#5B6770]">٣. الخصومات</span>
                            <span class="mt-0.5 block">تحديد الخصومات {{ settlementStep >= 3 ? '✓' : '' }}</span>
                        </div>

                        <div
                            class="p-2.5 rounded-xl border"
                            :class="settlementStep >= 4 ? 'border-emerald-300 bg-emerald-50 text-emerald-900 font-bold' : 'border-[#D6E0E7] bg-white text-[#8A9AA7]'"
                        >
                            <span class="block text-[10px] text-[#5B6770]">٤. الاعتماد</span>
                            <span class="mt-0.5 block">اعتماد التسوية {{ settlementStep >= 4 ? '✓' : '' }}</span>
                        </div>

                        <div
                            class="p-2.5 rounded-xl border col-span-2 sm:col-span-1"
                            :class="settlementStep >= 5 ? 'border-emerald-300 bg-emerald-50 text-emerald-900 font-bold' : 'border-[#D6E0E7] bg-white text-[#8A9AA7]'"
                        >
                            <span class="block text-[10px] text-[#5B6770]">٥. الصرف</span>
                            <span class="mt-0.5 block">رد المستحق {{ settlementStep >= 5 ? '✓' : '' }}</span>
                        </div>
                    </div>
                </div>

                <!-- 3. Unit Inspection Summary Line (نتيجة الفحص) -->
                <div class="rounded-2xl border border-[#EEF2F4] p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-[#163E3E]">تقرير فحص الوحدة:</span>
                            <span
                                v-if="securityDeposit.settlement"
                                class="rounded-md bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-800"
                            >
                                {{ securityDeposit.settlement.inspection_category_label }}
                            </span>
                            <span v-else class="text-xs text-[#8A9AA7]">لم يبدأ الفحص الميداني بعد</span>
                        </div>
                        <p v-if="securityDeposit.settlement?.inspection_notes" class="text-xs text-[#5B6770]">
                            {{ securityDeposit.settlement.inspection_notes }}
                        </p>
                        <p v-if="securityDeposit.settlement?.settled_by" class="text-[11px] text-[#8A9AA7]">
                            بواسطة: {{ securityDeposit.settlement.settled_by }}
                            <span v-if="securityDeposit.settlement.settled_at">· {{ securityDeposit.settlement.settled_at }}</span>
                        </p>
                    </div>

                    <button
                        v-if="securityDeposit.actions.can_start_settlement"
                        type="button"
                        class="inline-flex h-8 cursor-pointer items-center justify-center rounded-xl border border-[#D6E0E7] bg-white px-3 text-xs font-bold text-[#163E3E] transition hover:bg-[#F8F4EA]"
                        @click="emit('startSettlement')"
                    >
                        <span>{{ securityDeposit.status === 'under_review' ? 'تحديث نتيجة الفحص' : 'بدء فحص الوحدة' }}</span>
                    </button>
                </div>

                <!-- 4. Deductions Section (الخصومات المقتطعة) -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs sm:text-sm font-bold text-[#163E3E] flex items-center gap-1.5">
                            <span>الخصومات المقتطعة</span>
                            <span class="font-mono text-xs text-[#5B6770]">({{ securityDeposit.deductions.length }})</span>
                        </h3>

                        <button
                            v-if="securityDeposit.actions.can_record_deduction"
                            type="button"
                            class="inline-flex h-8 cursor-pointer items-center gap-1 rounded-xl border border-[#D6E0E7] bg-white px-3 text-xs font-bold text-[#163E3E] transition hover:bg-[#F8F4EA]"
                            @click="emit('recordDeduction')"
                        >
                            <Plus class="h-3.5 w-3.5" />
                            <span>إضافة خصم</span>
                        </button>
                    </div>

                    <div v-if="securityDeposit.deductions.length === 0" class="rounded-xl bg-[#FBFAF6] p-4 text-center text-xs text-[#5B6770]">
                        لا توجد خصومات مسجلة على التأمين حتى الآن.
                    </div>

                    <div v-else class="space-y-2">
                        <div
                            v-for="deduction in securityDeposit.deductions"
                            :key="deduction.id"
                            class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 rounded-xl border border-[#EEF2F4] p-3 transition hover:bg-[#FBFAF6]"
                        >
                            <div class="space-y-0.5">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-xs text-[#163E3E]">{{ deduction.category_label }}</span>
                                    <span class="font-mono text-xs font-bold text-rose-700">
                                        − {{ formatMoney(deduction.amount) }} {{ currencySymbol }}
                                    </span>
                                </div>
                                <p class="text-xs text-[#5B6770]">{{ deduction.description }}</p>
                                <span class="text-[10px] text-[#8A9AA7]">
                                    بتاريخ {{ deduction.deducted_at }} · بواسطة {{ deduction.recorded_by }}
                                </span>
                            </div>

                            <div class="flex items-center gap-2 self-end sm:self-auto">
                                <a
                                    v-if="deduction.attachment_url"
                                    :href="deduction.attachment_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 text-xs font-semibold text-[#163E3E] underline-offset-4 hover:underline"
                                >
                                    <Paperclip class="h-3.5 w-3.5" />
                                    <span>المرفق</span>
                                </a>
                                <button
                                    v-if="securityDeposit.status !== 'settled'"
                                    type="button"
                                    class="cursor-pointer text-xs font-semibold text-[#163E3E] hover:underline"
                                    @click="emit('editDeduction', deduction)"
                                >
                                    تعديل
                                </button>
                                <button
                                    v-if="securityDeposit.status !== 'settled'"
                                    type="button"
                                    class="cursor-pointer text-xs font-semibold text-rose-700 hover:underline inline-flex items-center gap-0.5"
                                    @click="emit('deleteDeduction', deduction)"
                                >
                                    <Trash2 class="h-3 w-3" />
                                    <span>حذف</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. Finalize Settlement & Other Actions -->
                <div class="rounded-2xl border border-[#D6E0E7] bg-[#FBFAF6] p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="space-y-1">
                        <h4 class="text-xs sm:text-sm font-bold text-[#163E3E]">اعتماد التسوية النهائية للتأمين</h4>
                        <p v-if="finalizeDisabledReason" class="text-xs text-amber-800 font-semibold">
                            {{ finalizeDisabledReason }}
                        </p>
                        <p v-else class="text-xs text-[#5B6770]">
                            إغلاق حساب التأمين واحتساب المبلغ المستحق رده للمستأجر بصورة قطعية.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <!-- اعتماد التسوية النهائية Button -->
                        <button
                            type="button"
                            :disabled="!canFinalizeNow"
                            class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-xl px-4 text-xs font-bold transition shadow-xs disabled:opacity-50 disabled:cursor-not-allowed"
                            :class="canFinalizeNow ? 'bg-[#163E3E] text-white hover:bg-[#102F2F]' : 'bg-slate-200 text-slate-500'"
                            @click="canFinalizeNow && emit('finalizeSettlement')"
                        >
                            <FileCheck class="h-4 w-4" />
                            <span>اعتماد التسوية النهائية</span>
                        </button>

                        <!-- إجراءات أخرى Dropdown -->
                        <div class="relative">
                            <button
                                type="button"
                                class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-xl border border-[#D6E0E7] bg-white px-3 text-xs font-bold text-[#163E3E] transition hover:bg-[#F8F4EA]"
                                @click="isMoreActionsOpen = !isMoreActionsOpen"
                            >
                                <span>إجراءات أخرى</span>
                                <ChevronDown class="h-3.5 w-3.5" />
                            </button>

                            <div
                                v-if="isMoreActionsOpen"
                                class="absolute end-0 z-20 mt-1.5 w-52 rounded-xl border border-[#D6E0E7] bg-white p-1.5 shadow-lg text-xs"
                            >
                                <button
                                    v-if="securityDeposit.actions.can_record_payment"
                                    type="button"
                                    class="w-full text-start px-3 py-2 rounded-lg font-semibold hover:bg-[#F8F4EA] text-[#163E3E]"
                                    @click="isMoreActionsOpen = false; emit('recordPayment');"
                                >
                                    تسجيل استلام دفعة تأمين
                                </button>
                                <button
                                    v-if="securityDeposit.actions.can_start_settlement"
                                    type="button"
                                    class="w-full text-start px-3 py-2 rounded-lg font-semibold hover:bg-[#F8F4EA] text-[#163E3E]"
                                    @click="isMoreActionsOpen = false; emit('startSettlement');"
                                >
                                    تحديث نتيجة الفحص
                                </button>
                                <button
                                    v-if="securityDeposit.actions.can_record_refund"
                                    type="button"
                                    class="w-full text-start px-3 py-2 rounded-lg font-semibold hover:bg-[#F8F4EA] text-[#163E3E]"
                                    @click="isMoreActionsOpen = false; emit('recordRefund');"
                                >
                                    تسجيل رد للمستأجر
                                </button>
                                <button
                                    v-if="securityDeposit.actions.can_record_pending_refund"
                                    type="button"
                                    class="w-full text-start px-3 py-2 rounded-lg font-bold hover:bg-emerald-50 text-emerald-800"
                                    @click="isMoreActionsOpen = false; emit('pendingRefund');"
                                >
                                    توثيق صرف المبلغ المعلق
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 6. Existing Settlement Summary Card if settled -->
                <SettlementSummary
                    v-if="securityDeposit.settlement"
                    :settlement="securityDeposit.settlement"
                    :currency-symbol="currencySymbol"
                    :export-url="route('leases.security-deposit.export', lease.id)"
                    :statement-url="route('leases.security-deposit.statement', lease.id)"
                />

                <!-- 7. Collapsible Ledger (سجل حركة التأمين) -->
                <div class="border-t border-[#EEF2F4] pt-4">
                    <button
                        type="button"
                        class="flex w-full cursor-pointer items-center justify-between rounded-2xl bg-[#FBFAF6] px-4 py-3 text-start text-xs sm:text-sm font-bold text-[#163E3E] transition hover:bg-[#F8F4EA]"
                        @click="isLedgerOpen = !isLedgerOpen"
                    >
                        <div class="flex items-center gap-2">
                            <RotateCcw class="h-4 w-4 text-[#5B6770]" />
                            <span>سجل حركة التأمين</span>
                            <span class="rounded-full bg-slate-200 px-2 py-0.5 font-mono text-[11px] font-bold text-slate-700">
                                {{ depositLedger.length }}
                            </span>
                        </div>

                        <div class="flex items-center gap-1 text-xs text-[#5B6770]">
                            <span>{{ isLedgerOpen ? 'إخفاء السجل' : 'عرض السجل الزمني' }}</span>
                            <ChevronUp v-if="isLedgerOpen" class="h-4 w-4" />
                            <ChevronDown v-else class="h-4 w-4" />
                        </div>
                    </button>

                    <!-- Ledger entries -->
                    <div v-if="isLedgerOpen" class="mt-3">
                        <p v-if="depositLedger.length === 0" class="p-6 text-center text-xs text-[#5B6770]">
                            لم تُسجل أي حركة مالية على التأمين بعد.
                        </p>
                        <ol v-else class="divide-y divide-[#EEF2F4] rounded-2xl border border-[#EEF2F4] px-4 py-1">
                            <li
                                v-for="entry in depositLedger"
                                :key="entry.key"
                                class="flex items-start gap-3 py-3.5"
                            >
                                <span
                                    class="mt-2 h-2.5 w-2.5 shrink-0 rounded-full"
                                    :class="depositLedgerStyles[entry.kind]?.dot || 'bg-slate-400'"
                                    aria-hidden="true"
                                ></span>

                                <div class="min-w-0 flex-1">
                                    <div :class="entry.isReversed ? 'opacity-60' : ''">
                                        <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                            <p class="text-xs sm:text-sm font-bold text-[#163E3E]">
                                                {{ entry.title }}
                                                <span v-if="entry.isReversed" class="text-xs font-bold text-rose-700">· ملغاة</span>
                                            </p>
                                            <p
                                                class="font-mono text-xs sm:text-sm font-bold tabular-nums"
                                                :class="[depositLedgerStyles[entry.kind]?.amount || 'text-[#163E3E]', entry.isReversed ? 'line-through' : '']"
                                            >
                                                <span dir="ltr">{{ depositLedgerStyles[entry.kind]?.sign }}{{ formatMoney(entry.amount) }}</span>
                                                <span class="ms-1 text-xs font-sans font-medium">{{ currencySymbol }}</span>
                                            </p>
                                        </div>

                                        <p class="mt-0.5 text-[11px] text-[#5B6770]">
                                            <span class="font-mono tabular-nums">{{ entry.occurredAt }}</span>
                                            <span v-if="entry.method"> · {{ entry.method }}</span>
                                            <span v-if="entry.reference"> · <span class="font-mono">{{ entry.reference }}</span></span>
                                            · بواسطة {{ entry.recordedBy }}
                                        </p>
                                        <p v-if="entry.description" class="mt-1 text-xs text-[#334155]">{{ entry.description }}</p>
                                        <p v-if="entry.notes" class="mt-1 text-xs text-[#5B6770]">{{ entry.notes }}</p>
                                        <p v-if="entry.isReversed && entry.reversalReason" class="mt-1 text-xs text-rose-700 font-semibold">
                                            سبب الإلغاء: {{ entry.reversalReason }}
                                        </p>
                                    </div>

                                    <div
                                        v-if="entry.attachmentUrl || (securityDeposit.status !== 'settled' && !entry.isReversed && (entry.payment || entry.deduction))"
                                        class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs font-semibold"
                                    >
                                        <a
                                            v-if="entry.attachmentUrl"
                                            :href="entry.attachmentUrl"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center gap-1 text-[#163E3E] hover:underline"
                                        >
                                            <Paperclip class="h-3.5 w-3.5" />
                                            <span>عرض المرفق</span>
                                        </a>

                                        <template v-if="securityDeposit.status !== 'settled' && !entry.isReversed">
                                            <button
                                                v-if="entry.payment"
                                                type="button"
                                                class="cursor-pointer text-rose-700 hover:underline"
                                                @click="emit('reversePayment', entry.payment)"
                                            >
                                                عكس وإلغاء الدفعة
                                            </button>

                                            <template v-if="entry.deduction">
                                                <button
                                                    type="button"
                                                    class="cursor-pointer text-[#163E3E] hover:underline"
                                                    @click="emit('editDeduction', entry.deduction)"
                                                >
                                                    تعديل
                                                </button>
                                                <button
                                                    type="button"
                                                    class="cursor-pointer text-rose-700 hover:underline"
                                                    @click="emit('deleteDeduction', entry.deduction)"
                                                >
                                                    حذف
                                                </button>
                                            </template>
                                        </template>
                                    </div>
                                </div>
                            </li>
                        </ol>
                    </div>
                </div>
            </div>
        </template>
    </section>
</template>

