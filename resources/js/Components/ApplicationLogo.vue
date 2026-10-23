<script setup lang="ts">
defineProps<{
    alt?: string;
}>();
</script>

<template>
    <div
        class="rounded-full bg-white flex items-center justify-center p-2 shadow-sm shrink-0 inline-flex"
        v-bind="$attrs"
    >
        <img
            src="/images/logo.png"
            :alt="alt || 'شعار دار — إدارة الأملاك والتأجير'"
            class="w-full h-full object-contain"
        />
    </div>
</template>

