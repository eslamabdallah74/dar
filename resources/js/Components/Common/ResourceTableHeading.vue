<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

defineProps<{
    href: string;
}>();
</script>

<template>
    <Link
        :href="href"
        class="group inline-flex items-center gap-1.5 rounded-sm transition-colors duration-200 ease-out hover:text-[#36594F] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#52766C]"
    >
        <slot />
        <ArrowLeft
            aria-hidden="true"
            class="h-3 w-3 shrink-0 opacity-50 transition duration-200 ease-out group-hover:-translate-x-0.5 group-hover:opacity-100"
        />
    </Link>
</template>
