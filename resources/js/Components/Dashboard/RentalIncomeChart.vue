<script setup lang="ts">
import { ref, computed } from 'vue';
import { ArrowUpRight, TrendingUp, Calendar, AlertCircle } from 'lucide-vue-next';

interface IncomeHistoryItem {
    month: string;
    year: number;
    amount: number;
}

const props = defineProps<{
    months?: string[];
    amounts?: number[];
    history?: IncomeHistoryItem[];
    hasHistory?: boolean;
    totalCollected?: number;
    currencySymbol?: string;
    currencyLabel?: string;
}>();

const hoveredIndex = ref<number | null>(null);

const activeMonths = computed(() => props.months || ['مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر']);
const activeAmounts = computed(() => props.amounts || [0, 0, 0, 0, 0, 0]);

const currencySymbol = computed(() => props.currencySymbol || 'ر.س');
const currencyLabel = computed(() => props.currencyLabel || 'بالعملة المحلية');

const totalIncome = computed(() => {
    if (props.totalCollected !== undefined) {
        return props.totalCollected;
    }
    return activeAmounts.value.reduce((acc, curr) => acc + curr, 0);
});

const hasData = computed(() => {
    if (props.hasHistory !== undefined) {
        return props.hasHistory;
    }
    return activeAmounts.value.some(a => a > 0);
});

// SVG Dimensions
const svgWidth = 640;
const svgHeight = 180;
const paddingX = 45;
const paddingY = 25;

const maxAmount = computed(() => {
    const maxVal = Math.max(...activeAmounts.value, 1000);
    const magnitude = Math.pow(10, Math.floor(Math.log10(maxVal)));
    return Math.ceil(maxVal / magnitude) * magnitude;
});

function getY(val: number): number {
    const max = maxAmount.value;
    const normalized = max > 0 ? (val / max) : 0;
    return svgHeight - paddingY - (normalized * (svgHeight - paddingY * 2));
}

function getX(index: number): number {
    const usableWidth = svgWidth - paddingX * 2;
    const len = activeMonths.value.length;
    if (len <= 1) return svgWidth / 2;
    return paddingX + (index / (len - 1)) * usableWidth;
}

// Points for smooth line / area
const chartPoints = computed(() => {
    return activeAmounts.value.map((amount, i) => ({
        x: getX(i),
        y: getY(amount),
        amount,
        month: activeMonths.value[i] || '',
    }));
});

const smoothPathD = computed(() => {
    const pts = chartPoints.value;
    if (pts.length === 0) return '';
    let d = `M ${pts[0].x} ${pts[0].y}`;
    for (let i = 0; i < pts.length - 1; i++) {
        const p0 = pts[i];
        const p1 = pts[i + 1];
        const mx = (p0.x + p1.x) / 2;
        d += ` C ${mx} ${p0.y}, ${mx} ${p1.y}, ${p1.x} ${p1.y}`;
    }
    return d;
});

const smoothAreaD = computed(() => {
    const pts = chartPoints.value;
    if (pts.length === 0) return '';
    const lastX = pts[pts.length - 1].x;
    const firstX = pts[0].x;
    return `${smoothPathD.value} L ${lastX} ${svgHeight - paddingY} L ${firstX} ${svgHeight - paddingY} Z`;
});

// Calculate growth percentage if there's history
const growthPercentage = computed(() => {
    const arr = activeAmounts.value;
    if (arr.length < 2) return null;
    const current = arr[arr.length - 1];
    const previous = arr[arr.length - 2];
    if (previous <= 0) return current > 0 ? 100 : 0;
    return Math.round(((current - previous) / previous) * 100);
});
</script>

<template>
    <div class="rounded-2xl border border-[#D6E0E7] bg-white p-6 shadow-2xs flex flex-col justify-between h-full">
        <!-- الرأس ومعلومات الإيراد -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-bold text-[#163E3E]">الإيرادات الإيجارية الشهرية</h3>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-[#E8F0F0] text-[#163E3E]">
                        آخر 6 أشهر
                    </span>
                </div>
                <p class="text-xs text-[#5B6770] font-medium mt-1">
                    صافي التحصيلات النقدية المستلمة فعلياً {{ currencyLabel }}
                </p>
            </div>

            <!-- إجمالي التحصيل والشارة -->
            <div class="flex items-center gap-3 self-start sm:self-auto">
                <div class="text-start sm:text-left">
                    <span class="text-xs text-[#5B6770] font-medium block">إجمالي المحصّل</span>
                    <span class="text-xl font-black text-[#163E3E]">
                        {{ Number(totalIncome).toLocaleString() }} {{ currencySymbol }}
                    </span>
                </div>

                <div
                    v-if="hasData && growthPercentage !== null"
                    class="flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold border"
                    :class="growthPercentage >= 0 ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200'"
                >
                    <ArrowUpRight v-if="growthPercentage >= 0" class="h-3.5 w-3.5 stroke-[2.5]" />
                    <span>{{ growthPercentage > 0 ? `+${growthPercentage}%` : `${growthPercentage}%` }}</span>
                </div>
            </div>
        </div>

        <!-- حالة عدم وجود بيانات (Empty State) -->
        <div
            v-if="!hasData"
            class="my-8 py-10 px-6 rounded-xl border border-dashed border-[#D6E0E7] bg-[#F8FAFC] flex flex-col items-center justify-center text-center space-y-3"
        >
            <div class="w-12 h-12 rounded-2xl bg-[#E8F0F0] text-[#163E3E] flex items-center justify-center">
                <TrendingUp class="h-6 w-6 stroke-[1.75]" />
            </div>
            <div class="max-w-md">
                <h4 class="text-sm font-bold text-[#163E3E]">لا توجد سجلات مدفوعات سابقة حتى الآن</h4>
                <p class="text-xs text-[#5B6770] font-medium mt-1 leading-relaxed">
                    ستظهر الإحصائيات ومنحنى التحصيل المالي تلقائياً بمجرد تسجيل أول دفعة إيجار في أحد عقاراتك.
                </p>
            </div>
        </div>

        <!-- مساحة الرسم البياني التفاعلي (عند توفر بيانات) -->
        <div v-else class="mt-6 relative">
            <svg
                :viewBox="`0 0 ${svgWidth} ${svgHeight}`"
                class="w-full h-[180px] overflow-visible"
                preserveAspectRatio="none"
            >
                <defs>
                    <linearGradient id="incomeGradient" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#163E3E" stop-opacity="0.25" />
                        <stop offset="60%" stop-color="#163E3E" stop-opacity="0.06" />
                        <stop offset="100%" stop-color="#163E3E" stop-opacity="0.0" />
                    </linearGradient>
                </defs>

                <!-- خطوط الشبكة الأفقية -->
                <line
                    v-for="tick in [1, 0.66, 0.33, 0]"
                    :key="tick"
                    :x1="paddingX"
                    :y1="paddingY + (1 - tick) * (svgHeight - paddingY * 2)"
                    :x2="svgWidth - paddingX"
                    :y2="paddingY + (1 - tick) * (svgHeight - paddingY * 2)"
                    stroke="#E8EFF4"
                    stroke-dasharray="3 3"
                    stroke-width="1"
                />

                <!-- مساحة التدرج تحت المنحنى -->
                <path :d="smoothAreaD" fill="url(#incomeGradient)" />

                <!-- خط المنحنى الأساسي -->
                <path
                    :d="smoothPathD"
                    fill="none"
                    stroke="#163E3E"
                    stroke-width="3"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />

                <!-- نقاط المنحنى التفاعلية -->
                <g v-for="(point, idx) in chartPoints" :key="idx">
                    <!-- خط عمودي عند التمرير -->
                    <line
                        v-if="hoveredIndex === idx"
                        :x1="point.x"
                        :y1="paddingY"
                        :x2="point.x"
                        :y2="svgHeight - paddingY"
                        stroke="#163E3E"
                        stroke-width="1"
                        stroke-dasharray="2 2"
                    />

                    <!-- الدائرة الخارجية -->
                    <circle
                        :cx="point.x"
                        :cy="point.y"
                        :r="hoveredIndex === idx ? 7 : (idx === chartPoints.length - 1 ? 5.5 : 4)"
                        fill="#FFFFFF"
                        stroke="#163E3E"
                        :stroke-width="hoveredIndex === idx ? 3.5 : 2.5"
                        class="transition-all duration-150 cursor-pointer"
                        @mouseenter="hoveredIndex = idx"
                        @mouseleave="hoveredIndex = null"
                    />

                    <!-- منطقة التقاط لمس أكبر للتفاعل السلس -->
                    <rect
                        :x="point.x - 25"
                        y="0"
                        width="50"
                        :height="svgHeight"
                        fill="transparent"
                        class="cursor-pointer"
                        @mouseenter="hoveredIndex = idx"
                        @mouseleave="hoveredIndex = null"
                    />
                </g>
            </svg>

            <!-- التلميح التفاعلي (Tooltip) عند التمرير -->
            <div
                v-if="hoveredIndex !== null && chartPoints[hoveredIndex]"
                class="absolute -top-3 pointer-events-none transform -translate-x-1/2 transition-all duration-150"
                :style="{ left: `${(chartPoints[hoveredIndex].x / svgWidth) * 100}%` }"
            >
                <div class="bg-[#163E3E] text-white text-xs py-1.5 px-3 rounded-xl shadow-lg whitespace-nowrap flex flex-col items-center">
                    <span class="text-[10px] text-white/70">{{ chartPoints[hoveredIndex].month }}</span>
                    <span class="font-bold font-mono">{{ Number(chartPoints[hoveredIndex].amount).toLocaleString() }} {{ currencySymbol }}</span>
                </div>
            </div>

            <!-- مسميات الأشهر بالأسفل -->
            <div class="flex items-center justify-between px-2 pt-3 border-t border-[#E8EFF4]">
                <span
                    v-for="(month, idx) in activeMonths"
                    :key="idx"
                    class="text-xs font-semibold text-[#5B6770] transition-colors"
                    :class="{ 'text-[#163E3E] font-bold': hoveredIndex === idx || idx === activeMonths.length - 1 }"
                >
                    {{ month }}
                </span>
            </div>
        </div>
    </div>
</template>
