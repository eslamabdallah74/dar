<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useLocale } from '@/composables/useLocale';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Lock, Mail } from 'lucide-vue-next';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const { isRtl } = useLocale();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <GuestLayout image-background>
        <Head :title="`${$t('auth.login_title')} — Nook Mate`" />

        <div class="mb-3 text-center sm:mb-6">
            <h1 class="text-2xl font-bold text-white">{{ $t('auth.login_title') }}</h1>
            <p class="mt-1.5 text-xs text-zinc-300 font-medium">
                {{ $t('auth.login_subtitle') }}
            </p>
        </div>

        <div v-if="status" class="mb-4 rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-3 text-xs font-semibold text-emerald-400">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-2 sm:space-y-4">
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
                        autofocus
                        autocomplete="email"
                        placeholder="ahmed@example.com"
                    />
                    <Mail class="pointer-events-none absolute start-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-500" />
                </div>
                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <!-- كلمة المرور -->
            <div>
                <div class="flex items-center justify-between mb-1">
                    <InputLabel for="password" :value="$t('auth.password')" class="!mb-0" />
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-xs text-zinc-400 hover:text-emerald-400 transition"
                    >
                        {{ $t('auth.forgot_password') }}
                    </Link>
                </div>
                <div class="relative mt-1">
                    <TextInput
                        id="password"
                        type="password"
                        class="block w-full ps-10 pe-3.5 text-start"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••••••"
                    />
                    <Lock class="pointer-events-none absolute start-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-500" />
                </div>
                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <!-- تذكرني -->
            <div class="flex items-center justify-between pt-1">
                <label class="flex items-center cursor-pointer select-none">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-xs text-zinc-300 font-medium">{{ $t('auth.remember_me') }}</span>
                </label>
            </div>

            <!-- زرار الدخول -->
            <div class="pt-2">
                <PrimaryButton
                    class="w-full"
                    :loading="form.processing"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing">{{ $t('auth.signing_in') }}</span>
                    <span v-else class="flex items-center gap-1.5 font-bold">
                        {{ $t('auth.login_btn') }}
                        <ArrowLeft class="h-4 w-4 transition-transform" :class="isRtl ? '' : 'rotate-180'" />
                    </span>
                </PrimaryButton>
            </div>

            <!-- رابط التسجيل الجديد -->
            <div class="border-t border-zinc-800/80 pt-2 text-center text-xs text-zinc-400 sm:pt-4">
                {{ $t('auth.no_account') }}
                <Link
                    :href="route('register')"
                    class="font-bold text-emerald-400 hover:text-emerald-300 transition underline underline-offset-4 ms-1"
                >
                    {{ $t('auth.register_free') }}
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>