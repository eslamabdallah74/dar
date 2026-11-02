<script setup lang="ts">
import { ref, nextTick } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ChatBubble from '@/Components/AiAssistant/ChatBubble.vue';
import ChatInput from '@/Components/AiAssistant/ChatInput.vue';
import {
    AlertCircle,
    Bot,
    HelpCircle,
    Settings as SettingsIcon,
    Sparkles,
} from 'lucide-vue-next';

interface Message {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    timestamp: string;
}

const props = defineProps<{
    aiAvailable: boolean;
    reason?: string | null;
    workspaceName: string;
    currency?: string;
}>();

const messages = ref<Message[]>([
    {
        id: 1,
        role: 'assistant',
        content: `مرحباً بك! أنا مساعد دار الذكي لمنشأة "${props.workspaceName}". يمكنك سؤالي عن إيرادات المنشأة، عقود الإيجار المنتهية قريباً، الوحدات الشاغرة، أو تفاصيل مستأجر ومستحقاته المالية.`,
        timestamp: new Date().toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' }),
    },
]);

const loading = ref(false);
const errorMessage = ref<string | null>(null);
const chatContainer = ref<HTMLElement | null>(null);

const suggestedQuestions = [
    'ما إجمالي إيرادات هذا الشهر؟',
    'من هم المستأجرون المتأخرون في السداد؟',
    'ما هي الوحدات الشاغرة حالياً؟',
    'هل توجد عقود تنتهي خلال 30 يوماً؟',
];

async function scrollToBottom() {
    await nextTick();
    if (chatContainer.value) {
        chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
    }
}

async function handleQuestionSubmit(question: string) {
    if (!props.aiAvailable || loading.value) {
        return;
    }

    errorMessage.value = null;
    const now = new Date().toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });

    messages.value.push({
        id: Date.now(),
        role: 'user',
        content: question,
        timestamp: now,
    });

    loading.value = true;
    await scrollToBottom();

    try {
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const token = csrfMeta ? csrfMeta.getAttribute('content') : '';

        const response = await fetch(route('ai.ask'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token || '',
            },
            body: JSON.stringify({ question }),
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'تعذر الحصول على إجابة من المساعد.');
        }

        messages.value.push({
            id: Date.now() + 1,
            role: 'assistant',
            content: data.answer || 'لم يتم إرجاع إجابة.',
            timestamp: new Date().toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' }),
        });
    } catch (error: any) {
        errorMessage.value = error.message || 'حدث خطأ غير متوقع أثناء الاتصال بالمساعد الذكي.';
    } finally {
        loading.value = false;
        await scrollToBottom();
    }
}

function askSuggested(suggestion: string) {
    handleQuestionSubmit(suggestion);
}
</script>

<template>
    <AppLayout
        title="المساعد الذكي — Nook Mate"
        active-tab="ai"
        header-title="المساعد الذكي (Dar AI)"
        header-subtitle="استعلام فوري باللغة الطبيعية عن الإيرادات، الشواغر، العقود والمستأجرين"
        breadcrumb="المساعد الذكي"
    >
        <div class="space-y-6">
            <!-- حالة عدم التوفر -->
            <div
                v-if="!aiAvailable"
                class="rounded-3xl border border-[#D6E0E7] bg-white p-8 shadow-2xs space-y-5 text-center max-w-2xl mx-auto"
            >
                <div class="w-16 h-16 rounded-3xl bg-[#F8FAFC] border border-[#D6E0E7] flex items-center justify-center mx-auto text-[#5B6770]">
                    <Bot class="w-8 h-8" />
                </div>

                <div class="space-y-2">
                    <h3 class="text-lg font-bold text-[#163E3E]">المساعد الذكي غير متاح حالياً</h3>
                    <p class="text-sm text-[#5B6770] leading-relaxed">
                        {{ reason || 'المساعد الذكي غير مفعّل لمساحة العمل هذه، أو لم يتم إعداد مفتاح الربط المركزي من قبل مدير النظام.' }}
                    </p>
                </div>

                <div class="pt-4 flex items-center justify-center gap-3">
                    <Link
                        :href="route('settings.index')"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#163E3E] text-white text-xs font-bold transition hover:bg-[#123333]"
                    >
                        <SettingsIcon class="w-4 h-4" />
                        <span>الانتقال إلى إعدادات المنشأة</span>
                    </Link>
                </div>
            </div>

            <!-- واجهة المحادثة النشطة -->
            <div v-else class="flex flex-col h-[calc(100vh-250px)] min-h-[550px] bg-[#F8FAFC] rounded-3xl border border-[#D6E0E7] overflow-hidden shadow-2xs">
                <!-- شريط الأسئلة المقترحة السريعة -->
                <div class="bg-white px-5 py-3 border-b border-[#D6E0E7] flex items-center gap-2 overflow-x-auto custom-scrollbar">
                    <div class="flex items-center gap-1.5 text-xs font-bold text-[#163E3E] shrink-0 pl-2">
                        <Sparkles class="w-3.5 h-3.5 text-[#E08A3C]" />
                        <span>اقتراحات سريعة:</span>
                    </div>

                    <button
                        v-for="suggestion in suggestedQuestions"
                        :key="suggestion"
                        type="button"
                        :disabled="loading"
                        class="px-3.5 py-1.5 rounded-full text-xs font-medium bg-[#F0F4F7] text-[#163E3E] hover:bg-[#E2EBEF] transition shrink-0 cursor-pointer disabled:opacity-50"
                        @click="askSuggested(suggestion)"
                    >
                        {{ suggestion }}
                    </button>
                </div>

                <!-- صندوق المحادثة المتدفق -->
                <div
                    ref="chatContainer"
                    class="flex-1 p-5 overflow-y-auto space-y-4 custom-scrollbar"
                >
                    <ChatBubble
                        v-for="msg in messages"
                        :key="msg.id"
                        :role="msg.role"
                        :content="msg.content"
                        :timestamp="msg.timestamp"
                    />

                    <!-- Skeleton Loader أثناء المعالجة -->
                    <div v-if="loading" class="flex gap-3 max-w-xl w-full ml-auto">
                        <div class="w-9 h-9 rounded-2xl bg-[#E08A3C] text-white flex items-center justify-center shrink-0 shadow-2xs">
                            <Bot class="w-4 h-4 animate-pulse" />
                        </div>
                        <div class="bg-white border border-[#D6E0E7] rounded-3xl rounded-tl-xs p-4 shadow-2xs space-y-2 w-72">
                            <div class="h-3 bg-[#E2E8F0] rounded-full animate-pulse w-3/4"></div>
                            <div class="h-3 bg-[#E2E8F0] rounded-full animate-pulse w-1/2"></div>
                        </div>
                    </div>

                    <!-- تنبيه الخطأ إن وجد -->
                    <div
                        v-if="errorMessage"
                        class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs flex items-center gap-2 max-w-xl mx-auto shadow-2xs"
                    >
                        <AlertCircle class="w-4 h-4 shrink-0" />
                        <span>{{ errorMessage }}</span>
                    </div>
                </div>

                <!-- شريط الإدخال في الأسفل -->
                <div class="p-4 bg-white border-t border-[#D6E0E7]">
                    <ChatInput
                        :processing="loading"
                        :disabled="!aiAvailable"
                        @submit="handleQuestionSubmit"
                    />
                    <div class="mt-2 text-center text-[11px] text-[#8C9BA5] flex items-center justify-center gap-1">
                        <HelpCircle class="w-3 h-3" />
                        <span>يعتمد المساعد على بيانات منشأتك المسجلة في النظام فقط لضمان دقة الأرقام والخصوصية.</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

