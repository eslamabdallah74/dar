<script setup lang="ts">
import { computed, ref } from 'vue';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import type { RentChargeItem } from './types';
import { formatMoney, formatRelativeDays } from './useLeaseUtils';
import {
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    Clock,
    Coins,
    FileText,
} from 'lucide-vue-next';

const props = defineProps<{
    charges: RentChargeItem[];
    currencySymbol: string;
    isLeaseActive: boolean;
}>();

const emit = defineEmits<{
    (e: 'recordPayment', charge?: RentChargeItem): void;
}>();

const showPaidInvoices = ref(false);

const dueAndUpcomingCharges = computed(() => {
    return props.charges.filter((c) => c.status !== 'paid' && c.outstanding_balance > 0);
});

const paidCharges = computed(() => {
    return props.charges.filter((c) => c.status === 'paid' || c.outstanding_balance === 0);
});

const hasPartialPayment = computed(() => {
    return props.charges.some((c) => c.paid_amount > 0 && c.outstanding_balance > 0);
});

const rentChargeBadge = (charge: RentChargeItem): { status: string; label: string } => {
    if (charge.status === 'paid' || charge.outstanding_balance === 0) {
        return { status: 'paid', label: 'مسدد' };
    }
    if (charge.status === 'overdue') {
        return { status: 'overdue', label: 'متأخر' };
    }
    return charge.paid_amount > 0
        ? { status: 'partial', label: 'مسدد جزئياً' }
        : { status: 'pending', label: 'مستحق' };
};
</script>

<template>
    <section aria-labelledby="invoices-section-heading" class="rounded-3xl border border-[#D6E0E7] bg-white shadow-xs overflow-hidden">
        <!-- Header -->
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-[#EEF2F4] p-5 sm:p-6">
            <div class="space-y-0.5">
                <div class="flex items-center gap-2">
                    <div class="flex h-7 w-7 items-center justify-center rounded-xl bg-[#E8F0F0] text-[#163E3E]">
                        <Coins class="h-3.5 w-3.5" />
                    </div>
                    <h2 id="invoices-section-heading" class="text-base sm:text-lg font-bold text-[#163E3E]">
                        الفواتير والدفعات
                    </h2>
                </div>
                <p class="text-xs text-[#5B6770]">
                    سجل الفترات الإيجارية، مواعيد الاستحقاق، وحالة السداد
                </p>
            </div>

            <button
                v-if="isLeaseActive && dueAndUpcomingCharges.length > 0"
                type="button"
                class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-xl bg-[#163E3E] px-3.5 text-xs font-bold text-white transition hover:bg-[#102F2F] shadow-xs active:translate-y-px"
                @click="emit('recordPayment')"
            >
                <Coins class="h-3.5 w-3.5" />
                <span>تسجيل دفعة إيجار</span>
            </button>
        </header>

        <div class="p-5 sm:p-6 space-y-6">
            <!-- 1. الفواتير المستحقة والقادمة (مفتوحة دائماً) -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs sm:text-sm font-bold text-[#163E3E] flex items-center gap-2">
                        <Clock class="h-4 w-4 text-amber-600" />
                        <span>فواتير مستحقة وقادمة</span>
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 font-mono text-[11px] font-bold text-amber-800">
                            {{ dueAndUpcomingCharges.length }}
                        </span>
                    </h3>
                </div>

                <!-- Empty state for due charges -->
                <div
                    v-if="dueAndUpcomingCharges.length === 0"
                    class="rounded-2xl border border-dashed border-emerald-200 bg-emerald-50/40 p-6 text-center text-xs text-emerald-900"
                >
                    <CheckCircle2 class="mx-auto h-7 w-7 text-emerald-600 mb-1.5" />
                    <p class="font-bold">رائع! لا توجد فواتير مستحقة أو متأخرة حالياً.</p>
                    <p class="text-emerald-800/80 mt-0.5">جميع الفترات الإيجارية الصادرة مسددة بالكامل.</p>
                </div>

                <!-- Table for due charges -->
                <div v-else class="overflow-x-auto rounded-2xl border border-[#EEF2F4]">
                    <table class="w-full text-right text-xs sm:text-sm">
                        <thead class="bg-[#FBFAF6] text-xs font-semibold text-[#5B6770] border-b border-[#EEF2F4]">
                            <tr>
                                <th scope="col" class="px-4 py-3">الفترة</th>
                                <th scope="col" class="px-3 py-3">الاستحقاق</th>
                                <th scope="col" class="px-3 py-3">القيمة</th>
                                <th v-if="hasPartialPayment" scope="col" class="px-3 py-3">المسدد</th>
                                <th v-if="hasPartialPayment" scope="col" class="px-3 py-3">المتبقي</th>
                                <th scope="col" class="px-3 py-3">الحالة</th>
                                <th scope="col" class="px-4 py-3 text-start"><span class="sr-only">إجراء السداد</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#EEF2F4]">
                            <tr
                                v-for="charge in dueAndUpcomingCharges"
                                :key="charge.id"
                                class="transition-colors hover:bg-[#FBFAF6]"
                            >
                                <td class="px-4 py-3.5 font-mono font-bold tabular-nums text-[#163E3E]">
                                    {{ charge.period }}
                                </td>
                                <td class="px-3 py-3.5">
                                    <div class="space-y-0.5">
                                        <span
                                            class="font-bold block"
                                            :class="charge.status === 'overdue' ? 'text-rose-700' : 'text-[#163E3E]'"
                                        >
                                            {{ formatRelativeDays(charge.due_date).text }}
                                        </span>
                                        <span class="font-mono text-[11px] text-[#5B6770] block">
                                            {{ charge.due_date }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5 font-mono font-bold tabular-nums text-[#163E3E]">
                                    {{ formatMoney(charge.amount) }} {{ currencySymbol }}
                                </td>
                                <td v-if="hasPartialPayment" class="px-3 py-3.5 font-mono tabular-nums text-emerald-700">
                                    {{ formatMoney(charge.paid_amount) }} {{ currencySymbol }}
                                </td>
                                <td v-if="hasPartialPayment" class="px-3 py-3.5 font-mono font-bold tabular-nums text-rose-700">
                                    {{ formatMoney(charge.outstanding_balance) }} {{ currencySymbol }}
                                </td>
                                <td class="px-3 py-3.5">
                                    <StatusBadge
                                        :status="rentChargeBadge(charge).status"
                                        :label="rentChargeBadge(charge).label"
                                        size="sm"
                                    />
                                </td>
                                <td class="px-4 py-3.5 text-start">
                                    <button
                                        v-if="isLeaseActive"
                                        type="button"
                                        class="inline-flex h-8 cursor-pointer items-center justify-center gap-1 rounded-xl bg-[#163E3E] px-3 text-xs font-bold text-white transition hover:bg-[#102F2F] shadow-xs active:translate-y-px"
                                        @click="emit('recordPayment', charge)"
                                    >
                                        <span>تسجيل دفعة</span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 2. الفواتير المسددة (مطوية Collapsible) -->
            <div class="border-t border-[#EEF2F4] pt-4">
                <button
                    type="button"
                    class="flex w-full cursor-pointer items-center justify-between rounded-2xl bg-[#FBFAF6] px-4 py-3 text-start text-xs sm:text-sm font-bold text-[#163E3E] transition hover:bg-[#F8F4EA]"
                    @click="showPaidInvoices = !showPaidInvoices"
                >
                    <div class="flex items-center gap-2">
                        <CheckCircle2 class="h-4 w-4 text-emerald-600" />
                        <span>الفواتير المسددة</span>
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 font-mono text-[11px] font-bold text-emerald-800">
                            {{ paidCharges.length }}
                        </span>
                    </div>

                    <div class="flex items-center gap-1 text-xs text-[#5B6770]">
                        <span>{{ showPaidInvoices ? 'إخفاء' : 'عرض السجل' }}</span>
                        <ChevronUp v-if="showPaidInvoices" class="h-4 w-4" />
                        <ChevronDown v-else class="h-4 w-4" />
                    </div>
                </button>

                <!-- Collapsible content -->
                <div v-if="showPaidInvoices" class="mt-3 overflow-x-auto rounded-2xl border border-[#EEF2F4]">
                    <div v-if="paidCharges.length === 0" class="p-6 text-center text-xs text-[#5B6770]">
                        لا توجد فواتير مسددة بعد في سجل هذا العقد.
                    </div>

                    <table v-else class="w-full text-right text-xs sm:text-sm">
                        <thead class="bg-[#FBFAF6] text-xs font-semibold text-[#5B6770] border-b border-[#EEF2F4]">
                            <tr>
                                <th scope="col" class="px-4 py-3">الفترة</th>
                                <th scope="col" class="px-3 py-3">تاريخ الاستحقاق</th>
                                <th scope="col" class="px-3 py-3">المبلغ المسدد</th>
                                <th scope="col" class="px-3 py-3">الحالة</th>
                                <th scope="col" class="px-4 py-3 text-start">الإيصال</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#EEF2F4]">
                            <tr
                                v-for="charge in paidCharges"
                                :key="charge.id"
                                class="transition-colors hover:bg-[#FBFAF6]"
                            >
                                <td class="px-4 py-3 font-mono font-bold tabular-nums text-[#163E3E]">
                                    {{ charge.period }}
                                </td>
                                <td class="px-3 py-3 font-mono text-xs text-[#5B6770]">
                                    {{ charge.due_date }}
                                </td>
                                <td class="px-3 py-3 font-mono font-bold tabular-nums text-emerald-700">
                                    {{ formatMoney(charge.paid_amount || charge.amount) }} {{ currencySymbol }}
                                </td>
                                <td class="px-3 py-3">
                                    <StatusBadge status="paid" label="مسدد بالكامل" size="sm" />
                                </td>
                                <td class="px-4 py-3 text-start">
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-800">
                                        <FileText class="h-3.5 w-3.5" />
                                        <span>إيصال معتمد</span>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</template>

