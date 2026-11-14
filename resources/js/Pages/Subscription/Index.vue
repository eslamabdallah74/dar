<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import {
    AlertCircle,
    Check,
    Clock,
    Crown,
    Download,
    FileText,
    Sparkles,
} from 'lucide-vue-next';

interface PlanItem {
    id: number | string;
    slug?: string;
    name: string;
    badge: string;
    price: number;
    period: string;
    units_limit: number | null;
    parent_unit_limit?: number | null;
    active_rental_limit?: number | null;
    features: string[];
    is_current: boolean;
    is_requested?: boolean;
}

interface InvoiceItem {
    id: string;
    date: string;
    description: string;
    amount: number;
    status: string;
    status_label: string;
}

interface RequestedPlanData {
    id: number;
    name: string;
    slug: string;
    monthly_price: number;
    parent_unit_limit: number | null;
    status: string;
    status_label: string;
    requested_at: string;
    notes?: string | null;
}

defineProps<{
    current_plan: {
        name: string;
        slug?: string;
        units_used: number;
        units_limit: number | null;
        parent_unit_limit?: number | null;
        active_rental_limit?: number | null;
        renewal_date: string | null;
    };
    requested_plan?: RequestedPlanData | null;
    plans: PlanItem[];
    billing_history: InvoiceItem[];
}>();

const upgradeModalOpen = ref(false);
const selectedPlanToUpgrade = ref<PlanItem | null>(null);

const requestForm = useForm({
    plan_id: 0,
    notes: '',
});

const handleUpgradeClick = (plan: PlanItem) => {
    selectedPlanToUpgrade.value = plan;
    requestForm.plan_id = Number(plan.id);
    requestForm.notes = '';
    upgradeModalOpen.value = true;
};

const submitPlanRequest = () => {
    requestForm.post(route('subscription.request'), {
        preserveScroll: true,
        onSuccess: () => {
            upgradeModalOpen.value = false;
        },
    });
};
</script>

<template>
    <AppLayout
        title="الاشتراك والباقات — Nook Mate"
        active-tab="subscription"
        header-title="الاشتراك وسعة الوحدات"
        header-subtitle="إدارة خطة المنشأة، استهلاك الوحدات وتاريخ فواتير التجديد"
        breadcrumb="الاشتراك"
    >
        <!-- إشعار حالة طلب الترقية المعلق إن وجد -->
        <div
            v-if="requested_plan && requested_plan.status === 'pending'"
            class="rounded-3xl border border-amber-300 bg-amber-50/80 p-5 shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-4"
            dir="rtl"
        >
            <div class="flex items-start gap-3.5">
                <div class="w-10 h-10 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0 mt-0.5">
                    <Clock class="w-5 h-5" />
                </div>
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-amber-900">طلب تغيير الباقة قيد مراجعة واعتماد الإدارة</h3>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-200 text-amber-800">
                            قيد الانتظار
                        </span>
                    </div>
                    <p class="text-xs text-amber-800">
                        لقد طلبت الانتقال إلى <strong class="font-bold text-amber-950">{{ requested_plan.name }}</strong>
                        <template v-if="requested_plan.monthly_price > 0">
                            ({{ requested_plan.monthly_price }} ج.م/شهرياً)
                        </template>
                        <template v-else>
                            (تسعير مخصص)
                        </template>
                        بتاريخ {{ requested_plan.requested_at }}. سيقوم مدير النظام بمراجعة الطلب وتفعيله لمساحة عملك.
                    </p>
                </div>
            </div>
            <div class="shrink-0 text-left">
                <span class="text-xs font-semibold text-amber-700">باقتك الحالية تبقى نشطة دون انقطاع</span>
            </div>
        </div>

        <!-- إشعار إذا تم رفض الطلب الأخير -->
        <div
            v-else-if="requested_plan && requested_plan.status === 'rejected'"
            class="rounded-3xl border border-rose-200 bg-rose-50/80 p-5 shadow-2xs flex items-start gap-3.5"
            dir="rtl"
        >
            <div class="w-10 h-10 rounded-2xl bg-rose-100 text-rose-700 flex items-center justify-center shrink-0 mt-0.5">
                <AlertCircle class="w-5 h-5" />
            </div>
            <div class="space-y-1 text-right">
                <h3 class="text-sm font-bold text-rose-900">تم رفض طلب الترقية الأخير إلى {{ requested_plan.name }}</h3>
                <p class="text-xs text-rose-700">
                    {{ requested_plan.notes || 'يمكنك اختيار باقة أخرى تناسب احتياجاتك وإرسال طلب جديد.' }}
                </p>
            </div>
        </div>

        <!-- بطاقة الخطة الحالية واستهلاك الوحدات (Active Plan Hero) -->
        <div class="rounded-3xl border border-[#163E3E]/30 bg-gradient-to-l from-[#E8F0F0]/40 to-white p-6 sm:p-8 shadow-2xs space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center gap-4 pb-6 border-b border-[#D6E0E7]">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl bg-[#163E3E] text-white flex items-center justify-center shadow-xs">
                        <Crown class="w-7 h-7" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-xl font-bold text-[#163E3E]">{{ current_plan.name }}</h2>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-[#163E3E] text-white">
                                نشطة حالياً
                            </span>
                        </div>
                        <p v-if="current_plan.renewal_date" class="text-xs text-[#5B6770] mt-1">
                            موعد التجديد القادم: {{ current_plan.renewal_date }}
                        </p>
                    </div>
                </div>

            </div>

            <!-- شريط استهلاك العقارات والوحدات الرئيسية -->
            <div class="space-y-2">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-bold text-[#163E3E]">سعة العقارات والوحدات الرئيسية</span>
                    <span class="font-mono font-bold text-[#163E3E]">
                        <template v-if="current_plan.parent_unit_limit !== null && current_plan.parent_unit_limit !== undefined">
                            {{ current_plan.units_used }} من أصل {{ current_plan.parent_unit_limit }} عقار رئيسي
                        </template>
                        <template v-else-if="current_plan.units_limit !== null && current_plan.units_limit !== undefined">
                            {{ current_plan.units_used }} من أصل {{ current_plan.units_limit }} عقار رئيسي
                        </template>
                        <template v-else>
                            {{ current_plan.units_used }} عقار رئيسي (سعة غير محدودة ∞)
                        </template>
                    </span>
                </div>
                <div class="w-full h-3 bg-slate-200/60 rounded-full overflow-hidden">
                    <div
                        class="h-full bg-[#163E3E] rounded-full transition-all duration-500"
                        :style="{ width: (current_plan.parent_unit_limit ?? current_plan.units_limit) ? `${Math.min(100, Math.round((current_plan.units_used / (current_plan.parent_unit_limit ?? current_plan.units_limit ?? 1)) * 100))}%` : '100%' }"
                    ></div>
                </div>
                <div class="flex items-center justify-between text-[11px] text-[#5B6770]">
                    <span v-if="current_plan.parent_unit_limit">تتيح حتى {{ current_plan.parent_unit_limit }} عقار رئيسي مع تقسيمات داخلية غير محدودة (غرف وأسرّة)</span>
                    <span v-else-if="current_plan.units_limit">تتيح حتى {{ current_plan.units_limit }} عقار رئيسي مع تقسيمات داخلية غير محدودة</span>
                    <span v-else>سعة غير محدودة للعقارات الرئيسية وعقود التأجير النشطة</span>
                    <span v-if="current_plan.parent_unit_limit ?? current_plan.units_limit">{{ Math.round((current_plan.units_used / (current_plan.parent_unit_limit ?? current_plan.units_limit ?? 1)) * 100) }}% مستخدمة</span>
                    <span v-else>غير محدود ∞</span>
                </div>
            </div>
        </div>

        <!-- باقات الاشتراك ومقارنة الميزات (Pricing Cards) -->
        <div class="space-y-4">
            <div class="flex items-center justify-between pb-2 border-b border-[#D6E0E7]">
                <div class="flex items-center gap-2">
                    <Sparkles class="w-5 h-5 text-[#163E3E]" />
                    <h2 class="text-lg font-bold text-[#163E3E]">باقات Nook Mate المتاحة</h2>
                </div>
                <span class="text-xs text-[#5B6770]">اختر الخطة المناسبة لاحتياجات مساحة عملك لطلب تفعيلها</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                <div
                    v-for="plan in plans"
                    :key="plan.id"
                    class="rounded-3xl border bg-white p-6 shadow-2xs flex flex-col justify-between transition-all"
                    :class="plan.is_current ? 'border-2 border-[#163E3E] shadow-md ring-4 ring-[#163E3E]/10' : plan.is_requested ? 'border-2 border-amber-400 shadow-md ring-4 ring-amber-100' : 'border-[#D6E0E7] hover:border-[#163E3E]/40'"
                >
                    <div class="space-y-5">
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-bold text-[#163E3E]">{{ plan.name }}</h3>
                            <span
                                class="px-2.5 py-0.5 rounded-full text-xs font-bold"
                                :class="plan.is_current ? 'bg-[#E8F0F0] text-[#163E3E]' : plan.is_requested ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-[#5B6770]'"
                            >
                                {{ plan.is_current ? 'باقتك الحالية' : plan.is_requested ? 'طلبك المعلق' : plan.badge }}
                            </span>
                        </div>

                        <!-- السعر -->
                        <div class="flex items-baseline gap-1">
                            <span v-if="plan.price > 0" class="text-3xl font-bold text-[#163E3E] font-mono">{{ plan.price }}</span>
                            <span v-else-if="plan.slug === 'trial'" class="text-2xl font-bold text-[#163E3E]">مجاناً</span>
                            <span v-else class="text-2xl font-bold text-[#163E3E]">تسعير مخصص</span>
                            <span v-if="plan.price > 0" class="text-xs text-[#5B6770]">ج.م / {{ plan.period }}</span>
                        </div>

                        <!-- السعة -->
                        <div class="p-2.5 rounded-xl bg-[#F8FAFC] border border-[#E8EFF4] text-[11px] font-bold text-[#163E3E]">
                            <template v-if="plan.parent_unit_limit">
                                السعة: حتى {{ plan.parent_unit_limit }} عقارات رئيسية
                            </template>
                            <template v-else>
                                السعة: أكثر من 50 عقاراً رئيسياً (غير محدود)
                            </template>
                        </div>

                        <!-- الميزات -->
                        <ul class="space-y-2.5 text-xs text-[#163E3E] pt-2 border-t border-slate-100">
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
                            v-else-if="plan.is_requested"
                            type="button"
                            disabled
                            class="w-full h-11 rounded-xl bg-amber-50 text-amber-800 border border-amber-300 text-xs font-bold cursor-default"
                        >
                            طلبك قيد مراجعة الإدارة
                        </button>
                        <button
                            v-else
                            @click="handleUpgradeClick(plan)"
                            type="button"
                            class="w-full h-11 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold transition shadow-xs cursor-pointer"
                        >
                            طلب الترقية لهذه الباقة
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- سجل فواتير الاشتراك والمدفوعات (Invoices History) -->
        <div class="space-y-4">
            <div class="flex items-center justify-between pb-2 border-b border-[#D6E0E7]">
                <div class="flex items-center gap-2">
                    <FileText class="w-5 h-5 text-[#163E3E]" />
                    <h2 class="text-lg font-bold text-[#163E3E]">سجل فواتير الاشتراك</h2>
                </div>
                <span class="text-xs text-[#5B6770]">فواتير صادرة لمنشأتك</span>
            </div>

            <div class="rounded-3xl border border-[#D6E0E7] bg-white overflow-hidden shadow-2xs">
                <div class="overflow-x-auto">
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
                        <tr v-for="inv in billing_history" :key="inv.id" class="hover:bg-[#F8F4EA] transition">
                            <td class="py-4 px-6 font-mono font-bold text-[#163E3E]">{{ inv.id }}</td>
                            <td class="py-4 px-6 font-mono text-[#5B6770]">{{ inv.date }}</td>
                            <td class="py-4 px-6 font-bold text-[#163E3E]">{{ inv.description }}</td>
                            <td class="py-4 px-6 font-mono font-bold text-[#163E3E]">{{ inv.amount }} ج.م</td>
                            <td class="py-4 px-6">
                                <StatusBadge :status="inv.status" :label="inv.status_label" size="sm" />
                            </td>
                            <td class="py-4 px-6 text-start sm:text-left">
                                <button
                                    type="button"
                                    class="p-1.5 rounded-lg border border-[#D6E0E7] hover:bg-slate-100 text-[#5B6770] hover:text-[#163E3E] transition cursor-pointer"
                                    title="تحميل الفاتورة PDF"
                                >
                                    <Download class="w-3.5 h-3.5" />
                                </button>
                            </td>
                        </tr>
                        <tr v-if="billing_history.length === 0">
                            <td colspan="6" class="py-6 text-center text-[#5B6770]">لا توجد فواتير اشتراك مسجلة حتى الآن.</td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <!-- نافذة تأكيد طلب الترقية -->
        <div v-if="upgradeModalOpen && selectedPlanToUpgrade" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="upgradeModalOpen = false" class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-6 z-10 text-center space-y-4" dir="rtl">
                <div class="w-14 h-14 rounded-2xl bg-[#E8F0F0] text-[#163E3E] mx-auto flex items-center justify-center">
                    <Sparkles class="w-7 h-7" />
                </div>
                <h3 class="text-lg font-bold text-[#163E3E]">طلب الانتقال إلى {{ selectedPlanToUpgrade.name }}</h3>
                <p class="text-xs text-[#5B6770] leading-relaxed">
                    سيتم إرسال طلبك إلى إدارة المنصة لتفعيل باقة
                    <strong>{{ selectedPlanToUpgrade.parent_unit_limit ? ('حتى ' + selectedPlanToUpgrade.parent_unit_limit + ' عقارات رئيسية') : 'سعة غير محدودة' }}</strong>.
                </p>
                <div class="p-4 rounded-2xl bg-[#F8FAFC] border border-[#D6E0E7] font-mono text-lg font-bold text-[#163E3E]">
                    <span v-if="selectedPlanToUpgrade.price > 0">{{ selectedPlanToUpgrade.price }} ج.م / شهرياً</span>
                    <span v-else-if="selectedPlanToUpgrade.slug === 'trial'">0 ج.م (تجربة مجانية)</span>
                    <span v-else>تسعير مخصص</span>
                </div>

                <div v-if="requestForm.errors.plan_id" class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold text-right flex items-center gap-2">
                    <AlertCircle class="w-4 h-4 text-rose-600 shrink-0" />
                    <span>{{ requestForm.errors.plan_id }}</span>
                </div>

                <div class="text-right">
                    <label for="req_notes" class="block text-xs font-semibold text-[#163E3E] mb-1.5">ملاحظات إضافية للإدارة (اختياري)</label>
                    <textarea
                        id="req_notes"
                        v-model="requestForm.notes"
                        rows="2"
                        class="w-full mt-1 p-2.5 rounded-xl border border-[#D6E0E7] text-xs text-[#163E3E]"
                        placeholder="أي تفاصيل خاصة ترغب بتوضيحها للإدارة..."
                        aria-label="ملاحظات إضافية للإدارة (اختياري)"
                    ></textarea>
                    <InputError :message="requestForm.errors.notes" class="mt-1" />
                </div>

                <div class="pt-2 flex items-center justify-center gap-3">
                    <SecondaryButton
                        @click="upgradeModalOpen = false"
                        type="button"
                    >
                        إلغاء
                    </SecondaryButton>
                    <PrimaryButton
                        @click="submitPlanRequest"
                        :disabled="requestForm.processing"
                        type="button"
                        class="!px-6 !py-2.5 !text-xs !bg-[#163E3E] hover:!bg-[#102F2F] !text-white"
                    >
                        {{ requestForm.processing ? 'جاري إرسال الطلب...' : 'إرسال طلب التغيير للإدارة' }}
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
