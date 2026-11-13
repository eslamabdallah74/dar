<script setup lang="ts">
import { computed } from 'vue';

interface OccupancyData {
    total: number;
    occupied: number;
    vacant: number;
    maintenance: number;
    occupancy_rate: number;
}

const props = defineProps<{
    summary?: OccupancyData;
    subtitle?: string;
}>();

const totalUnits = computed(() => props.summary?.total ?? 0);
const occupiedUnits = computed(() => props.summary?.occupied ?? 0);
const vacantUnits = computed(() => props.summary?.vacant ?? 0);
const maintenanceUnits = computed(() => props.summary?.maintenance ?? 0);
const occupancyRate = computed(() => props.summary?.occupancy_rate ?? 0);

const occupiedPercent = computed(() => {
    if (totalUnits.value <= 0) return 0;
    return Math.round((occupiedUnits.value / totalUnits.value) * 100);
});

const vacantPercent = computed(() => {
    if (totalUnits.value <= 0) return 0;
    return Math.round((vacantUnits.value / totalUnits.value) * 100);
});

const maintenancePercent = computed(() => {
    if (totalUnits.value <= 0) return 0;
    return Math.min(100, Math.round((maintenanceUnits.value / totalUnits.value) * 100));
});
</script>

<template>
    <div class="rounded-2xl border border-[#D6E0E7] bg-white p-6 shadow-2xs flex flex-col justify-between h-full">
        <div>
            <!-- الرأس -->
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-[#163E3E]">{{ $t('dashboard.occupancy_summary_title') }}</h3>
                    <p class="text-xs text-[#5B6770] font-medium mt-0.5">
                        {{ subtitle || $t('dashboard.occupancy_summary_desc') }}
                    </p>
                </div>
                <div class="text-start">
                    <span class="text-2xl font-black text-[#163E3E]">
                        {{ occupancyRate }}%
                    </span>
                    <span class="text-[11px] text-[#5B6770] block font-medium">{{ $t('dashboard.occupancy_rate_label') }}</span>
                </div>
            </div>

            <!-- شريط التوزيع الملون التراكمي (Stacked distribution bar) -->
            <div class="mt-5 space-y-2">
                <div class="h-3 w-full bg-[#E8EFF4] rounded-full overflow-hidden flex">
                    <!-- مشغولة -->
                    <div
                        class="bg-emerald-600 transition-all duration-700"
                        :style="{ width: `${occupiedPercent}%` }"
                        :title="`${$t('dashboard.occupied')}: ${occupiedUnits} (${occupiedPercent}%)`"
                    ></div>
                    <!-- شاغرة -->
                    <div
                        class="bg-amber-400 transition-all duration-700"
                        :style="{ width: `${vacantPercent}%` }"
                        :title="`${$t('dashboard.vacant')}: ${vacantUnits} (${vacantPercent}%)`"
                    ></div>
                    <!-- تحت الصيانة -->
                    <div
                        v-if="maintenanceUnits > 0"
                        class="bg-sky-500 transition-all duration-700"
                        :style="{ width: `${maintenancePercent}%` }"
                        :title="`${$t('dashboard.maintenance')}: ${maintenanceUnits}`"
                    ></div>
                </div>

                <div class="text-[11px] text-[#5B6770] font-medium text-start">
                    {{ $t('dashboard.total_units_registered', { total: totalUnits }) }}
                </div>
            </div>

            <!-- تفاصيل الحالات الثلاث -->
            <div class="mt-6 grid grid-cols-3 gap-2 py-3 border-y border-[#E8EFF4]">
                <!-- مشغولة -->
                <div class="text-center p-2 rounded-xl bg-emerald-50/50">
                    <div class="flex items-center justify-center gap-1.5 text-xs font-bold text-emerald-800">
                        <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                        <span>{{ $t('dashboard.occupied') }}</span>
                    </div>
                    <div class="text-lg font-black font-mono text-emerald-900 mt-1">
                        {{ occupiedUnits }}
                    </div>
                    <span class="text-[10px] text-emerald-700 font-medium">{{ occupiedPercent }}%</span>
                </div>

                <!-- شاغرة -->
                <div class="text-center p-2 rounded-xl bg-amber-50/50">
                    <div class="flex items-center justify-center gap-1.5 text-xs font-bold text-amber-800">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        <span>{{ $t('dashboard.vacant') }}</span>
                    </div>
                    <div class="text-lg font-black font-mono text-amber-900 mt-1">
                        {{ vacantUnits }}
                    </div>
                    <span class="text-[10px] text-amber-700 font-medium">{{ vacantPercent }}%</span>
                </div>

                <!-- صيانة -->
                <div class="text-center p-2 rounded-xl bg-sky-50/50">
                    <div class="flex items-center justify-center gap-1.5 text-xs font-bold text-sky-800">
                        <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                        <span>{{ $t('dashboard.maintenance') }}</span>
                    </div>
                    <div class="text-lg font-black font-mono text-sky-900 mt-1">
                        {{ maintenanceUnits }}
                    </div>
                    <span class="text-[10px] text-sky-700 font-medium">{{ maintenanceUnits }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

