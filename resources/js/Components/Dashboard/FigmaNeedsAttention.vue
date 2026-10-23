<script setup lang="ts">
import { AlertTriangle, ChevronLeft, Clock, MessageCircle, Wrench } from 'lucide-vue-next';

interface AttentionItem {
    id: number;
    type: string;
    title: string;
    desc: string;
    phone?: string;
    amount?: number;
    severity?: string;
}

defineProps<{
    title?: string;
    items: AttentionItem[];
}>();

const emit = defineEmits<{
    (e: 'viewAll'): void;
    (e: 'action', item: AttentionItem): void;
}>();
</script>

<template>
    <div class="rounded-2xl border border-[#D6E0E7] bg-white p-6 shadow-2xs flex flex-col justify-between h-full">
        <div class="space-y-4">
            <!-- العنوان -->
            <h3 class="text-base font-bold text-[#0F2747]">
                {{ title || 'تحتاج انتباهك' }}
            </h3>

            <!-- العناصر الثلاثة المطابقة لفيجما (Attention items) -->
            <div class="divide-y divide-[#E8EFF4]">
                <div
                    v-for="item in items"
                    :key="item.id"
                    class="py-3 first:pt-0 last:pb-0 flex items-center justify-between gap-3 group cursor-pointer"
                    @click="emit('action', item)"
                >
                    <div class="flex items-center gap-3">
                        <!-- أيقونة التنبيه الملونة -->
                        <div
                            class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
                            :class="[
                                item.severity === 'danger' ? 'bg-rose-50 text-rose-600 border border-rose-100' :
                                item.severity === 'warning' ? 'bg-amber-50 text-amber-700 border border-amber-100' :
                                'bg-[#E3F1F4] text-[#0E6E7E] border border-[#0E6E7E]/10'
                            ]"
                        >
                            <AlertTriangle v-if="item.severity === 'danger'" class="h-4 w-4" />
                            <Clock v-else-if="item.severity === 'warning'" class="h-4 w-4" />
                            <Wrench v-else class="h-4 w-4" />
                        </div>

                        <div>
                            <h4 class="text-xs font-bold text-[#0F2747] group-hover:text-[#0E6E7E] transition">
                                {{ item.title }}
                            </h4>
                            <p class="text-[11px] text-[#5B6770] font-medium mt-0.5">
                                {{ item.desc }}
                            </p>
                        </div>
                    </div>

                    <ChevronLeft class="h-4 w-4 text-[#B6C5D0] group-hover:text-[#0E6E7E] group-hover:-translate-x-1 transition" />
                </div>
            </div>
        </div>

        <!-- فاصل وزر عرض كل التنبيهات (Button ghost default) -->
        <div class="pt-4 border-t border-[#D6E0E7] mt-4">
            <button
                @click="emit('viewAll')"
                type="button"
                class="w-full text-center text-xs font-bold text-[#0E6E7E] hover:text-[#0B5A68] transition py-1 flex items-center justify-center gap-1"
            >
                <span>عرض كل التنبيهات</span>
                <span class="text-sm">←</span>
            </button>
        </div>
    </div>
</template>
