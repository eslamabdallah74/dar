<script setup lang="ts">
defineProps<{
    copyright?: string;
    lastUpdated?: string;
}>();
</script>

<template>
    <footer class="pt-4 border-t border-[#D6E0E7] flex items-center justify-between text-[11px] text-[#5B6770] font-medium">
        <span>{{ copyright || 'Nook Mate © 2026' }}</span>
        <span>{{ lastUpdated || 'آخر تحديث اليوم، 09:41' }}</span>
    </footer>
</template>
