<script setup lang="ts">
import { LoaderCircle } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        loading?: boolean;
        disabled?: boolean;
        type?: 'button' | 'submit' | 'reset';
    }>(),
    {
        loading: false,
        disabled: false,
        type: 'submit',
    },
);
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#163E3E] px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-[#163E3E]/15 transition duration-150 hover:bg-[#17334F] focus:outline-none focus:ring-2 focus:ring-[#163E3E]/40 disabled:cursor-not-allowed disabled:opacity-60 active:scale-[0.99]"
        :class="{ 'cursor-wait opacity-80': loading }"
    >
        <LoaderCircle v-if="loading" class="h-4 w-4 animate-spin shrink-0 text-current" />
        <slot />
    </button>
</template>
