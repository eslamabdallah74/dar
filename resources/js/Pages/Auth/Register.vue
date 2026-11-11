<script setup lang="ts">
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, Lock, Mail, ShieldCheck, Sparkles, User } from 'lucide-vue-next';

interface PlanOption {
    id: number;
    name: string;
    slug: string;
    monthly_price: number | string;
    parent_unit_limit: number | null;
}

const props = defineProps<{
    initialPlanSlug?: string | null;
    selectedPlan?: {
        id: number;
        name: string;
        slug: string;
        monthly_price: number;
        parent_unit_limit: number | null;
    } | null;
    plans?: PlanOption[];
}>();

const defaultPlanSlug = computed(() => {
    if (props.selectedPlan?.slug) {
        return props.selectedPlan.slug;
    }
    if (props.initialPlanSlug) {
        return props.initialPlanSlug;
    }
    if (typeof window !== 'undefined') {
        const queryPlan = new URLSearchParams(window.location.search).get('plan');
        if (queryPlan) {
            return queryPlan;
        }
    }
    return 'trial';
});

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    plan: defaultPlanSlug.value,
});

const activeSelectedPlan = computed(() => {
    if (!props.plans || props.plans.length === 0) {
        return props.selectedPlan || null;
    }
    return props.plans.find((p) => p.slug === form.plan) || props.selectedPlan || null;
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <GuestLayout image-background>
        <Head>
            <title>إنشاء حساب جديد | Nook Mate</title>
            <meta
                name="description"
                head-key="description"
                content="أنشئ حسابك المجاني في Nook Mate وابدأ إدارة عقاراتك، وحداتك السكنية، وغرفك بنظام الصناديق المتداخلة مع تذكيرات واتساب فورية."
            />
            <meta name="robots" head-key="robots" content="noindex, follow" />
        </Head>

        <div class="mb-3 text-center sm:mb-6">
            <h1 class="text-2xl font-bold text-white">إنشاء حساب جديد في Nook Mate</h1>
            <p class="mt-1.5 text-xs text-zinc-300 font-medium">
                ابدأ مساحة عملك المجانية الآن وأضف عقارك الأول وتابع عقودك ومستأجريك بكل سهولة.
            </p>
        </div>

        <!-- كارت الباقة المختارة -->
        <div class="mb-4 p-3.5 rounded-2xl bg-emerald-950/60 border border-emerald-500/30 text-right space-y-1.5" dir="rtl">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-emerald-300 flex items-center gap-1.5">
                    <Sparkles class="w-3.5 h-3.5 text-emerald-400" />
                    <span>الباقة المطلوبة عند التسجيل:</span>
                </span>
                <span v-if="activeSelectedPlan" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                    {{ activeSelectedPlan.monthly_price == 0 ? 'تجربة مجانية / مخصصة' : `${activeSelectedPlan.monthly_price} ج.م/شهر` }}
                </span>
            </div>
            <div class="text-sm font-black text-white flex items-center gap-2">
                <CheckCircle2 class="w-4 h-4 text-emerald-400 shrink-0" />
                <span>{{ activeSelectedPlan?.name || 'باقة التجربة (Trial)' }}</span>
            </div>
            <p class="text-[11px] text-zinc-300 leading-relaxed">
                ستبدأ مساحة عملك فوراً، وسيقوم مدير النظام بمراجعة ومنح الباقة المطلوبة لحسابك.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-2 sm:space-y-4">
            <!-- اختيار أو تغيير الباقة المطلوبة -->
            <div v-if="plans && plans.length > 0">
                <label for="plan" class="block text-xs font-semibold text-zinc-300 mb-1.5">الباقة التي ترغب بها</label>
                <select
                    id="plan"
                    v-model="form.plan"
                    class="block w-full mt-1 h-10 px-3 rounded-xl border border-zinc-700 bg-zinc-900/90 text-white text-xs font-semibold focus:border-emerald-500 focus:ring-emerald-500"
                    dir="rtl"
                    aria-label="الباقة التي ترغب بها"
                >
                    <option v-for="pl in plans" :key="pl.id" :value="pl.slug">
                        {{ pl.name }} — {{ pl.monthly_price == 0 ? (pl.slug === 'trial' ? '0 ج.م (تجربة)' : 'تسعير مخصص') : `${pl.monthly_price} ج.م/شهرياً` }}
                        {{ pl.parent_unit_limit ? `(حتى ${pl.parent_unit_limit} وحدات)` : '(سعة غير محدودة)' }}
                    </option>
                </select>
                <InputError class="mt-1.5" :message="form.errors.plan" />
            </div>

            <!-- الاسم بالكامل -->
            <div>
                <InputLabel for="name" value="اسمك بالكامل" />
                <div class="relative mt-1">
                    <TextInput
                        id="name"
                        type="text"
                        class="block w-full pr-10"
                        v-model="form.name"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="أحمد منصور"
                    />
                    <User class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-500" />
                </div>
                <InputError class="mt-1.5" :message="form.errors.name" />
            </div>

            <!-- البريد الإلكتروني -->
            <div>
                <InputLabel for="email" value="البريد الإلكتروني" />
                <div class="relative mt-1">
                    <TextInput
                        id="email"
                        type="email"
                        class="block w-full pr-10 text-right"
                        v-model="form.email"
                        required
                        autocomplete="email"
                        dir="ltr"
                        placeholder="ahmed@example.com"
                    />
                    <Mail class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-500" />
                </div>
                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <!-- كلمة المرور -->
            <div>
                <InputLabel for="password" value="كلمة المرور" />
                <div class="relative mt-1">
                    <TextInput
                        id="password"
                        type="password"
                        class="block w-full pr-10 text-right"
                        v-model="form.password"
                        required
                        autocomplete="new-password"
                        placeholder="8 أحرف على الأقل"
                    />
                    <Lock class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-500" />
                </div>
                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <!-- تأكيد كلمة المرور -->
            <div>
                <InputLabel for="password_confirmation" value="تأكيد كلمة المرور" />
                <div class="relative mt-1">
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        class="block w-full pr-10 text-right"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="أعد كتابة كلمة المرور"
                    />
                    <Lock class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-500" />
                </div>
                <InputError class="mt-1.5" :message="form.errors.password_confirmation" />
            </div>

            <!-- زرار البدء -->
            <div class="pt-1 sm:pt-3">
                <PrimaryButton
                    class="w-full font-bold"
                    :loading="form.processing"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing">جاري تجهيز حسابك...</span>
                    <span v-else class="flex items-center gap-1.5">
                        إنشاء الحساب والبدء
                        <ArrowLeft class="h-4 w-4" />
                    </span>
                </PrimaryButton>
            </div>

            <!-- رابط الدخول إذا مسجل بالفعل -->
            <div class="border-t border-zinc-800/80 pt-2 text-center text-xs text-zinc-400 sm:pt-4">
                عندك حساب مسجل بالفعل؟
                <Link
                    :href="route('login')"
                    class="font-bold text-emerald-400 hover:text-emerald-300 transition underline underline-offset-4 mr-1"
                >
                    سجّل دخولك من هنا
                </Link>
            </div>
        </form>

        <!-- ميزات مساحة عمل Nook Mate (Platform Features & Guarantees) -->
        <div class="mt-6 border-t border-white/10 pt-4 text-right">
            <h2 class="text-xs font-bold text-zinc-200 mb-2">مميزات البدء مع Nook Mate:</h2>
            <ul class="space-y-1.5 text-[11px] text-zinc-300">
                <li class="flex items-center gap-1.5">
                    <CheckCircle2 class="h-3.5 w-3.5 text-emerald-400 shrink-0" />
                    <span>تجهيز فوري لمساحة عملك وإمكانية إضافة عقارك الأول بضغطة زر.</span>
                </li>
                <li class="flex items-center gap-1.5">
                    <CheckCircle2 class="h-3.5 w-3.5 text-emerald-400 shrink-0" />
                    <span>إرسال إشعارات الاستحقاق وسندات القبض المباشرة عبر واتساب.</span>
                </li>
                <li class="flex items-center gap-1.5">
                    <ShieldCheck class="h-3.5 w-3.5 text-emerald-400 shrink-0" />
                    <span>حماية متقدمة لبيانات العقود وأرشيف المستأجرين مع نسخ احتياطي دوري.</span>
                </li>
            </ul>
        </div>
    </GuestLayout>
</template>
