<script setup lang="ts">
import { ref } from 'vue';
import { ArrowUpRight } from 'lucide-vue-next';

const props = defineProps<{
    title?: string;
    subtitle?: string;
    currentRate?: string;
    badge?: string;
    months?: string[];
    rates?: number[];
}>();

const hoveredIndex = ref<number | null>(null);

const defaultMonths = ['مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر'];
const defaultRates = [67, 70, 72, 70, 72, 75];

const activeMonths = props.months || defaultMonths;
const activeRates = props.rates || defaultRates;

// إحداثيات الرسم البياني المنحني داخل SVG (عرض 600، ارتفاع 160)
// القيم تتراوح بين 65% و 78%
const svgWidth = 600;
const svgHeight = 130;
const paddingX = 40;
const paddingY = 20;

function getY(rate: number): number {
    const min = 65;
    const max = 78;
    const normalized = (rate - min) / (max - min);
    return svgHeight - paddingY - (normalized * (svgHeight - paddingY * 2));
}

function getX(index: number): number {
    const usableWidth = svgWidth - paddingX * 2;
    return paddingX + (index / (activeMonths.length - 1)) * usableWidth;
}

// إنشاء مسار المنحنى الممتع (Smooth Path)
const points = activeRates.map((rate, i) => ({ x: getX(i), y: getY(rate), rate, month: activeMonths[i] }));

let pathD = `M ${points[0].x} ${points[0].y}`;
for (let i = 0; i < points.length - 1; i++) {
    const p0 = points[i];
    const p1 = points[i + 1];
    const mx = (p0.x + p1.x) / 2;
    pathD += ` C ${mx} ${p0.y}, ${mx} ${p1.y}, ${p1.x} ${p1.y}`;
}

const areaD = `${pathD} L ${points[points.length - 1].x} ${svgHeight} L ${points[0].x} ${svgHeight} Z`;
</script>

<template>
    <div class="rounded-2xl border border-[#D6E0E7] bg-white p-6 shadow-2xs flex flex-col justify-between">
        <!-- الرأس -->
        <div class="flex items-start justify-between">
            <div>
                <h3 class="text-base font-bold text-[#163E3E]">{{ title || 'اتجاه الإشغال' }}</h3>
                <p class="text-xs text-[#5B6770] font-medium mt-0.5">
                    {{ subtitle || 'مايو – أكتوبر 2026 • كل العقارات' }}
                </p>
            </div>

            <!-- النسبة وشارة التغيير المطابقة لنوتس فيجما تباين AA -->
            <div class="flex items-center gap-2.5">
                <span class="text-2xl font-black text-[#163E3E]">
                    {{ currentRate || '75%' }}
                </span>
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-xs font-bold text-[#163E3E]">
                    <ArrowUpRight class="h-3.5 w-3.5 text-emerald-600 stroke-[2.5]" />
                    <span>{{ badge || '+8 نقاط منذ مايو' }}</span>
                </span>
            </div>
        </div>

        <!-- مساحة الرسم البياني التفاعلي -->
        <div class="mt-4 relative">
            <svg
                :viewBox="`0 0 ${svgWidth} ${svgHeight}`"
                class="w-full h-[150px] overflow-visible"
                preserveAspectRatio="none"
            >
                <defs>
                    <!-- تدرج لوني ناعم تحت المنحنى -->
                    <linearGradient id="occupancyGradient" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#163E3E" stop-opacity="0.25" />
                        <stop offset="100%" stop-color="#163E3E" stop-opacity="0.0" />
                    </linearGradient>
                </defs>

                <!-- خطوط الشبكة الأفقية للأرقام: 75%، 72%، 70%، 67% -->
                <line x1="20" :y1="getY(75)" :x2="svgWidth - 20" :y2="getY(75)" stroke="#E8EFF4" stroke-dasharray="3 3" />
                <line x1="20" :y1="getY(72)" :x2="svgWidth - 20" :y2="getY(72)" stroke="#E8EFF4" stroke-dasharray="3 3" />
                <line x1="20" :y1="getY(70)" :x2="svgWidth - 20" :y2="getY(70)" stroke="#E8EFF4" stroke-dasharray="3 3" />
                <line x1="20" :y1="getY(67)" :x2="svgWidth - 20" :y2="getY(67)" stroke="#E8EFF4" stroke-dasharray="3 3" />

                <!-- مساحة التدرج -->
                <path :d="areaD" fill="url(#occupancyGradient)" />

                <!-- خط المنحنى الرئيسي بلون فيجما Teal -->
                <path
                    :d="pathD"
                    fill="none"
                    stroke="#163E3E"
                    stroke-width="3"
                    stroke-linecap="round"
                />

                <!-- نقاط التفاعل -->
                <g v-for="(p, i) in points" :key="i">
                    <circle
                        :cx="p.x"
                        :cy="p.y"
                        r="5"
                        fill="#FFFFFF"
                        stroke="#163E3E"
                        stroke-width="3"
                        class="cursor-pointer transition-transform hover:scale-125"
                        @mouseenter="hoveredIndex = i"
                        @mouseleave="hoveredIndex = null"
                    />
                </g>
            </svg>

            <!-- تسميات الشهور في الأسفل -->
            <div class="flex items-center justify-between text-xs text-[#5B6770] font-medium pt-2 border-t border-[#E8EFF4]">
                <span
                    v-for="(m, i) in activeMonths"
                    :key="m"
                    class="transition-colors"
                    :class="hoveredIndex === i ? 'text-[#163E3E] font-bold' : ''"
                >
                    {{ m }}
                </span>
            </div>
        </div>
    </div>
</template>
