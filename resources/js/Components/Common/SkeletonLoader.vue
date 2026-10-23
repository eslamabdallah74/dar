<script setup lang="ts">
withDefaults(
    defineProps<{
        rows?: number;
        type?: 'table' | 'cards' | 'kpis';
    }>(),
    {
        rows: 4,
        type: 'table',
    }
);
</script>

<template>
    <div class="animate-pulse space-y-4 w-full">
        <template v-if="type === 'kpis'">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div v-for="i in 4" :key="i" class="h-28 rounded-2xl bg-white border border-[#D6E0E7] p-5 space-y-3">
                    <div class="h-4 bg-slate-200 rounded w-1/3"></div>
                    <div class="h-7 bg-slate-200 rounded w-2/3"></div>
                    <div class="h-3 bg-slate-100 rounded w-1/2"></div>
                </div>
            </div>
        </template>

        <template v-else-if="type === 'cards'">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <div v-for="i in rows" :key="i" class="h-44 rounded-2xl bg-white border border-[#D6E0E7] p-6 space-y-4">
                    <div class="flex justify-between items-center">
                        <div class="h-5 bg-slate-200 rounded w-1/2"></div>
                        <div class="h-5 bg-slate-200 rounded-full w-16"></div>
                    </div>
                    <div class="h-4 bg-slate-100 rounded w-3/4"></div>
                    <div class="h-10 bg-slate-100 rounded-xl w-full"></div>
                </div>
            </div>
        </template>

        <template v-else>
            <div class="rounded-2xl border border-[#D6E0E7] bg-white overflow-hidden p-4 space-y-3">
                <div class="h-10 bg-slate-100 rounded-xl w-full mb-4"></div>
                <div v-for="i in rows" :key="i" class="h-12 bg-slate-50 border-b border-slate-100 rounded flex items-center justify-between px-4">
                    <div class="h-4 bg-slate-200 rounded w-1/4"></div>
                    <div class="h-4 bg-slate-200 rounded w-1/6"></div>
                    <div class="h-4 bg-slate-200 rounded w-1/5"></div>
                    <div class="h-6 bg-slate-200 rounded-full w-16"></div>
                </div>
            </div>
        </template>
    </div>
</template>

