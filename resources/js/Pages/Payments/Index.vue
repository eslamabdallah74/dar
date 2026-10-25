<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import ModalRecordPayment from '@/Components/Common/ModalRecordPayment.vue';
import ModalCorrectPayment from '@/Components/Common/ModalCorrectPayment.vue';
import {
    ArrowUpRight,
    Calendar,
    CheckCircle2,
    Clock,
    Coins,
    CreditCard,
    DollarSign,
    Download,
    FileText,
    History,
    MessageCircle,
    Paperclip,
    Pencil,
    Plus,
    Printer,
    Receipt,
    Search,
    TrendingUp,
} from 'lucide-vue-next';

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
}>();

const currentTab = ref<'dues' | 'receipts'>((props.active_tab as 'dues' | 'receipts') || 'dues');
const searchQuery = ref('');
const receiptSearchQuery = ref('');
const statusFilter = ref('all');
const recordPaymentOpen = ref(false);
const correctPaymentOpen = ref(false);
const activeChargeForPayment = ref<ChargeItem | null>(null);
const activePaymentForCorrection = ref<PaymentReceiptItem | null>(null);

const filteredCharges = computed(() => {
    return props.charges.filter(c => {
        const matchesSearch = c.renter_name.includes(searchQuery.value)
            || c.unit_name.includes(searchQuery.value)
            || c.period.includes(searchQuery.value);

        const matchesStatus = statusFilter.value === 'all'
            || c.status === statusFilter.value;

        return matchesSearch && matchesStatus;
    });
});

const filteredPayments = computed(() => {
    if (!receiptSearchQuery.value) return props.payments;
    const q = receiptSearchQuery.value.toLowerCase();
    return props.payments.filter(p =>
        (p.reference && p.reference.toLowerCase().includes(q)) ||
        (p.renter_name && p.renter_name.toLowerCase().includes(q)) ||
        (p.unit_name && p.unit_name.toLowerCase().includes(q)) ||
        (p.method && p.method.toLowerCase().includes(q))
    );
});

const openPaymentForCharge = (charge: ChargeItem) => {
    activeChargeForPayment.value = charge;
    recordPaymentOpen.value = true;
};

const openCorrectionForPayment = (payment: PaymentReceiptItem) => {
    activePaymentForCorrection.value = payment;
    correctPaymentOpen.value = true;
};
</script>

<template>
    <AppLayout
        title="الاستحقاقات والدفعات — Nook Mate"
        active-tab="payments"
        header-title="الاستحقاقات والدفعات"
        header-subtitle="متابعة تحصيل الإيجارات، الفواتير المستحقة وإصدار سندات القبض الإلكترونية"
        breadcrumb="الدفعات"
    >
        <template #actions>
            <a
                :href="currentTab === 'dues' ? route('reports.collections.export') : route('reports.payments.export')"
                class="h-11 px-4 rounded-xl border border-[#D6E0E7] bg-white hover:bg-[#F8F4EA] text-sm font-semibold text-[#163E3E] transition flex items-center gap-2 shadow-2xs"
                title="تصدير السجلات إلى ملف Excel متوافق مع اللغة العربية"
            >
                <Download class="h-4 w-4 text-[#163E3E]" />
                <span>تصدير Excel</span>
            </a>

            <button
                @click="recordPaymentOpen = true"
                type="button"
                class="h-11 px-5 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-sm font-semibold transition shadow-xs flex items-center gap-2 cursor-pointer"
            >
                <Plus class="h-4 w-4 stroke-[2.5]" />
                <span>تسجيل دفعة يدوية</span>
            </button>
        </template>

        <!-- كروت مؤشرات التحصيل (4 KPIs) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">المستهدف الإيجاري للشهر</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#163E3E] font-mono">{{ stats.target.toLocaleString() }} <span class="text-xs font-sans font-normal text-[#5B6770]">ج.م</span></span>
                    <span class="text-xs text-[#5B6770]">إجمالي المطالبات</span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">المحصل الفعلي</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-emerald-700 font-mono">{{ stats.collected.toLocaleString() }} <span class="text-xs font-sans font-normal text-emerald-600">ج.م</span></span>
                    <span class="text-xs text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md font-semibold">
                        {{ stats.rate }}% نسبة التحصيل
                    </span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">المتأخرات المعلقة</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-rose-600 font-mono">{{ stats.overdue.toLocaleString() }} <span class="text-xs font-sans font-normal text-rose-500">ج.م</span></span>
                    <span class="text-xs text-rose-700 bg-rose-50 px-2 py-0.5 rounded-md font-semibold">
                        تتطلب متابعة
                    </span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">سندات القبض المسجلة</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#163E3E] font-mono">{{ payments.length }}</span>
                    <span class="text-xs text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md font-semibold">
                        مرحّلة مالياً
                    </span>
                </div>
            </div>
        </div>

        <!-- شريط التبويبات الرئيسي (الاستحقاقات vs سندات القبض) -->
        <div class="flex items-center justify-between gap-4 border-b border-[#D6E0E7] pb-3">
            <div class="flex items-center gap-2">
                <button
                    @click="currentTab = 'dues'"
                    type="button"
                    class="px-5 py-2.5 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-2"
                    :class="currentTab === 'dues'
                        ? 'bg-[#163E3E] text-white shadow-xs'
                        : 'bg-white text-[#5B6770] hover:text-[#163E3E] border border-[#D6E0E7]'"
                >
                    <Clock class="w-4 h-4" />
                    <span>الاستحقاقات والفواتير الشهرية ({{ charges.length }})</span>
                </button>

                <button
                    @click="currentTab = 'receipts'"
                    type="button"
                    class="px-5 py-2.5 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-2"
                    :class="currentTab === 'receipts'
                        ? 'bg-[#163E3E] text-white shadow-xs'
                        : 'bg-white text-[#5B6770] hover:text-[#163E3E] border border-[#D6E0E7]'"
                >
                    <Receipt class="w-4 h-4" />
                    <span>سندات القبض والدفعات المسددة ({{ payments.length }})</span>
                </button>
            </div>
        </div>

        <!-- 1. جدول الاستحقاقات -->
        <div v-if="currentTab === 'dues'" class="space-y-4">
            <!-- شريط البحث والتصفية -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-white p-4 rounded-2xl border border-[#D6E0E7] shadow-2xs">
                <div class="relative flex-1 max-w-md">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="ابحث باسم المستأجر أو الوحدة أو الفترة..."
                        class="w-full h-10 pr-10 pl-4 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] placeholder:text-[#5B6770] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right"
                    />
                    <Search class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-[#5B6770]" />
                </div>

                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
                    <button
                        v-for="f in [
                            { id: 'all', label: 'الكل' },
                            { id: 'overdue', label: 'المتأخرات' },
                            { id: 'pending', label: 'مستحق قريباً' },
                            { id: 'paid', label: 'مسدد بالكامل' }
                        ]"
                        :key="f.id"
                        @click="statusFilter = f.id"
                        type="button"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition cursor-pointer shrink-0"
                        :class="statusFilter === f.id
                            ? 'bg-[#163E3E] text-white shadow-2xs'
                            : 'bg-[#F8FAFC] text-[#5B6770] hover:text-[#163E3E] border border-[#D6E0E7]'"
                    >
                        {{ f.label }}
                    </button>
                </div>
            </div>

            <!-- جدول الفواتير والاستحقاقات -->
            <div v-if="filteredCharges.length > 0" class="rounded-3xl border border-[#D6E0E7] bg-white overflow-hidden shadow-2xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-sm">
                        <thead>
                            <tr class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] text-xs font-bold">
                                <th class="py-3.5 px-6">المستأجر</th>
                                <th class="py-3.5 px-6">الوحدة والعقار</th>
                                <th class="py-3.5 px-6">الفترة وتاريخ الاستحقاق</th>
                                <th class="py-3.5 px-6">المبلغ المطلوب</th>
                                <th class="py-3.5 px-6">المسدد وتاريخ السداد</th>
                                <th class="py-3.5 px-6">المتبقي المعلق</th>
                                <th class="py-3.5 px-6">الحالة</th>
                                <th class="py-3.5 px-6 text-start sm:text-left">إجراء</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#D6E0E7]">
                            <tr
                                v-for="c in filteredCharges"
                                :key="c.id"
                                class="hover:bg-[#F8F4EA] transition"
                            >
                                <td class="py-4 px-6">
                                    <Link
                                        :href="route('renters.show', c.renter_id)"
                                        class="font-bold text-[#163E3E] hover:text-[#163E3E] transition block"
                                    >
                                        {{ c.renter_name }}
                                    </Link>
                                    <span class="text-xs text-[#5B6770] font-mono" dir="ltr">{{ c.renter_phone }}</span>
                                </td>

                                <td class="py-4 px-6 text-xs text-[#163E3E]">
                                    <span class="font-bold block">{{ c.unit_name }}</span>
                                    <span class="text-[#5B6770] block">{{ c.property_name }}</span>
                                </td>

                                <td class="py-4 px-6 text-xs text-[#163E3E]">
                                    <div class="flex items-center gap-1.5 font-bold text-[#163E3E]">
                                        <Calendar class="w-3.5 h-3.5 text-[#163E3E] shrink-0" />
                                        <span>{{ c.period_name || `شهر ${c.period}` }}</span>
                                        <span class="text-[11px] font-mono font-normal text-[#5B6770]">({{ c.period }})</span>
                                    </div>
                                    <div class="text-[11px] text-[#5B6770] mt-1 space-y-0.5">
                                        <div class="font-mono">
                                            <span>الاستحقاق: </span>
                                            <span class="font-bold text-[#163E3E]">{{ c.due_date }}</span>
                                            <span v-if="c.status === 'overdue' && c.days_overdue" class="text-rose-600 font-semibold mr-1">
                                                (متأخر {{ c.days_overdue }} يوم)
                                            </span>
                                        </div>
                                        <div v-if="c.period_start && c.period_end" class="font-mono text-[10px] text-[#8A9AA7]">
                                            الفترة: {{ c.period_start }} — {{ c.period_end }}
                                        </div>
                                    </div>
                                </td>

                                <td class="py-4 px-6 font-mono text-xs font-bold text-[#163E3E]">
                                    {{ c.amount.toLocaleString() }} ج.م
                                </td>

                                <td class="py-4 px-6 text-xs">
                                    <div class="font-mono font-bold text-emerald-700 text-sm">
                                        {{ c.paid_amount.toLocaleString() }} ج.م
                                    </div>
                                    <div v-if="c.paid_at" class="text-[11px] text-emerald-800 font-medium flex items-center gap-1 mt-0.5">
                                        <CheckCircle2 class="w-3 h-3 text-emerald-600 shrink-0" />
                                        <span class="font-mono">سُدد: {{ c.paid_at }}</span>
                                    </div>
                                    <div v-else-if="c.outstanding_balance > 0" class="text-[11px] text-[#8A9AA7] mt-0.5 font-mono">
                                        بانتظار السداد
                                    </div>
                                </td>

                                <td class="py-4 px-6 font-mono text-xs" :class="c.outstanding_balance > 0 ? 'text-rose-600 font-bold' : 'text-[#5B6770]'">
                                    {{ c.outstanding_balance.toLocaleString() }} ج.م
                                </td>

                                <td class="py-4 px-6">
                                    <StatusBadge :status="c.status" :label="c.status_label" size="sm" />
                                </td>

                                <td class="py-4 px-6 text-start sm:text-left">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            v-if="c.outstanding_balance > 0"
                                            @click="openPaymentForCharge(c)"
                                            type="button"
                                            class="h-8 px-3 rounded-lg bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold transition flex items-center gap-1 shadow-xs cursor-pointer"
                                        >
                                            <CreditCard class="w-3.5 h-3.5" />
                                            <span>تسجيل سداد</span>
                                        </button>

                                        <a
                                            v-if="c.status === 'overdue'"
                                            :href="`https://wa.me/20${c.renter_phone.replace(/^0+/, '')}?text=${encodeURIComponent(`السلام عليكم ${c.renter_name}، نود تذكيركم بسداد قسط إيجار ${c.unit_name} المستحق بقيمة ${c.outstanding_balance} ج.م. شكراً لتعاونكم.`)}`"
                                            target="_blank"
                                            class="h-8 px-2.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold transition flex items-center gap-1 border border-emerald-200"
                                            title="إرسال تذكير WhatsApp فوري"
                                        >
                                            <MessageCircle class="w-3.5 h-3.5 text-emerald-600" />
                                            <span>تذكير</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <EmptyState
                v-else
                type="document"
                title="لا توجد استحقاقات تطابق البحث"
                description="لم يتم العثور على أي فواتير أو استحقاقات تطابق معايير التصفية الحالية."
            />
        </div>

        <!-- 2. جدول سندات القبض المسددة -->
        <div v-else class="space-y-4">
            <!-- شريط البحث لسندات القبض -->
            <div class="flex items-center justify-between gap-3 bg-white p-4 rounded-2xl border border-[#D6E0E7] shadow-2xs">
                <div class="relative flex-1 max-w-md">
                    <input
                        v-model="receiptSearchQuery"
                        type="text"
                        placeholder="ابحث برقم السند أو اسم المستأجر أو الوحدة أو طريقة الدفع..."
                        class="w-full h-10 pr-10 pl-4 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] placeholder:text-[#5B6770] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right"
                    />
                    <Search class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-[#5B6770]" />
                </div>
                <span class="text-xs text-[#5B6770] font-semibold">
                    عرض {{ filteredPayments.length }} من أصل {{ payments.length }} سند
                </span>
            </div>

            <div v-if="filteredPayments.length > 0" class="rounded-3xl border border-[#D6E0E7] bg-white overflow-hidden shadow-2xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-sm">
                        <thead>
                            <tr class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] text-xs font-bold">
                                <th class="py-3.5 px-6">رقم السند</th>
                                <th class="py-3.5 px-6">المستأجر</th>
                                <th class="py-3.5 px-6">الوحدة</th>
                                <th class="py-3.5 px-6">المبلغ المقبوض</th>
                                <th class="py-3.5 px-6">الفترة المغطاة بالسند</th>
                                <th class="py-3.5 px-6">تاريخ التحصيل والقيد</th>
                                <th class="py-3.5 px-6">طريقة السداد</th>
                                <th class="py-3.5 px-6">حالة الرقابة</th>
                                <th class="py-3.5 px-6 text-center">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#D6E0E7]">
                            <tr v-for="pay in filteredPayments" :key="pay.id" class="hover:bg-[#F8F4EA] transition">
                                <td class="py-4 px-6 font-mono font-bold text-[#163E3E]">
                                    <div>{{ pay.reference }}</div>
                                    <span v-if="pay.notes" class="text-[11px] text-[#5B6770] font-sans block max-w-xs truncate">{{ pay.notes }}</span>
                                </td>
                                <td class="py-4 px-6 font-bold text-[#163E3E]">
                                    <Link :href="route('renters.show', pay.renter_id)" class="hover:text-[#163E3E] transition">
                                        {{ pay.renter_name }}
                                    </Link>
                                    <span v-if="pay.renter_phone" class="text-[11px] text-[#5B6770] font-mono block" dir="ltr">{{ pay.renter_phone }}</span>
                                </td>
                                <td class="py-4 px-6 text-xs text-[#5B6770]">
                                    <span class="font-bold text-[#163E3E] block">{{ pay.unit_name }}</span>
                                    <span v-if="pay.property_name" class="block">{{ pay.property_name }}</span>
                                </td>
                                <td class="py-4 px-6 font-mono text-sm font-bold text-[#163E3E]">
                                    <div>{{ pay.amount.toLocaleString() }} ج.م</div>
                                    <span v-if="pay.allocations && pay.allocations.length > 1" class="text-[10px] font-sans font-semibold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200 inline-block mt-0.5">
                                        موزع على {{ pay.allocations.length }} فترات
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-xs text-[#163E3E]">
                                    <div v-if="pay.allocations && pay.allocations.length > 0" class="space-y-1">
                                        <div v-for="alloc in pay.allocations" :key="alloc.charge_id" class="flex items-center gap-1.5 flex-wrap">
                                            <span class="px-2 py-0.5 rounded-lg bg-[#E8F0F0] text-[#163E3E] font-bold text-[11px] border border-[#163E3E]/10 flex items-center gap-1">
                                                <Calendar class="w-3 h-3 text-[#163E3E]" />
                                                <span>{{ alloc.period_name || `إيجار ${alloc.period}` }}</span>
                                            </span>
                                            <span v-if="pay.allocations.length > 1" class="font-mono text-[10px] text-[#5B6770]">
                                                ({{ alloc.amount.toLocaleString() }} ج.م)
                                            </span>
                                        </div>
                                    </div>
                                    <span v-else class="text-[#8A9AA7] text-[11px] font-mono">—</span>
                                </td>
                                <td class="py-4 px-6 text-xs text-[#163E3E]">
                                    <div class="flex items-center gap-1.5 font-bold text-[#163E3E]">
                                        <Calendar class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                        <span class="font-mono">{{ pay.paid_date || pay.paid_at }}</span>
                                        <span v-if="pay.paid_time" class="text-[11px] font-mono font-normal text-[#5B6770]" dir="ltr">
                                            {{ pay.paid_time }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-[#5B6770] font-mono mt-1 flex items-center gap-1">
                                        <Clock class="w-3 h-3 text-[#8A9AA7] shrink-0" />
                                        <span>تاريخ القيد: {{ pay.created_date || pay.created_at }}</span>
                                    </div>
                                </td>
                                <td class="py-4 px-6 text-xs text-[#163E3E]">
                                    {{ pay.method }}
                                </td>
                                <td class="py-4 px-6">
                                    <span v-if="pay.is_corrected" class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-800 bg-amber-50 px-2.5 py-1 rounded-xl border border-amber-200">
                                        <History class="w-3 h-3 text-amber-600" />
                                        <span>مُعدل رقابياً ({{ pay.corrections_count }})</span>
                                    </span>
                                    <span v-else class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-lg border border-emerald-100">
                                        أصلي معتمد
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <a
                                            v-if="pay.has_receipt && pay.receipt_url"
                                            :href="pay.receipt_url"
                                            target="_blank"
                                            class="h-8 px-2 rounded-lg border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold transition inline-flex items-center gap-1 shadow-2xs"
                                            title="عرض إشعار أو صورة التحويل المرفقة"
                                        >
                                            <Paperclip class="w-3.5 h-3.5 text-emerald-700" />
                                            <span>إشعار</span>
                                        </a>

                                        <a
                                            :href="route('payments.receipt', pay.id)"
                                            target="_blank"
                                            class="h-8 px-2.5 rounded-lg border border-[#D6E0E7] bg-white hover:bg-slate-50 text-[#163E3E] text-xs font-bold transition inline-flex items-center gap-1 shadow-2xs"
                                            title="عرض وطباعة سند القبض الإلكتروني المعتمد"
                                        >
                                            <Printer class="w-3.5 h-3.5" />
                                            <span>عرض</span>
                                        </a>

                                        <button
                                            @click="openCorrectionForPayment(pay)"
                                            type="button"
                                            class="h-8 px-2.5 rounded-lg border border-amber-200 bg-amber-50 hover:bg-amber-100 text-amber-800 text-xs font-bold transition inline-flex items-center gap-1 shadow-2xs cursor-pointer"
                                            title="تصحيح وتعديل السند وإعادة تسوية الالتزامات"
                                        >
                                            <Pencil class="w-3.5 h-3.5 text-amber-600" />
                                            <span>تعديل</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <EmptyState
                v-else
                type="document"
                title="لا توجد سندات قبض مسجلة حتى الآن"
                description="ابدأ بتسجيل أول دفعة نقدية أو بنكية للمستأجرين لإصدار سند قبض إلكتروني فوري."
                action-label="تسجيل أول دفعة الآن"
                @action="recordPaymentOpen = true"
            />
        </div>

        <!-- نافذة تسجيل الدفعة السريعة -->
        <ModalRecordPayment
            :show="recordPaymentOpen"
            :renters="renters"
            :initial-renter-id="activeChargeForPayment?.renter_id"
            :initial-amount="activeChargeForPayment?.outstanding_balance"
            :initial-rent-charge-id="activeChargeForPayment?.id"
            @close="recordPaymentOpen = false"
        />

        <!-- نافذة تصحيح وتعديل سند القبض والرقابة المالية -->
        <ModalCorrectPayment
            :show="correctPaymentOpen"
            :payment="activePaymentForCorrection"
            @close="correctPaymentOpen = false"
        />
    </AppLayout>
</template>
