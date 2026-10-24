<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { User, Mail, CheckCircle2 } from 'lucide-vue-next';

defineProps<{
    mustVerifyEmail?: Boolean;
    status?: String;
}>();

const user = (usePage().props.auth as any).user;

const form = useForm({
    name: user.name,
    email: user.email,
});
</script>

<template>
    <section>
        <header class="pb-4 border-b border-[#E8EFF4]">
            <h2 class="text-base font-bold text-[#0F2747] flex items-center gap-2">
                <User class="w-5 h-5 text-[#0E6E7E]" />
                البيانات الشخصية للحساب
            </h2>

            <p class="mt-1 text-xs text-[#5B6770] font-medium">
                تعديل اسمك وبريدك الإلكتروني المسجل في منصة دار.
            </p>
        </header>

        <form
            @submit.prevent="form.patch(route('profile.update'))"
            class="mt-6 space-y-5"
        >
            <div>
                <InputLabel for="name" value="الاسم الكامل" class="text-xs font-bold text-[#0F2747] mb-1.5" />

                <div class="relative">
                    <TextInput
                        id="name"
                        type="text"
                        class="w-full h-11 pr-10 pl-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-right"
                        v-model="form.name"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="أحمد حسن"
                    />
                    <User class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-[#8E9CA6]" />
                </div>

                <InputError class="mt-1.5" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="البريد الإلكتروني" class="text-xs font-bold text-[#0F2747] mb-1.5" />

                <div class="relative">
                    <TextInput
                        id="email"
                        type="email"
                        class="w-full h-11 pr-10 pl-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-right"
                        v-model="form.email"
                        required
                        autocomplete="username"
                        placeholder="ahmed@example.com"
                    />
                    <Mail class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-[#8E9CA6]" />
                </div>

                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-xs text-[#5B6770]">
                    بريدك الإلكتروني غير مفعّل حتى الآن.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="font-bold text-[#0E6E7E] hover:underline"
                    >
                        اضغط هنا لإعادة إرسال رابط التفعيل.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-xs font-bold text-emerald-700 bg-emerald-50 p-2.5 rounded-lg border border-emerald-200"
                >
                    تم إرسال رابط تفعيل جديد إلى بريدك الإلكتروني.
                </div>
            </div>

            <div class="flex items-center gap-4 pt-2">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="h-10 px-6 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-bold transition flex items-center gap-2 shadow-xs cursor-pointer disabled:opacity-50"
                >
                    <span v-if="form.processing">جاري الحفظ...</span>
                    <span v-else>حفظ التعديلات</span>
                </button>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-xs text-emerald-700 font-bold flex items-center gap-1.5"
                    >
                        <CheckCircle2 class="w-4 h-4" />
                        تم الحفظ بنجاح
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
