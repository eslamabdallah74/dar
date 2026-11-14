<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, Lock, Mail, ShieldCheck } from 'lucide-vue-next';

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
    <GuestLayout image-background>
        <Head>
            <title>تسجيل الدخول | Nook Mate</title>
            <meta
                name="description"
                head-key="description"
                content="سجّل الدخول إلى مساحة عملك في Nook Mate لمتابعة عقاراتك، إدارة المستأجرين، عقود الإيجار، وسندات القبض الفورية بكل سهولة وأمان."
            />
            <meta name="robots" head-key="robots" content="noindex, follow" />
        </Head>

        <div class="mb-3 text-center sm:mb-6">
            <h1 class="text-2xl font-bold text-white">تسجيل الدخول إلى Nook Mate</h1>
            <p class="mt-1.5 text-xs text-zinc-300 font-medium">
                أهلاً بك مجدداً — سجّل دخولك لمتابعة عقاراتك، المستأجرين، وتحصيلات الإيجار.
            </p>
        </div>

        <div v-if="status" class="mb-4 rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-3 text-xs font-semibold text-emerald-400">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-2 sm:space-y-4">
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
                        autofocus
                        autocomplete="email"
                        placeholder="ahmed@example.com"
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
                    class="w-full"
                    :loading="form.processing"
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
            <div class="border-t border-zinc-800/80 pt-2 text-center text-xs text-zinc-400 sm:pt-4">
                معندكش حساب مسجّل لسه؟
                <Link
                    :href="route('register')"
                    class="font-bold text-emerald-400 hover:text-emerald-300 transition underline underline-offset-4 mr-1"
                >
                    افتح مساحتك مجاناً دلوقتي
                </Link>
            </div>
        </form>

        <!-- ملخص مزايا المنصة والأمان (Platform Highlights & Trust) -->
        <div class="mt-6 border-t border-white/10 pt-4 text-right">
            <h2 class="text-xs font-bold text-zinc-200 mb-2">لماذا يختار ملاك ومديرو العقارات Nook Mate؟</h2>
            <ul class="space-y-1.5 text-[11px] text-zinc-300">
                <li class="flex items-center gap-1.5">
                    <CheckCircle2 class="h-3.5 w-3.5 text-emerald-400 shrink-0" />
                    <span>نظام الصناديق المتداخلة لإدارة مرنة من المبنى الكامل إلى السرير المفرد.</span>
                </li>
                <li class="flex items-center gap-1.5">
                    <CheckCircle2 class="h-3.5 w-3.5 text-emerald-400 shrink-0" />
                    <span>تذكيرات آلية للمستأجرين بمواعيد الإيجار عبر واتساب وسندات قبض إلكترونية معتمدة.</span>
                </li>
                <li class="flex items-center gap-1.5">
                    <ShieldCheck class="h-3.5 w-3.5 text-emerald-400 shrink-0" />
                    <span>بيانات مشفرة وسحابية آمنة تضمن استمرارية أعمالك العقارية وحمايتها دائماً.</span>
                </li>
            </ul>
        </div>
    </GuestLayout>
</template>
