<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useLocale } from '@/composables/useLocale';
import {
    Building2,
    Home,
    PieChart,
    Wallet,
    ArrowUpLeft,
} from 'lucide-vue-next';

interface KpiItem {
    label?: string;
    title?: string;
    value: string;
    subtitle: string;
    count?: number;
}

const props = withDefaults(defineProps<{
    kpis: {
        properties?: KpiItem;
        units?: KpiItem;
        occupancy?: KpiItem;
        collected?: KpiItem;
        overdue?: KpiItem;
        expiring?: KpiItem;
        properties_units?: KpiItem;
        collections?: KpiItem;
    };
    disableLinks?: boolean;
}>(), {
    disableLinks: false,
});

const emit = defineEmits<{
    (e: 'selectKpi', key: 'properties' | 'units' | 'occupancy' | 'collected'): void;
}>();

const { isRtl } = useLocale();

const propertiesKpi = computed(() => props.kpis.properties || {
    label: '',
    value: '0',
    subtitle: '',
});

const unitsKpi = computed(() => props.kpis.units || {
    label: '',
    value: '0',
    subtitle: '',
});

const occupancyKpi = computed(() => props.kpis.occupancy || {
    label: '',
    value: '0%',
    subtitle: '',
});

const collectedKpi = computed(() => props.kpis.collected || props.kpis.collections || {
    label: '',
    value: '0',
    subtitle: '',
});

const occupancyNumber = computed(() => {
    const raw = Number.parseInt(String(occupancyKpi.value.value).replace(/[^0-9]/g, '') || '0', 10);
    return Math.min(100, Math.max(0, raw));
});
</script>

<template>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- كارت 1: إجمالي العقارات -->
        <component
            :is="disableLinks ? 'div' : Link"
            :href="disableLinks ? undefined : route('properties.index')"
            :role="disableLinks ? 'button' : undefined"
            :tabindex="disableLinks ? 0 : undefined"
            @click="disableLinks && emit('selectKpi', 'properties')"
            @keydown.enter="disableLinks && emit('selectKpi', 'properties')"
            class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs hover:border-[#163E3E]/50 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 flex flex-col justify-between group cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#163E3E]"
        >
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-[#5B6770] group-hover:text-[#163E3E] transition-colors">
                    {{ $t('dashboard.kpi_properties') }}
                </span>
                <div class="w-10 h-10 rounded-xl bg-[#E8F0F0] text-[#163E3E] flex items-center justify-center group-hover:scale-105 group-hover:bg-[#163E3E] group-hover:text-white transition-all shadow-2xs">
                    <Building2 class="h-4 w-4 stroke-[2.2]" />
                </div>
            </div>

            <div class="mt-4">
                <div class="flex items-baseline justify-between">
                    <div class="text-3xl font-black text-[#163E3E] leading-none">
                        {{ propertiesKpi.value }}
                    </div>
                    <ArrowUpLeft class="w-4 h-4 text-[#8E9CA6] group-hover:text-[#163E3E] transition-all" :class="isRtl ? 'group-hover:-translate-x-0.5 group-hover:-translate-y-0.5' : 'scale-x-[-1] group-hover:translate-x-0.5 group-hover:-translate-y-0.5'" />
                </div>
                <p class="text-xs text-[#5B6770] font-medium mt-2 truncate">
                    {{ propertiesKpi.subtitle }}
                </p>
            </div>
        </component>

        <!-- كارت 2: الوحدات القابلة للتأجير -->
        <component
            :is="disableLinks ? 'div' : Link"
            :href="disableLinks ? undefined : route('properties.index')"
            :role="disableLinks ? 'button' : undefined"
            :tabindex="disableLinks ? 0 : undefined"
            @click="disableLinks && emit('selectKpi', 'units')"
            @keydown.enter="disableLinks && emit('selectKpi', 'units')"
            class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs hover:border-sky-500/50 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 flex flex-col justify-between group cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-600"
        >
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-[#5B6770] group-hover:text-sky-700 transition-colors">
                    {{ $t('dashboard.kpi_units') }}
                </span>
                <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-700 flex items-center justify-center group-hover:scale-105 group-hover:bg-sky-600 group-hover:text-white transition-all shadow-2xs">
                    <Home class="h-4 w-4 stroke-[2.2]" />
                </div>
            </div>

            <div class="mt-4">
                <div class="flex items-baseline justify-between">
                    <div class="text-3xl font-black text-[#163E3E] leading-none">
                        {{ unitsKpi.value }}
                    </div>
                    <ArrowUpLeft class="w-4 h-4 text-[#8E9CA6] group-hover:text-sky-700 transition-all" :class="isRtl ? 'group-hover:-translate-x-0.5 group-hover:-translate-y-0.5' : 'scale-x-[-1] group-hover:translate-x-0.5 group-hover:-translate-y-0.5'" />
                </div>
                <p class="text-xs text-[#5B6770] font-medium mt-2 truncate">
                    {{ unitsKpi.subtitle }}
                </p>
            </div>
        </component>

        <!-- كارت 3: معدل الإشغال -->
        <component
            :is="disableLinks ? 'div' : Link"
            :href="disableLinks ? undefined : route('leases.index')"
            :role="disableLinks ? 'button' : undefined"
            :tabindex="disableLinks ? 0 : undefined"
            @click="disableLinks && emit('selectKpi', 'occupancy')"
            @keydown.enter="disableLinks && emit('selectKpi', 'occupancy')"
            class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs hover:border-emerald-500/50 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 flex flex-col justify-between group cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600"
        >
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-[#5B6770] group-hover:text-emerald-700 transition-colors">
                    {{ $t('dashboard.kpi_occupancy') }}
                </span>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center group-hover:scale-105 group-hover:bg-emerald-600 group-hover:text-white transition-all shadow-2xs">
                    <PieChart class="h-4 w-4 stroke-[2.2]" />
                </div>
            </div>

            <div class="mt-4">
                <div class="flex items-baseline justify-between">
                    <div class="text-3xl font-black text-[#163E3E] leading-none">
                        {{ occupancyKpi.value }}
                    </div>
                    <ArrowUpLeft class="w-4 h-4 text-[#8E9CA6] group-hover:text-emerald-700 transition-all" :class="isRtl ? 'group-hover:-translate-x-0.5 group-hover:-translate-y-0.5' : 'scale-x-[-1] group-hover:translate-x-0.5 group-hover:-translate-y-0.5'" />
                </div>
                <div class="mt-2 w-full bg-[#E8EFF4] h-1.5 rounded-full overflow-hidden">
                    <div
                        class="bg-emerald-600 h-full rounded-full transition-all duration-700"
                        :style="{ width: `${occupancyNumber}%` }"
                    ></div>
                </div>
                <p class="text-xs text-[#5B6770] font-medium mt-2 truncate">
                    {{ occupancyKpi.subtitle }}
                </p>
            </div>
        </component>

        <!-- كارت 4: الإيجارات المحصلة هذا الشهر -->
        <component
            :is="disableLinks ? 'div' : Link"
            :href="disableLinks ? undefined : route('payments.index')"
            :role="disableLinks ? 'button' : undefined"
            :tabindex="disableLinks ? 0 : undefined"
            @click="disableLinks && emit('selectKpi', 'collected')"
            @keydown.enter="disableLinks && emit('selectKpi', 'collected')"
            class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs hover:border-teal-500/50 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 flex flex-col justify-between group cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-600"
        >
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-[#5B6770] group-hover:text-teal-700 transition-colors">
                    {{ $t('dashboard.kpi_collected') }}
                </span>
                <div class="w-10 h-10 rounded-xl bg-teal-50 text-teal-700 flex items-center justify-center group-hover:scale-105 group-hover:bg-teal-600 group-hover:text-white transition-all shadow-2xs">
                    <Wallet class="h-4 w-4 stroke-[2.2]" />
                </div>
            </div>

            <div class="mt-4">
                <div class="flex items-baseline justify-between">
                    <div class="text-2xl sm:text-3xl font-black text-[#163E3E] leading-none">
                        {{ collectedKpi.value }}
                    </div>
                    <ArrowUpLeft class="w-4 h-4 text-[#8E9CA6] group-hover:text-teal-700 transition-all" :class="isRtl ? 'group-hover:-translate-x-0.5 group-hover:-translate-y-0.5' : 'scale-x-[-1] group-hover:translate-x-0.5 group-hover:-translate-y-0.5'" />
                </div>
                <p class="text-xs text-[#5B6770] font-medium mt-2 truncate">
                    {{ collectedKpi.subtitle }}
                </p>
            </div>
        </component>
    </div>
</template>
