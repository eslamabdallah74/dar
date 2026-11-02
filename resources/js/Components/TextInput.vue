<script setup lang="ts">
import { onMounted, ref } from 'vue';

const model = defineModel<string | number>({ required: true });

const input = ref<HTMLInputElement | null>(null);

onMounted(() => {
    if (input.value?.hasAttribute('autofocus')) {
        input.value?.focus();
    }
});

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <input
        class="rounded-xl border border-[#D6E0E7] bg-white px-3.5 py-2.5 text-sm text-[#163E3E] placeholder-[#8E9CA6] shadow-sm transition focus:border-[#163E3E] focus:outline-none focus:ring-2 focus:ring-[#163E3E]/20"
        v-model="model"
        ref="input"
    />
</template>
