<script setup lang="ts">
defineProps<{
    value?: string;
}>();
</script>

<template>
    <label class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-1.5 font-mono">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
    </label>
</template>
