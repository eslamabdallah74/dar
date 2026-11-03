<script setup lang="ts">
interface Props {
    currentPage: number;
    lastPage: number;
    from: number | null;
    to: number | null;
    total: number;
    loading: boolean;
}

defineProps<Props>();

const emit = defineEmits<{
    changePage: [page: number];
}>();
</script>

<template>
    <div class="flex flex-col gap-3 border-t border-[#E8EFF4] px-4 py-3 text-xs sm:flex-row sm:items-center sm:justify-between">
        <p class="text-[#5B6770]" aria-live="polite">
            <span v-if="loading">جارٍ تحميل النتائج...</span>
            <span v-else-if="total === 0">لا توجد نتائج مسجلة</span>
            <span v-else>عرض {{ from ?? 1 }}–{{ to ?? total }} من {{ total }}</span>
        </p>

        <nav v-if="lastPage > 1" class="flex items-center gap-2" aria-label="التنقل بين صفحات النتائج">
            <button
                type="button"
                :disabled="loading || currentPage <= 1"
                class="rounded-lg border border-[#D6E0E7] bg-white px-3 py-2 font-semibold text-[#163E3E] transition hover:bg-[#F4F7F7] disabled:cursor-not-allowed disabled:opacity-50"
                @click="emit('changePage', currentPage - 1)"
            >
                السابق
            </button>
            <span class="min-w-20 text-center font-medium text-[#5B6770]">
                {{ currentPage }} / {{ lastPage }}
            </span>
            <button
                type="button"
                :disabled="loading || currentPage >= lastPage"
                class="rounded-lg border border-[#D6E0E7] bg-white px-3 py-2 font-semibold text-[#163E3E] transition hover:bg-[#F4F7F7] disabled:cursor-not-allowed disabled:opacity-50"
                @click="emit('changePage', currentPage + 1)"
            >
                التالي
            </button>
        </nav>
    </div>
</template>
