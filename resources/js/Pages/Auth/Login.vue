<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Lock, Mail } from 'lucide-vue-next';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

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
    <GuestLayout>
        <Head title="تسجيل الدخول — دار" />

        <div class="mb-6 text-center">
            <h1 class="text-2xl font-bold text-white">أهلاً بيك من جديد 👋</h1>
            <p class="mt-1.5 text-xs text-zinc-300 font-medium">
                سجّل دخولك لمتابعة عقاراتك، المستأجرين، وتحصيلات الإيجار.
            </p>
        </div>

        <div v-if="status" class="mb-4 rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-3 text-xs font-semibold text-emerald-400">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <!-- البريد الإلكتروني أو الاسم -->
            <div>
                <InputLabel for="email" value="البريد الإلكتروني أو الاسم" />
                <div class="relative mt-1">
                    <TextInput
                        id="email"
                        type="text"
                        class="block w-full pr-10 text-left font-mono"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="ahmed@example.com أو اسمك"
                        dir="ltr"
                    />
                    <Mail class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-500" />
                </div>
                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <!-- كلمة المرور -->
            <div>
                <div class="flex items-center justify-between mb-1">
                    <InputLabel for="password" value="كلمة المرور" class="!mb-0" />
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-xs text-zinc-400 hover:text-emerald-400 transition"
                    >
                        نسيت كلمة المرور؟
                    </Link>
                </div>
                <div class="relative mt-1">
                    <TextInput
                        id="password"
                        type="password"
                        class="block w-full pr-10 text-left font-mono"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••••••"
                        dir="ltr"
                    />
                    <Lock class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-500" />
                </div>
                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <!-- تذكرني -->
            <div class="flex items-center justify-between pt-1">
                <label class="flex items-center cursor-pointer select-none">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="mr-2 text-xs text-zinc-300 font-medium">افتكرني على الجهاز ده</span>
                </label>
            </div>

            <!-- زرار الدخول -->
            <div class="pt-2">
                <PrimaryButton
                    class="w-full flex items-center justify-center gap-2"
                    :class="{ 'opacity-50': form.processing }"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing">جاري تسجيل الدخول...</span>
                    <span v-else class="flex items-center gap-1.5 font-bold">
                        الدخول لمساحة العمل
                        <ArrowLeft class="h-4 w-4" />
                    </span>
                </PrimaryButton>
            </div>

            <!-- رابط التسجيل الجديد -->
            <div class="pt-4 border-t border-zinc-800/80 text-center text-xs text-zinc-400">
                معندكش حساب مسجّل لسه؟
                <Link
                    :href="route('register')"
                    class="font-bold text-emerald-400 hover:text-emerald-300 transition underline underline-offset-4 mr-1"
                >
                    افتح مساحتك مجاناً دلوقتي
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>