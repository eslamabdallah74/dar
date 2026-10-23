<script setup lang="ts">
import { computed } from 'vue';
import { CreditCard, FileText, Wrench, Activity, Clock } from 'lucide-vue-next';

interface ActivityItem {
    id: string;
    type: string;
    title: string;
    subtitle: string;
    time_ago: string;
    timestamp: number;
}

const props = defineProps<{
    activities?: ActivityItem[];
}>();

const safeActivities = computed(() => props.activities || []);
</script>

<template>
    <div class="rounded-2xl border border-[#D6E0E7] bg-white p-6 shadow-2xs flex flex-col justify-between h-full">
        <div>
            <!-- الرأس -->
            <div class="flex items-center justify-between pb-3 border-b border-[#E8EFF4]">
                <div>
                    <h3 class="text-base font-bold text-[#0F2747]">سجل النشاط الأخير</h3>
                    <p class="text-xs text-[#5B6770] font-medium mt-0.5">
                        آخر العمليات الإدارية والمالية في مساحة العمل
                    </p>
                </div>
                <div class="w-8 h-8 rounded-lg bg-[#E8F3F5] text-[#0E6E7E] flex items-center justify-center">
                    <Activity class="h-4 w-4" />
                </div>
            </div>

            <!-- قائمة الأنشطة -->
            <div v-if="safeActivities.length > 0" class="mt-4 divide-y divide-[#E8EFF4]">
                <div
                    v-for="act in safeActivities"
                    :key="act.id"
                    class="py-3 first:pt-0 last:pb-0 flex items-start gap-3"
                >
                    <!-- الأيقونة حسب نوع النشاط -->
                    <div
                        class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 mt-0.5"
                        :class="[
                            act.type === 'payment' ? 'bg-emerald-50 text-emerald-700' :
                            act.type === 'lease' ? 'bg-sky-50 text-sky-700' :
                            act.type === 'maintenance' ? 'bg-amber-50 text-amber-700' :
                            'bg-[#E8F3F5] text-[#0E6E7E]'
                        ]"
                    >
                        <CreditCard v-if="act.type === 'payment'" class="h-4 w-4" />
                        <FileText v-else-if="act.type === 'lease'" class="h-4 w-4" />
                        <Wrench v-else-if="act.type === 'maintenance'" class="h-4 w-4" />
                        <Activity v-else class="h-4 w-4" />
                    </div>

                    <!-- التفاصيل -->
                    <div class="flex-1 min-w-0">
                        <h4 class="text-xs font-bold text-[#0F2747] truncate">
                            {{ act.title }}
                        </h4>
                        <p class="text-[11px] text-[#5B6770] font-medium mt-0.5 truncate">
                            {{ act.subtitle }}
                        </p>
                    </div>

                    <!-- التوقيت النسبي بالعربية -->
                    <span class="text-[10px] font-medium text-[#8E9CA6] shrink-0 font-mono mt-0.5" dir="rtl">
                        {{ act.time_ago }}
                    </span>
                </div>
            </div>

            <!-- حالة فارغة -->
            <div
                v-else
                class="py-8 px-4 rounded-xl border border-dashed border-[#D6E0E7] bg-[#F8FAFC] text-center my-4"
            >
                <p class="text-xs text-[#5B6770] font-medium">لا توجد حركات أو أنشطة مسجلة حتى الآن.</p>
            </div>
        </div>

        <div class="pt-3 border-t border-[#E8EFF4] text-[11px] text-[#5B6770] font-medium text-center">
            تحديث فوري مع كل عملية سداد أو توثيق عقد جديد
        </div>
    </div>
</template>

