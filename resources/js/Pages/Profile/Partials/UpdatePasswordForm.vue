<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { KeyRound, Lock, CheckCircle2 } from 'lucide-vue-next';

const passwordInput = ref<HTMLInputElement | null>(null);
const currentPasswordInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <section>
        <header class="pb-4 border-b border-[#E8EFF4]">
            <h2 class="text-base font-bold text-[#163E3E] flex items-center gap-2">
                <KeyRound class="w-5 h-5 text-[#163E3E]" />
                تغيير كلمة المرور
            </h2>

            <p class="mt-1 text-xs text-[#5B6770] font-medium">
                تأكد من استخدام كلمة مرور قوية وغير مكررة لحماية حسابك وعقاراتك.
            </p>
        </header>

        <form @submit.prevent="updatePassword" class="mt-6 space-y-5">
            <div>
                <InputLabel for="current_password" value="كلمة المرور الحالية" class="text-xs font-bold text-[#163E3E] mb-1.5" />

                <div class="relative">
                    <TextInput
                        id="current_password"
                        ref="currentPasswordInput"
                        v-model="form.current_password"
                        type="password"
                        class="w-full h-11 pr-10 pl-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right"
                        autocomplete="current-password"
                        placeholder="••••••••••••"
                    />
                    <Lock class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-[#8E9CA6]" />
                </div>

                <InputError
                    :message="form.errors.current_password"
                    class="mt-1.5"
                />
            </div>

            <div>
                <InputLabel for="password" value="كلمة المرور الجديدة" class="text-xs font-bold text-[#163E3E] mb-1.5" />

                <div class="relative">
                    <TextInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="w-full h-11 pr-10 pl-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right"
                        autocomplete="new-password"
                        placeholder="8 أحرف على الأقل"
                    />
                    <Lock class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-[#8E9CA6]" />
                </div>

                <InputError :message="form.errors.password" class="mt-1.5" />
            </div>

            <div>
                <InputLabel
                    for="password_confirmation"
                    value="تأكيد كلمة المرور الجديدة"
                    class="text-xs font-bold text-[#163E3E] mb-1.5"
                />

                <div class="relative">
                    <TextInput
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        class="w-full h-11 pr-10 pl-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right"
                        autocomplete="new-password"
                        placeholder="أعد كتابة كلمة المرور"
                    />
                    <Lock class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-[#8E9CA6]" />
                </div>

                <InputError
                    :message="form.errors.password_confirmation"
                    class="mt-1.5"
                />
            </div>

            <div class="flex items-center gap-4 pt-2">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="h-10 px-6 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold transition flex items-center gap-2 shadow-xs cursor-pointer disabled:opacity-50"
                >
                    <span v-if="form.processing">جاري التحديث...</span>
                    <span v-else>تحديث كلمة المرور</span>
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
                        تم تحديث كلمة المرور بنجاح
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
