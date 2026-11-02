<script setup lang="ts">
defineProps<{
    copyright?: string;
}>();
</script>

<template>
    <footer class="pt-4 border-t border-[#D6E0E7] text-[11px] text-[#5B6770] font-medium">
        <span>{{ copyright || 'Nook Mate © 2026' }}</span>
    </footer>
</template>
