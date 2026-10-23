<script setup lang="ts">
import { computed } from 'vue';
import { CheckCircle2, CircleDollarSign, Clock, HelpCircle, TrendingUp } from 'lucide-vue-next';

const props = defineProps<{
    monthlyTarget: number;
    monthlyCollected: number;
    totalOverdue: number;
    currency?: string;
}>();

function formatCurrency(val: number): string {
    return new Intl.NumberFormat('en-US').format(Math.round(val || 0));
}

const remainingDue = computed(() => {
    return Math.max(0, (props.monthlyTarget || 0) - (props.monthlyCollected || 0));
});

const collectedPercent = computed(() => {
    if (!props.monthlyTarget) return 0;
    return Math.min(100, Math.round((props.monthlyCollected / props.monthlyTarget) * 100));
});

const remainingPercent = computed(() => {
    if (!props.monthlyTarget) return 0;
    return Math.max(0, 100 - collectedPercent.value);
});
</script>

<template>
    <div class="rounded-2xl border border-[#D6E0E7] bg-white p-6 shadow-xs">
        <!-- العنوان والرأس -->
        <div class="flex items-center justify-between pb-4 border-b border-[#D6E0E7]">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center">
                    <CircleDollarSign class="h-4 w-4" />
                </div>
                <div>
                    <h2 class="text-base font-extrabold text-[#0F2747]">مؤشر التحصيل الشهري</h2>
                    <p class="text-[11px] font-semibold text-[#5B6770]">مقارنة الإيجارات المحصلة بالمستهدفة لهذا الشهر</p>
                </div>
            </div>

            <div class="text-left font-mono">
                <span class="text-xs font-bold text-[#5B6770]">المستهدف: </span>
                <span class="text-sm font-extrabold text-[#0F2747]">{{ formatCurrency(monthlyTarget) }} {{ currency || 'ج.م' }}</span>
            </div>
        </div>

        <!-- شريط التقدم التراكمي المقسم (Multi-Segment Bar) -->
        <div class="mt-5">
            <div class="flex items-center justify-between text-xs font-bold mb-2">
                <span class="text-emerald-700 flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                    محصل ({{ collectedPercent }}%)
                </span>
                <span class="text-[#5B6770] flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                    متبقي للاستحقاق ({{ remainingPercent }}%)
                </span>
                <span v-if="totalOverdue > 0" class="text-rose-600 flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                    متأخرات سابقة
                </span>
            </div>

            <div class="w-full h-3 bg-[#E8EFF4] rounded-full overflow-hidden flex">
                <div
                    class="bg-emerald-500 transition-all duration-500"
                    :style="{ width: `${collectedPercent}%` }"
                    title="المحصل"
                ></div>
                <div
                    class="bg-amber-400 transition-all duration-500"
                    :style="{ width: `${remainingPercent}%` }"
                    title="مستحق متبقي"
                ></div>
            </div>
        </div>

        <!-- بطاقات التوزيع الثلاثية -->
        <div class="grid grid-cols-3 gap-3 mt-5 pt-4 border-t border-[#D6E0E7]">
            <!-- المحصل بالفعل -->
            <div class="rounded-xl bg-emerald-50/60 border border-emerald-100 p-3">
                <span class="text-[11px] font-bold text-emerald-800">المحصل فعلياً</span>
                <div class="mt-1 flex items-baseline gap-1">
                    <span class="text-lg font-black text-emerald-700 font-mono">
                        {{ formatCurrency(monthlyCollected) }}
                    </span>
                    <span class="text-[10px] font-bold text-emerald-600">{{ currency || 'ج.م' }}</span>
                </div>
            </div>

            <!-- المتبقي من الشهر -->
            <div class="rounded-xl bg-amber-50/60 border border-amber-100 p-3">
                <span class="text-[11px] font-bold text-amber-800">مستحق خلال الشهر</span>
                <div class="mt-1 flex items-baseline gap-1">
                    <span class="text-lg font-black text-amber-700 font-mono">
                        {{ formatCurrency(remainingDue) }}
                    </span>
                    <span class="text-[10px] font-bold text-amber-600">{{ currency || 'ج.م' }}</span>
                </div>
            </div>

            <!-- المتأخرات السابقة -->
            <div class="rounded-xl bg-rose-50/60 border border-rose-100 p-3">
                <span class="text-[11px] font-bold text-rose-800">متأخرات إجمالية</span>
                <div class="mt-1 flex items-baseline gap-1">
                    <span class="text-lg font-black text-rose-700 font-mono">
                        {{ formatCurrency(totalOverdue) }}
                    </span>
                    <span class="text-[10px] font-bold text-rose-600">{{ currency || 'ج.م' }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
