<script setup lang="ts">
import { computed } from 'vue';
import { Bell, FileText, Key, Plus, Search, Wrench } from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        type?: 'key' | 'document' | 'bell' | 'wrench' | 'search';
        title: string;
        description: string;
        actionLabel?: string;
    }>(),
    {
        type: 'document',
    }
);

const emit = defineEmits<{
    (e: 'action'): void;
}>();

const iconComponent = computed(() => {
    switch (props.type) {
        case 'key':
            return Key;
        case 'bell':
            return Bell;
        case 'wrench':
            return Wrench;
        case 'search':
            return Search;
        case 'document':
        default:
            return FileText;
    }
});
</script>

<template>
    <div class="rounded-2xl border border-dashed border-[#D6E0E7] bg-white/70 p-10 text-center flex flex-col items-center justify-center">
        <!-- أيقونة التوضيح الهندسية المتطابقة مع فيجما 64x64 -->
        <div class="w-16 h-16 rounded-2xl bg-[#E8F0F0] text-[#163E3E] flex items-center justify-center mb-4 shadow-2xs">
            <component :is="iconComponent" class="w-8 h-8 stroke-[1.75]" />
        </div>

        <h3 class="text-base font-bold text-[#163E3E] mb-1.5">
            {{ title }}
        </h3>

        <p class="text-sm text-[#5B6770] max-w-md mx-auto mb-6 leading-relaxed">
            {{ description }}
        </p>

        <button
            v-if="actionLabel"
            @click="emit('action')"
            type="button"
            class="h-10 px-5 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-sm font-semibold transition flex items-center gap-2 shadow-xs cursor-pointer"
        >
            <Plus class="h-4 w-4" />
            <span>{{ actionLabel }}</span>
        </button>
    </div>
</template>

