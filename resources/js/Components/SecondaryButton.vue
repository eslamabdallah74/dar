<script setup lang="ts">
withDefaults(
    defineProps<{
        type?: 'button' | 'submit' | 'reset';
    }>(),
    {
        type: 'button',
    },
);
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center rounded-xl border border-[#D6E0E7] bg-white px-4 py-2 text-xs font-semibold text-[#163E3E] shadow-sm transition hover:bg-[#F8FAFC] focus:outline-none focus:ring-2 focus:ring-[#163E3E]/30 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
    >
        <slot />
    </button>
</template>
