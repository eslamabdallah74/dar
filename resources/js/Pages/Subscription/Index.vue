<script setup lang="ts">
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import {
    Check,
    CreditCard,
    Crown,
    Download,
    FileText,
    HelpCircle,
    Layers,
    ShieldCheck,
    Sparkles,
    Zap,
} from 'lucide-vue-next';

interface PlanItem {
    id: string;
    name: string;
    badge: string;
    price: number;
    period: string;
    units_limit: number;
    features: string[];
    is_current: boolean;
}

interface InvoiceItem {
    id: string;
    date: string;
    description: string;
    amount: number;
    status: string;
    status_label: string;
}

const props = defineProps<{
    current_plan: {
        name: string;
        units_used: number;
        units_limit: number;
        renewal_date: string | null;
        payment_card: string;
    };
    plans: PlanItem[];
    billing_history: InvoiceItem[];
}>();

const upgradeModalOpen = ref(false);
const selectedPlanToUpgrade = ref<PlanItem | null>(null);

const handleUpgradeClick = (plan: PlanItem) => {
    selectedPlanToUpgrade.value = plan;
    upgradeModalOpen.value = true;
};
</script>

<template>
    <AppLayout
        title="الاشتراك والباقات — دار"
        active-tab="subscription"
        header-title="الاشتراك وسعة الوحدات"
        header-subtitle="إدارة خطة المنشأة، استهلاك الوحدات وتاريخ فواتير التجديد"
        breadcrumb="الاشتراك"
    >
        <!-- بطاقة الخطة الحالية واستهلاك الوحدات (Active Plan Hero) -->
        <div class="rounded-3xl border border-[#0E6E7E]/30 bg-gradient-to-l from-[#E3F1F4]/40 to-white p-6 sm:p-8 shadow-2xs space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-[#D6E0E7]">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl bg-[#0E6E7E] text-white flex items-center justify-center shadow-xs">
                        <Crown class="w-7 h-7" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-xl font-bold text-[#0F2747]">{{ current_plan.name }}</h2>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-[#0E6E7E] text-white">
                                نشطة
                            </span>
                        </div>
                        <p v-if="current_plan.renewal_date" class="text-xs text-[#5B6770] mt-1">
                            موعد التجديد القادم: {{ current_plan.renewal_date }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <span class="text-xs text-[#5B6770] flex items-center gap-1.5 bg-white px-3 py-2 rounded-xl border border-[#D6E0E7]">
                        <CreditCard class="w-4 h-4 text-[#5B6770]" />
                        <span>البطاقة: {{ current_plan.payment_card }}</span>
                    </span>
                </div>
            </div>

            <!-- شريط استهلاك الوحدات التأجيرية -->
            <div class="space-y-2">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-bold text-[#0F2747]">سعة الوحدات التأجيرية المستخدمة</span>
                    <span class="font-mono font-bold text-[#0E6E7E]">
                        {{ current_plan.units_used }} من أصل {{ current_plan.units_limit }} وحدة مسجلة
                    </span>
                </div>
                <div class="w-full h-3 bg-slate-200/60 rounded-full overflow-hidden">
                    <div
                        class="h-full bg-[#0E6E7E] rounded-full transition-all duration-500"
                        :style="{ width: `${Math.round((current_plan.units_used / current_plan.units_limit) * 100)}%` }"
                    ></div>
                </div>
                <div class="flex items-center justify-between text-[11px] text-[#5B6770]">
                    <span>باقة النمو تتيح حتى 25 وحدة مع رسائل WhatsApp مجانية غير محدودة</span>
                    <span>{{ Math.round((current_plan.units_used / current_plan.units_limit) * 100) }}% مستخدمة</span>
                </div>
            </div>
        </div>

        <!-- باقات الاشتراك ومقارنة الميزات (Pricing Cards) -->
        <div class="space-y-4">
            <div class="flex items-center justify-between pb-2 border-b border-[#D6E0E7]">
                <div class="flex items-center gap-2">
                    <Sparkles class="w-5 h-5 text-[#0E6E7E]" />
                    <h2 class="text-lg font-bold text-[#0F2747]">باقات دار المتاحة</h2>
                </div>
                <span class="text-xs text-[#5B6770]">اختر الخطة المناسبة لحجم محفظتك العقارية</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div
                    v-for="plan in plans"
                    :key="plan.id"
                    class="rounded-3xl border bg-white p-6 shadow-2xs flex flex-col justify-between transition-all"
                    :class="plan.is_current ? 'border-2 border-[#0E6E7E] shadow-md ring-4 ring-[#0E6E7E]/10' : 'border-[#D6E0E7] hover:border-[#0E6E7E]/40'"
                >
                    <div class="space-y-5">
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-bold text-[#0F2747]">{{ plan.name }}</h3>
                            <span
                                class="px-2.5 py-0.5 rounded-full text-xs font-bold"
                                :class="plan.is_current ? 'bg-[#E3F1F4] text-[#0E6E7E]' : 'bg-slate-100 text-[#5B6770]'"
                            >
                                {{ plan.badge }}
                            </span>
                        </div>

                        <!-- السعر -->
                        <div class="flex items-baseline gap-1">
                            <span class="text-3xl font-bold text-[#0F2747] font-mono">{{ plan.price }}</span>
                            <span class="text-xs text-[#5B6770]">ج.م / {{ plan.period }}</span>
                        </div>

                        <!-- الميزات -->
                        <ul class="space-y-2.5 text-xs text-[#0F2747] pt-2 border-t border-slate-100">
                            <li v-for="feat in plan.features" :key="feat" class="flex items-start gap-2">
                                <Check class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
                                <span>{{ feat }}</span>
                            </li>
                        </ul>
                    </div>

                    <div class="pt-6 mt-4 border-t border-slate-100">
                        <button
                            v-if="plan.is_current"
                            type="button"
                            disabled
                            class="w-full h-11 rounded-xl bg-slate-100 text-[#5B6770] text-xs font-bold cursor-default"
                        >
                            باقتك الحالية النشطة
                        </button>
                        <button
                            v-else
                            @click="handleUpgradeClick(plan)"
                            type="button"
                            class="w-full h-11 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-bold transition shadow-xs cursor-pointer"
                        >
                            ترقية لهذه الباقة
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- سجل فواتير الاشتراك والمدفوعات (Invoices History) -->
        <div class="space-y-4">
            <div class="flex items-center justify-between pb-2 border-b border-[#D6E0E7]">
                <div class="flex items-center gap-2">
                    <FileText class="w-5 h-5 text-[#0E6E7E]" />
                    <h2 class="text-lg font-bold text-[#0F2747]">سجل فواتير الاشتراك</h2>
                </div>
                <span class="text-xs text-[#5B6770]">فواتير ضريبية صادرة لمنشأتك</span>
            </div>

            <div class="rounded-3xl border border-[#D6E0E7] bg-white overflow-hidden shadow-2xs">
                <table class="w-full text-right text-xs">
                    <thead>
                        <tr class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] font-bold">
                            <th class="py-3.5 px-6">رقم الفاتورة</th>
                            <th class="py-3.5 px-6">التاريخ</th>
                            <th class="py-3.5 px-6">الوصف</th>
                            <th class="py-3.5 px-6">المبلغ</th>
                            <th class="py-3.5 px-6">الحالة</th>
                            <th class="py-3.5 px-6 text-start sm:text-left">تحميل</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#D6E0E7]">
                        <tr v-for="inv in billing_history" :key="inv.id" class="hover:bg-[#F3F7FA] transition">
                            <td class="py-4 px-6 font-mono font-bold text-[#0E6E7E]">{{ inv.id }}</td>
                            <td class="py-4 px-6 font-mono text-[#5B6770]">{{ inv.date }}</td>
                            <td class="py-4 px-6 font-bold text-[#0F2747]">{{ inv.description }}</td>
                            <td class="py-4 px-6 font-mono font-bold text-[#0F2747]">{{ inv.amount }} ج.م</td>
                            <td class="py-4 px-6">
                                <StatusBadge :status="inv.status" :label="inv.status_label" size="sm" />
                            </td>
                            <td class="py-4 px-6 text-start sm:text-left">
                                <button
                                    type="button"
                                    class="p-1.5 rounded-lg border border-[#D6E0E7] hover:bg-slate-100 text-[#5B6770] hover:text-[#0F2747] transition cursor-pointer"
                                    title="تحميل الفاتورة PDF"
                                >
                                    <Download class="w-3.5 h-3.5" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- نافذة تأكيد الترقية -->
        <div v-if="upgradeModalOpen && selectedPlanToUpgrade" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="upgradeModalOpen = false" class="fixed inset-0 bg-[#0F2747]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-6 z-10 text-center space-y-4" dir="rtl">
                <div class="w-14 h-14 rounded-2xl bg-[#E3F1F4] text-[#0E6E7E] mx-auto flex items-center justify-center">
                    <Sparkles class="w-7 h-7" />
                </div>
                <h3 class="text-lg font-bold text-[#0F2747]">الترقية إلى {{ selectedPlanToUpgrade.name }}</h3>
                <p class="text-xs text-[#5B6770] leading-relaxed">
                    سيتم تفعيل سعة {{ selectedPlanToUpgrade.units_limit }} وحدة تأجيرية فوراً واحتساب فارق السعر على بطاقتك الائتمانية.
                </p>
                <div class="p-4 rounded-2xl bg-[#F8FAFC] border border-[#D6E0E7] font-mono text-lg font-bold text-[#0E6E7E]">
                    {{ selectedPlanToUpgrade.price }} ج.م / شهرياً
                </div>
                <div class="pt-2 flex items-center justify-center gap-3">
                    <button
                        @click="upgradeModalOpen = false"
                        type="button"
                        class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold text-[#5B6770]"
                    >
                        إلغاء
                    </button>
                    <button
                        @click="upgradeModalOpen = false"
                        type="button"
                        class="h-10 px-6 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-bold transition shadow-xs"
                    >
                        تأكيد الترقية الفورية
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
