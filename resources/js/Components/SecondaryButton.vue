<script setup lang="ts">
import { LoaderCircle } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        type?: 'button' | 'submit' | 'reset';
        loading?: boolean;
        disabled?: boolean;
    }>(),
    {
        type: 'button',
        loading: false,
        disabled: false,
    },
);
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-[#D6E0E7] bg-white px-4 py-2 text-xs font-semibold text-[#163E3E] shadow-sm transition hover:bg-[#F8FAFC] focus:outline-none focus:ring-2 focus:ring-[#163E3E]/30 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 active:scale-[0.99]"
        :class="{ 'cursor-wait opacity-80': loading }"
    >
        <LoaderCircle v-if="loading" class="h-3.5 w-3.5 animate-spin shrink-0 text-current" />
        <slot />
    </button>
</template>
