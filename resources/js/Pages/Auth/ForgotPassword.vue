<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useLocale } from '@/composables/useLocale';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Inbox, Mail, MailCheck, RotateCcw } from 'lucide-vue-next';

const props = defineProps<{
    status?: string;
}>();

const { isRtl, dir } = useLocale();

const form = useForm({
    email: '',
});

const showFormAgain = ref(false);
const submittedEmail = ref('');

const isSent = computed(() => !form.processing && (Boolean(props.status) || form.wasSuccessful));

watch(
    () => props.status,
    (newVal) => {
        if (newVal) {
            showFormAgain.value = false;
        }
    }
);

const submit = () => {
    submittedEmail.value = form.email;
    form.post(route('password.email'), {
        onSuccess: () => {
            showFormAgain.value = false;
        },
    });
};

const handleTryAgain = () => {
    showFormAgain.value = true;
};
</script>

<template>
    <GuestLayout>
        <Head>
            <title>{{ `${$t('auth.reset_password')} — Nook Mate` }}</title>
        </Head>

        <!-- 1. Success State: When email is sent successfully -->
        <div v-if="isSent && !showFormAgain" class="space-y-6 py-2 text-center">
            <!-- Animated Icon Badge -->
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl border border-emerald-500/30 bg-emerald-500/15 text-emerald-400 shadow-xl shadow-emerald-950/40 ring-8 ring-emerald-500/10">
                <MailCheck class="h-8 w-8 animate-pulse" />
            </div>

            <!-- Header & Description -->
            <div class="space-y-2">
                <h1 class="text-2xl font-black tracking-tight text-white">{{ isRtl ? 'تفقد صندوق بريدك' : 'Check Your Inbox' }}</h1>
                <p class="mx-auto max-w-sm text-xs leading-relaxed text-zinc-300 sm:text-sm">
                    {{ isRtl ? 'تم إرسال رابط آمن لإعادة تعيين كلمة المرور إلى' : 'A secure password reset link was sent to' }}
                    <template v-if="submittedEmail">
                        <span class="dir-ltr mt-1.5 inline-block max-w-full truncate rounded-lg border border-[#F7C33D]/20 bg-[#F7C33D]/10 px-3 py-1 font-mono text-xs font-bold text-[#F7C33D]" dir="ltr">
                            {{ submittedEmail }}
                        </span>
                    </template>
                    <template v-else>
                        {{ isRtl ? 'عنوان بريدك الإلكتروني المسجل.' : 'your registered email address.' }}
                    </template>
                </p>
            </div>

            <!-- Helpful Guidance Box -->
            <div class="space-y-2 rounded-xl border border-white/10 bg-white/5 p-3.5 text-start text-xs text-zinc-300" :dir="dir">
                <div class="flex items-center gap-2 text-[13px] font-bold text-white">
                    <Inbox class="h-4 w-4 shrink-0 text-[#F7C33D]" />
                    <span>{{ isRtl ? 'ماذا تفعل الآن؟' : 'What to do next?' }}</span>
                </div>
                <ul class="list-inside list-disc space-y-1.5 ps-1 text-[11px] leading-relaxed text-zinc-400">
                    <li>{{ isRtl ? 'افتح رسالة البريد واضغط على زر تعيين كلمة المرور الجديدة.' : 'Open the email and click the password reset button.' }}</li>
                    <li>{{ isRtl ? 'الرابط صالح لمدة 60 دقيقة فقط لحماية حسابك.' : 'The link is valid for 60 minutes for security.' }}</li>
                    <li>{{ isRtl ? 'لم تجد الرسالة؟ تحقق من مجلد الرسائل غير المرغوب فيها (Spam).' : 'Cannot find the email? Check your spam/junk folder.' }}</li>
                </ul>
            </div>

            <!-- Actions -->
            <div class="space-y-2.5 pt-2">
                <Link
                    :href="route('login')"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#F7C33D] px-5 py-3 text-sm font-black text-[#163E3E] shadow-lg shadow-[#F7C33D]/10 transition hover:bg-[#e5b12a] active:scale-[0.99]"
                >
                    <span>{{ isRtl ? 'الرجوع لتسجيل الدخول' : 'Back to Sign In' }}</span>
                    <ArrowLeft class="h-4 w-4 transition-transform" :class="isRtl ? '' : 'rotate-180'" />
                </Link>

                <button
                    type="button"
                    @click="handleTryAgain"
                    class="inline-flex w-full cursor-pointer items-center justify-center gap-1.5 py-2 text-xs font-semibold text-zinc-400 transition hover:text-white"
                >
                    <RotateCcw class="h-3.5 w-3.5" />
                    <span>{{ isRtl ? 'أخطأت في البريد أو ترغب بإعادة المحاولة؟' : 'Wrong email address or want to try again?' }}</span>
                </button>
            </div>
        </div>

        <!-- 2. Form State: Initial / Resend Request -->
        <div v-else>
            <div class="mb-6 text-center">
                <h1 class="text-2xl font-bold text-white">{{ $t('auth.reset_password') }}</h1>
                <p class="mt-1.5 text-xs font-medium text-zinc-300">
                    {{ $t('auth.reset_password_desc') }}
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <InputLabel for="email" :value="$t('auth.email')" />
                    <div class="relative mt-1">
                        <TextInput
                            id="email"
                            type="email"
                            class="block w-full ps-10 pe-3.5 text-start"
                            v-model="form.email"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="ahmed@example.com"
                            :aria-label="$t('auth.email')"
                        />
                        <Mail class="pointer-events-none absolute start-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-500" />
                    </div>
                    <InputError class="mt-1.5" :message="form.errors.email" />
                </div>

                <div class="pt-2">
                    <PrimaryButton
                        class="w-full font-bold"
                        :loading="form.processing"
                        :disabled="form.processing"
                    >
                        <span v-if="form.processing">{{ $t('auth.sending') }}</span>
                        <span v-else class="flex items-center gap-1.5">
                            {{ $t('auth.send_reset_link') }}
                            <ArrowLeft class="h-4 w-4 transition-transform" :class="isRtl ? '' : 'rotate-180'" />
                        </span>
                    </PrimaryButton>
                </div>

                <div class="border-t border-zinc-800/80 pt-4 text-center text-xs text-zinc-400">
                    {{ isRtl ? 'تذكرت كلمة المرور؟' : 'Remembered your password?' }}
                    <Link
                        :href="route('login')"
                        class="ms-1 font-bold text-emerald-400 underline underline-offset-4 transition hover:text-emerald-300"
                    >
                        {{ isRtl ? 'الرجوع لتسجيل الدخول' : 'Back to Sign In' }}
                    </Link>
                </div>
            </form>
        </div>
    </GuestLayout>
</template>