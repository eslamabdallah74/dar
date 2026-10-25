<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Building2,
    UserPlus,
    FileText,
    CreditCard,
    Receipt,
    Wrench,
    Zap,
} from 'lucide-vue-next';

const emit = defineEmits<{
    (e: 'recordPayment'): void;
    (e: 'addProperty'): void;
}>();

function navigate(routeName: string, params: Record<string, any> = {}) {
    router.visit(route(routeName, params));
}
</script>

<template>
    <div class="rounded-2xl border border-[#D6E0E7] bg-white p-4 sm:p-5 shadow-2xs">
        <div class="flex items-center justify-between mb-3.5">
            <div class="flex items-center gap-2">
                <div class="w-6 h-6 rounded-lg bg-[#E8F0F0] text-[#163E3E] flex items-center justify-center">
                    <Zap class="w-3.5 h-3.5 fill-[#163E3E]" />
                </div>
                <span class="text-xs font-bold text-[#163E3E]">إجراءات واختصارات سريعة</span>
            </div>
            <span class="text-[11px] text-[#5B6770] font-medium hidden sm:inline">
                الوصول المباشر إلى أهم العمليات والنماذج
            </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5">
            <!-- 1. إضافة عقار -->
            <button
                @click="emit('addProperty')"
                type="button"
                class="h-11 px-3 rounded-xl border border-[#E8EFF4] hover:border-[#163E3E] hover:bg-[#E8F0F0]/30 text-xs font-bold text-[#163E3E] hover:text-[#163E3E] transition shadow-2xs flex items-center justify-center gap-2 group cursor-pointer"
            >
                <Building2 class="h-4 w-4 text-[#163E3E] group-hover:scale-110 transition-transform" />
                <span>+ عقار جديد</span>
            </button>

            <!-- 2. إضافة مستأجر -->
            <button
                @click="navigate('renters.index', { new: 1 })"
                type="button"
                class="h-11 px-3 rounded-xl border border-[#E8EFF4] hover:border-[#163E3E] hover:bg-[#E8F0F0]/30 text-xs font-bold text-[#163E3E] hover:text-[#163E3E] transition shadow-2xs flex items-center justify-center gap-2 group cursor-pointer"
            >
                <UserPlus class="h-4 w-4 text-[#163E3E] group-hover:scale-110 transition-transform" />
                <span>+ مستأجر</span>
            </button>

            <!-- 3. توثيق عقد -->
            <button
                @click="navigate('leases.create')"
                type="button"
                class="h-11 px-3 rounded-xl border border-[#E8EFF4] hover:border-[#163E3E] hover:bg-[#E8F0F0]/30 text-xs font-bold text-[#163E3E] hover:text-[#163E3E] transition shadow-2xs flex items-center justify-center gap-2 group cursor-pointer"
            >
                <FileText class="h-4 w-4 text-[#163E3E] group-hover:scale-110 transition-transform" />
                <span>+ عقد إيجار</span>
            </button>

            <!-- 4. تسجيل دفعة (مميز) -->
            <button
                @click="emit('recordPayment')"
                type="button"
                class="h-11 px-3 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold transition shadow-xs flex items-center justify-center gap-2 group cursor-pointer"
            >
                <CreditCard class="h-4 w-4 text-white group-hover:scale-110 transition-transform" />
                <span>+ تسجيل دفعة</span>
            </button>

            <!-- 5. تسجيل مصروف -->
            <button
                @click="navigate('expenses.index', { new: 1 })"
                type="button"
                class="h-11 px-3 rounded-xl border border-[#E8EFF4] hover:border-[#163E3E] hover:bg-[#E8F0F0]/30 text-xs font-bold text-[#163E3E] hover:text-[#163E3E] transition shadow-2xs flex items-center justify-center gap-2 group cursor-pointer"
            >
                <Receipt class="h-4 w-4 text-[#163E3E] group-hover:scale-110 transition-transform" />
                <span>+ تسجيل مصروف</span>
            </button>

            <!-- 6. طلب صيانة -->
            <button
                @click="navigate('maintenance.index', { new: 1 })"
                type="button"
                class="h-11 px-3 rounded-xl border border-[#E8EFF4] hover:border-[#163E3E] hover:bg-[#E8F0F0]/30 text-xs font-bold text-[#163E3E] hover:text-[#163E3E] transition shadow-2xs flex items-center justify-center gap-2 group cursor-pointer"
            >
                <Wrench class="h-4 w-4 text-[#163E3E] group-hover:scale-110 transition-transform" />
                <span>+ طلب صيانة</span>
            </button>
        </div>
    </div>
</template>
