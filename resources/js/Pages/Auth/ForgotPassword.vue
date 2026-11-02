<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Mail } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="استعادة كلمة المرور — Nook Mate" />

        <div class="mb-6 text-center">
            <h1 class="text-2xl font-bold text-white">استعادة كلمة المرور</h1>
            <p class="mt-1.5 text-xs text-zinc-300 font-medium">
                اكتب بريدك الإلكتروني وهنبعتلك رابط آمن لتعيين كلمة مرور جديدة.
            </p>
        </div>

        <div
            v-if="status"
            class="mb-4 rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-3 text-xs font-semibold text-emerald-400"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="email" value="البريد الإلكتروني" />
                <div class="relative mt-1">
                    <TextInput
                        id="email"
                        type="email"
                        class="block w-full pr-10 text-right"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="ahmed@example.com"
                    />
                    <Mail class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-500" />
                </div>
                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <div class="pt-2">
                <PrimaryButton
                    class="w-full flex items-center justify-center gap-2 font-bold"
                    :class="{ 'opacity-50': form.processing }"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing">جاري الإرسال...</span>
                    <span v-else class="flex items-center gap-1.5">
                        إرسال رابط تعيين كلمة المرور
                        <ArrowLeft class="h-4 w-4" />
                    </span>
                </PrimaryButton>
            </div>

            <div class="pt-4 border-t border-zinc-800/80 text-center text-xs text-zinc-400">
                افتكرت كلمة المرور؟
                <Link
                    :href="route('login')"
                    class="font-bold text-emerald-400 hover:text-emerald-300 transition underline underline-offset-4 mr-1"
                >
                    الرجوع لتسجيل الدخول
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>