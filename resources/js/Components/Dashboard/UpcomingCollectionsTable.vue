<script setup lang="ts">
import { ref, computed } from 'vue';
import {
    ArrowUpDown,
    MessageCircle,
    CreditCard,
    CheckCircle2,
    Calendar,
    Search,
    MapPin,
    Building2,
} from 'lucide-vue-next';

interface DueItem {
    id: number;
    tenant: string;
    renter_id?: number;
    unit: string;
    due_date: string;
    due_date_iso?: string;
    amount: string;
    amount_raw?: number;
    currency_symbol?: string;
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
const searchQuery = ref('');

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

const filteredItems = computed(() => {
    let list = safeItems.value;
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim();
        list = list.filter(item =>
            item.tenant.toLowerCase().includes(q) ||
            item.unit.toLowerCase().includes(q) ||
            (item.phone && item.phone.includes(q))
        );
    }

    const sorted = [...list];
    sorted.sort((a: any, b: any) => {
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
    return sorted;
});
</script>

<template>
    <div class="rounded-2xl border border-[#D6E0E7] bg-white p-6 shadow-2xs space-y-5 flex flex-col justify-between h-full">
        <div>
            <!-- الرأس وشريط البحث والفرز -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-[#E8EFF4]">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-bold text-[#163E3E]">
                            {{ title || 'الاستحقاقات القادمة ومواعيد التحصيل' }}
                        </h3>
                        <span
                            v-if="safeItems.length > 0"
                            class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-[#E8F0F0] text-[#163E3E]"
                        >
                            {{ safeItems.length }}
                        </span>
                    </div>
                    <p class="text-xs text-[#5B6770] font-medium mt-0.5">
                        دفعات الإيجار المستحقة أو التي اقترب موعد سدادها
                    </p>
                </div>

                <!-- شريط البحث السريع داخل الاستحقاقات -->
                <div v-if="safeItems.length > 0" class="relative w-full sm:w-60">
                    <Search class="w-3.5 h-3.5 text-[#8E9CA6] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="ابحث عن مستأجر أو وحدة..."
                        class="w-full h-9 pr-9 pl-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-[#163E3E] placeholder-[#8E9CA6] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition outline-hidden"
                    />
                </div>
            </div>

            <!-- حالة عدم وجود استحقاقات (Empty State) -->
            <div
                v-if="safeItems.length === 0"
                class="my-6 py-10 px-4 rounded-xl border border-dashed border-emerald-200 bg-emerald-50/40 flex flex-col items-center justify-center text-center space-y-2.5"
            >
                <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center shadow-2xs">
                    <CheckCircle2 class="h-6 w-6 stroke-[2]" />
                </div>
                <h4 class="text-sm font-bold text-emerald-950">لا توجد استحقاقات إيجار قادمة حالياً</h4>
                <p class="text-xs text-emerald-800 font-medium leading-relaxed max-w-sm">
                    جميع الإيجارات مسددة بالكامل، ولا توجد مطالبات معلقة تستحق السداد في الأيام القادمة.
                </p>
            </div>

            <!-- لا توجد نتائج مطابقة للبحث -->
            <div
                v-else-if="filteredItems.length === 0"
                class="my-6 py-8 px-4 rounded-xl border border-dashed border-[#D6E0E7] bg-[#F8FAFC] text-center space-y-2"
            >
                <p class="text-xs text-[#5B6770] font-medium">لا توجد نتائج مطابقة لـ "{{ searchQuery }}"</p>
                <button
                    @click="searchQuery = ''"
                    type="button"
                    class="text-xs text-[#163E3E] font-bold hover:underline"
                >
                    مسح البحث
                </button>
            </div>

            <!-- جدول الاستحقاقات الحقيقية -->
            <div v-else class="overflow-x-auto mt-2">
                <table class="w-full text-right text-sm">
                    <thead>
                        <tr class="border-b border-[#E8EFF4] text-[11px] font-bold text-[#5B6770]">
                            <th class="pb-3 pr-2 cursor-pointer hover:text-[#163E3E] transition select-none" @click="toggleSort('tenant')">
                                <span class="inline-flex items-center gap-1">المستأجر ↕</span>
                            </th>
                            <th class="pb-3 cursor-pointer hover:text-[#163E3E] transition select-none" @click="toggleSort('unit')">
                                <span class="inline-flex items-center gap-1">العقار والوحدة ↕</span>
                            </th>
                            <th class="pb-3 cursor-pointer hover:text-[#163E3E] transition select-none" @click="toggleSort('due_date')">
                                <span class="inline-flex items-center gap-1">تاريخ الاستحقاق ↕</span>
                            </th>
                            <th class="pb-3 cursor-pointer hover:text-[#163E3E] transition select-none" @click="toggleSort('amount')">
                                <span class="inline-flex items-center gap-1">المبلغ المطلوب ↕</span>
                            </th>
                            <th class="pb-3 pl-2 text-start sm:text-left">إجراء فوري</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E8EFF4]">
                        <tr
                            v-for="item in filteredItems"
                            :key="item.id"
                            class="hover:bg-[#F8FAFC] transition group"
                        >
                            <!-- المستأجر -->
                            <td class="py-3.5 pr-2">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-[#E8F0F0] text-[#163E3E] flex items-center justify-center font-bold text-xs shrink-0">
                                        {{ item.tenant.charAt(0) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-bold text-xs sm:text-sm text-[#163E3E] truncate">
                                            {{ item.tenant }}
                                        </div>
                                        <span v-if="item.phone" class="text-[11px] text-[#8E9CA6] block font-medium" dir="ltr">
                                            {{ item.phone }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- العقار والوحدة -->
                            <td class="py-3.5">
                                <div class="text-xs font-semibold text-[#163E3E]">
                                    {{ item.unit }}
                                </div>
                            </td>

                            <!-- تاريخ الاستحقاق والشارة -->
                            <td class="py-3.5 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-[#163E3E]">
                                        {{ item.due_date }}
                                    </span>
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold"
                                        :class="[
                                            item.status === 'overdue' ? 'bg-rose-50 text-rose-700 border border-rose-200' :
                                             item.status === 'due_today' ? 'bg-amber-50 text-amber-800 border border-amber-200' :
                                            item.status === 'partial' ? 'bg-purple-50 text-purple-700 border border-purple-200' :
                                            'bg-sky-50 text-sky-700 border border-sky-200'
                                        ]"
                                    >
                                        {{ item.status_label }}
                                    </span>
                                </div>
                            </td>

                            <!-- المبلغ المطلوب -->
                            <td class="py-3.5 whitespace-nowrap">
                                <span class="font-black text-sm text-[#163E3E]">
                                    {{ item.amount }}
                                </span>
                            </td>

                            <!-- الإجراءات السريعة -->
                            <td class="py-3.5 pl-2 text-start sm:text-left whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <!-- زر إرسال واتساب المباشر -->
                                    <button
                                        v-if="item.phone"
                                        @click="emit('sendWhatsApp', item)"
                                        type="button"
                                        class="h-8 px-2.5 rounded-lg border border-emerald-200 bg-emerald-50/80 hover:bg-emerald-100 text-emerald-800 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-2xs"
                                        title="إرسال تذكير رسمي عبر واتساب"
                                    >
                                        <MessageCircle class="h-3.5 w-3.5 text-emerald-700" />
                                        <span class="hidden md:inline">واتساب</span>
                                    </button>

                                    <!-- زر تسجيل الدفعة السريع -->
                                    <button
                                        @click="emit('recordPayment', item)"
                                        type="button"
                                        class="h-8 px-3 rounded-lg bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs"
                                        title="تسجيل دفعة سداد لهذا المستحق"
                                    >
                                        <CreditCard class="h-3.5 w-3.5" />
                                        <span>سداد</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
