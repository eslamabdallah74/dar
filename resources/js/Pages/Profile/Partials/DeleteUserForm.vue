<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import { AlertTriangle, Trash2, Lock, X } from 'lucide-vue-next';

const confirmingUserDeletion = ref(false);
const passwordInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;
    nextTick(() => passwordInput.value?.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value?.focus(),
        onFinish: () => {
            form.reset();
        },
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;
    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-4">
        <header class="pb-4 border-b border-[#E8EFF4]">
            <h2 class="text-base font-bold text-rose-700 flex items-center gap-2">
                <AlertTriangle class="w-5 h-5" />
                حذف الحساب نهائياً
            </h2>

            <p class="mt-1 text-xs text-[#5B6770] font-medium">
                بمجرد حذف حسابك، سيتم مسح كافة البيانات والعقود والوحدات التابعة له نهائياً. لا يمكن التراجع عن هذا الإجراء.
            </p>
        </header>

        <div class="pt-2">
            <button
                type="button"
                @click="confirmUserDeletion"
                class="h-10 px-4 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-bold transition inline-flex items-center gap-2 cursor-pointer"
            >
                <Trash2 class="w-4 h-4" />
                <span>حذف حسابي من المنصة</span>
            </button>
        </div>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-6 text-right" dir="rtl">
                <div class="flex items-center justify-between pb-3 border-b border-[#E8EFF4]">
                    <h3 class="text-base font-bold text-rose-700 flex items-center gap-2">
                        <AlertTriangle class="w-5 h-5 text-rose-600" />
                        تأكيد حذف الحساب نهائياً
                    </h3>
                    <button
                        type="button"
                        @click="closeModal"
                        class="p-1 rounded-lg text-[#8E9CA6] hover:text-[#0F2747] hover:bg-[#F3F7FA] transition"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <p class="mt-4 text-xs text-[#5B6770] leading-relaxed">
                    هل أنت متأكد تماماً من رغبتك في حذف الحساب؟ سيتم إلغاء كافة الصلاحيات وحذف البيانات المرتبطة بالحساب نهائياً. يُرجى إدخال كلمة المرور لتأكيد الإجراء.
                </p>

                <div class="mt-5">
                    <InputLabel
                        for="password"
                        value="كلمة المرور لتأكيد الحذف"
                        class="text-xs font-bold text-[#0F2747] mb-1.5"
                    />

                    <div class="relative">
                        <TextInput
                            id="password"
                            ref="passwordInput"
                            v-model="form.password"
                            type="password"
                            class="w-full h-11 pr-10 pl-3.5 rounded-xl border border-rose-200 bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-rose-500 focus:bg-white focus:ring-1 focus:ring-rose-500 transition text-right"
                            placeholder="••••••••••••"
                            @keyup.enter="deleteUser"
                        />
                        <Lock class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-rose-400" />
                    </div>

                    <InputError :message="form.errors.password" class="mt-1.5" />
                </div>

                <div class="mt-6 flex items-center justify-start gap-2.5 pt-4 border-t border-[#E8EFF4]">
                    <button
                        type="button"
                        :disabled="form.processing"
                        @click="deleteUser"
                        class="h-10 px-5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition flex items-center gap-2 shadow-xs cursor-pointer disabled:opacity-50"
                    >
                        <Trash2 class="w-4 h-4" />
                        <span v-if="form.processing">جاري الحذف...</span>
                        <span v-else>تأكيد الحذف النهائي</span>
                    </button>

                    <button
                        type="button"
                        @click="closeModal"
                        class="h-10 px-4 rounded-xl border border-[#D6E0E7] bg-white hover:bg-[#F3F7FA] text-[#0F2747] text-xs font-bold transition cursor-pointer"
                    >
                        إلغاء
                    </button>
                </div>
            </div>
        </Modal>
    </section>
</template>
