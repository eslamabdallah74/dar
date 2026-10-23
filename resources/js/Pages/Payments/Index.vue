<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import ModalRecordPayment from '@/Components/Common/ModalRecordPayment.vue';
import {
    ArrowUpRight,
    Calendar,
    CheckCircle2,
    Clock,
    Coins,
    CreditCard,
    DollarSign,
    FileText,
    MessageCircle,
    Plus,
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
    due_date: string;
    raw_due_date: string;
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
    unit_name: string;
    amount: number;
    paid_at: string;
    method: string;
    notes: string | null;
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
}>();

const currentTab = ref<'dues' | 'receipts'>('dues');
const searchQuery = ref('');
const statusFilter = ref('all');
const recordPaymentOpen = ref(false);
const activeChargeForPayment = ref<ChargeItem | null>(null);

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

const openPaymentForCharge = (charge: ChargeItem) => {
    activeChargeForPayment.value = charge;
    recordPaymentOpen.value = true;
};
</script>

<template>
    <AppLayout
        title="الاستحقاقات والدفعات — دار"
        active-tab="payments"
        header-title="الاستحقاقات والدفعات"
        header-subtitle="متابعة تحصيل الإيجارات، الفواتير المستحقة وإصدار سندات القبض الإلكترونية"
        breadcrumb="الدفعات"
    >
        <template #actions>
            <button
                @click="recordPaymentOpen = true"
                type="button"
                class="h-11 px-5 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-sm font-semibold transition shadow-xs flex items-center gap-2 cursor-pointer"
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
                    <span class="text-2xl font-bold text-[#0F2747] font-mono">{{ stats.target.toLocaleString() }} <span class="text-xs font-sans font-normal text-[#5B6770]">ج.م</span></span>
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
                    <span class="text-2xl font-bold text-[#0F2747] font-mono">{{ payments.length }}</span>
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
                        ? 'bg-[#0E6E7E] text-white shadow-xs'
                        : 'bg-white text-[#5B6770] hover:text-[#0F2747] border border-[#D6E0E7]'"
                >
                    <Clock class="w-4 h-4" />
                    <span>الاستحقاقات والفواتير الشهرية ({{ charges.length }})</span>
                </button>

                <button
                    @click="currentTab = 'receipts'"
                    type="button"
                    class="px-5 py-2.5 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-2"
                    :class="currentTab === 'receipts'
                        ? 'bg-[#0E6E7E] text-white shadow-xs'
                        : 'bg-white text-[#5B6770] hover:text-[#0F2747] border border-[#D6E0E7]'"
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
                        class="w-full h-10 pr-10 pl-4 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] placeholder:text-[#5B6770] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-right"
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
                            ? 'bg-[#0E6E7E] text-white shadow-2xs'
                            : 'bg-[#F8FAFC] text-[#5B6770] hover:text-[#0F2747] border border-[#D6E0E7]'"
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
                                <th class="py-3.5 px-6">تاريخ الاستحقاق</th>
                                <th class="py-3.5 px-6">المبلغ المطلوب</th>
                                <th class="py-3.5 px-6">المسدد</th>
                                <th class="py-3.5 px-6">المتبقي المعلق</th>
                                <th class="py-3.5 px-6">الحالة</th>
                                <th class="py-3.5 px-6 text-left">إجراء</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#D6E0E7]">
                            <tr
                                v-for="c in filteredCharges"
                                :key="c.id"
                                class="hover:bg-[#F3F7FA] transition"
                            >
                                <td class="py-4 px-6">
                                    <Link
                                        :href="route('renters.show', c.renter_id)"
                                        class="font-bold text-[#0F2747] hover:text-[#0E6E7E] transition block"
                                    >
                                        {{ c.renter_name }}
                                    </Link>
                                    <span class="text-xs text-[#5B6770] font-mono" dir="ltr">{{ c.renter_phone }}</span>
                                </td>

                                <td class="py-4 px-6 text-xs text-[#0F2747]">
                                    <span class="font-bold block">{{ c.unit_name }}</span>
                                    <span class="text-[#5B6770] block">{{ c.property_name }}</span>
                                </td>

                                <td class="py-4 px-6 font-mono text-xs text-[#5B6770]">
                                    {{ c.due_date }}
                                </td>

                                <td class="py-4 px-6 font-mono text-xs font-bold text-[#0F2747]">
                                    {{ c.amount.toLocaleString() }} ج.م
                                </td>

                                <td class="py-4 px-6 font-mono text-xs font-bold text-emerald-700">
                                    {{ c.paid_amount.toLocaleString() }} ج.م
                                </td>

                                <td class="py-4 px-6 font-mono text-xs" :class="c.outstanding_balance > 0 ? 'text-rose-600 font-bold' : 'text-[#5B6770]'">
                                    {{ c.outstanding_balance.toLocaleString() }} ج.م
                                </td>

                                <td class="py-4 px-6">
                                    <StatusBadge :status="c.status" :label="c.status_label" size="sm" />
                                </td>

                                <td class="py-4 px-6 text-left">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            v-if="c.outstanding_balance > 0"
                                            @click="openPaymentForCharge(c)"
                                            type="button"
                                            class="h-8 px-3 rounded-lg bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-bold transition flex items-center gap-1 shadow-xs cursor-pointer"
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
            <div v-if="payments.length > 0" class="rounded-3xl border border-[#D6E0E7] bg-white overflow-hidden shadow-2xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-sm">
                        <thead>
                            <tr class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] text-xs font-bold">
                                <th class="py-3.5 px-6">رقم السند</th>
                                <th class="py-3.5 px-6">المستأجر</th>
                                <th class="py-3.5 px-6">الوحدة</th>
                                <th class="py-3.5 px-6">المبلغ المقبوض</th>
                                <th class="py-3.5 px-6">تاريخ التحصيل</th>
                                <th class="py-3.5 px-6">طريقة السداد</th>
                                <th class="py-3.5 px-6">ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#D6E0E7]">
                            <tr v-for="pay in payments" :key="pay.id" class="hover:bg-[#F3F7FA] transition">
                                <td class="py-4 px-6 font-mono font-bold text-[#0E6E7E]">
                                    {{ pay.reference }}
                                </td>
                                <td class="py-4 px-6 font-bold text-[#0F2747]">
                                    <Link :href="route('renters.show', pay.renter_id)" class="hover:text-[#0E6E7E] transition">
                                        {{ pay.renter_name }}
                                    </Link>
                                </td>
                                <td class="py-4 px-6 text-xs text-[#5B6770]">
                                    {{ pay.unit_name }}
                                </td>
                                <td class="py-4 px-6 font-mono text-sm font-bold text-[#0F2747]">
                                    {{ pay.amount.toLocaleString() }} ج.م
                                </td>
                                <td class="py-4 px-6 font-mono text-xs text-[#5B6770]">
                                    {{ pay.paid_at }}
                                </td>
                                <td class="py-4 px-6 text-xs text-[#0F2747]">
                                    {{ pay.method }}
                                </td>
                                <td class="py-4 px-6 text-xs text-[#5B6770]">
                                    {{ pay.notes || '—' }}
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
    </AppLayout>
</template>

