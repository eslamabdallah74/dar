<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    BarChart3,
    Calendar,
    Coins,
    CreditCard,
    Download,
    FileSpreadsheet,
    FileText,
    TrendingDown,
    TrendingUp,
    Wallet,
} from 'lucide-vue-next';

interface Stats {
    total_collected: number;
    total_billed: number;
    total_outstanding: number;
    total_expenses: number;
    net_cash_flow: number;
    collection_rate: number;
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

const props = defineProps<{
    stats: Stats;
    expenses_by_category: ExpenseCategory[];
    monthly_breakdown: MonthlyData[];
    date_range: {
        start_date: string;
        end_date: string;
    };
    currency: string;
    currency_symbol: string;
}>();

const startDate = ref(props.date_range.start_date);
const endDate = ref(props.date_range.end_date);
const isFiltering = ref(false);

const applyDateFilter = () => {
    isFiltering.value = true;
    router.get(
        route('reports.index'),
        {
            start_date: startDate.value,
            end_date: endDate.value,
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

const formatCurrency = (amount: number): string => {
    return new Intl.NumberFormat('ar-EG', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(amount);
};
</script>

<template>
    <AppLayout title="التقارير المالية">
        <div class="space-y-6">
            <!-- Header Section -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">التقارير والتحليلات المالية</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        متابعة حية للتدفقات النقدية، نسب التحصيل، والمصروفات التشغيلية للمحفظة العقارية
                    </p>
                </div>

                <!-- Export Options Dropdown / Buttons -->
                <div class="flex flex-wrap items-center gap-2">
                    <a
                        :href="route('reports.collections.export')"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                        title="تحميل ملف إكسل لجدول التحصيل"
                    >
                        <FileSpreadsheet class="h-4 w-4 text-emerald-600" />
                        <span>تصدير التحصيلات</span>
                    </a>

                    <a
                        :href="route('reports.payments.export')"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                        title="تحميل ملف إكسل لجميع سندات القبض"
                    >
                        <Download class="h-4 w-4 text-blue-600" />
                        <span>تصدير المدفوعات</span>
                    </a>

                    <a
                        :href="route('reports.expenses.export')"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                        title="تحميل ملف إكسل لسجل المصروفات التشغيلية"
                    >
                        <Download class="h-4 w-4 text-rose-600" />
                        <span>تصدير المصروفات</span>
                    </a>

                    <a
                        :href="route('reports.leases.export')"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                        title="تحميل ملف إكسل لبيانات العقود التأجيرية"
                    >
                        <FileText class="h-4 w-4 text-purple-600" />
                        <span>تصدير العقود</span>
                    </a>
                </div>
            </div>

            <!-- Date Range Filter Bar -->
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <form @submit.prevent="applyDateFilter" class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-2">
                        <Calendar class="h-4 w-4 text-gray-500" />
                        <span class="text-sm font-medium text-gray-700">الفترة الزمنية:</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="text-xs text-gray-500">من:</label>
                        <input
                            v-model="startDate"
                            type="date"
                            class="rounded-lg border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="text-xs text-gray-500">إلى:</label>
                        <input
                            v-model="endDate"
                            type="date"
                            class="rounded-lg border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="isFiltering"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:opacity-50"
                    >
                        {{ isFiltering ? 'جاري التحديث...' : 'تطبيق الفلترة' }}
                    </button>
                </form>
            </div>

            <!-- KPI Cards Grid -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <!-- Total Collected -->
                <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-emerald-800">إجمالي المحصل الفعلي</span>
                        <div class="rounded-lg bg-emerald-100 p-2 text-emerald-600">
                            <TrendingUp class="h-5 w-5" />
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-2xl font-bold text-gray-900">
                            {{ formatCurrency(stats.total_collected) }}
                            <span class="text-xs font-normal text-gray-500">{{ currency_symbol }}</span>
                        </div>
                        <p class="mt-1 text-xs text-emerald-600 font-medium">سندات قبض معتمدة</p>
                    </div>
                </div>

                <!-- Total Billed -->
                <div class="rounded-xl border border-blue-100 bg-blue-50/50 p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-blue-800">المستحقات المفوترة</span>
                        <div class="rounded-lg bg-blue-100 p-2 text-blue-600">
                            <CreditCard class="h-5 w-5" />
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-2xl font-bold text-gray-900">
                            {{ formatCurrency(stats.total_billed) }}
                            <span class="text-xs font-normal text-gray-500">{{ currency_symbol }}</span>
                        </div>
                        <p class="mt-1 text-xs text-blue-600 font-medium">مستحقات عقود الفترة</p>
                    </div>
                </div>

                <!-- Total Outstanding -->
                <div class="rounded-xl border border-amber-100 bg-amber-50/50 p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-amber-800">المتأخرات غير المحصلة</span>
                        <div class="rounded-lg bg-amber-100 p-2 text-amber-600">
                            <Wallet class="h-5 w-5" />
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-2xl font-bold text-gray-900">
                            {{ formatCurrency(stats.total_outstanding) }}
                            <span class="text-xs font-normal text-gray-500">{{ currency_symbol }}</span>
                        </div>
                        <p class="mt-1 text-xs text-amber-600 font-medium">متبقي قيد السداد</p>
                    </div>
                </div>

                <!-- Total Expenses -->
                <div class="rounded-xl border border-rose-100 bg-rose-50/50 p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-rose-800">المصروفات التشغيلية</span>
                        <div class="rounded-lg bg-rose-100 p-2 text-rose-600">
                            <Coins class="h-5 w-5" />
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-2xl font-bold text-gray-900">
                            {{ formatCurrency(stats.total_expenses) }}
                            <span class="text-xs font-normal text-gray-500">{{ currency_symbol }}</span>
                        </div>
                        <p class="mt-1 text-xs text-rose-600 font-medium">صيانة وتشغيل وفواتير</p>
                    </div>
                </div>

                <!-- Net Cash Flow -->
                <div
                    class="rounded-xl border p-4 shadow-sm"
                    :class="[
                        stats.net_cash_flow >= 0
                            ? 'border-gray-200 bg-white'
                            : 'border-red-200 bg-red-50/40'
                    ]"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-gray-700">صافي التدفق النقدي</span>
                        <div
                            class="rounded-lg p-2"
                            :class="stats.net_cash_flow >= 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                        >
                            <TrendingUp v-if="stats.net_cash_flow >= 0" class="h-5 w-5" />
                            <TrendingDown v-else class="h-5 w-5" />
                        </div>
                    </div>
                    <div class="mt-3">
                        <div
                            class="text-2xl font-bold"
                            :class="stats.net_cash_flow >= 0 ? 'text-emerald-600' : 'text-red-600'"
                        >
                            {{ formatCurrency(stats.net_cash_flow) }}
                            <span class="text-xs font-normal text-gray-500">{{ currency_symbol }}</span>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-xs text-gray-500">
                            <span>نسبة التحصيل:</span>
                            <span class="font-bold text-gray-900">{{ stats.collection_rate }}%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Two Columns: Monthly Breakdown & Expense Distribution -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Monthly Breakdown (2 cols) -->
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm lg:col-span-2">
                    <div class="flex items-center justify-between border-b border-gray-200 pb-4">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">التدفقات النقدية الشهرية</h2>
                            <p class="text-xs text-gray-500">مقارنة الإيرادات المحصلة بالمصروفات شهرياً خلال العام</p>
                        </div>
                        <BarChart3 class="h-5 w-5 text-gray-400" />
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-right text-sm">
                            <thead class="border-b border-gray-200 bg-gray-50 text-xs font-medium text-gray-500">
                                <tr>
                                    <th class="py-3 px-4">الشهر</th>
                                    <th class="py-3 px-4 text-emerald-700">المحصل</th>
                                    <th class="py-3 px-4 text-rose-700">المصروف</th>
                                    <th class="py-3 px-4">صافي العائد</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr
                                    v-for="item in monthly_breakdown"
                                    :key="item.month"
                                    class="hover:bg-gray-50/80"
                                >
                                    <td class="py-3 px-4 font-medium text-gray-900">
                                        {{ item.month_name }}
                                    </td>
                                    <td class="py-3 px-4 text-emerald-600 font-semibold">
                                        {{ formatCurrency(item.collected) }} {{ currency_symbol }}
                                    </td>
                                    <td class="py-3 px-4 text-rose-600">
                                        {{ formatCurrency(item.expenses) }} {{ currency_symbol }}
                                    </td>
                                    <td class="py-3 px-4 font-bold" :class="item.net >= 0 ? 'text-emerald-700' : 'text-red-600'">
                                        {{ formatCurrency(item.net) }} {{ currency_symbol }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Operating Expenses by Category (1 col) -->
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-200 pb-4">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">توزيع المصروفات</h2>
                            <p class="text-xs text-gray-500">المصروفات حسب التصنيف للفترة</p>
                        </div>
                        <Coins class="h-5 w-5 text-gray-400" />
                    </div>

                    <div v-if="expenses_by_category.length === 0" class="mt-8 text-center text-sm text-gray-500">
                        لا توجد مصروفات مسجلة خلال الفترة المحددة.
                    </div>

                    <div v-else class="mt-4 space-y-4">
                        <div
                            v-for="cat in expenses_by_category"
                            :key="cat.category"
                            class="rounded-lg border border-gray-100 bg-gray-50/50 p-3"
                        >
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-gray-900 text-sm">{{ cat.category }}</span>
                                <span class="text-xs font-semibold text-gray-500">{{ cat.count }} عمليات</span>
                            </div>
                            <div class="mt-2 flex items-center justify-between">
                                <span class="text-base font-bold text-rose-600">
                                    {{ formatCurrency(cat.total) }} {{ currency_symbol }}
                                </span>
                                <span class="text-xs text-gray-500">
                                    {{ stats.total_expenses > 0 ? Math.round((cat.total / stats.total_expenses) * 100) : 0 }}%
                                </span>
                            </div>
                            <!-- Simple Progress Bar -->
                            <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-200">
                                <div
                                    class="h-full bg-rose-500 rounded-full"
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
