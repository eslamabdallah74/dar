<script setup lang="ts">
import { computed } from 'vue';
import { Building, FilePlus, PlusCircle, Sparkles } from 'lucide-vue-next';

const props = defineProps<{
    userName?: string;
    workspaceName?: string;
    isEmpty?: boolean;
}>();

const emit = defineEmits<{
    (e: 'recordPayment'): void;
    (e: 'newLease'): void;
    (e: 'newProperty'): void;
    (e: 'toggleDemo'): void;
}>();

// تنسيق التاريخ باللغة العربية
const currentDateArabic = computed(() => {
    try {
        const options: Intl.DateTimeFormatOptions = {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        };
        return new Intl.DateTimeFormat('ar-EG', options).format(new Date());
    } catch {
        return 'اليوم';
    }
});
</script>

<template>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-[#D6E0E7]">
        <!-- الجانب الأيمن: الترحيب والتاريخ -->
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl sm:text-3xl font-extrabold text-[#0F2747] tracking-tight">
                    أهلاً بك، {{ userName || 'يا بطل' }} 👋
                </h1>
                <span v-if="isEmpty" class="px-2 py-0.5 rounded-full bg-amber-50 border border-amber-200 text-amber-700 text-[11px] font-bold">
                    محفظة جديدة
                </span>
            </div>
            <p class="mt-1 text-xs sm:text-sm font-semibold text-[#5B6770]">
                <span>{{ currentDateArabic }}</span>
                <span class="mx-2 text-[#D6E0E7]">•</span>
                <span>نظرة شاملة على أداء محفظتك العقارية والتحصيلات</span>
            </p>
        </div>

        <!-- الجانب الأيسر: الإجراءات السريعة (Quick Actions) -->
        <div class="flex flex-wrap items-center gap-2.5">
            <!-- زر تبديل وضع البيانات التجريبية للمعاينة -->
            <button
                @click="emit('toggleDemo')"
                type="button"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-[#D6E0E7] bg-white text-xs font-bold text-[#0F2747] hover:bg-[#F3F7FA] transition shadow-xs"
                title="معاينة اللوحة ببيانات حية نموذجية"
            >
                <Sparkles class="h-3.5 w-3.5 text-amber-500" />
                <span>معاينة بيانات تجريبية</span>
            </button>

            <!-- زر إضافة عقار / وحدة -->
            <button
                @click="emit('newProperty')"
                type="button"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-[#D6E0E7] bg-white text-xs font-bold text-[#0F2747] hover:border-[#0E6E7E] hover:text-[#0E6E7E] transition shadow-xs"
            >
                <Building class="h-4 w-4" />
                <span>عقار جديد</span>
            </button>

            <!-- زر إضافة عقد جديد -->
            <button
                @click="emit('newLease')"
                type="button"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#0F2747] text-white text-xs font-bold hover:bg-[#1a3860] transition shadow-xs"
            >
                <FilePlus class="h-4 w-4" />
                <span>عقد جديد</span>
            </button>

            <!-- زر تسجيل دفعة إيجار (CTA رئيسي) -->
            <button
                @click="emit('recordPayment')"
                type="button"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#0E6E7E] text-white text-xs font-bold hover:bg-[#0B5A68] transition shadow-sm"
            >
                <PlusCircle class="h-4 w-4" />
                <span>تسجيل دفعة إيجار</span>
            </button>
        </div>
    </div>
</template>
