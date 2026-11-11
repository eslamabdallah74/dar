<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useLocale } from '@/composables/useLocale';
import { Head, useForm } from '@inertiajs/vue3';
import { Lock, ShieldAlert } from 'lucide-vue-next';

const { isRtl } = useLocale();

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="`${$t('auth.confirm_password_title')} — Nook Mate`" />

        <div class="mb-6 text-center">
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-400 border border-amber-500/20">
                <ShieldAlert class="h-6 w-6" />
            </div>
            <h1 class="text-2xl font-bold text-white">{{ $t('auth.confirm_password_title') }}</h1>
            <p class="mt-1.5 text-xs text-zinc-300 font-medium">
                {{ $t('auth.confirm_password_desc') }}
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="password" :value="$t('auth.password')" />
                <div class="relative mt-1">
                    <TextInput
                        id="password"
                        type="password"
                        class="block w-full ps-10 pe-3.5 text-start"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                        autofocus
                        placeholder="••••••••••••"
                    />
                    <Lock class="pointer-events-none absolute start-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-500" />
                </div>
                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <div class="pt-2">
                <PrimaryButton
                    class="w-full font-bold"
                    :loading="form.processing"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing">{{ isRtl ? 'جاري التأكيد...' : 'Confirming...' }}</span>
                    <span v-else>{{ $t('auth.confirm') }}</span>
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
