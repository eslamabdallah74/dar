<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    ArrowRight,
    Bot,
    CheckCircle2,
    Globe,
    Key,
    Save,
    Shield,
    Trash2,
    XCircle,
} from 'lucide-vue-next';

const props = defineProps<{
    provider: 'openai' | 'openrouter';
    supported_providers: string[];
    is_configured: boolean;
    has_stored_key: boolean;
    model: string;
    supported_models: string[];
    openai_models: string[];
    openrouter_models: string[];
}>();

const form = useForm({
    provider: props.provider || 'openai',
    token: '',
    model: props.model,
});

// Update default model when provider toggles if model not explicitly customized
watch(
    () => form.provider,
    (newProvider) => {
        if (newProvider === 'openrouter') {
            if (!props.openrouter_models.includes(form.model)) {
                form.model = 'openai/gpt-4o-mini';
            }
        } else {
            if (!props.openai_models.includes(form.model)) {
                form.model = 'gpt-4o-mini';
            }
        }
    }
);

const currentProviderModels = computed(() => {
    return form.provider === 'openrouter' ? props.openrouter_models : props.openai_models;
});

function submitConfig() {
    form.put(route('admin.ai-configuration.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('token');
        },
    });
}

function removeKey() {
    if (confirm('هل أنت متأكد من رغبتك في حذف مفتاح API المخزن لهذا المزود؟ سيتم إيقاف المساعد الذكي عن العمل للمنشآت حتى يتم إدخال مفتاح جديد.')) {
        router.delete(route('admin.ai-configuration.destroy', { provider: form.provider }), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('token');
            },
        });
    }
}
</script>

<template>
    <AppLayout
        title="إعدادات المساعد الذكي المركزية — لوحة الإدارة"
        :admin-mode="true"
        header-title="إعدادات المساعد الذكي المركزي"
        header-subtitle="إدارة مفتاح API المركزي والنموذج المعتمد عبر OpenAI أو OpenRouter"
        breadcrumb="المساعد الذكي"
    >
        <div class="max-w-4xl space-y-6">
            <div class="flex items-center justify-between">
                <Link
                    :href="route('admin.dashboard')"
                    class="inline-flex items-center gap-1.5 text-xs font-bold text-[#163E3E] hover:text-[#102F2F] bg-white px-3.5 py-2 rounded-xl border border-[#D6E0E7] shadow-2xs transition"
                >
                    <ArrowRight class="w-3.5 h-3.5" />
                    <span>العودة للوحة الإدارة العامة</span>
                </Link>
            </div>

            <!-- بطاقة الحالة الراهنة -->
            <div class="rounded-3xl border border-[#D6E0E7] bg-white p-6 sm:p-8 shadow-2xs space-y-6">
                <div class="flex items-center justify-between border-b border-[#D6E0E7] pb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-[#F0F4F7] text-[#163E3E] flex items-center justify-center">
                            <Bot class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-[#163E3E]">حالة مزود الذكاء الاصطناعي المركزي</h3>
                            <p class="text-xs text-[#5B6770]">
                                المزود النشط حالياً:
                                <strong class="text-[#163E3E] uppercase">{{ provider }}</strong>
                                ({{ provider === 'openrouter' ? 'OpenRouter API Gateway' : 'OpenAI Direct API' }})
                            </p>
                        </div>
                    </div>

                    <span
                        v-if="is_configured"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"
                    >
                        <CheckCircle2 class="w-3.5 h-3.5" />
                        <span>جاهز ومفعّل</span>
                    </span>
                    <span
                        v-else
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200"
                    >
                        <XCircle class="w-3.5 h-3.5" />
                        <span>غير مهيأ</span>
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                    <div class="p-4 rounded-2xl bg-[#F8FAFC] border border-[#E2E8F0] space-y-1">
                        <div class="text-[#5B6770] font-medium">موقع تخزين المفتاح</div>
                        <div class="font-bold text-[#163E3E]">
                            <span v-if="has_stored_key">قاعدة البيانات (مشفر بـ AES-256)</span>
                            <span v-else-if="is_configured">ملف الإعدادات البيئية (.env)</span>
                            <span v-else>غير مسجل</span>
                        </div>
                    </div>

                    <div class="p-4 rounded-2xl bg-[#F8FAFC] border border-[#E2E8F0] space-y-1">
                        <div class="text-[#5B6770] font-medium">النموذج النشط حالياً</div>
                        <div class="font-bold text-[#163E3E] font-mono">{{ model }}</div>
                    </div>

                    <div class="p-4 rounded-2xl bg-[#F8FAFC] border border-[#E2E8F0] space-y-1">
                        <div class="text-[#5B6770] font-medium">مستوى الأمان والخصوصية</div>
                        <div class="font-bold text-emerald-700 flex items-center gap-1">
                            <Shield class="w-3.5 h-3.5" />
                            <span>عزل تام للمنشآت</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- نموذج تعديل الإعدادات ومفتاح API -->
            <form @submit.prevent="submitConfig" class="rounded-3xl border border-[#D6E0E7] bg-white p-6 sm:p-8 shadow-2xs space-y-6">
                <div class="space-y-1 border-b border-[#D6E0E7] pb-4">
                    <h3 class="text-base font-bold text-[#163E3E]">تحديث بيانات الربط</h3>
                    <p class="text-xs text-[#5B6770]">
                        يمكنك استخدام مفاتيح OpenAI المباشرة أو مفاتيح OpenRouter (التي تدعم نماذج OpenAI، Claude، Llama وغيرها).
                    </p>
                </div>

                <div class="space-y-5">
                    <!-- اختيار المزود -->
                    <div>
                        <span class="block text-xs font-bold text-[#163E3E] mb-2">مزود الخدمة المعتمد</span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label
                                for="provider-openai"
                                class="flex items-center gap-3 p-3.5 rounded-2xl border cursor-pointer transition"
                                :class="form.provider === 'openai' ? 'border-[#163E3E] bg-[#F0F4F7]' : 'border-[#D6E0E7] bg-[#F8FAFC]'"
                            >
                                <input
                                    id="provider-openai"
                                    type="radio"
                                    v-model="form.provider"
                                    value="openai"
                                    aria-label="OpenAI المباشر"
                                    class="text-[#163E3E] focus:ring-[#163E3E]"
                                />
                                <div>
                                    <div class="text-xs font-bold text-[#163E3E]">OpenAI المباشر</div>
                                    <div class="text-[11px] text-[#5B6770]">api.openai.com (gpt-4o, gpt-4o-mini)</div>
                                </div>
                            </label>

                            <label
                                for="provider-openrouter"
                                class="flex items-center gap-3 p-3.5 rounded-2xl border cursor-pointer transition"
                                :class="form.provider === 'openrouter' ? 'border-[#163E3E] bg-[#F0F4F7]' : 'border-[#D6E0E7] bg-[#F8FAFC]'"
                            >
                                <input
                                    id="provider-openrouter"
                                    type="radio"
                                    v-model="form.provider"
                                    value="openrouter"
                                    aria-label="OpenRouter"
                                    class="text-[#163E3E] focus:ring-[#163E3E]"
                                />
                                <div>
                                    <div class="text-xs font-bold text-[#163E3E] flex items-center gap-1.5">
                                        <span>OpenRouter.ai</span>
                                        <Globe class="w-3.5 h-3.5 text-blue-600" />
                                    </div>
                                    <div class="text-[11px] text-[#5B6770]">openrouter.ai (Claude, GPT, Llama, DeepSeek)</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label for="ai-token" class="block text-xs font-bold text-[#163E3E] mb-1.5">
                            {{ form.provider === 'openrouter' ? 'مفتاح OpenRouter API السري (sk-or-v1-...)' : 'مفتاح OpenAI API السري (sk-...)' }}
                        </label>
                        <div class="relative">
                            <input
                                id="ai-token"
                                v-model="form.token"
                                type="password"
                                autocomplete="new-password"
                                :placeholder="has_stored_key ? 'اتركه فارغاً للإبقاء على المفتاح الحالي المخزن...' : 'أدخل المفتاح السري هنا...'"
                                aria-label="مفتاح API السري"
                                class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] font-mono focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                            />
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#8C9BA5]">
                                <Key class="w-4 h-4" />
                            </div>
                        </div>
                        <p v-if="form.errors.token" class="text-xs text-rose-600 font-semibold mt-1">
                            {{ form.errors.token }}
                        </p>
                    </div>

                    <div>
                        <label for="ai-model" class="block text-xs font-bold text-[#163E3E] mb-1.5">
                            النموذج المعتمد (Model Name)
                        </label>
                        <div class="space-y-2">
                            <select
                                id="ai-model"
                                v-model="form.model"
                                aria-label="اختيار النموذج"
                                class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                            >
                                <option v-for="m in currentProviderModels" :key="m" :value="m">
                                    {{ m }}
                                </option>
                            </select>

                            <input
                                id="ai-model-custom"
                                v-model="form.model"
                                type="text"
                                placeholder="أو أدخل اسم النموذج يدوياً هنا..."
                                aria-label="اسم النموذج مخصص"
                                class="w-full h-10 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-[#163E3E] font-mono focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                            />
                        </div>
                        <p v-if="form.errors.model" class="text-xs text-rose-600 font-semibold mt-1">
                            {{ form.errors.model }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-[#D6E0E7]">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        aria-label="حفظ إعدادات المساعد الذكي"
                        class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-[#163E3E] text-white text-xs font-bold transition hover:bg-[#123333] disabled:opacity-50 cursor-pointer shadow-xs"
                    >
                        <Save class="w-4 h-4" />
                        <span>{{ form.processing ? 'جاري التحقق والحفظ...' : 'حفظ الإعدادات' }}</span>
                    </button>

                    <button
                        v-if="has_stored_key"
                        type="button"
                        aria-label="حذف المفتاح المخزن"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-rose-600 bg-rose-50 hover:bg-rose-100 text-xs font-bold transition cursor-pointer"
                        @click="removeKey"
                    >
                        <Trash2 class="w-4 h-4" />
                        <span>حذف المفتاح المخزن</span>
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
