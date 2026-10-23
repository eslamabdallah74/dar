<script setup lang="ts">
interface KpiItem {
    title: string;
    value: string;
    subtitle: string;
}

defineProps<{
    kpis: {
        maintenance_open: KpiItem;
        maintenance_expenses: KpiItem;
        operating_expenses: KpiItem;
    };
}>();
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- كارت 1: طلبات صيانة مفتوحة -->
        <div class="h-[139px] rounded-2xl border border-[#D6E0E7] bg-white p-6 shadow-2xs flex flex-col justify-between hover:border-[#B6C5D0] transition">
            <span class="text-xs font-medium text-[#5B6770]">{{ kpis.maintenance_open?.title || 'طلبات صيانة مفتوحة' }}</span>
            <span class="text-2xl font-bold font-mono text-[#0F2747] tracking-tight leading-none" dir="ltr">
                {{ kpis.maintenance_open?.value || '4' }}
            </span>
            <span class="text-xs text-[#5B6770] font-medium">{{ kpis.maintenance_open?.subtitle || '2 قيد التنفيذ' }}</span>
        </div>

        <!-- كارت 2: مصروفات الصيانة -->
        <div class="h-[139px] rounded-2xl border border-[#D6E0E7] bg-white p-6 shadow-2xs flex flex-col justify-between hover:border-[#B6C5D0] transition">
            <span class="text-xs font-medium text-[#5B6770]">{{ kpis.maintenance_expenses?.title || 'مصروفات الصيانة' }}</span>
            <span class="text-[22px] font-bold font-mono text-[#0F2747] tracking-tight leading-none" dir="ltr">
                {{ kpis.maintenance_expenses?.value || '1,200 جنيه' }}
            </span>
            <span class="text-xs text-[#5B6770] font-medium">{{ kpis.maintenance_expenses?.subtitle || 'أكتوبر 2026' }}</span>
        </div>

        <!-- كارت 3: مصروفات التشغيل -->
        <div class="h-[139px] rounded-2xl border border-[#D6E0E7] bg-white p-6 shadow-2xs flex flex-col justify-between hover:border-[#B6C5D0] transition">
            <span class="text-xs font-medium text-[#5B6770]">{{ kpis.operating_expenses?.title || 'مصروفات التشغيل' }}</span>
            <span class="text-[22px] font-bold font-mono text-[#0F2747] tracking-tight leading-none" dir="ltr">
                {{ kpis.operating_expenses?.value || '2,600 جنيه' }}
            </span>
            <span class="text-xs text-[#5B6770] font-medium">{{ kpis.operating_expenses?.subtitle || 'مرافق ونظافة وخدمات' }}</span>
        </div>
    </div>
</template>
