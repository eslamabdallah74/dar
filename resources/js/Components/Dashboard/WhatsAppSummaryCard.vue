<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MessageSquare, CheckCheck, Clock, AlertCircle, ArrowLeft } from 'lucide-vue-next';

interface WhatsAppData {
    total: number;
    sent: number;
    delivered: number;
    pending: number;
    failed: number;
}

defineProps<{
    summary?: WhatsAppData;
}>();
</script>

<template>
    <div class="rounded-2xl border border-[#D6E0E7] bg-white p-6 shadow-2xs flex flex-col justify-between h-full">
        <div>
            <!-- الرأس -->
            <div class="flex items-center justify-between pb-3 border-b border-[#E8EFF4]">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center">
                        <MessageSquare class="h-4 w-4" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-[#163E3E]">تذكيرات واتساب التلقائية</h3>
                        <p class="text-xs text-[#5B6770] font-medium mt-0.5">
                            سجل رسائل المطالبات والتنبيهات الدورية
                        </p>
                    </div>
                </div>

                <span class="text-xs font-bold font-mono text-[#163E3E] bg-[#F8FAFC] px-2.5 py-1 rounded-lg border border-[#E8EFF4]">
                    إجمالي {{ summary?.total ?? 0 }} رسالة
                </span>
            </div>

            <!-- شبكة الحالات الأربعة -->
            <div class="mt-4 grid grid-cols-2 gap-3">
                <!-- تم الإرسال للمزود -->
                <div class="p-3 rounded-xl bg-[#F8FAFC] border border-[#E8EFF4]">
                    <div class="flex items-center gap-1.5 text-xs text-[#5B6770] font-medium">
                        <CheckCheck class="h-3.5 w-3.5 text-sky-600" />
                        <span>تم الإرسال</span>
                    </div>
                    <div class="text-xl font-bold font-mono text-[#163E3E] mt-1">
                        {{ summary?.sent ?? 0 }}
                    </div>
                    <span class="text-[10px] text-[#8E9CA6]">مقبولة لدى مزود الخدمة</span>
                </div>

                <!-- تم التسليم للمستأجر -->
                <div class="p-3 rounded-xl bg-emerald-50/50 border border-emerald-100">
                    <div class="flex items-center gap-1.5 text-xs text-emerald-800 font-medium">
                        <CheckCheck class="h-3.5 w-3.5 text-emerald-600 stroke-[2.5]" />
                        <span>تم التسليم</span>
                    </div>
                    <div class="text-xl font-bold font-mono text-emerald-900 mt-1">
                        {{ summary?.delivered ?? 0 }}
                    </div>
                    <span class="text-[10px] text-emerald-700">مؤكدة بتقرير التسليم</span>
                </div>

                <!-- قيد الإرسال -->
                <div class="p-3 rounded-xl bg-amber-50/50 border border-amber-100">
                    <div class="flex items-center gap-1.5 text-xs text-amber-800 font-medium">
                        <Clock class="h-3.5 w-3.5 text-amber-600" />
                        <span>قيد المعالجة</span>
                    </div>
                    <div class="text-xl font-bold font-mono text-amber-900 mt-1">
                        {{ summary?.pending ?? 0 }}
                    </div>
                    <span class="text-[10px] text-amber-700">في طابور الإرسال المجدول</span>
                </div>

                <!-- تعذر الإرسال -->
                <div class="p-3 rounded-xl bg-rose-50/50 border border-rose-100">
                    <div class="flex items-center gap-1.5 text-xs text-rose-800 font-medium">
                        <AlertCircle class="h-3.5 w-3.5 text-rose-600" />
                        <span>تعذر الإرسال</span>
                    </div>
                    <div class="text-xl font-bold font-mono text-rose-900 mt-1">
                        {{ summary?.failed ?? 0 }}
                    </div>
                    <span class="text-[10px] text-rose-700">رقم خاطئ أو تعذر الاتصال</span>
                </div>
            </div>

            <!-- ملاحظة دقة التسليم -->
            <p class="text-[11px] text-[#5B6770] font-medium mt-3 leading-relaxed">
                * يتم تحديث حالة التسليم الفعلية فور استلام تقرير التسليم من مزود خدمة واتساب المعتمد.
            </p>
        </div>

        <!-- رابط إدارة رسائل واتساب -->
        <div class="pt-4 border-t border-[#E8EFF4] mt-3">
            <Link
                :href="route('whatsapp.index')"
                class="w-full py-2 px-3 rounded-xl border border-[#D6E0E7] hover:border-[#163E3E] hover:bg-[#E8F0F0]/30 text-xs font-bold text-[#163E3E] transition flex items-center justify-center gap-2"
            >
                <span>سجل الرسائل وإعدادات القوالب</span>
                <ArrowLeft class="h-3.5 w-3.5" />
            </Link>
        </div>
    </div>
</template>

