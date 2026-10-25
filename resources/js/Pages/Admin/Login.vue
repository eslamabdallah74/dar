<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Lock, Mail, Shield, ShieldCheck } from 'lucide-vue-next';

const props = defineProps<{
    status?: string;
    defaultEmail?: string;
}>();

const form = useForm({
    email: props.defaultEmail || '',
    password: '',
    remember: true,
});

const submit = () => {
    form.post(route('admin.login.store'), {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="دخول لوحة الإدارة المركزية — Nook Mate" />

        <div class="mb-6 text-center space-y-2">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-400/10 border border-amber-400/20 text-amber-300 text-xs font-bold">
                <Shield class="w-3.5 h-3.5" />
                <span>بوابة الإشراف والإدارة المركزية</span>
            </div>
            <h1 class="text-2xl font-black text-white">تسجيل الدخول الإداري</h1>
            <p class="text-xs text-zinc-400 font-medium">
                تحكم كامل في كافة مساحات العمل والمستخدمين والعمليات المالية والاشتراكات.
            </p>
        </div>

        <div v-if="status" class="mb-4 rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-3 text-xs font-semibold text-emerald-400">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <!-- البريد الإلكتروني الإداري -->
            <div>
                <InputLabel for="email" value="البريد الإلكتروني الإداري" />
                <div class="relative mt-1">
                    <TextInput
                        id="email"
                        type="email"
                        class="block w-full pr-10 text-right"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="email"
                        placeholder="admin@nookmate.com"
                    />
                    <Mail class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-500" />
                </div>
                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <!-- كلمة المرور -->
            <div>
                <InputLabel for="password" value="كلمة المرور الإدارية" />
                <div class="relative mt-1">
                    <TextInput
                        id="password"
                        type="password"
                        class="block w-full pr-10 text-right"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••••••"
                    />
                    <Lock class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-500" />
                </div>
                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <!-- تذكر الجلسة -->
            <div class="flex items-center justify-between pt-1">
                <label class="flex items-center cursor-pointer select-none">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="mr-2 text-xs text-zinc-300 font-medium">تذكر الجلسة الإدارية على هذا الجهاز</span>
                </label>
            </div>

            <div class="pt-2">
                <PrimaryButton
                    class="w-full justify-center !py-3 text-sm font-bold !bg-amber-400 !text-slate-950 hover:!bg-amber-300 !ring-amber-400 transition cursor-pointer shadow-lg shadow-amber-400/10"
                    :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                    :disabled="form.processing"
                >
                    <ShieldCheck class="w-4 h-4 ml-1.5" />
                    <span>دخول لوحة التحكم المركزية</span>
                </PrimaryButton>
            </div>

            <div class="pt-4 border-t border-zinc-800 text-center">
                <Link
                    :href="route('login')"
                    class="text-xs text-zinc-400 hover:text-white transition font-medium"
                >
                    الذهاب إلى تسجيل دخول العملاء العاديين ↗
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
