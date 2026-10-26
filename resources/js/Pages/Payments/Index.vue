<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import ModalRecordPayment from '@/Components/Common/ModalRecordPayment.vue';
import ModalCorrectPayment from '@/Components/Common/ModalCorrectPayment.vue';
import { Download, MessageCircle, Paperclip, Pencil, Plus, Printer, Search, X } from 'lucide-vue-next';

interface ChargeItem {
    id: number;
    lease_id: number;
    renter_id: number;
    renter_name: string;
    renter_phone: string;
    unit_name: string;
    property_name: string;
    period: string;
    period_name?: string;
    period_start?: string;
    period_end?: string;
    due_date: string;
    raw_due_date: string;
    paid_at?: string | null;
    raw_paid_at?: string | null;
    days_overdue?: number | null;
    amount: number;
    paid_amount: number;
    outstanding_balance: number;
    status: string;
    status_label: string;
}

interface PaymentReceiptItem {
    id: number;
    reference: string;
    renter_id: number;
    renter_name: string;
    renter_phone?: string;
    unit_name: string;
    property_name?: string;
    amount: number;
    paid_at: string;
    paid_date?: string;
    paid_time?: string;
    raw_paid_at?: string;
    created_at?: string;
    created_date?: string;
    created_time?: string;
    raw_created_at?: string;
    payment_method: string;
    method: string;
    notes: string | null;
    has_receipt?: boolean;
    receipt_url?: string | null;
    is_corrected?: boolean;
    corrections_count?: number;
    allocations?: Array<{
        charge_id: number;
        period: string;
        period_name?: string;
        due_date: string;
        amount: number;
    }>;
}

const props = defineProps<{
    charges: ChargeItem[];
    payments: PaymentReceiptItem[];
    renters: Array<{
        id: number;
        name: string;
        phone: string;
        active_lease?: {
            id: number;
            unit_name?: string;
            rent_amount: number;
        } | null;
    }>;
    stats: {
        target: number;
        collected: number;
        overdue: number;
        rate: number;
    };
    active_tab?: 'dues' | 'receipts';
    currency_symbol: string;
}>();

type ChargeStatusFilter = 'all' | 'overdue' | 'unpaid' | 'paid';

const currentTab = ref<'dues' | 'receipts'>(props.active_tab === 'receipts' ? 'receipts' : 'dues');
const searchQuery = ref('');
const statusFilter = ref<ChargeStatusFilter>('all');
const receiptSearchQuery = ref('');
const receiptChargeFilter = ref<ChargeItem | null>(null);
const recordPaymentOpen = ref(false);
const correctPaymentOpen = ref(false);
const activeChargeForPayment = ref<ChargeItem | null>(null);
const activePaymentForCorrection = ref<PaymentReceiptItem | null>(null);

const formatMoney = (amount: number): string => Number(amount).toLocaleString();

const chargeMatchesStatus = (charge: ChargeItem, filter: ChargeStatusFilter): boolean => {
    if (filter === 'all') {
        return true;
    }
    if (filter === 'unpaid') {
        return charge.status === 'unpaid' || charge.status === 'partially_paid';
    }

    return charge.status === filter;
};

const chargeStatusFilters = computed(() =>
    (
        [
            { id: 'all', label: 'الكل' },
            { id: 'overdue', label: 'متأخرة' },
            { id: 'unpaid', label: 'لم يحن موعدها' },
            { id: 'paid', label: 'مسددة' },
        ] as const
    ).map((filter) => ({
        ...filter,
        count: props.charges.filter((charge) => chargeMatchesStatus(charge, filter.id)).length,
    }))
);

const filteredCharges = computed(() => {
    const query = searchQuery.value.trim();

    return props.charges.filter(
        (charge) =>
            chargeMatchesStatus(charge, statusFilter.value) &&
            (!query ||
                [charge.renter_name, charge.unit_name, charge.property_name, charge.period, charge.period_name ?? ''].some((value) =>
                    value.includes(query)
                ))
    );
});

const overdueChargesCount = computed(() => props.charges.filter((charge) => charge.status === 'overdue').length);

const chargeBadgeStatuses: Record<string, string> = {
    paid: 'paid',
    overdue: 'overdue',
    partially_paid: 'partial',
    unpaid: 'pending',
};

/**
 * How many receipts paid each charge, including advance payments on charges whose due date has not come yet.
 */
const receiptCountByCharge = computed(() => {
    const counts = new Map<number, number>();
    for (const payment of props.payments) {
        for (const allocation of payment.allocations ?? []) {
            counts.set(allocation.charge_id, (counts.get(allocation.charge_id) ?? 0) + 1);
        }
    }

    return counts;
});

const receiptCountLabel = (count: number): string => {
    if (count === 1) {
        return 'سند واحد';
    }
    if (count === 2) {
        return 'سندان';
    }

    return count <= 10 ? `${count} سندات` : `${count} سنداً`;
};

const overdueDaysLabel = (days: number): string => {
    if (days === 1) {
        return 'يوم واحد';
    }
    if (days === 2) {
        return 'يومين';
    }

    return days <= 10 ? `${days} أيام` : `${days} يوماً`;
};

const filteredPayments = computed(() => {
    const query = receiptSearchQuery.value.trim().toLowerCase();
    const chargeId = receiptChargeFilter.value?.id;

    return props.payments.filter(
        (payment) =>
            (chargeId === undefined || (payment.allocations ?? []).some((allocation) => allocation.charge_id === chargeId)) &&
            (!query ||
                [payment.reference, payment.renter_name, payment.unit_name, payment.method].some((value) =>
                    value?.toLowerCase().includes(query)
                ))
    );
});

const collectionPercent = computed(() => Math.min(100, Math.max(0, props.stats.rate)));

const showReceiptsForCharge = (charge: ChargeItem) => {
    receiptChargeFilter.value = charge;
    receiptSearchQuery.value = '';
    currentTab.value = 'receipts';
};

const showAllReceipts = () => {
    receiptChargeFilter.value = null;
    receiptSearchQuery.value = '';
    currentTab.value = 'receipts';
};

const openPaymentForCharge = (charge: ChargeItem) => {
    activeChargeForPayment.value = charge;
    recordPaymentOpen.value = true;
};

const openGeneralPayment = () => {
    activeChargeForPayment.value = null;
    recordPaymentOpen.value = true;
};

const closeRecordPayment = () => {
    recordPaymentOpen.value = false;
    activeChargeForPayment.value = null;
};

const openCorrectionForPayment = (payment: PaymentReceiptItem) => {
    activePaymentForCorrection.value = payment;
    correctPaymentOpen.value = true;
};

const whatsappReminderUrl = (charge: ChargeItem): string => {
    const message = `السلام عليكم ${charge.renter_name}، نود تذكيركم بسداد قسط إيجار ${charge.unit_name} المستحق بقيمة ${charge.outstanding_balance} ${props.currency_symbol}. شكراً لتعاونكم.`;

    return `https://wa.me/20${charge.renter_phone.replace(/^0+/, '')}?text=${encodeURIComponent(message)}`;
};

const tabClass = (tab: 'dues' | 'receipts'): string =>
    currentTab.value === tab ? 'bg-white text-[#163E3E] shadow-sm' : 'text-[#5B6770] hover:text-[#163E3E]';

const linkButton = 'cursor-pointer text-xs font-semibold text-[#163E3E] underline-offset-4 hover:underline focus-visible:underline focus-visible:outline-none';
const rowAction =
    'inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-lg border border-[#D6E0E7] bg-white px-2.5 text-xs font-semibold text-[#163E3E] transition hover:bg-[#F8F4EA] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#163E3E]/30';
</script>

<template>
    <AppLayout
        title="المدفوعات — Nook Mate"
        active-tab="payments"
        header-title="المدفوعات"
        header-subtitle="فواتير الإيجار الشهرية وسندات القبض المسجلة"
        breadcrumb="الدفعات"
    >
        <template #actions>
            <a
                :href="currentTab === 'dues' ? route('reports.collections.export') : route('reports.payments.export')"
                class="inline-flex h-10 items-center gap-2 rounded-xl border border-[#D6E0E7] bg-white px-4 text-sm font-semibold text-[#163E3E] transition hover:bg-[#F8F4EA]"
                title="تصدير القائمة الحالية إلى Excel"
            >
                <Download class="h-4 w-4" />
                <span>تصدير Excel</span>
            </a>
            <button
                type="button"
                class="inline-flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-[#163E3E] px-4 text-sm font-semibold text-white transition hover:bg-[#102F2F] active:translate-y-px"
                @click="openGeneralPayment"
            >
                <Plus class="h-4 w-4" />
                <span>تسجيل دفعة</span>
            </button>
        </template>

        <section aria-label="ملخص التحصيل" class="grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-[#D6E0E7] bg-[#E6ECF0] lg:grid-cols-4">
            <div class="bg-white p-5">
                <span class="text-xs font-medium text-[#5B6770]">مستحق هذا الشهر</span>
                <p class="mt-2 font-mono text-2xl font-semibold tabular-nums text-[#163E3E]">
                    {{ formatMoney(stats.target) }}
                    <span class="font-sans text-xs font-medium text-[#5B6770]">{{ currency_symbol }}</span>
                </p>
                <p class="mt-1 text-xs text-[#5B6770]">فواتير يحل موعدها هذا الشهر</p>
            </div>

            <div class="bg-white p-5">
                <span class="text-xs font-medium text-[#5B6770]">المحصّل هذا الشهر</span>
                <p class="mt-2 font-mono text-2xl font-semibold tabular-nums text-emerald-700">
                    {{ formatMoney(stats.collected) }}
                    <span class="font-sans text-xs font-medium text-[#5B6770]">{{ currency_symbol }}</span>
                </p>
                <div
                    class="mt-2 h-1.5 overflow-hidden rounded-full bg-[#EEF2F4]"
                    role="progressbar"
                    aria-label="نسبة التحصيل"
                    :aria-valuenow="collectionPercent"
                    aria-valuemin="0"
                    aria-valuemax="100"
                >
                    <div class="h-full rounded-full bg-emerald-600" :style="{ width: `${collectionPercent}%` }"></div>
                </div>
                <p class="mt-1.5 text-xs text-[#5B6770]">{{ stats.rate }}% من المستحق</p>
            </div>

            <div class="bg-white p-5">
                <span class="text-xs font-medium text-[#5B6770]">المتأخرات</span>
                <p class="mt-2 font-mono text-2xl font-semibold tabular-nums" :class="stats.overdue > 0 ? 'text-rose-700' : 'text-[#163E3E]'">
                    {{ formatMoney(stats.overdue) }}
                    <span class="font-sans text-xs font-medium text-[#5B6770]">{{ currency_symbol }}</span>
                </p>
                <button
                    v-if="overdueChargesCount > 0"
                    type="button"
                    class="mt-1 cursor-pointer text-xs font-semibold text-rose-700 underline-offset-4 hover:underline"
                    @click="
                        currentTab = 'dues';
                        statusFilter = 'overdue';
                    "
                >
                    عرض {{ overdueChargesCount }} {{ overdueChargesCount === 1 ? 'فاتورة متأخرة' : 'فواتير متأخرة' }}
                </button>
                <p v-else class="mt-1 text-xs text-[#5B6770]">لا توجد فواتير متأخرة</p>
            </div>

            <button
                type="button"
                class="flex cursor-pointer flex-col items-start bg-white p-5 text-right transition hover:bg-[#FBFAF6] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#163E3E]/30"
                @click="showAllReceipts"
            >
                <span class="block text-xs font-medium text-[#5B6770]">سندات القبض</span>
                <span class="mt-2 block font-mono text-2xl font-semibold tabular-nums text-[#163E3E]">{{ payments.length }}</span>
                <span class="mt-1 block text-xs font-semibold text-[#163E3E]">عرض كل السندات ←</span>
            </button>
        </section>

        <div class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div role="tablist" aria-label="عرض المدفوعات" class="inline-flex rounded-xl bg-[#EFECE2] p-1">
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="currentTab === 'dues'"
                        class="cursor-pointer rounded-lg px-4 py-2 text-sm font-semibold transition"
                        :class="tabClass('dues')"
                        @click="currentTab = 'dues'"
                    >
                        فواتير الإيجار
                        <span class="mr-1 font-mono text-xs tabular-nums opacity-70">{{ charges.length }}</span>
                    </button>
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="currentTab === 'receipts'"
                        class="cursor-pointer rounded-lg px-4 py-2 text-sm font-semibold transition"
                        :class="tabClass('receipts')"
                        @click="showAllReceipts"
                    >
                        سندات القبض
                        <span class="mr-1 font-mono text-xs tabular-nums opacity-70">{{ payments.length }}</span>
                    </button>
                </div>

                <div class="relative w-full sm:max-w-xs">
                    <input
                        v-if="currentTab === 'dues'"
                        v-model="searchQuery"
                        type="search"
                        aria-label="بحث في فواتير الإيجار"
                        placeholder="ابحث بالمستأجر أو الوحدة أو الشهر"
                        class="h-10 w-full rounded-xl border border-[#D6E0E7] bg-white pl-3 pr-10 text-sm text-[#163E3E] placeholder:text-[#8A9AA7] focus:border-[#163E3E] focus:ring-1 focus:ring-[#163E3E]"
                    />
                    <input
                        v-else
                        v-model="receiptSearchQuery"
                        type="search"
                        aria-label="بحث في سندات القبض"
                        placeholder="ابحث برقم السند أو المستأجر"
                        class="h-10 w-full rounded-xl border border-[#D6E0E7] bg-white pl-3 pr-10 text-sm text-[#163E3E] placeholder:text-[#8A9AA7] focus:border-[#163E3E] focus:ring-1 focus:ring-[#163E3E]"
                    />
                    <Search class="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8A9AA7]" />
                </div>
            </div>

            <template v-if="currentTab === 'dues'">
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="filter in chargeStatusFilters"
                        :key="filter.id"
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                        :class="
                            statusFilter === filter.id
                                ? 'border-[#163E3E] bg-[#163E3E] text-white'
                                : 'border-[#D6E0E7] bg-white text-[#5B6770] hover:text-[#163E3E]'
                        "
                        @click="statusFilter = filter.id"
                    >
                        {{ filter.label }}
                        <span class="font-mono tabular-nums opacity-70">{{ filter.count }}</span>
                    </button>
                </div>

                <div v-if="filteredCharges.length > 0" class="overflow-hidden rounded-2xl border border-[#D6E0E7] bg-white">
                    <ul class="divide-y divide-[#E6ECF0] md:hidden">
                        <li v-for="charge in filteredCharges" :key="charge.id" class="space-y-3 px-4 py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <Link :href="route('renters.show', charge.renter_id)" class="block truncate font-semibold text-[#163E3E]">
                                        {{ charge.renter_name }}
                                    </Link>
                                    <p class="truncate text-xs text-[#5B6770]">{{ charge.unit_name }} · {{ charge.property_name }}</p>
                                </div>
                                <StatusBadge :status="chargeBadgeStatuses[charge.status] ?? 'pending'" :label="charge.status_label" size="sm" />
                            </div>
                            <div class="flex items-end justify-between gap-3">
                                <div class="text-xs text-[#5B6770]">
                                    <p class="font-semibold text-[#163E3E]">{{ charge.period_name || charge.period }}</p>
                                    <p>
                                        يستحق <span class="font-mono tabular-nums">{{ charge.due_date }}</span>
                                        <span v-if="charge.status === 'overdue' && charge.days_overdue" class="font-semibold text-rose-700">
                                            · متأخر {{ overdueDaysLabel(charge.days_overdue) }}
                                        </span>
                                    </p>
                                </div>
                                <div class="text-left">
                                    <p class="font-mono text-base font-semibold tabular-nums text-[#163E3E]">
                                        {{ formatMoney(charge.outstanding_balance > 0 ? charge.outstanding_balance : charge.amount) }}
                                        <span class="font-sans text-xs font-medium text-[#5B6770]">{{ currency_symbol }}</span>
                                    </p>
                                    <p class="text-xs text-[#5B6770]">{{ charge.outstanding_balance > 0 ? 'متبقٍ' : 'مسدد' }}</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <button
                                    v-if="receiptCountByCharge.get(charge.id)"
                                    type="button"
                                    :class="linkButton"
                                    @click="showReceiptsForCharge(charge)"
                                >
                                    سُدد {{ formatMoney(charge.paid_amount) }} عبر {{ receiptCountLabel(receiptCountByCharge.get(charge.id) ?? 0) }}
                                </button>
                                <span v-else class="text-xs text-[#8A9AA7]">لم يُسدد شيء بعد</span>
                                <div class="flex items-center gap-2">
                                    <a
                                        v-if="charge.status === 'overdue' && charge.renter_phone"
                                        :href="whatsappReminderUrl(charge)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        :class="rowAction"
                                        aria-label="تذكير عبر واتساب"
                                    >
                                        <MessageCircle class="h-3.5 w-3.5 text-emerald-600" />
                                        <span>تذكير</span>
                                    </a>
                                    <button
                                        v-if="charge.outstanding_balance > 0"
                                        type="button"
                                        class="inline-flex h-8 cursor-pointer items-center rounded-lg bg-[#163E3E] px-3 text-xs font-semibold text-white transition hover:bg-[#102F2F]"
                                        @click="openPaymentForCharge(charge)"
                                    >
                                        تسجيل سداد
                                    </button>
                                </div>
                            </div>
                        </li>
                    </ul>

                    <table class="hidden w-full text-right text-sm md:table">
                        <thead class="bg-[#FBFAF6] text-xs text-[#5B6770]">
                            <tr>
                                <th scope="col" class="px-5 py-3 font-medium">المستأجر</th>
                                <th scope="col" class="px-3 py-3 font-medium">الفترة</th>
                                <th scope="col" class="px-3 py-3 font-medium">القيمة</th>
                                <th scope="col" class="px-3 py-3 font-medium">المتبقي</th>
                                <th scope="col" class="px-3 py-3 font-medium">الحالة</th>
                                <th scope="col" class="px-5 py-3"><span class="sr-only">إجراءات</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E6ECF0]">
                            <tr v-for="charge in filteredCharges" :key="charge.id" class="align-top transition-colors hover:bg-[#FBFAF6]">
                                <td class="px-5 py-4">
                                    <Link
                                        :href="route('renters.show', charge.renter_id)"
                                        class="font-semibold text-[#163E3E] underline-offset-4 hover:underline"
                                    >
                                        {{ charge.renter_name }}
                                    </Link>
                                    <p class="mt-0.5 text-xs text-[#5B6770]">{{ charge.unit_name }} · {{ charge.property_name }}</p>
                                </td>
                                <td class="px-3 py-4">
                                    <p class="font-semibold text-[#163E3E]">{{ charge.period_name || charge.period }}</p>
                                    <p class="mt-0.5 text-xs text-[#5B6770]">
                                        يستحق <span class="font-mono tabular-nums">{{ charge.due_date }}</span>
                                    </p>
                                    <p v-if="charge.status === 'overdue' && charge.days_overdue" class="mt-0.5 text-xs font-semibold text-rose-700">
                                        متأخر {{ overdueDaysLabel(charge.days_overdue) }}
                                    </p>
                                </td>
                                <td class="px-3 py-4">
                                    <p class="font-mono tabular-nums text-[#163E3E]">{{ formatMoney(charge.amount) }}</p>
                                    <button
                                        v-if="receiptCountByCharge.get(charge.id)"
                                        type="button"
                                        class="mt-0.5 whitespace-nowrap"
                                        :class="linkButton"
                                        @click="showReceiptsForCharge(charge)"
                                    >
                                        سُدد {{ formatMoney(charge.paid_amount) }} · {{ receiptCountLabel(receiptCountByCharge.get(charge.id) ?? 0) }}
                                    </button>
                                </td>
                                <td
                                    class="px-3 py-4 font-mono font-semibold tabular-nums"
                                    :class="charge.outstanding_balance <= 0 ? 'text-[#8A9AA7]' : charge.status === 'overdue' ? 'text-rose-700' : 'text-[#163E3E]'"
                                >
                                    {{ formatMoney(charge.outstanding_balance) }}
                                </td>
                                <td class="px-3 py-4">
                                    <StatusBadge :status="chargeBadgeStatuses[charge.status] ?? 'pending'" :label="charge.status_label" size="sm" />
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <a
                                            v-if="charge.status === 'overdue' && charge.renter_phone"
                                            :href="whatsappReminderUrl(charge)"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            :class="rowAction"
                                            title="إرسال تذكير عبر واتساب"
                                        >
                                            <MessageCircle class="h-3.5 w-3.5 text-emerald-600" />
                                            <span>تذكير</span>
                                        </a>
                                        <button
                                            v-if="charge.outstanding_balance > 0"
                                            type="button"
                                            class="inline-flex h-8 cursor-pointer items-center whitespace-nowrap rounded-lg bg-[#163E3E] px-3 text-xs font-semibold text-white transition hover:bg-[#102F2F] active:translate-y-px"
                                            @click="openPaymentForCharge(charge)"
                                        >
                                            تسجيل سداد
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <EmptyState
                    v-else
                    :type="charges.length ? 'search' : 'document'"
                    :title="charges.length ? 'لا توجد فواتير تطابق البحث' : 'لا توجد فواتير إيجار بعد'"
                    :description="charges.length ? 'جرّب كلمة بحث أخرى أو اختر «الكل».' : 'تُنشأ فاتورة لكل شهر تلقائياً بعد إضافة عقد إيجار.'"
                />
            </template>

            <template v-else>
                <div v-if="receiptChargeFilter" class="flex flex-wrap items-center gap-2 text-xs text-[#5B6770]">
                    <span>سندات فاتورة</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-[#E8F0F0] py-1 pl-1.5 pr-3 font-semibold text-[#163E3E]">
                        {{ receiptChargeFilter.renter_name }} · {{ receiptChargeFilter.period_name || receiptChargeFilter.period }}
                        <button
                            type="button"
                            class="inline-flex h-5 w-5 cursor-pointer items-center justify-center rounded-full hover:bg-white"
                            aria-label="إظهار كل السندات"
                            @click="receiptChargeFilter = null"
                        >
                            <X class="h-3 w-3" />
                        </button>
                    </span>
                </div>

                <div v-if="filteredPayments.length > 0" class="overflow-hidden rounded-2xl border border-[#D6E0E7] bg-white">
                    <ul class="divide-y divide-[#E6ECF0] md:hidden">
                        <li v-for="payment in filteredPayments" :key="payment.id" class="space-y-3 px-4 py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-mono text-sm font-semibold text-[#163E3E]">{{ payment.reference }}</p>
                                    <p class="truncate text-xs text-[#5B6770]">{{ payment.renter_name }} · {{ payment.unit_name }}</p>
                                </div>
                                <p class="shrink-0 font-mono text-base font-semibold tabular-nums text-emerald-700">
                                    {{ formatMoney(payment.amount) }}
                                    <span class="font-sans text-xs font-medium text-[#5B6770]">{{ currency_symbol }}</span>
                                </p>
                            </div>
                            <p class="text-xs text-[#5B6770]">
                                <span class="font-mono tabular-nums">{{ payment.paid_date || payment.paid_at }}</span> · {{ payment.method }}
                                <span v-if="payment.allocations?.length">
                                    · {{ payment.allocations.map((allocation) => allocation.period_name || allocation.period).join('، ') }}
                                </span>
                                <span v-if="payment.is_corrected" class="font-semibold text-amber-700"> · مُعدّل</span>
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <a :href="route('payments.receipt', payment.id)" target="_blank" rel="noopener noreferrer" :class="rowAction">
                                    <Printer class="h-3.5 w-3.5" />
                                    <span>السند</span>
                                </a>
                                <a
                                    v-if="payment.has_receipt && payment.receipt_url"
                                    :href="payment.receipt_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    :class="rowAction"
                                >
                                    <Paperclip class="h-3.5 w-3.5" />
                                    <span>الإشعار</span>
                                </a>
                                <button type="button" :class="rowAction" @click="openCorrectionForPayment(payment)">
                                    <Pencil class="h-3.5 w-3.5" />
                                    <span>تصحيح</span>
                                </button>
                            </div>
                        </li>
                    </ul>

                    <table class="hidden w-full text-right text-sm md:table">
                        <thead class="bg-[#FBFAF6] text-xs text-[#5B6770]">
                            <tr>
                                <th scope="col" class="px-5 py-3 font-medium">السند</th>
                                <th scope="col" class="px-3 py-3 font-medium">المستأجر</th>
                                <th scope="col" class="px-3 py-3 font-medium">المبلغ</th>
                                <th scope="col" class="px-3 py-3 font-medium">يغطي</th>
                                <th scope="col" class="px-5 py-3"><span class="sr-only">إجراءات</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E6ECF0]">
                            <tr v-for="payment in filteredPayments" :key="payment.id" class="align-top transition-colors hover:bg-[#FBFAF6]">
                                <td class="px-5 py-4">
                                    <p class="font-mono font-semibold text-[#163E3E]">{{ payment.reference }}</p>
                                    <p class="mt-0.5 text-xs text-[#5B6770]">
                                        <span class="font-mono tabular-nums">{{ payment.paid_date || payment.paid_at }}</span>
                                        <span v-if="payment.is_corrected" class="font-semibold text-amber-700">
                                            · مُعدّل {{ payment.corrections_count ?? '' }}
                                        </span>
                                    </p>
                                    <p v-if="payment.notes" class="mt-0.5 max-w-[16rem] truncate text-xs text-[#8A9AA7]">{{ payment.notes }}</p>
                                </td>
                                <td class="px-3 py-4">
                                    <Link
                                        :href="route('renters.show', payment.renter_id)"
                                        class="font-semibold text-[#163E3E] underline-offset-4 hover:underline"
                                    >
                                        {{ payment.renter_name }}
                                    </Link>
                                    <p class="mt-0.5 text-xs text-[#5B6770]">
                                        {{ payment.unit_name }}<span v-if="payment.property_name"> · {{ payment.property_name }}</span>
                                    </p>
                                </td>
                                <td class="px-3 py-4">
                                    <p class="font-mono font-semibold tabular-nums text-emerald-700">
                                        {{ formatMoney(payment.amount) }}
                                        <span class="font-sans text-xs font-medium text-[#5B6770]">{{ currency_symbol }}</span>
                                    </p>
                                    <p class="mt-0.5 text-xs text-[#5B6770]">{{ payment.method }}</p>
                                </td>
                                <td class="px-3 py-4">
                                    <ul v-if="payment.allocations?.length" class="space-y-1">
                                        <li v-for="allocation in payment.allocations" :key="allocation.charge_id" class="whitespace-nowrap text-xs text-[#163E3E]">
                                            {{ allocation.period_name || allocation.period }}
                                            <span v-if="payment.allocations.length > 1" class="font-mono tabular-nums text-[#5B6770]">
                                                · {{ formatMoney(allocation.amount) }}
                                            </span>
                                        </li>
                                    </ul>
                                    <span v-else class="text-xs text-[#8A9AA7]">غير موزع على فواتير</span>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <a
                                            v-if="payment.has_receipt && payment.receipt_url"
                                            :href="payment.receipt_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            :class="rowAction"
                                            title="إشعار التحويل المرفق"
                                        >
                                            <Paperclip class="h-3.5 w-3.5" />
                                            <span>الإشعار</span>
                                        </a>
                                        <a
                                            :href="route('payments.receipt', payment.id)"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            :class="rowAction"
                                            title="عرض وطباعة سند القبض"
                                        >
                                            <Printer class="h-3.5 w-3.5" />
                                            <span>السند</span>
                                        </a>
                                        <button type="button" :class="rowAction" title="تصحيح السند" @click="openCorrectionForPayment(payment)">
                                            <Pencil class="h-3.5 w-3.5" />
                                            <span>تصحيح</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <EmptyState
                    v-else-if="payments.length"
                    type="search"
                    title="لا توجد سندات تطابق البحث"
                    description="جرّب رقم سند أو اسم مستأجر آخر."
                />
                <EmptyState
                    v-else
                    type="document"
                    title="لا توجد سندات قبض بعد"
                    description="سجّل أول دفعة لمستأجر ليصدر سند القبض تلقائياً."
                    action-label="تسجيل دفعة"
                    @action="openGeneralPayment"
                />
            </template>
        </div>

        <ModalRecordPayment
            v-if="recordPaymentOpen"
            :show="true"
            :renters="renters"
            :initial-renter-id="activeChargeForPayment?.renter_id"
            :initial-amount="activeChargeForPayment?.outstanding_balance"
            :initial-rent-charge-id="activeChargeForPayment?.id"
            @close="closeRecordPayment"
        />

        <ModalCorrectPayment :show="correctPaymentOpen" :payment="activePaymentForCorrection" @close="correctPaymentOpen = false" />
    </AppLayout>
</template>
