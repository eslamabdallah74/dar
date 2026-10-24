<script setup lang="ts">
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { MailCheck, LogOut } from 'lucide-vue-next';

const props = defineProps<{
    status?: string;
}>();

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="تأكيد البريد الإلكتروني — دار" />

        <div class="mb-6 text-center">
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                <MailCheck class="h-6 w-6" />
            </div>
            <h1 class="text-2xl font-bold text-white">تأكيد البريد الإلكتروني</h1>
            <p class="mt-1.5 text-xs text-zinc-300 font-medium">
                شكراً لانضمامك إلى دار! لقد أرسلنا رابط تحقق إلى بريدك الإلكتروني. يُرجى النقر على الرابط لتفعيل حسابك بالكامل.
            </p>
        </div>

        <div
            class="mb-4 rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-3 text-xs font-semibold text-emerald-400 text-center"
            v-if="verificationLinkSent"
        >
            تم إرسال رابط تأكيد جديد إلى عنوان البريد الإلكتروني الذي سجلت به.
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <PrimaryButton
                    class="w-full flex items-center justify-center gap-2 font-bold"
                    :class="{ 'opacity-50': form.processing }"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing">جاري الإرسال...</span>
                    <span v-else>إعادة إرسال رسالة التأكيد</span>
                </PrimaryButton>
            </div>

            <div class="pt-2 text-center">
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="inline-flex items-center gap-1.5 text-xs text-zinc-400 hover:text-rose-400 transition"
                >
                    <LogOut class="h-3.5 w-3.5" />
                    <span>تسجيل الخروج</span>
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
