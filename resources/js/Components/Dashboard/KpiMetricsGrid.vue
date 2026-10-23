<script setup lang="ts">
import { computed } from 'vue';
import {
    AlertCircle,
    ArrowUpRight,
    CheckCircle2,
    Clock,
    DollarSign,
    Home,
    TrendingUp,
} from 'lucide-vue-next';

const props = defineProps<{
    metrics: {
        total_units: number;
        occupied_units: number;
        vacant_units: number;
        occupancy_rate: number;
        monthly_target: number;
        monthly_collected: number;
        collection_percentage: number;
        total_overdue_balance: number;
        overdue_renters_count: number;
        expiring_leases_count: number;
    };
    currency?: string;
}>();

// تنسيق المبالغ المالية
function formatCurrency(val: number): string {
    return new Intl.NumberFormat('en-US').format(Math.round(val || 0));
}
</script>

<template>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- البطاقة 1: الإيجارات المحصلة هذا الشهر -->
        <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-xs hover:border-[#0E6E7E]/50 transition-all flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-[#5B6770]">المحصل هذا الشهر</span>
                <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center border border-emerald-100">
                    <CheckCircle2 class="h-4 w-4" />
                </div>
            </div>

            <div class="mt-3">
                <div class="flex items-baseline gap-1.5">
                    <span class="text-2xl font-black text-[#0F2747] font-mono tracking-tight">
                        {{ formatCurrency(metrics.monthly_collected) }}
                    </span>
                    <span class="text-xs font-bold text-[#5B6770]">{{ currency || 'ج.م' }}</span>
                </div>

                <div class="mt-2.5 flex items-center justify-between text-xs font-semibold">
                    <span class="text-[#5B6770]">
                        من أصل <strong class="text-[#0F2747] font-mono">{{ formatCurrency(metrics.monthly_target) }}</strong>
                    </span>
                    <span class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 text-[11px] font-bold border border-emerald-200">
                        {{ metrics.collection_percentage }}%
                    </span>
                </div>

                <!-- شريط تقدم مصغر -->
                <div class="w-full h-1.5 bg-[#E8EFF4] rounded-full mt-2 overflow-hidden">
                    <div
                        class="h-full bg-emerald-500 rounded-full transition-all duration-500"
                        :style="{ width: `${Math.min(100, metrics.collection_percentage)}%` }"
                    ></div>
                </div>
            </div>
        </div>

        <!-- البطاقة 2: رصيد المتأخرات غير المحصلة -->
        <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-xs hover:border-rose-300 transition-all flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-[#5B6770]">المتأخرات غير المحصلة</span>
                <div class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center border border-rose-100">
                    <AlertCircle class="h-4 w-4" />
                </div>
            </div>

            <div class="mt-3">
                <div class="flex items-baseline gap-1.5">
                    <span class="text-2xl font-black text-rose-600 font-mono tracking-tight">
                        {{ formatCurrency(metrics.total_overdue_balance) }}
                    </span>
                    <span class="text-xs font-bold text-rose-500">{{ currency || 'ج.م' }}</span>
                </div>

                <div class="mt-2.5 flex items-center justify-between text-xs font-semibold">
                    <span class="text-[#5B6770]">
                        المستأجرين المتأخرين:
                    </span>
                    <span class="px-2 py-0.5 rounded-md text-[11px] font-bold" :class="metrics.overdue_renters_count > 0 ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-zinc-100 text-[#5B6770]'">
                        {{ metrics.overdue_renters_count }} مستأجر
                    </span>
                </div>

                <!-- شريط تأكيد الحالة -->
                <div class="w-full h-1.5 bg-[#E8EFF4] rounded-full mt-2 overflow-hidden">
                    <div
                        class="h-full bg-rose-500 rounded-full transition-all duration-500"
                        :style="{ width: metrics.total_overdue_balance > 0 ? '100%' : '0%' }"
                    ></div>
                </div>
            </div>
        </div>

        <!-- البطاقة 3: نسبة الإشغال الحالية -->
        <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-xs hover:border-[#0E6E7E]/50 transition-all flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-[#5B6770]">نسبة الإشغال</span>
                <div class="w-9 h-9 rounded-xl bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center border border-[#0E6E7E]/20">
                    <Home class="h-4 w-4" />
                </div>
            </div>

            <div class="mt-3">
                <div class="flex items-baseline gap-1.5">
                    <span class="text-2xl font-black text-[#0F2747] font-mono tracking-tight">
                        {{ metrics.occupancy_rate }}%
                    </span>
                    <span class="text-xs font-bold text-emerald-600 inline-flex items-center">
                        <ArrowUpRight class="h-3.5 w-3.5" />
                        سليم
                    </span>
                </div>

                <div class="mt-2.5 flex items-center justify-between text-xs font-semibold">
                    <span class="text-[#5B6770]">
                        الوحدات المؤجرة:
                    </span>
                    <span class="text-[#0F2747] font-mono font-bold">
                        {{ metrics.occupied_units }} من أصل {{ metrics.total_units }}
                    </span>
                </div>

                <!-- شريط تقدم الإشغال -->
                <div class="w-full h-1.5 bg-[#E8EFF4] rounded-full mt-2 overflow-hidden">
                    <div
                        class="h-full bg-[#0E6E7E] rounded-full transition-all duration-500"
                        :style="{ width: `${Math.min(100, metrics.occupancy_rate)}%` }"
                    ></div>
                </div>
            </div>
        </div>

        <!-- البطاقة 4: عقود تنتهي قريباً -->
        <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-xs hover:border-amber-300 transition-all flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-[#5B6770]">تنتهي خلال 30 يوم</span>
                <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center border border-amber-100">
                    <Clock class="h-4 w-4" />
                </div>
            </div>

            <div class="mt-3">
                <div class="flex items-baseline gap-1.5">
                    <span class="text-2xl font-black text-[#0F2747] font-mono tracking-tight">
                        {{ metrics.expiring_leases_count }}
                    </span>
                    <span class="text-xs font-bold text-[#5B6770]">عقود نشطة</span>
                </div>

                <div class="mt-2.5 flex items-center justify-between text-xs font-semibold">
                    <span class="text-[#5B6770]">
                        حالة التجديد:
                    </span>
                    <span
                        class="px-2 py-0.5 rounded-md text-[11px] font-bold"
                        :class="metrics.expiring_leases_count > 0 ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'"
                    >
                        {{ metrics.expiring_leases_count > 0 ? 'تتطلب تواصل' : 'لا يوجد انتهاء قريب' }}
                    </span>
                </div>

                <!-- شريط التنبيه -->
                <div class="w-full h-1.5 bg-[#E8EFF4] rounded-full mt-2 overflow-hidden">
                    <div
                        class="h-full bg-amber-500 rounded-full transition-all duration-500"
                        :style="{ width: metrics.expiring_leases_count > 0 ? '60%' : '0%' }"
                    ></div>
                </div>
            </div>
        </div>
    </div>
</template>
