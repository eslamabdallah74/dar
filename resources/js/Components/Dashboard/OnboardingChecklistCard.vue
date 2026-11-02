<script setup lang="ts">
defineProps<{
    title?: string;
    steps?: string[];
    percentage?: number;
    actionLabel?: string;
}>();

const emit = defineEmits<{
    (e: 'action'): void;
}>();
</script>

<template>
    <div class="rounded-2xl border border-[#D6E0E7] bg-white p-6 shadow-2xs flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <!-- خطوات الإعداد والتقدم -->
        <div class="space-y-2 flex-1">
            <h3 class="text-sm font-bold text-[#163E3E]">
                {{ title || 'حسابك جاهز بالكامل • اكتملت 4 من 4 خطوات' }}
            </h3>
            <p class="text-xs text-[#5B6770] font-medium flex flex-wrap items-center gap-x-4 gap-y-1">
                <span class="inline-flex items-center gap-1 text-emerald-700 font-semibold">✓ أضفت عقارك</span>
                <span class="inline-flex items-center gap-1 text-emerald-700 font-semibold">✓ جهزت الوحدات</span>
                <span class="inline-flex items-center gap-1 text-emerald-700 font-semibold">✓ أضفت المستأجر</span>
                <span class="inline-flex items-center gap-1 text-emerald-700 font-semibold">✓ أول عقد</span>
            </p>
            <!-- شريط التقدم 4px -->
            <div class="w-full h-1 bg-[#E8EFF4] rounded-full overflow-hidden mt-2">
                <div
                    class="h-full bg-[#163E3E] rounded-full transition-all duration-700"
                    :style="{ width: `${percentage || 100}%` }"
                ></div>
            </div>
        </div>

        <!-- زر الإجراء الثانوي المطابق لفيجما (Button secondary default) -->
        <button
            @click="emit('action')"
            type="button"
            class="h-11 px-5 rounded-xl border border-[#D6E0E7] bg-white hover:bg-[#F8F4EA] hover:border-[#B6C5D0] text-sm font-semibold text-[#163E3E] transition shadow-2xs shrink-0"
        >
            {{ actionLabel || 'عرض العقارات' }}
        </button>
    </div>
</template>
