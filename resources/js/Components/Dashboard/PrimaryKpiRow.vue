<script setup lang="ts">
import { computed } from 'vue';
import { Building2, Home, PieChart, Wallet, AlertCircle } from 'lucide-vue-next';

interface KpiItem {
    label?: string;
    title?: string;
    value: string;
    subtitle: string;
    count?: number;
}

const props = defineProps<{
    kpis: {
        properties?: KpiItem;
        units?: KpiItem;
        occupancy?: KpiItem;
        collected?: KpiItem;
        overdue?: KpiItem;
        expiring?: KpiItem;
        // Legacy fallback support
        properties_units?: KpiItem;
        collections?: KpiItem;
    };
}>();

const propertiesKpi = computed(() => props.kpis.properties || {
    label: 'إجمالي العقارات',
    value: '0',
    subtitle: 'لا توجد عقارات مسجلة',
});

const unitsKpi = computed(() => props.kpis.units || {
    label: 'الوحدات القابلة للتأجير',
    value: '0',
    subtitle: 'لا توجد وحدات نشطة',
});

const occupancyKpi = computed(() => props.kpis.occupancy || {
    label: 'معدل الإشغال',
    value: '0%',
    subtitle: '0 مشغولة · 0 شاغرة',
});

const collectedKpi = computed(() => props.kpis.collected || props.kpis.collections || {
    label: 'الإيجارات المحصلة هذا الشهر',
    value: '0 ج.م',
    subtitle: 'من إجمالي 0 ج.م مستحقة',
});

const overdueKpi = computed(() => props.kpis.overdue);
</script>

<template>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- كارت 1: إجمالي العقارات -->
        <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs hover:border-[#B6C5D0] transition flex flex-col justify-between group">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-[#5B6770]">
                    {{ propertiesKpi.label || propertiesKpi.title }}
                </span>
                <div class="w-9 h-9 rounded-xl bg-[#E8F3F5] text-[#0E6E7E] flex items-center justify-center group-hover:scale-105 transition-transform">
                    <Building2 class="h-4 w-4 stroke-[2.2]" />
                </div>
            </div>
            <div class="mt-3">
                <div class="text-2xl font-bold font-mono text-[#0F2747] tracking-tight leading-none" dir="ltr">
                    {{ propertiesKpi.value }}
                </div>
                <p class="text-xs text-[#5B6770] font-medium mt-1.5 truncate">
                    {{ propertiesKpi.subtitle }}
                </p>
            </div>
        </div>

        <!-- كارت 2: الوحدات القابلة للتأجير -->
        <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs hover:border-[#B6C5D0] transition flex flex-col justify-between group">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-[#5B6770]">
                    {{ unitsKpi.label || unitsKpi.title }}
                </span>
                <div class="w-9 h-9 rounded-xl bg-sky-50 text-sky-700 flex items-center justify-center group-hover:scale-105 transition-transform">
                    <Home class="h-4 w-4 stroke-[2.2]" />
                </div>
            </div>
            <div class="mt-3">
                <div class="text-2xl font-bold font-mono text-[#0F2747] tracking-tight leading-none" dir="ltr">
                    {{ unitsKpi.value }}
                </div>
                <p class="text-xs text-[#5B6770] font-medium mt-1.5 truncate">
                    {{ unitsKpi.subtitle }}
                </p>
            </div>
        </div>

        <!-- كارت 3: معدل الإشغال -->
        <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs hover:border-[#B6C5D0] transition flex flex-col justify-between group">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-[#5B6770]">
                    {{ occupancyKpi.label || occupancyKpi.title }}
                </span>
                <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center group-hover:scale-105 transition-transform">
                    <PieChart class="h-4 w-4 stroke-[2.2]" />
                </div>
            </div>
            <div class="mt-3">
                <div class="text-2xl font-bold font-mono text-[#0F2747] tracking-tight leading-none" dir="ltr">
                    {{ occupancyKpi.value }}
                </div>
                <p class="text-xs text-[#5B6770] font-medium mt-1.5 truncate">
                    {{ occupancyKpi.subtitle }}
                </p>
            </div>
        </div>

        <!-- كارت 4: الإيجارات المحصلة هذا الشهر -->
        <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs hover:border-[#B6C5D0] transition flex flex-col justify-between group">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-[#5B6770]">
                    {{ collectedKpi.label || collectedKpi.title }}
                </span>
                <div class="w-9 h-9 rounded-xl bg-teal-50 text-teal-700 flex items-center justify-center group-hover:scale-105 transition-transform">
                    <Wallet class="h-4 w-4 stroke-[2.2]" />
                </div>
            </div>
            <div class="mt-3">
                <div class="text-2xl font-bold font-mono text-[#0F2747] tracking-tight leading-none" dir="ltr">
                    {{ collectedKpi.value }}
                </div>
                <p class="text-xs text-[#5B6770] font-medium mt-1.5 truncate">
                    {{ collectedKpi.subtitle }}
                </p>
            </div>
        </div>
    </div>
</template>
