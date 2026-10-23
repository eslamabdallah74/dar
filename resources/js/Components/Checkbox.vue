<script setup lang="ts">
import { computed } from 'vue';

const emit = defineEmits(['update:checked']);

const props = defineProps<{
    checked: boolean;
    value?: any;
}>();

const proxyChecked = computed({
    get() {
        return props.checked;
    },

    set(val) {
        emit('update:checked', val);
    },
});
</script>

<template>
    <input
        type="checkbox"
        :value="value"
        v-model="proxyChecked"
        class="rounded border-zinc-700 bg-zinc-950 text-emerald-500 shadow-sm focus:ring-emerald-500/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-emerald-500 dark:focus:ring-emerald-500/20"
    />
</template>
