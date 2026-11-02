<script setup lang="ts">
import { ref } from 'vue';
import { Send, Sparkles } from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        processing?: boolean;
        disabled?: boolean;
        placeholder?: string;
    }>(),
    {
        processing: false,
        disabled: false,
        placeholder: 'اكتب سؤالك هنا عن الإيرادات، العقود المنتهية، الوحدات الشاغرة، أو المستأجرين...',
    }
);

const emit = defineEmits<{
    (e: 'submit', question: string): void;
}>();

const question = ref('');

function handleSubmit() {
    const trimmed = question.value.trim();
    if (!trimmed || props.processing || props.disabled) {
        return;
    }

    emit('submit', trimmed);
    question.value = '';
}

function handleKeydown(event: KeyboardEvent) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        handleSubmit();
    }
}
</script>

<template>
    <div class="w-full bg-white border border-[#D6E0E7] rounded-3xl p-3 shadow-2xs">
        <label for="ai-question" class="sr-only">سؤال للمساعد الذكي</label>
        <div class="relative flex items-end gap-2">
            <textarea
                id="ai-question"
                v-model="question"
                rows="2"
                :disabled="disabled || processing"
                :placeholder="placeholder"
                aria-label="سؤال للمساعد الذكي"
                class="w-full resize-none border-0 bg-transparent p-2 text-sm text-[#163E3E] placeholder-[#8C9BA5] focus:ring-0 focus:outline-hidden disabled:opacity-50"
                @keydown="handleKeydown"
            ></textarea>

            <button
                type="button"
                :disabled="disabled || processing || !question.trim()"
                aria-label="إرسال السؤال"
                class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-2xl bg-[#163E3E] text-white text-xs font-bold transition hover:bg-[#123333] disabled:opacity-40 disabled:cursor-not-allowed shrink-0 cursor-pointer"
                @click="handleSubmit"
            >
                <Sparkles v-if="processing" class="w-4 h-4 animate-spin" />
                <Send v-else class="w-4 h-4 rtl:rotate-180" />
                <span class="hidden sm:inline">{{ processing ? 'جاري التحليل...' : 'إرسال' }}</span>
            </button>
        </div>
    </div>
</template>

