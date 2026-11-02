<script setup lang="ts">
import { Bot, User } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        role: 'user' | 'assistant';
        content: string;
        timestamp?: string;
    }>(),
    {
        timestamp: '',
    }
);
</script>

<template>
    <div
        class="flex gap-3 max-w-3xl w-full"
        :class="role === 'user' ? 'mr-auto flex-row-reverse' : 'ml-auto flex-row'"
    >
        <!-- Avatar -->
        <div
            class="w-9 h-9 rounded-2xl flex items-center justify-center shrink-0 shadow-2xs text-white"
            :class="role === 'user' ? 'bg-[#163E3E]' : 'bg-[#E08A3C]'"
            aria-hidden="true"
        >
            <User v-if="role === 'user'" class="w-4 h-4" />
            <Bot v-else class="w-4 h-4" />
        </div>

        <!-- Message Body -->
        <div class="flex-1 space-y-1">
            <div class="flex items-center gap-2" :class="role === 'user' ? 'justify-end' : 'justify-start'">
                <span class="text-xs font-bold text-[#163E3E]">
                    {{ role === 'user' ? 'أنت' : 'المساعد الذكي (دار)' }}
                </span>
                <span v-if="timestamp" class="text-[10px] text-[#5B6770]">
                    {{ timestamp }}
                </span>
            </div>

            <div
                class="rounded-3xl p-4 text-sm leading-relaxed whitespace-pre-wrap shadow-2xs"
                :class="
                    role === 'user'
                        ? 'bg-[#163E3E] text-white rounded-tr-xs'
                        : 'bg-white text-[#163E3E] border border-[#D6E0E7] rounded-tl-xs'
                "
            >
                {{ content }}
            </div>
        </div>
    </div>
</template>

