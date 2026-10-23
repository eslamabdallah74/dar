<script setup lang="ts">
interface KpiItem {
    title: string;
    value: string;
    subtitle: string;
}

defineProps<{
    kpis: {
        properties_units: KpiItem;
        occupancy: KpiItem;
        collections: KpiItem;
        expiring: KpiItem;
    };
}>();
</script>

<template>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- كارت 1: العقارات / الوحدات -->
        <div class="h-[139px] rounded-2xl border border-[#D6E0E7] bg-white p-6 shadow-2xs flex flex-col justify-between hover:border-[#B6C5D0] transition">
            <span class="text-xs font-medium text-[#5B6770]">{{ kpis.properties_units?.title || 'العقارات / الوحدات' }}</span>
            <span class="text-2xl font-bold font-mono text-[#0F2747] tracking-tight leading-none" dir="ltr">
                {{ kpis.properties_units?.value || '3 / 24' }}
            </span>
            <span class="text-xs text-[#5B6770] font-medium">{{ kpis.properties_units?.subtitle || '3 مبانٍ في محفظتك' }}</span>
        </div>

        <!-- كارت 2: الإشغال -->
        <div class="h-[139px] rounded-2xl border border-[#D6E0E7] bg-white p-6 shadow-2xs flex flex-col justify-between hover:border-[#B6C5D0] transition">
            <span class="text-xs font-medium text-[#5B6770]">{{ kpis.occupancy?.title || 'الإشغال' }}</span>
            <span class="text-2xl font-bold font-mono text-[#0F2747] tracking-tight leading-none" dir="ltr">
                {{ kpis.occupancy?.value || '75%' }}
            </span>
            <span class="text-xs text-[#5B6770] font-medium">{{ kpis.occupancy?.subtitle || '18 مشغولة · 6 شاغرة' }}</span>
        </div>

        <!-- كارت 3: المحصّل / المستحق -->
        <div class="h-[139px] rounded-2xl border border-[#D6E0E7] bg-white p-6 shadow-2xs flex flex-col justify-between hover:border-[#B6C5D0] transition">
            <span class="text-xs font-medium text-[#5B6770]">{{ kpis.collections?.title || 'المحصّل / المستحق' }}</span>
            <span class="text-[22px] font-bold font-mono text-[#0F2747] tracking-tight leading-none" dir="ltr">
                {{ kpis.collections?.value || '37,000 / 42,000' }}
            </span>
            <span class="text-xs text-[#5B6770] font-medium">{{ kpis.collections?.subtitle || 'جنيه • أكتوبر 2026' }}</span>
        </div>

        <!-- كارت 4: عقود تنتهي قريبًا -->
        <div class="h-[139px] rounded-2xl border border-[#D6E0E7] bg-white p-6 shadow-2xs flex flex-col justify-between hover:border-[#B6C5D0] transition">
            <span class="text-xs font-medium text-[#5B6770]">{{ kpis.expiring?.title || 'عقود تنتهي قريبًا' }}</span>
            <span class="text-2xl font-bold font-mono text-[#0F2747] tracking-tight leading-none" dir="ltr">
                {{ kpis.expiring?.value || '3' }}
            </span>
            <span class="text-xs text-[#5B6770] font-medium">{{ kpis.expiring?.subtitle || 'خلال 30 يومًا القادمة' }}</span>
        </div>
    </div>
</template>
