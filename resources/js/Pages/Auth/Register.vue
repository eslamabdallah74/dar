<script setup lang="ts">
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useLocale } from '@/composables/useLocale';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, Lock, Mail, Sparkles, User } from 'lucide-vue-next';

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

const { isRtl, dir } = useLocale();

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
        <Head :title="`${$t('auth.register_title')} — Nook Mate`" />

        <div class="mb-3 text-center sm:mb-6">
            <h1 class="text-2xl font-bold text-white">{{ $t('auth.register_title') }}</h1>
            <p class="mt-1.5 text-xs text-zinc-300 font-medium">
                {{ $t('auth.register_subtitle') }}
            </p>
        </div>

        <!-- كارت الباقة المختارة -->
        <div class="mb-4 p-3.5 rounded-2xl bg-emerald-950/60 border border-emerald-500/30 text-start space-y-1.5" :dir="dir">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-emerald-300 flex items-center gap-1.5">
                    <Sparkles class="w-3.5 h-3.5 text-emerald-400" />
                    <span>{{ isRtl ? 'الباقة المطلوبة عند التسجيل:' : 'Requested Plan:' }}</span>
                </span>
                <span v-if="activeSelectedPlan" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                    {{ activeSelectedPlan.monthly_price == 0 ? (isRtl ? 'تجربة مجانية / مخصصة' : 'Free Trial / Custom') : `${activeSelectedPlan.monthly_price} ${$t('common.currency_egp')}/${isRtl ? 'شهر' : 'mo'}` }}
                </span>
            </div>
            <div class="text-sm font-black text-white flex items-center gap-2">
                <CheckCircle2 class="w-4 h-4 text-emerald-400 shrink-0" />
                <span>{{ activeSelectedPlan?.name || (isRtl ? 'باقة التجربة (Trial)' : 'Trial Plan') }}</span>
            </div>
            <p class="text-[11px] text-zinc-300 leading-relaxed">
                {{ isRtl ? 'ستبدأ مساحة عملك فوراً، وسيقوم مدير النظام بمراجعة ومنح الباقة المطلوبة لحسابك.' : 'Your workspace starts immediately; an administrator will review and assign your requested plan.' }}
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-2 sm:space-y-4">
            <!-- اختيار أو تغيير الباقة المطلوبة -->
            <div v-if="plans && plans.length > 0">
                <label for="plan" class="block text-xs font-semibold text-zinc-300 mb-1.5">{{ isRtl ? 'الباقة التي ترغب بها' : 'Desired Plan' }}</label>
                <select
                    id="plan"
                    v-model="form.plan"
                    class="block w-full mt-1 h-10 px-3 rounded-xl border border-zinc-700 bg-zinc-900/90 text-white text-xs font-semibold focus:border-emerald-500 focus:ring-emerald-500"
                    :dir="dir"
                    :aria-label="isRtl ? 'الباقة التي ترغب بها' : 'Desired Plan'"
                >
                    <option v-for="pl in plans" :key="pl.id" :value="pl.slug">
                        {{ pl.name }} — {{ pl.monthly_price == 0 ? (pl.slug === 'trial' ? (isRtl ? '0 ج.م (تجربة)' : '0 EGP (Trial)') : (isRtl ? 'تسعير مخصص' : 'Custom Pricing')) : `${pl.monthly_price} ${$t('common.currency_egp')}/${isRtl ? 'شهرياً' : 'month'}` }}
                        {{ pl.parent_unit_limit ? (isRtl ? `(حتى ${pl.parent_unit_limit} وحدات)` : `(Up to ${pl.parent_unit_limit} units)`) : (isRtl ? '(سعة غير محدودة)' : '(Unlimited)') }}
                    </option>
                </select>
                <InputError class="mt-1.5" :message="form.errors.plan" />
            </div>

            <!-- الاسم بالكامل -->
            <div>
                <InputLabel for="name" :value="$t('auth.full_name')" />
                <div class="relative mt-1">
                    <TextInput
                        id="name"
                        type="text"
                        class="block w-full ps-10 pe-3.5 text-start"
                        v-model="form.name"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="Ahmed Mansour"
                    />
                    <User class="pointer-events-none absolute start-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-500" />
                </div>
                <InputError class="mt-1.5" :message="form.errors.name" />
            </div>

            <!-- البريد الإلكتروني -->
            <div>
                <InputLabel for="email" :value="$t('auth.email')" />
                <div class="relative mt-1">
                    <TextInput
                        id="email"
                        type="email"
                        class="block w-full ps-10 pe-3.5 text-start"
                        v-model="form.email"
                        required
                        autocomplete="email"
                        dir="ltr"
                        placeholder="ahmed@example.com"
                    />
                    <Mail class="pointer-events-none absolute start-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-500" />
                </div>
                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <!-- كلمة المرور -->
            <div>
                <InputLabel for="password" :value="$t('auth.password')" />
                <div class="relative mt-1">
                    <TextInput
                        id="password"
                        type="password"
                        class="block w-full ps-10 pe-3.5 text-start"
                        v-model="form.password"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••••••"
                    />
                    <Lock class="pointer-events-none absolute start-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-500" />
                </div>
                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <!-- تأكيد كلمة المرور -->
            <div>
                <InputLabel for="password_confirmation" :value="$t('auth.password_confirm')" />
                <div class="relative mt-1">
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        class="block w-full ps-10 pe-3.5 text-start"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••••••"
                    />
                    <Lock class="pointer-events-none absolute start-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-500" />
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
                    <span v-if="form.processing">{{ $t('auth.creating_account') }}</span>
                    <span v-else class="flex items-center gap-1.5">
                        {{ $t('auth.register_btn') }}
                        <ArrowLeft class="h-4 w-4 transition-transform" :class="isRtl ? '' : 'rotate-180'" />
                    </span>
                </PrimaryButton>
            </div>

            <!-- رابط الدخول إذا مسجل بالفعل -->
            <div class="border-t border-zinc-800/80 pt-2 text-center text-xs text-zinc-400 sm:pt-4">
                {{ $t('auth.have_account') }}
                <Link
                    :href="route('login')"
                    class="font-bold text-emerald-400 hover:text-emerald-300 transition underline underline-offset-4 ms-1"
                >
                    {{ $t('auth.login_btn') }}
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
