<script setup lang="ts">
import { computed } from 'vue';
import { AlertTriangle, ChevronLeft, Clock, Wrench, CheckCircle, ArrowLeft } from 'lucide-vue-next';

interface AttentionItem {
    id: number;
    type: string;
    title: string;
    desc: string;
    phone?: string;
    amount?: number;
    severity?: string;
    link?: string;
}

const props = defineProps<{
    title?: string;
    items?: AttentionItem[];
}>();

const emit = defineEmits<{
    (e: 'viewAll'): void;
    (e: 'action', item: AttentionItem): void;
}>();

const safeItems = computed(() => props.items || []);
</script>

<template>
    <div class="rounded-2xl border border-[#D6E0E7] bg-white p-6 shadow-2xs flex flex-col justify-between h-full">
        <div class="space-y-4">
            <!-- العنوان وعدد التنبيهات -->
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-[#163E3E]">
                    {{ title || 'تحتاج انتباهك' }}
                </h3>
                <span
                    v-if="safeItems.length > 0"
                    class="px-2 py-0.5 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200"
                >
                    {{ safeItems.length }} تنبيهات
                </span>
            </div>

            <!-- الحالة الإيجابية: لا توجد تنبيهات (Positive Empty State) -->
            <div
                v-if="safeItems.length === 0"
                class="my-4 py-8 px-4 rounded-xl border border-dashed border-emerald-200 bg-emerald-50/50 flex flex-col items-center justify-center text-center space-y-2"
            >
                <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center">
                    <CheckCircle class="h-5 w-5" />
                </div>
                <h4 class="text-xs font-bold text-emerald-900">كل شيء على ما يرام!</h4>
                <p class="text-[11px] text-emerald-700 font-medium leading-relaxed max-w-[220px]">
                    لا توجد متأخرات دفع أو عقود تنتهي قريباً أو طلبات صيانة تتطلب إجراءً عاجلاً.
                </p>
            </div>

            <!-- قائمة التنبيهات الحقيقية -->
            <div v-else class="divide-y divide-[#E8EFF4]">
                <div
                    v-for="item in safeItems"
                    :key="item.id"
                    class="py-3 first:pt-0 last:pb-0 flex items-center justify-between gap-3 group cursor-pointer hover:bg-[#F8FAFC] -mx-2 px-2 rounded-xl transition"
                    @click="emit('action', item)"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <!-- أيقونة التنبيه الملونة -->
                        <div
                            class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
                            :class="[
                                item.severity === 'danger' ? 'bg-rose-50 text-rose-600 border border-rose-100' :
                                item.severity === 'warning' ? 'bg-amber-50 text-amber-700 border border-amber-100' :
                                'bg-[#E8F0F0] text-[#163E3E] border border-[#163E3E]/10'
                            ]"
                        >
                            <AlertTriangle v-if="item.severity === 'danger'" class="h-4 w-4" />
                            <Clock v-else-if="item.severity === 'warning'" class="h-4 w-4" />
                            <Wrench v-else class="h-4 w-4" />
                        </div>

                        <div class="min-w-0">
                            <h4 class="text-xs font-bold text-[#163E3E] group-hover:text-[#163E3E] transition truncate">
                                {{ item.title }}
                            </h4>
                            <p class="text-[11px] text-[#5B6770] font-medium mt-0.5 truncate">
                                {{ item.desc }}
                            </p>
                        </div>
                    </div>

                    <ChevronLeft class="h-4 w-4 text-[#B6C5D0] group-hover:text-[#163E3E] group-hover:-translate-x-1 transition shrink-0" />
                </div>
            </div>
        </div>

        <!-- زر الانتقال للتفاصيل -->
        <div class="pt-4 border-t border-[#D6E0E7] mt-4">
            <button
                @click="emit('viewAll')"
                type="button"
                class="w-full text-center text-xs font-bold text-[#163E3E] hover:text-[#102F2F] transition py-1 flex items-center justify-center gap-1.5"
            >
                <span>متابعة كافة المستحقات والعقود</span>
                <ArrowLeft class="h-3.5 w-3.5" />
            </button>
        </div>
    </div>
</template>
