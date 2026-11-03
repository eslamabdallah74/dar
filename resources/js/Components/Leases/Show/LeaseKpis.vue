<script setup lang="ts">
import { computed } from 'vue';
import type { LeaseData, RentChargeItem, RentTotals, SecurityDepositData } from './types';
import { formatMoney, formatRelativeDays } from './useLeaseUtils';
import { CheckCircle2, Clock, Coins, Shield } from 'lucide-vue-next';

const props = defineProps<{
    lease: LeaseData;
    rentTotals: RentTotals;
    rentCharges: RentChargeItem[];
    securityDeposit: SecurityDepositData;
    currencySymbol: string;
}>();

const hasDeposit = computed(() => {
    const deposit = props.securityDeposit;
    return deposit.agreed_amount > 0 || deposit.total_received > 0 || deposit.payments.length > 0 || deposit.settlement !== null;
});

const paidPercentage = computed(() => {
    if (props.rentTotals.billed <= 0) return 0;
    return Math.min(100, Math.round((props.rentTotals.paid / props.rentTotals.billed) * 100));
});

const paidCount = computed(() => {
    return props.rentCharges.filter((c) => c.status === 'paid' || c.outstanding_balance === 0).length;
});

const upcomingCharge = computed(() => {
    return props.rentCharges.find((charge) => {
        if (charge.outstanding_balance <= 0) return false;
        const rel = formatRelativeDays(charge.due_date);
        return !rel.isPast;
    });
});

const nextDueText = computed(() => {
    if (!upcomingCharge.value) return 'لا توجد فواتير قادمة';
    const rel = formatRelativeDays(upcomingCharge.value.due_date);
    return `${rel.text} (${upcomingCharge.value.due_date})`;
});

const depositRefundCaption = computed(() => {
    const dep = props.securityDeposit;
    if (dep.total_deductions > 0) {
        return `(المستلم ${formatMoney(dep.total_received)} − خصم ${formatMoney(dep.total_deductions)})`;
    }
    return `(من أصل ${formatMoney(dep.agreed_amount)} ${props.currencySymbol} متفق عليه)`;
});
</script>

<template>
    <section aria-label="مؤشرات العقد المالية" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- KPI 1: المحصّل من الإيجار -->
        <article class="rounded-3xl border border-[#D6E0E7] bg-white p-5 sm:p-6 shadow-xs flex flex-col justify-between space-y-4">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-[#5B6770] flex items-center gap-1.5">
                    <Coins class="h-3.5 w-3.5 text-[#163E3E]" />
                    <span>المحصّل من الإيجار</span>
                </span>
                <span class="rounded-full bg-[#E8F0F0] px-2.5 py-0.5 font-mono text-xs font-bold text-[#163E3E]">
                    {{ paidPercentage }}%
                </span>
            </div>

            <div>
                <p class="font-mono text-2xl sm:text-3xl font-extrabold tabular-nums text-[#163E3E]">
                    {{ formatMoney(rentTotals.paid) }}
                    <span class="text-sm font-sans font-medium text-[#5B6770]">من {{ formatMoney(rentTotals.billed) }} {{ currencySymbol }}</span>
                </p>
                <div class="mt-3 relative h-2 w-full overflow-hidden rounded-full bg-[#E6ECF0]">
                    <div
                        class="h-full rounded-full bg-[#163E3E] transition-all duration-500 ease-out"
                        :style="{ width: `${paidPercentage}%` }"
                    ></div>
                </div>
            </div>

            <p class="text-xs text-[#5B6770] flex items-center justify-between border-t border-[#EEF2F4] pt-2.5">
                <span>الفترات المسددة:</span>
                <span class="font-mono font-semibold tabular-nums text-[#163E3E]">
                    {{ paidCount }} من {{ rentCharges.length }} فترة
                </span>
            </p>
        </article>

        <!-- KPI 2: المستحق الآن والقادم -->
        <article class="rounded-3xl border border-[#D6E0E7] bg-white p-5 sm:p-6 shadow-xs flex flex-col justify-between space-y-4">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-[#5B6770] flex items-center gap-1.5">
                    <Clock class="h-3.5 w-3.5 text-amber-700" />
                    <span>المستحق الآن والقادم</span>
                </span>
                <span
                    v-if="rentTotals.overdue > 0"
                    class="rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-bold text-rose-800 border border-rose-300"
                >
                    متأخر
                </span>
                <span
                    v-else-if="rentTotals.outstanding > 0"
                    class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800 border border-amber-300"
                >
                    قيد الاستحقاق
                </span>
                <span
                    v-else
                    class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800 border border-emerald-300"
                >
                    منتظم
                </span>
            </div>

            <div>
                <div v-if="rentTotals.overdue > 0" class="space-y-1">
                    <p class="font-mono text-2xl sm:text-3xl font-extrabold tabular-nums text-rose-700">
                        {{ formatMoney(rentTotals.overdue) }}
                        <span class="text-sm font-sans font-medium text-rose-800">{{ currencySymbol }} متأخر</span>
                    </p>
                    <p class="text-xs font-semibold text-[#5B6770]">
                        القادمة: {{ formatMoney(Math.max(0, rentTotals.outstanding - rentTotals.overdue)) }} {{ currencySymbol }}
                    </p>
                </div>

                <div v-else-if="rentTotals.outstanding > 0" class="space-y-1">
                    <p class="font-mono text-2xl sm:text-3xl font-extrabold tabular-nums text-[#163E3E]">
                        {{ formatMoney(rentTotals.outstanding) }}
                        <span class="text-sm font-sans font-medium text-[#5B6770]">{{ currencySymbol }}</span>
                    </p>
                    <p class="text-xs font-semibold text-emerald-700">
                        0 متأخر · الاستحقاق القادم: {{ nextDueText }}
                    </p>
                </div>

                <div v-else class="space-y-1">
                    <p class="font-mono text-2xl sm:text-3xl font-extrabold tabular-nums text-emerald-700">
                        0
                        <span class="text-sm font-sans font-medium text-emerald-800">{{ currencySymbol }}</span>
                    </p>
                    <p class="text-xs font-semibold text-emerald-700 flex items-center gap-1">
                        <CheckCircle2 class="h-3.5 w-3.5 text-emerald-600" />
                        <span>جميع الفواتير الصادرة مسددة بالكامل</span>
                    </p>
                </div>
            </div>

            <p class="text-xs text-[#5B6770] flex items-center justify-between border-t border-[#EEF2F4] pt-2.5">
                <span>دورية سداد الإيجار:</span>
                <span class="font-semibold text-[#163E3E]">{{ lease.billing_interval }}</span>
            </p>
        </article>

        <!-- KPI 3: التأمين -->
        <article class="rounded-3xl border border-[#D6E0E7] bg-white p-5 sm:p-6 shadow-xs flex flex-col justify-between space-y-4">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-[#5B6770] flex items-center gap-1.5">
                    <Shield class="h-3.5 w-3.5 text-[#163E3E]" />
                    <span>التأمين</span>
                </span>
                <span
                    class="rounded-full px-2.5 py-0.5 text-xs font-bold"
                    :class="hasDeposit ? 'bg-[#E8F0F0] text-[#163E3E]' : 'bg-slate-100 text-slate-500'"
                >
                    {{ securityDeposit.status_label }}
                </span>
            </div>

            <template v-if="hasDeposit">
                <div>
                    <p class="font-mono text-2xl sm:text-3xl font-extrabold tabular-nums text-[#163E3E]">
                        {{ formatMoney(securityDeposit.refundable_balance) }}
                        <span class="text-sm font-sans font-medium text-[#5B6770]">{{ currencySymbol }}</span>
                    </p>
                    <p class="text-xs font-semibold text-emerald-800 mt-1">
                        {{ securityDeposit.status === 'settled' ? 'تمت تسويته وإغلاقه' : 'المبلغ المتبقي القابل للرد للمستأجر' }}
                    </p>
                </div>

                <p class="text-xs text-[#5B6770] flex items-center justify-between border-t border-[#EEF2F4] pt-2.5 font-mono tabular-nums">
                    <span>حساب الرصيد:</span>
                    <span>{{ depositRefundCaption }}</span>
                </p>
            </template>

            <template v-else>
                <div>
                    <p class="text-xl font-bold text-[#8A9AA7]">بدون تأمين</p>
                    <p class="text-xs text-[#5B6770] mt-1">لم يُشترط تأمين مالي في هذا العقد</p>
                </div>

                <p class="text-xs text-[#8A9AA7] border-t border-[#EEF2F4] pt-2.5">
                    لا توجد تسوية تأمين مطلوبة عند نهاية العقد
                </p>
            </template>
        </article>
    </section>
</template>

