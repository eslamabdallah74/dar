<script setup lang="ts">
import { ArrowLeft, Building2, CheckCircle2, FileText, MessageSquare, Sparkles } from 'lucide-vue-next';

defineProps<{
    workspaceName?: string;
}>();

const emit = defineEmits<{
    (e: 'newProperty'): void;
    (e: 'toggleDemo'): void;
}>();
</script>

<template>
    <div class="rounded-3xl border border-[#D6E0E7] bg-white p-6 sm:p-10 shadow-sm relative overflow-hidden">
        <!-- خلفية تزيينية مستوحاة من أفق العمارة واللون النيلي -->
        <div class="absolute -top-24 -left-24 w-80 h-80 rounded-full bg-[#E3F1F4]/60 pointer-events-none blur-3xl"></div>
        <div class="absolute -bottom-24 -right-24 w-80 h-80 rounded-full bg-emerald-50/60 pointer-events-none blur-3xl"></div>

        <div class="relative z-10 max-w-3xl mx-auto text-center">
            <!-- شارة البداية -->
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#E3F1F4] border border-[#0E6E7E]/20 text-[#0E6E7E] text-xs font-bold mb-4">
                <Sparkles class="h-3.5 w-3.5" />
                <span>مساحتك العقارية جاهزة للانطلاق</span>
            </div>

            <h2 class="text-2xl sm:text-3xl font-extrabold text-[#0F2747] tracking-tight">
                ابدأ بإضافة أول عمارة أو شقة في محفظتك 🏢
            </h2>
            <p class="mt-2.5 text-xs sm:text-sm font-semibold text-[#5B6770] max-w-xl mx-auto">
                نظام «دار» مصمم ليريحك من دفاتر الإيجارات اليدوية ورسائل الشات المتفرقة، نظّم وحداتك في 3 خطوات بسيطة:
            </p>

            <!-- الخطوات الثلاث -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 my-8 text-right">
                <div class="rounded-2xl border border-[#D6E0E7] bg-[#F8FAFC] p-4 flex flex-col justify-between">
                    <div>
                        <div class="w-8 h-8 rounded-xl bg-[#0F2747] text-white flex items-center justify-center font-bold text-xs mb-3">
                            1
                        </div>
                        <h3 class="text-xs font-bold text-[#0F2747]">أضف عقارك الأول</h3>
                        <p class="text-[11px] font-semibold text-[#5B6770] mt-1">
                            سجّل عمارة أو فيلا مع أرقام الشقق أو الغرف أو السراير.
                        </p>
                    </div>
                </div>

                <div class="rounded-2xl border border-[#D6E0E7] bg-[#F8FAFC] p-4 flex flex-col justify-between">
                    <div>
                        <div class="w-8 h-8 rounded-xl bg-[#0E6E7E] text-white flex items-center justify-center font-bold text-xs mb-3">
                            2
                        </div>
                        <h3 class="text-xs font-bold text-[#0F2747]">سجّل المستأجر والعقد</h3>
                        <p class="text-[11px] font-semibold text-[#5B6770] mt-1">
                            حدد قيمة الإيجار الشهري وتاريخ الاستحقاق ورقم الواتساب.
                        </p>
                    </div>
                </div>

                <div class="rounded-2xl border border-[#D6E0E7] bg-[#F8FAFC] p-4 flex flex-col justify-between">
                    <div>
                        <div class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-xs mb-3">
                            3
                        </div>
                        <h3 class="text-xs font-bold text-[#0F2747]">تابع التحصيل بنقرة</h3>
                        <p class="text-[11px] font-semibold text-[#5B6770] mt-1">
                            سجّل دفعات الكاش والإنستاباي وأرسل تذكيرات الواتساب فوراً.
                        </p>
                    </div>
                </div>
            </div>

            <!-- أزرار الإجراء -->
            <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                <button
                    @click="emit('newProperty')"
                    type="button"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-[#0E6E7E] text-white text-xs font-bold hover:bg-[#0B5A68] transition shadow-md"
                >
                    <Building2 class="h-4 w-4" />
                    <span>إضافة أول عقار الآن</span>
                    <ArrowLeft class="h-4 w-4" />
                </button>

                <button
                    @click="emit('toggleDemo')"
                    type="button"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl border border-[#D6E0E7] bg-white text-xs font-bold text-[#0F2747] hover:bg-[#F3F7FA] transition"
                >
                    <Sparkles class="h-4 w-4 text-amber-500" />
                    <span>معاينة لوحة التحكم ببيانات نموذجية</span>
                </button>
            </div>
        </div>
    </div>
</template>
