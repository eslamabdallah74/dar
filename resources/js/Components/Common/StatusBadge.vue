<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        status: string;
        label?: string;
        size?: 'sm' | 'md';
    }>(),
    {
        size: 'md',
    }
);

interface StatusConfig {
    dotColor: string;
    bgColor: string;
    defaultLabel: string;
}

const statusMap: Record<string, StatusConfig> = {
    // Green / Success tints (Always #163E3E text per AA contrast rule)
    occupied: { dotColor: 'bg-emerald-500', bgColor: 'bg-[#E8F0F0]', defaultLabel: 'مؤجرة' },
    active: { dotColor: 'bg-emerald-500', bgColor: 'bg-[#DEF7EC]', defaultLabel: 'سارٍ' },
    paid: { dotColor: 'bg-emerald-500', bgColor: 'bg-[#DEF7EC]', defaultLabel: 'مسدد بالكامل' },
    closed: { dotColor: 'bg-emerald-500', bgColor: 'bg-[#DEF7EC]', defaultLabel: 'مكتمل' },
    regular: { dotColor: 'bg-emerald-500', bgColor: 'bg-[#DEF7EC]', defaultLabel: 'منتظم' },
    delivered: { dotColor: 'bg-emerald-500', bgColor: 'bg-[#DEF7EC]', defaultLabel: 'تم التسليم' },

    // Amber / Pending / Attention tints
    expiring: { dotColor: 'bg-amber-500', bgColor: 'bg-[#FEF3C7]', defaultLabel: 'ينتهي قريباً' },
    past_due: { dotColor: 'bg-amber-600', bgColor: 'bg-[#FEF3C7]', defaultLabel: 'انتهى موعده' },
    pending: { dotColor: 'bg-amber-500', bgColor: 'bg-[#FEF3C7]', defaultLabel: 'مستحق قريباً' },
    in_progress: { dotColor: 'bg-amber-500', bgColor: 'bg-[#FEF3C7]', defaultLabel: 'قيد المعالجة' },
    partial: { dotColor: 'bg-amber-500', bgColor: 'bg-[#FEF3C7]', defaultLabel: 'سداد جزئي' },

    // Rose / Danger / Overdue tints
    overdue: { dotColor: 'bg-rose-500', bgColor: 'bg-[#FEE2E2]', defaultLabel: 'متأخر' },
    maintenance: { dotColor: 'bg-rose-500', bgColor: 'bg-[#FEE2E2]', defaultLabel: 'تحت الصيانة' },
    open: { dotColor: 'bg-rose-500', bgColor: 'bg-[#FEE2E2]', defaultLabel: 'جديد' },
    failed: { dotColor: 'bg-rose-500', bgColor: 'bg-[#FEE2E2]', defaultLabel: 'فشل' },
    terminated: { dotColor: 'bg-rose-500', bgColor: 'bg-[#FEE2E2]', defaultLabel: 'ملغي' },

    // Slate / Neutral tints
    vacant: { dotColor: 'bg-slate-400', bgColor: 'bg-[#F1F5F9]', defaultLabel: 'شاغرة' },
    draft: { dotColor: 'bg-slate-400', bgColor: 'bg-[#F1F5F9]', defaultLabel: 'مسودة' },
    inactive: { dotColor: 'bg-slate-400', bgColor: 'bg-[#F1F5F9]', defaultLabel: 'غير نشط' },
    sent: { dotColor: 'bg-sky-500', bgColor: 'bg-[#E0F2FE]', defaultLabel: 'تم الإرسال' },
};

const config = computed<StatusConfig>(() => {
    return statusMap[props.status.toLowerCase()] ?? {
        dotColor: 'bg-slate-400',
        bgColor: 'bg-[#F1F5F9]',
        defaultLabel: props.status,
    };
});

const displayLabel = computed(() => props.label || config.value.defaultLabel);
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full font-medium text-[#163E3E] select-none transition-colors border border-black/5"
        :class="[
            config.bgColor,
            size === 'sm' ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-1 text-xs'
        ]"
    >
        <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="config.dotColor"></span>
        <span class="truncate">{{ displayLabel }}</span>
    </span>
</template>

