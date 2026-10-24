<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Mail, Lock, KeyRound } from 'lucide-vue-next';

const props = defineProps<{
    email: string;
    token: string;
}>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="تعيين كلمة المرور الجديدة — دار" />

        <div class="mb-6 text-center">
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                <KeyRound class="h-6 w-6" />
            </div>
            <h1 class="text-2xl font-bold text-white">تعيين كلمة المرور الجديدة</h1>
            <p class="mt-1.5 text-xs text-zinc-300 font-medium">
                أدخل بريدك الإلكتروني وكلمة المرور الجديدة لتسجيل الدخول إلى حسابك.
            </p>
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
                    />
                    <Mail class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-500" />
                </div>
                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" value="كلمة المرور الجديدة" />
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

            <div>
                <InputLabel
                    for="password_confirmation"
                    value="تأكيد كلمة المرور"
                />
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
                <InputError
                    class="mt-1.5"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <div class="pt-2">
                <PrimaryButton
                    class="w-full flex items-center justify-center gap-2 font-bold"
                    :class="{ 'opacity-50': form.processing }"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing">جاري الحفظ...</span>
                    <span v-else>حفظ كلمة المرور وتسجيل الدخول</span>
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
