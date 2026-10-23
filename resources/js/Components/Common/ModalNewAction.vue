<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Building2,
    CreditCard,
    FilePlus,
    UserPlus,
    Wrench,
    X,
} from 'lucide-vue-next';

defineProps<{
    show: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'recordPayment'): void;
    (e: 'addProperty'): void;
    (e: 'addRenter'): void;
    (e: 'addMaintenance'): void;
}>();
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 sm:p-6"
    >
        <!-- Backdrop -->
        <div
            @click="emit('close')"
            class="fixed inset-0 bg-[#0F2747]/60 backdrop-blur-xs transition-opacity"
        ></div>

        <!-- Modal Dialog -->
        <div
            class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] overflow-hidden z-10 animate-in fade-in zoom-in-95 p-6"
            dir="rtl"
        >
            <div class="flex items-center justify-between pb-4 border-b border-[#D6E0E7] mb-5">
                <div>
                    <h3 class="text-base font-bold text-[#0F2747]">إجراء سريع جديد</h3>
                    <p class="text-xs text-[#5B6770]">اختر نوع العملية التي ترغب بإنشائها</p>
                </div>
                <button
                    @click="emit('close')"
                    type="button"
                    class="p-2 text-[#5B6770] hover:text-[#0F2747] hover:bg-slate-100 rounded-xl transition cursor-pointer"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <div class="grid grid-cols-1 gap-3">
                <!-- 1. عقد جديد -->
                <Link
                    :href="route('leases.create')"
                    @click="emit('close')"
                    class="flex items-center gap-4 p-3.5 rounded-2xl border border-[#D6E0E7] hover:border-[#0E6E7E] hover:bg-[#F3F7FA] transition group text-right"
                >
                    <div class="w-11 h-11 rounded-xl bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center shrink-0 group-hover:bg-[#0E6E7E] group-hover:text-white transition">
                        <FilePlus class="w-5 h-5" />
                    </div>
                    <div>
                        <div class="text-sm font-bold text-[#0F2747] group-hover:text-[#0E6E7E] transition">
                            عقد إيجار جديد
                        </div>
                        <div class="text-xs text-[#5B6770]">
                            إنشاء عقد وربطه بوحدة ومستأجر مع كشف التعارض
                        </div>
                    </div>
                </Link>

                <!-- 2. تسجيل دفعة -->
                <button
                    @click="() => { emit('close'); emit('recordPayment'); }"
                    type="button"
                    class="flex items-center gap-4 p-3.5 rounded-2xl border border-[#D6E0E7] hover:border-[#0E6E7E] hover:bg-[#F3F7FA] transition group text-right cursor-pointer w-full"
                >
                    <div class="w-11 h-11 rounded-xl bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center shrink-0 group-hover:bg-[#0E6E7E] group-hover:text-white transition">
                        <CreditCard class="w-5 h-5" />
                    </div>
                    <div>
                        <div class="text-sm font-bold text-[#0F2747] group-hover:text-[#0E6E7E] transition">
                            تسجيل دفعة يدوية
                        </div>
                        <div class="text-xs text-[#5B6770]">
                            تحصيل نقدي أو حوالة بنكية وسند قبض إلكتروني فوري
                        </div>
                    </div>
                </button>

                <!-- 3. إضافة عقار جديد -->
                <Link
                    :href="route('properties.index')"
                    @click="emit('close')"
                    class="flex items-center gap-4 p-3.5 rounded-2xl border border-[#D6E0E7] hover:border-[#0E6E7E] hover:bg-[#F3F7FA] transition group text-right"
                >
                    <div class="w-11 h-11 rounded-xl bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center shrink-0 group-hover:bg-[#0E6E7E] group-hover:text-white transition">
                        <Building2 class="w-5 h-5" />
                    </div>
                    <div>
                        <div class="text-sm font-bold text-[#0F2747] group-hover:text-[#0E6E7E] transition">
                            إضافة عقار أو مبنى
                        </div>
                        <div class="text-xs text-[#5B6770]">
                            تعريف عمارة، مجمع سكني أو شقق جديدة
                        </div>
                    </div>
                </Link>

                <!-- 4. إضافة مستأجر جديد -->
                <Link
                    :href="route('renters.index')"
                    @click="emit('close')"
                    class="flex items-center gap-4 p-3.5 rounded-2xl border border-[#D6E0E7] hover:border-[#0E6E7E] hover:bg-[#F3F7FA] transition group text-right"
                >
                    <div class="w-11 h-11 rounded-xl bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center shrink-0 group-hover:bg-[#0E6E7E] group-hover:text-white transition">
                        <UserPlus class="w-5 h-5" />
                    </div>
                    <div>
                        <div class="text-sm font-bold text-[#0F2747] group-hover:text-[#0E6E7E] transition">
                            إضافة مستأجر جديد
                        </div>
                        <div class="text-xs text-[#5B6770]">
                            تسجيل بيانات الهوية ورقم WhatsApp
                        </div>
                    </div>
                </Link>

                <!-- 5. طلب صيانة -->
                <Link
                    :href="route('maintenance.index')"
                    @click="emit('close')"
                    class="flex items-center gap-4 p-3.5 rounded-2xl border border-[#D6E0E7] hover:border-[#0E6E7E] hover:bg-[#F3F7FA] transition group text-right"
                >
                    <div class="w-11 h-11 rounded-xl bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center shrink-0 group-hover:bg-[#0E6E7E] group-hover:text-white transition">
                        <Wrench class="w-5 h-5" />
                    </div>
                    <div>
                        <div class="text-sm font-bold text-[#0F2747] group-hover:text-[#0E6E7E] transition">
                            طلب صيانة جديد
                        </div>
                        <div class="text-xs text-[#5B6770]">
                            إدراج عطل أو بلاغ صيانة لوحدة معينة
                        </div>
                    </div>
                </Link>
            </div>
        </div>
    </div>
</template>

