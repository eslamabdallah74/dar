<script setup lang="ts">
import { ref, computed } from 'vue';
import { ArrowUpDown, MessageCircle, CreditCard, CheckCircle2, Calendar } from 'lucide-vue-next';

interface DueItem {
    id: number;
    tenant: string;
    renter_id?: number;
    unit: string;
    due_date: string;
    amount: string;
    amount_raw?: number;
    status: 'due_today' | 'due_soon' | 'partial' | 'overdue' | string;
    status_label: string;
    phone?: string;
}

const props = defineProps<{
    title?: string;
    items?: DueItem[];
}>();

const emit = defineEmits<{
    (e: 'recordPayment', item: DueItem): void;
    (e: 'sendWhatsApp', item: DueItem): void;
}>();

const safeItems = computed(() => props.items || []);

// Sorting
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

const sortedItems = computed(() => {
    const list = [...safeItems.value];
    list.sort((a: any, b: any) => {
        let valA = a[sortColumn.value] ?? '';
        let valB = b[sortColumn.value] ?? '';
        if (sortColumn.value === 'amount') {
            valA = a.amount_raw ?? parseFloat(String(a.amount).replace(/[^0-9.]/g, '') || '0');
            valB = b.amount_raw ?? parseFloat(String(b.amount).replace(/[^0-9.]/g, '') || '0');
        }
        if (valA < valB) return sortDirection.value === 'asc' ? -1 : 1;
        if (valA > valB) return sortDirection.value === 'asc' ? 1 : -1;
        return 0;
    });
    return list;
});
</script>

<template>
    <div class="rounded-2xl border border-[#D6E0E7] bg-white p-6 shadow-2xs space-y-4">
        <!-- العنوان -->
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-[#0F2747]">
                    {{ title || 'الاستحقاقات القادمة' }}
                </h3>
                <p class="text-xs text-[#5B6770] font-medium mt-0.5">
                    دفعات الإيجار المستحقة أو التي اقترب موعد سدادها
                </p>
            </div>

            <span v-if="safeItems.length > 0" class="text-xs text-[#5B6770] font-medium">
                {{ safeItems.length }} استحقاقات قيد المتابعة
            </span>
        </div>

        <!-- حالة عدم وجود استحقاقات (Empty State) -->
        <div
            v-if="safeItems.length === 0"
            class="py-10 px-4 rounded-xl border border-dashed border-[#D6E0E7] bg-[#F8FAFC] flex flex-col items-center justify-center text-center space-y-2"
        >
            <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center">
                <CheckCircle2 class="h-5 w-5" />
            </div>
            <h4 class="text-sm font-bold text-[#0F2747]">لا توجد استحقاقات إيجار قادمة حالياً</h4>
            <p class="text-xs text-[#5B6770] font-medium leading-relaxed max-w-sm">
                جميع الإيجارات مسددة أو لا توجد عقود نشطة تستحق السداد في الأيام القادمة.
            </p>
        </div>

        <!-- جدول الاستحقاقات الحقيقية -->
        <div v-else class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead>
                    <tr class="border-b border-[#D6E0E7] text-xs font-semibold text-[#5B6770]">
                        <th class="pb-3 cursor-pointer hover:text-[#0F2747] transition select-none" @click="toggleSort('tenant')">
                            <span class="inline-flex items-center gap-1">المستأجر ↕</span>
                        </th>
                        <th class="pb-3 cursor-pointer hover:text-[#0F2747] transition select-none" @click="toggleSort('unit')">
                            <span class="inline-flex items-center gap-1">الوحدة المؤجرة ↕</span>
                        </th>
                        <th class="pb-3 cursor-pointer hover:text-[#0F2747] transition select-none" @click="toggleSort('due_date')">
                            <span class="inline-flex items-center gap-1">تاريخ الاستحقاق ↕</span>
                        </th>
                        <th class="pb-3 cursor-pointer hover:text-[#0F2747] transition select-none" @click="toggleSort('amount')">
                            <span class="inline-flex items-center gap-1">المبلغ المطلوب ↕</span>
                        </th>
                        <th class="pb-3 cursor-pointer hover:text-[#0F2747] transition select-none" @click="toggleSort('status')">
                            <span class="inline-flex items-center gap-1">الحالة ↕</span>
                        </th>
                        <th class="pb-3 text-left">الإجراء السريع</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-[#E8EFF4]">
                    <tr
                        v-for="row in sortedItems"
                        :key="row.id"
                        class="hover:bg-[#F8FAFC] transition group"
                    >
                        <!-- المستأجر -->
                        <td class="py-3.5 font-bold text-[#0F2747]">
                            {{ row.tenant }}
                        </td>

                        <!-- الوحدة المؤجرة -->
                        <td class="py-3.5 text-[#334155] font-medium">
                            {{ row.unit }}
                        </td>

                        <!-- تاريخ الاستحقاق -->
                        <td class="py-3.5 font-mono text-xs text-[#5B6770]" dir="ltr">
                            {{ row.due_date }}
                        </td>

                        <!-- المبلغ المطلوب بالجنيه -->
                        <td class="py-3.5 font-bold font-mono text-[#0F2747]" dir="ltr">
                            {{ row.amount }}
                        </td>

                        <!-- شارة الحالة مع ألوان واضحة وتوافق عالي -->
                        <td class="py-3.5">
                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold border"
                                :class="[
                                    row.status === 'due_today' ? 'bg-amber-50 text-amber-900 border-amber-300' :
                                    row.status === 'due_soon' ? 'bg-sky-50 text-sky-900 border-sky-300' :
                                    row.status === 'partial' ? 'bg-purple-50 text-purple-900 border-purple-300' :
                                    'bg-rose-50 text-rose-900 border-rose-300'
                                ]"
                            >
                                <span
                                    class="w-1.5 h-1.5 rounded-full"
                                    :class="[
                                        row.status === 'due_today' ? 'bg-amber-500' :
                                        row.status === 'due_soon' ? 'bg-sky-500' :
                                        row.status === 'partial' ? 'bg-purple-500' :
                                        'bg-rose-500'
                                    ]"
                                ></span>
                                <span>{{ row.status_label }}</span>
                            </span>
                        </td>

                        <!-- الإجراءات السريعة: تسجيل دفعة + واتساب -->
                        <td class="py-3.5 text-left">
                            <div class="inline-flex items-center gap-1.5">
                                <button
                                    @click="emit('recordPayment', row)"
                                    type="button"
                                    class="px-3 py-1.5 rounded-lg bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-semibold transition shadow-2xs flex items-center gap-1.5"
                                >
                                    <CreditCard class="h-3.5 w-3.5" />
                                    <span>تسجيل دفعة</span>
                                </button>
                                <button
                                    v-if="row.phone"
                                    @click="emit('sendWhatsApp', row)"
                                    type="button"
                                    class="p-1.5 rounded-lg text-emerald-600 hover:bg-emerald-50 border border-emerald-200 transition"
                                    title="تذكير عبر واتساب"
                                >
                                    <MessageCircle class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
