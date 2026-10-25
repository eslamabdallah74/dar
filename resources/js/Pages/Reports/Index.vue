<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    AlertCircle,
    ArrowDownRight,
    ArrowUpRight,
    BarChart3,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    Coins,
    CreditCard,
    DollarSign,
    Download,
    ExternalLink,
    FileSpreadsheet,
    FileText,
    Filter,
    HelpCircle,
    Home,
    Layers,
    Receipt,
    RefreshCw,
    Shield,
    TrendingDown,
    TrendingUp,
    Wallet,
} from 'lucide-vue-next';

interface Stats {
    total_collected: number;
    total_billed: number;
    total_outstanding: number;
    total_advance?: number;
    total_expenses: number;
    net_cash_flow: number;
    collection_rate: number;

    // Pending & Maintenance Expenses
    actual_maintenance_expenses?: number;
    pending_expenses_count: number;
    pending_expenses_amount: number;
    pending_cost_count: number;
    ready_to_apply_count: number;

    // Security Deposits (Completely separate from operational income/expenses)
    security_deposits_received: number;
    security_deposits_refunded: number;
    security_deposits_retained: number;
    security_deposit_balance: number;
}

interface ExpenseCategory {
    category: string;
    total: number;
    count: number;
}

interface MonthlyData {
    month: number;
    month_name: string;
    collected: number;
    expenses: number;
    net: number;
}

interface UnitOption {
    id: number;
    property_id: number;
    name: string;
}

interface PropertyOption {
    id: number;
    name: string;
    units: UnitOption[];
}

const props = defineProps<{
    stats: Stats;
    expenses_by_category: ExpenseCategory[];
    monthly_breakdown: MonthlyData[];
    date_range: {
        start_date: string;
        end_date: string;
    };
    filters: {
        property_id: number | null;
        unit_id: number | null;
        category: string;
        transaction_type: string;
    };
    properties: PropertyOption[];
    currency: string;
    currency_symbol: string;
}>();

const startDate = ref(props.date_range.start_date);
const endDate = ref(props.date_range.end_date);
const selectedProperty = ref(props.filters.property_id ? String(props.filters.property_id) : '');
const selectedUnit = ref(props.filters.unit_id ? String(props.filters.unit_id) : '');
const selectedCategory = ref(props.filters.category || 'all');
const selectedTransactionType = ref(props.filters.transaction_type || 'all');
const isFiltering = ref(false);

const availableUnits = computed(() => {
    if (!selectedProperty.value) {
        return props.properties.flatMap(p => p.units || []);
    }
    const prop = props.properties.find(p => p.id === Number(selectedProperty.value));
    return prop?.units || [];
});

const applyFilters = () => {
    isFiltering.value = true;
    router.get(
        route('reports.index'),
        {
            start_date: startDate.value,
            end_date: endDate.value,
            property_id: selectedProperty.value || undefined,
            unit_id: selectedUnit.value || undefined,
            category: selectedCategory.value !== 'all' ? selectedCategory.value : undefined,
            transaction_type: selectedTransactionType.value !== 'all' ? selectedTransactionType.value : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isFiltering.value = false;
            },
        }
    );
};

const resetFilters = () => {
    startDate.value = props.date_range.start_date;
    endDate.value = props.date_range.end_date;
    selectedProperty.value = '';
    selectedUnit.value = '';
    selectedCategory.value = 'all';
    selectedTransactionType.value = 'all';
    applyFilters();
};

const formatCurrency = (amount: number | null | undefined): string => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(Number(amount ?? 0));
};
</script>

<template>
    <AppLayout
        title="التقارير المالية والتحليلات — Nook Mate"
        active-tab="reports"
        header-title="التقارير والتحليلات المالية"
        header-subtitle="تتبع دقيق وشامل لجميع التدفقات النقدية الواردة والمنصرفة، مبالغ التأمين، والمصروفات المعتمدة"
        breadcrumb="التقارير المالية"
    >
        <template #actions>
            <!-- أزرار التصدير إلى Excel/CSV -->
            <div class="flex flex-wrap items-center gap-2">
                <a
                    :href="route('reports.collections.export')"
                    class="h-10 px-3.5 rounded-xl border border-[#D6E0E7] bg-white hover:bg-slate-50 text-xs font-semibold text-[#163E3E] transition shadow-2xs flex items-center gap-1.5"
                    title="تحميل ملف إكسل لجدول التحصيل"
                >
                    <FileSpreadsheet class="h-4 w-4 text-emerald-600" />
                    <span>تصدير التحصيلات</span>
                </a>

                <a
                    :href="route('reports.payments.export')"
                    class="h-10 px-3.5 rounded-xl border border-[#D6E0E7] bg-white hover:bg-slate-50 text-xs font-semibold text-[#163E3E] transition shadow-2xs flex items-center gap-1.5"
                    title="تحميل ملف إكسل لجميع سندات القبض"
                >
                    <Download class="h-4 w-4 text-blue-600" />
                    <span>تصدير المقبوضات</span>
                </a>

                <a
                    :href="route('reports.expenses.export')"
                    class="h-10 px-3.5 rounded-xl border border-[#D6E0E7] bg-white hover:bg-slate-50 text-xs font-semibold text-[#163E3E] transition shadow-2xs flex items-center gap-1.5"
                    title="تحميل ملف إكسل لسجل المصروفات التشغيلية المعتمدة"
                >
                    <Download class="h-4 w-4 text-rose-600" />
                    <span>تصدير المصروفات</span>
                </a>

                <a
                    :href="route('reports.leases.export')"
                    class="h-10 px-3.5 rounded-xl border border-[#D6E0E7] bg-white hover:bg-slate-50 text-xs font-semibold text-[#163E3E] transition shadow-2xs flex items-center gap-1.5"
                    title="تحميل ملف إكسل لبيانات العقود التأجيرية"
                >
                    <FileText class="h-4 w-4 text-purple-600" />
                    <span>تصدير العقود</span>
                </a>
            </div>
        </template>

        <div class="space-y-6">
            <!-- شريط الفلاتر المتقدم: الفترة، العقار، الوحدة، التصنيف، ونوع المعاملة -->
            <div class="rounded-3xl border border-[#D6E0E7] bg-white p-5 shadow-2xs">
                <form @submit.prevent="applyFilters" class="space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <Filter class="h-4 w-4 text-[#163E3E]" />
                            <h3 class="text-xs font-bold text-[#163E3E]">تصفية التقارير والتدفقات المالية</h3>
                        </div>
                        <button
                            v-if="selectedProperty || selectedUnit || selectedCategory !== 'all' || selectedTransactionType !== 'all'"
                            @click="resetFilters"
                            type="button"
                            class="text-xs text-[#163E3E] hover:underline font-semibold flex items-center gap-1 cursor-pointer"
                        >
                            <RefreshCw class="w-3 h-3" />
                            <span>إعادة تعيين الفلاتر</span>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
                        <!-- من تاريخ -->
                        <div>
                            <label class="block text-[11px] font-bold text-[#5B6770] mb-1">من تاريخ</label>
                            <div class="relative">
                                <input
                                    v-model="startDate"
                                    type="date"
                                    class="w-full h-10 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right"
                                />
                            </div>
                        </div>

                        <!-- إلى تاريخ -->
                        <div>
                            <label class="block text-[11px] font-bold text-[#5B6770] mb-1">إلى تاريخ</label>
                            <div class="relative">
                                <input
                                    v-model="endDate"
                                    type="date"
                                    class="w-full h-10 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right"
                                />
                            </div>
                        </div>

                        <!-- العقار -->
                        <div>
                            <label class="block text-[11px] font-bold text-[#5B6770] mb-1">العقار / المبنى</label>
                            <select
                                v-model="selectedProperty"
                                @change="selectedUnit = ''"
                                class="w-full h-10 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                            >
                                <option value="">كل العقارات والمباني</option>
                                <option v-for="p in properties" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </div>

                        <!-- الوحدة -->
                        <div>
                            <label class="block text-[11px] font-bold text-[#5B6770] mb-1">الوحدة</label>
                            <select
                                v-model="selectedUnit"
                                class="w-full h-10 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                            >
                                <option value="">جميع الوحدات</option>
                                <option v-for="u in availableUnits" :key="u.id" :value="u.id">{{ u.name }}</option>
                            </select>
                        </div>

                        <!-- زر التطبيق -->
                        <div class="flex items-end">
                            <button
                                type="submit"
                                :disabled="isFiltering"
                                class="w-full h-10 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-2xs cursor-pointer disabled:opacity-50"
                            >
                                <Filter class="w-3.5 h-3.5" />
                                <span>{{ isFiltering ? 'تحديث البيانات...' : 'تطبيق الفلترة' }}</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- كروت المؤشرات المالية الرئيسية (Track All Money In & Out) -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- 1. إجمالي الإيرادات المحصلة (Money In) -->
                <div class="rounded-3xl border border-emerald-200/80 bg-emerald-50/40 p-5 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-emerald-900">إجمالي الإيرادات المحصلة (داخل)</span>
                        <div class="rounded-xl bg-emerald-100 p-2 text-emerald-700">
                            <ArrowDownRight class="h-5 w-5" />
                        </div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-[#163E3E] font-mono">
                            {{ formatCurrency(stats.total_collected) }}
                            <span class="text-xs font-normal text-[#5B6770]">{{ currency_symbol }}</span>
                        </div>
                        <p class="mt-1 text-xs text-emerald-700 font-semibold flex items-center gap-1">
                            <CheckCircle2 class="w-3 h-3" />
                            <span>سندات قبض معتمدة نقدية وبنكية</span>
                        </p>
                        <p v-if="stats.total_advance && stats.total_advance > 0" class="mt-1 text-[11px] text-blue-700 font-semibold">
                            (منها {{ formatCurrency(stats.total_advance) }} {{ currency_symbol }} دفعات مقدمة لفترات لاحقة)
                        </p>
                    </div>
                </div>

                <!-- 2. إجمالي المصروفات المعتمدة (Money Out) -->
                <div class="rounded-3xl border border-rose-200/80 bg-rose-50/40 p-5 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-rose-900">إجمالي المصروفات المعتمدة (خارج)</span>
                        <div class="rounded-xl bg-rose-100 p-2 text-rose-700">
                            <ArrowUpRight class="h-5 w-5" />
                        </div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-rose-700 font-mono">
                            {{ formatCurrency(stats.total_expenses) }}
                            <span class="text-xs font-normal text-[#5B6770]">{{ currency_symbol }}</span>
                        </div>
                        <p class="mt-1 text-xs text-rose-700 font-semibold flex items-center gap-1">
                            <span>فواتير تشغيلية ومصروفات صيانة مطبقة فقط</span>
                        </p>
                    </div>
                </div>

                <!-- 3. صافي التدفق النقدي الفعلي (Net Cash Flow = In - Out) -->
                <div
                    class="rounded-3xl border p-5 shadow-2xs space-y-3"
                    :class="[
                        stats.net_cash_flow >= 0
                            ? 'border-emerald-300 bg-white'
                            : 'border-red-300 bg-red-50/40'
                    ]"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-[#163E3E]">صافي التدفق النقدي (السيولة)</span>
                        <div
                            class="rounded-xl p-2"
                            :class="stats.net_cash_flow >= 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                        >
                            <TrendingUp v-if="stats.net_cash_flow >= 0" class="h-5 w-5" />
                            <TrendingDown v-else class="h-5 w-5" />
                        </div>
                    </div>
                    <div>
                        <div
                            class="text-2xl font-bold font-mono"
                            :class="stats.net_cash_flow >= 0 ? 'text-emerald-700' : 'text-red-700'"
                        >
                            {{ formatCurrency(stats.net_cash_flow) }}
                            <span class="text-xs font-normal text-[#5B6770]">{{ currency_symbol }}</span>
                        </div>
                        <p class="mt-1 text-xs text-[#5B6770]">
                            الفارق النقدي: المحصل الفعلي - المصروفات المعتمدة
                        </p>
                    </div>
                </div>

                <!-- 4. المتأخرات الإيجارية غير المحصلة -->
                <div class="rounded-3xl border border-amber-200/80 bg-amber-50/40 p-5 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-amber-900">المتأخرات الإيجارية المستحقة</span>
                        <div class="rounded-xl bg-amber-100 p-2 text-amber-700">
                            <Clock class="h-5 w-5" />
                        </div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-amber-800 font-mono">
                            {{ formatCurrency(stats.total_outstanding) }}
                            <span class="text-xs font-normal text-[#5B6770]">{{ currency_symbol }}</span>
                        </div>
                        <p class="mt-1 text-xs text-amber-800 font-semibold">
                            من إجمالي مفوتر {{ formatCurrency(stats.total_billed) }} {{ currency_symbol }} (نسبة التحصيل {{ stats.collection_rate }}%)
                        </p>
                    </div>
                </div>
            </div>

            <!-- الصف الثاني من المؤشرات: المصروفات المعلقة + مبالغ التأمين المنفصلة تماماً -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-4">
                <!-- كارت مصروفات الصيانة المعلقة (Pending Maintenance Expenses) -->
                <div class="rounded-3xl border border-indigo-200/80 bg-indigo-50/30 p-5 shadow-2xs flex flex-col justify-between">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Receipt class="h-5 w-5 text-indigo-700" />
                                <span class="text-xs font-bold text-indigo-950">تكاليف صيانة بانتظار الفاتورة (مستبعدة من الإجمالي)</span>
                            </div>
                            <span class="text-xs font-bold text-indigo-700 bg-indigo-100 px-2.5 py-0.5 rounded-full">
                                {{ stats.pending_cost_count }} طلب صيانة
                            </span>
                        </div>
                        <p class="text-xs text-[#5B6770] leading-relaxed">
                            طلبات صيانة لم تُسجل تكلفتها الفعلية بعد، وتظل معروضة تشغيلياً دون التأثير على إجمالي المصروفات أو صافي التدفق النقدي.
                        </p>
                    </div>

                    <div class="mt-4 pt-3 border-t border-indigo-200/60 flex items-center justify-between">
                        <div>
                            <span class="text-[11px] text-[#5B6770] block">مصروفات الصيانة الفعلية المحتسبة:</span>
                            <span class="text-lg font-bold text-indigo-700 font-mono">
                                {{ formatCurrency(stats.actual_maintenance_expenses ?? 0) }} {{ currency_symbol }}
                            </span>
                        </div>
                        <div class="text-left">
                            <span class="text-[11px] text-amber-800 font-semibold block">بانتظار تحديد التكلفة:</span>
                            <span class="text-sm font-bold text-amber-800 font-mono">{{ stats.pending_cost_count }} طلب</span>
                        </div>
                    </div>
                </div>

                <!-- كارت حساب مبالغ التأمين (Security Deposits Trust Account) -->
                <div class="rounded-3xl border border-purple-200/80 bg-purple-50/30 p-5 shadow-2xs flex flex-col justify-between">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Shield class="h-5 w-5 text-purple-700" />
                                <span class="text-xs font-bold text-purple-950">حساب أمانات التأمين (منفصل نظامياً)</span>
                            </div>
                            <span class="text-xs font-bold text-purple-700 bg-purple-100 px-2.5 py-0.5 rounded-full">
                                رصيد أمانات
                            </span>
                        </div>
                        <p class="text-xs text-[#5B6770] leading-relaxed">
                            مبالغ التأمين المستلمة من المستأجرين تُعامل كأمانات معزولة تماماً ولا تختلط مع إيرادات الإيجار أو المصروفات التشغيلية.
                        </p>
                    </div>

                    <div class="mt-4 pt-3 border-t border-purple-200/60 grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="p-2 rounded-xl bg-white border border-purple-100">
                            <span class="text-[10px] text-[#5B6770] block">المقبوض بالفترة</span>
                            <span class="font-bold text-purple-900 font-mono text-sm">{{ formatCurrency(stats.security_deposits_received) }}</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white border border-purple-100">
                            <span class="text-[10px] text-[#5B6770] block">المسترد لمستأجرين</span>
                            <span class="font-bold text-rose-700 font-mono text-sm">{{ formatCurrency(stats.security_deposits_refunded) }}</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white border border-purple-100">
                            <span class="text-[10px] text-[#5B6770] block">المستقطع (تلفيات)</span>
                            <span class="font-bold text-amber-700 font-mono text-sm">{{ formatCurrency(stats.security_deposits_retained) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- جدول التدفقات النقدية الشهرية + توزيع بنود المصروفات المعتمدة -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- جدول التدفقات النقدية الشهرية (2 أعمدة) -->
                <div class="rounded-3xl border border-[#D6E0E7] bg-white p-6 shadow-2xs lg:col-span-2">
                    <div class="flex items-center justify-between border-b border-[#D6E0E7] pb-4">
                        <div>
                            <h2 class="text-base font-bold text-[#163E3E]">جدول حركة التدفق النقدي الشهري</h2>
                            <p class="text-xs text-[#5B6770] mt-0.5">مقارنة شهرية دقيقة للإيرادات المحصلة بالمصروفات التشغيلية المعتمدة</p>
                        </div>
                        <BarChart3 class="h-5 w-5 text-[#163E3E]" />
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-right text-sm">
                            <thead class="border-b border-[#D6E0E7] bg-[#F8FAFC] text-xs font-bold text-[#5B6770]">
                                <tr>
                                    <th class="py-3 px-4">الشهر</th>
                                    <th class="py-3 px-4 text-emerald-800">المحصل الفعلي (داخل)</th>
                                    <th class="py-3 px-4 text-rose-800">المصروفات المعتمدة (خارج)</th>
                                    <th class="py-3 px-4">صافي السيولة النقدية</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#D6E0E7]">
                                <tr
                                    v-for="item in monthly_breakdown"
                                    :key="item.month"
                                    class="hover:bg-[#F8F4EA] transition"
                                >
                                    <td class="py-3 px-4 font-bold text-[#163E3E]">
                                        {{ item.month_name }}
                                    </td>
                                    <td class="py-3 px-4 text-emerald-700 font-bold font-mono">
                                        {{ formatCurrency(item.collected) }} {{ currency_symbol }}
                                    </td>
                                    <td class="py-3 px-4 text-rose-700 font-bold font-mono">
                                        {{ formatCurrency(item.expenses) }} {{ currency_symbol }}
                                    </td>
                                    <td class="py-3 px-4 font-bold font-mono" :class="item.net >= 0 ? 'text-emerald-700' : 'text-rose-600'">
                                        {{ formatCurrency(item.net) }} {{ currency_symbol }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- تصنيف وتوزيع المصروفات المعتمدة (1 عمود) -->
                <div class="rounded-3xl border border-[#D6E0E7] bg-white p-6 shadow-2xs">
                    <div class="flex items-center justify-between border-b border-[#D6E0E7] pb-4">
                        <div>
                            <h2 class="text-base font-bold text-[#163E3E]">توزيع المصروفات المعتمدة</h2>
                            <p class="text-xs text-[#5B6770] mt-0.5">حسب التصنيف والنسبة المئوية للفترة</p>
                        </div>
                        <Coins class="h-5 w-5 text-amber-600" />
                    </div>

                    <div v-if="expenses_by_category.length === 0" class="mt-8 text-center text-xs text-[#5B6770] py-8">
                        لا توجد مصروفات معتمدة مسجلة خلال الفترة أو الفلاتر المحددة.
                    </div>

                    <div v-else class="mt-4 space-y-3.5">
                        <div
                            v-for="cat in expenses_by_category"
                            :key="cat.category"
                            class="rounded-2xl border border-[#D6E0E7] bg-[#F8FAFC] p-3.5 space-y-2"
                        >
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-[#163E3E] text-xs">{{ cat.category }}</span>
                                <span class="text-[11px] font-semibold text-[#5B6770]">{{ cat.count }} عمليات</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-bold text-rose-700 font-mono">
                                    {{ formatCurrency(cat.total) }} {{ currency_symbol }}
                                </span>
                                <span class="text-xs font-bold text-[#163E3E] font-mono">
                                    {{ stats.total_expenses > 0 ? Math.round((cat.total / stats.total_expenses) * 100) : 0 }}%
                                </span>
                            </div>
                            <!-- شريط النسبة المئوية -->
                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
                                <div
                                    class="h-full bg-[#163E3E] rounded-full"
                                    :style="{ width: `${stats.total_expenses > 0 ? (cat.total / stats.total_expenses) * 100 : 0}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
