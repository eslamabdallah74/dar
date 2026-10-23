<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Building2,
    Home,
    UserPlus,
    FileText,
    CreditCard,
    Receipt,
    Plus,
    Wrench,
} from 'lucide-vue-next';

const emit = defineEmits<{
    (e: 'recordPayment'): void;
}>();

function navigate(routeName: string) {
    router.visit(route(routeName));
}
</script>

<template>
    <div class="rounded-2xl border border-[#D6E0E7] bg-white p-4 sm:p-5 shadow-2xs">
        <div class="flex items-center justify-between mb-3.5">
            <span class="text-xs font-bold text-[#0F2747] flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-[#0E6E7E]"></span>
                <span>إجراءات سريعة واختصارات فورية</span>
            </span>
            <span class="text-[11px] text-[#5B6770] font-medium hidden sm:inline">
                الوصول السريع إلى النماذج الإدارية الأساسية
            </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5">
            <!-- إضافة عقار -->
            <button
                @click="navigate('properties.create')"
                type="button"
                class="h-11 px-3 rounded-xl border border-[#E8EFF4] hover:border-[#0E6E7E] hover:bg-[#E8F3F5]/30 text-xs font-bold text-[#0F2747] hover:text-[#0E6E7E] transition shadow-2xs flex items-center justify-center gap-2 group"
            >
                <Building2 class="h-4 w-4 text-[#0E6E7E] group-hover:scale-110 transition-transform" />
                <span>+ عقار جديد</span>
            </button>

            <!-- إضافة مستأجر -->
            <button
                @click="navigate('renters.create')"
                type="button"
                class="h-11 px-3 rounded-xl border border-[#E8EFF4] hover:border-[#0E6E7E] hover:bg-[#E8F3F5]/30 text-xs font-bold text-[#0F2747] hover:text-[#0E6E7E] transition shadow-2xs flex items-center justify-center gap-2 group"
            >
                <UserPlus class="h-4 w-4 text-[#0E6E7E] group-hover:scale-110 transition-transform" />
                <span>+ مستأجر</span>
            </button>

            <!-- توثيق عقد -->
            <button
                @click="navigate('leases.create')"
                type="button"
                class="h-11 px-3 rounded-xl border border-[#E8EFF4] hover:border-[#0E6E7E] hover:bg-[#E8F3F5]/30 text-xs font-bold text-[#0F2747] hover:text-[#0E6E7E] transition shadow-2xs flex items-center justify-center gap-2 group"
            >
                <FileText class="h-4 w-4 text-[#0E6E7E] group-hover:scale-110 transition-transform" />
                <span>+ عقد إيجار</span>
            </button>

            <!-- تسجيل دفعة -->
            <button
                @click="emit('recordPayment')"
                type="button"
                class="h-11 px-3 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-bold transition shadow-xs flex items-center justify-center gap-2 group"
            >
                <CreditCard class="h-4 w-4 text-white group-hover:scale-110 transition-transform" />
                <span>+ تسجيل دفعة</span>
            </button>

            <!-- تسجيل مصروف -->
            <button
                @click="navigate('expenses.create')"
                type="button"
                class="h-11 px-3 rounded-xl border border-[#E8EFF4] hover:border-[#0E6E7E] hover:bg-[#E8F3F5]/30 text-xs font-bold text-[#0F2747] hover:text-[#0E6E7E] transition shadow-2xs flex items-center justify-center gap-2 group"
            >
                <Receipt class="h-4 w-4 text-[#0E6E7E] group-hover:scale-110 transition-transform" />
                <span>+ تسجيل مصروف</span>
            </button>

            <!-- طلب صيانة -->
            <button
                @click="navigate('maintenance.create')"
                type="button"
                class="h-11 px-3 rounded-xl border border-[#E8EFF4] hover:border-[#0E6E7E] hover:bg-[#E8F3F5]/30 text-xs font-bold text-[#0F2747] hover:text-[#0E6E7E] transition shadow-2xs flex items-center justify-center gap-2 group"
            >
                <Wrench class="h-4 w-4 text-[#0E6E7E] group-hover:scale-110 transition-transform" />
                <span>+ طلب صيانة</span>
            </button>
        </div>
    </div>
</template>

