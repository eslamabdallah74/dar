<script setup lang="ts">
import { ref } from 'vue';
import { ArrowUpDown, MessageCircle, MoreVertical } from 'lucide-vue-next';

interface DueItem {
    id: number;
    tenant: string;
    unit: string;
    due_date: string;
    amount: string;
    amount_raw?: number;
    status: 'due_soon' | 'partial' | 'overdue' | string;
    status_label: string;
    phone?: string;
}

const props = defineProps<{
    title?: string;
    items: DueItem[];
}>();

const emit = defineEmits<{
    (e: 'recordPayment', item: DueItem): void;
    (e: 'sendWhatsApp', item: DueItem): void;
}>();

// ترتيب الأعمدة التفاعلي
const sortColumn = ref<string>('due_date');
const sortDirection = ref<'asc' | 'desc'>('asc');

function toggleSort(col: string) {
    if (sortColumn.value === col) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn.value = col;
        sortDirection.value = 'asc';
    }
}
</script>

<template>
    <div class="rounded-2xl border border-[#D6E0E7] bg-white p-6 shadow-2xs space-y-4">
        <!-- العنوان -->
        <div class="flex items-center justify-between">
            <h3 class="text-base font-bold text-[#0F2747]">
                {{ title || 'الاستحقاقات القادمة' }}
            </h3>

            <span class="text-xs text-[#5B6770] font-medium">
                تصفية حسب موعد الاستحقاق القادم
            </span>
        </div>

        <!-- الجدول المتطابق 100% مع فيجما (Data table) -->
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead>
                    <tr class="border-b border-[#D6E0E7] text-xs font-semibold text-[#5B6770]">
                        <th class="pb-3 cursor-pointer hover:text-[#0F2747] transition" @click="toggleSort('tenant')">
                            <span class="inline-flex items-center gap-1">المستأجر ↕</span>
                        </th>
                        <th class="pb-3 cursor-pointer hover:text-[#0F2747] transition" @click="toggleSort('unit')">
                            <span class="inline-flex items-center gap-1">الوحدة المؤجرة ↕</span>
                        </th>
                        <th class="pb-3 cursor-pointer hover:text-[#0F2747] transition" @click="toggleSort('due_date')">
                            <span class="inline-flex items-center gap-1">تاريخ الاستحقاق ↕</span>
                        </th>
                        <th class="pb-3 cursor-pointer hover:text-[#0F2747] transition" @click="toggleSort('amount')">
                            <span class="inline-flex items-center gap-1">المبلغ ↕</span>
                        </th>
                        <th class="pb-3 cursor-pointer hover:text-[#0F2747] transition" @click="toggleSort('status')">
                            <span class="inline-flex items-center gap-1">الحالة ↕</span>
                        </th>
                        <th class="pb-3 text-left">الإجراء</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-[#E8EFF4]">
                    <tr
                        v-for="row in items"
                        :key="row.id"
                        class="hover:bg-[#F8FAFC] transition group"
                    >
                        <!-- المستأجر -->
                        <td class="py-4 font-bold text-[#0F2747]">
                            {{ row.tenant }}
                        </td>

                        <!-- الوحدة المؤجرة -->
                        <td class="py-4 text-[#334155] font-medium">
                            {{ row.unit }}
                        </td>

                        <!-- تاريخ الاستحقاق مع عزل BiDi -->
                        <td class="py-4 font-mono text-xs text-[#5B6770]" dir="ltr">
                            {{ row.due_date }}
                        </td>

                        <!-- المبلغ بالجنيه -->
                        <td class="py-4 font-bold font-mono text-[#0F2747]" dir="ltr">
                            {{ row.amount }}
                        </td>

                        <!-- شارات الحالة المطابقة لفيجما مع تباين AA -->
                        <td class="py-4">
                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold border"
                                :class="[
                                    row.status === 'due_soon' ? 'bg-amber-50 text-[#0F2747] border-amber-200' :
                                    row.status === 'partial' ? 'bg-sky-50 text-[#0F2747] border-sky-200' :
                                    'bg-rose-50 text-[#0F2747] border-rose-200'
                                ]"
                            >
                                <span
                                    class="w-1.5 h-1.5 rounded-full"
                                    :class="[
                                        row.status === 'due_soon' ? 'bg-amber-500' :
                                        row.status === 'partial' ? 'bg-sky-500' :
                                        'bg-rose-500'
                                    ]"
                                ></span>
                                <span>{{ row.status_label }}</span>
                            </span>
                        </td>

                        <!-- الإجراء السريع -->
                        <td class="py-4 text-left">
                            <div class="inline-flex items-center gap-1.5">
                                <button
                                    @click="emit('recordPayment', row)"
                                    type="button"
                                    class="px-2.5 py-1 rounded-lg border border-[#D6E0E7] bg-white hover:border-[#0E6E7E] hover:text-[#0E6E7E] text-xs font-semibold text-[#0F2747] transition shadow-2xs"
                                >
                                    سداد
                                </button>
                                <button
                                    v-if="row.phone"
                                    @click="emit('sendWhatsApp', row)"
                                    type="button"
                                    class="p-1.5 rounded-lg text-emerald-600 hover:bg-emerald-50 transition"
                                    title="تذكير واتساب"
                                >
                                    <MessageCircle class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- التصفح والصفحات المطابق لفيجما (Pagination) -->
        <div class="flex items-center justify-between pt-3 border-t border-[#D6E0E7] text-xs">
            <span class="text-[#5B6770] font-medium">
                عرض {{ items.length }} سجلات • الصفحة 1
            </span>

            <div class="flex items-center gap-1.5">
                <button
                    type="button"
                    class="h-8 px-3 rounded-lg border border-[#D6E0E7] bg-white text-[#5B6770] hover:text-[#0F2747] hover:bg-[#F3F7FA] font-medium transition"
                >
                    ← التالي
                </button>
                <span class="w-8 h-8 rounded-lg bg-[#0E6E7E] text-white flex items-center justify-center font-bold">
                    1
                </span>
                <button
                    type="button"
                    class="h-8 px-3 rounded-lg border border-[#D6E0E7] bg-white text-[#5B6770] hover:text-[#0F2747] hover:bg-[#F3F7FA] font-medium transition"
                >
                    السابق →
                </button>
            </div>
        </div>
    </div>
</template>
