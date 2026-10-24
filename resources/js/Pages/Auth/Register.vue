<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, Lock, User } from 'lucide-vue-next';

const form = useForm({
    name: '',
    password: '',
    password_confirmation: '',
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
        <Head title="إنشاء حساب جديد — دار" />

        <div class="mb-3 text-center sm:mb-6">
            <h1 class="text-2xl font-bold text-white">ابدأ بأول عقار لك</h1>
            <p class="mt-1.5 text-xs text-zinc-300 font-medium">
                أنشئ حسابك، ثم أضف عقارك الأول مباشرة من لوحة التحكم.
            </p>
        </div>

        <!-- شارة البداية السريعة -->
        <div class="mb-3 flex items-center gap-2 rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-3 text-xs font-semibold text-emerald-400 sm:mb-5">
            <CheckCircle2 class="h-4 w-4 shrink-0 text-emerald-400" />
            <span>أول 5 وحدات مجاناً بالكامل • بدون أي كروت بنكية</span>
        </div>

        <form @submit.prevent="submit" class="space-y-2 sm:space-y-4">
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
                    class="w-full flex items-center justify-center gap-2 font-bold"
                    :class="{ 'opacity-50': form.processing }"
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
    </GuestLayout>
</template>