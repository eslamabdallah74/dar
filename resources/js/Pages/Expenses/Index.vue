<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import {
    Building2,
    Calendar,
    Check,
    Coins,
    DollarSign,
    FileText,
    Plus,
    Receipt,
    Search,
    TrendingDown,
    X,
} from 'lucide-vue-next';

interface ExpenseItem {
    id: number;
    category: string;
    amount: number;
    expense_date: string;
    property_name: string;
    unit_name: string;
    notes: string;
    recorder_name: string;
}

const props = defineProps<{
    expenses: ExpenseItem[];
    properties: Array<{ id: number; name: string }>;
    stats: {
        total_amount: number;
        count: number;
        categories: Record<string, number>;
    };
}>();

const selectedCategory = ref('all');
const searchQuery = ref('');
const showAddModal = ref(false);

const form = useForm({
    property_id: props.properties[0]?.id ?? '',
    category: 'صيانة وتشغيل',
    amount: '',
    expense_date: new Date().toISOString().split('T')[0],
    notes: '',
});

const filteredExpenses = computed(() => {
    return props.expenses.filter(e => {
        const matchesSearch = e.notes.includes(searchQuery.value)
            || e.category.includes(searchQuery.value)
            || e.property_name.includes(searchQuery.value);

        const matchesCat = selectedCategory.value === 'all'
            || e.category === selectedCategory.value;

        return matchesSearch && matchesCat;
    });
});

const submit = () => {
    form.post(route('expenses.store'), {
        onSuccess: () => {
            form.reset();
            showAddModal.value = false;
        },
    });
};
</script>

<template>
    <AppLayout
        title="المصروفات التشغيلية — دار"
        active-tab="expenses"
        header-title="المصروفات التشغيلية"
        header-subtitle="تسجيل فواتير الخدمات، عقود الصيانة والمصروفات الدورية للمباني"
        breadcrumb="المصروفات"
    >
        <template #actions>
            <button
                @click="showAddModal = true"
                type="button"
                class="h-11 px-5 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-sm font-semibold transition shadow-xs flex items-center gap-2 cursor-pointer"
            >
                <Plus class="h-4 w-4 stroke-[2.5]" />
                <span>تسجيل مصروف جديد</span>
            </button>
        </template>

        <!-- كروت مؤشرات المصروفات (4 KPIs) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">إجمالي المصروفات للشهر</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#0F2747] font-mono">{{ stats.total_amount.toLocaleString() }} <span class="text-xs font-sans font-normal text-[#5B6770]">ج.م</span></span>
                    <span class="text-xs text-rose-600 font-semibold bg-rose-50 px-2 py-0.5 rounded-md flex items-center gap-1">
                        <TrendingDown class="w-3.5 h-3.5" />
                        <span>تشغيلي</span>
                    </span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">عدد الفواتير والقيود</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#0F2747] font-mono">{{ stats.count }}</span>
                    <span class="text-xs text-[#5B6770]">سند صرف</span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">أكبر بند مصروفات</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-lg font-bold text-[#0F2747]">كهرباء ومياه</span>
                    <span class="text-xs text-[#0E6E7E] font-bold font-mono">680 ج.م</span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">نسبة المصروفات للإيراد</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-emerald-700 font-mono">3.8%</span>
                    <span class="text-xs text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md font-semibold">
                        كفاءة عالية
                    </span>
                </div>
            </div>
        </div>

        <!-- شريط البحث والتصفية حسب الفئة -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-white p-4 rounded-2xl border border-[#D6E0E7] shadow-2xs">
            <div class="relative flex-1 max-w-md">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="ابحث ببيان المصروف أو الفئة أو العقار..."
                    class="w-full h-10 pr-10 pl-4 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] placeholder:text-[#5B6770] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-right"
                />
                <Search class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-[#5B6770]" />
            </div>

            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
                <button
                    v-for="cat in ['all', 'صيانة وتشغيل', 'كهرباء ومياه', 'نظافة وخدمات']"
                    :key="cat"
                    @click="selectedCategory = cat"
                    type="button"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition cursor-pointer shrink-0"
                    :class="selectedCategory === cat
                        ? 'bg-[#0E6E7E] text-white shadow-2xs'
                        : 'bg-[#F8FAFC] text-[#5B6770] hover:text-[#0F2747] border border-[#D6E0E7]'"
                >
                    {{ cat === 'all' ? 'الكل' : cat }}
                </button>
            </div>
        </div>

        <!-- جدول المصروفات -->
        <div v-if="filteredExpenses.length > 0" class="rounded-3xl border border-[#D6E0E7] bg-white overflow-hidden shadow-2xs">
            <div class="overflow-x-auto">
                <table class="w-full text-right text-sm">
                    <thead>
                        <tr class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] text-xs font-bold">
                            <th class="py-3.5 px-6">التاريخ</th>
                            <th class="py-3.5 px-6">الفئة والتصنيف</th>
                            <th class="py-3.5 px-6">العقار المعني</th>
                            <th class="py-3.5 px-6">المبلغ</th>
                            <th class="py-3.5 px-6">بيان المصروف والملاحظات</th>
                            <th class="py-3.5 px-6">المسؤول</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#D6E0E7]">
                        <tr
                            v-for="exp in filteredExpenses"
                            :key="exp.id"
                            class="hover:bg-[#F3F7FA] transition"
                        >
                            <td class="py-4 px-6 font-mono text-xs text-[#5B6770]">
                                {{ exp.expense_date }}
                            </td>
                            <td class="py-4 px-6">
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-[#F1F5F9] text-[#0F2747] border border-black/5">
                                    {{ exp.category }}
                                </span>
                            </td>
                            <td class="py-4 px-6 text-xs font-bold text-[#0F2747]">
                                {{ exp.property_name }}
                            </td>
                            <td class="py-4 px-6 font-mono text-sm font-bold text-rose-700">
                                {{ exp.amount.toLocaleString() }} ج.م
                            </td>
                            <td class="py-4 px-6 text-xs text-[#5B6770]">
                                {{ exp.notes }}
                            </td>
                            <td class="py-4 px-6 text-xs text-[#0F2747]">
                                {{ exp.recorder_name }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <EmptyState
            v-else
            type="document"
            title="لا توجد مصروفات مسجلة"
            description="لم يتم تسجيل أي مصروفات أو فواتير مطابقة لبحثك الحالي."
            action-label="تسجيل مصروف جديد"
            @action="showAddModal = true"
        />

        <!-- نافذة تسجيل مصروف جديد -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showAddModal = false" class="fixed inset-0 bg-[#0F2747]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-6 z-10 animate-in fade-in zoom-in-95" dir="rtl">
                <div class="flex items-center justify-between pb-4 border-b border-[#D6E0E7] mb-5">
                    <h3 class="text-base font-bold text-[#0F2747]">تسجيل مصروف تشغيلي جديد</h3>
                    <button @click="showAddModal = false" type="button" class="p-1.5 text-[#5B6770] hover:text-[#0F2747]">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">العقار المعني <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري)</span></label>
                        <select
                            v-model="form.property_id"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition"
                        >
                            <option value="">مصروف عام للمنشأة (غير مخصص لعقار)</option>
                            <option v-for="p in properties" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-[#0F2747] mb-1.5">فئة المصروف *</label>
                            <select
                                v-model="form.category"
                                required
                                class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition"
                            >
                                <option value="صيانة وتشغيل">صيانة وتشغيل</option>
                                <option value="كهرباء ومياه">كهرباء ومياه</option>
                                <option value="نظافة وخدمات">نظافة وخدمات</option>
                                <option value="حراسة وأمن">حراسة وأمن</option>
                                <option value="أخرى">أخرى</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-[#0F2747] mb-1.5">المبلغ المدفوع *</label>
                            <div class="relative">
                                <input
                                    v-model="form.amount"
                                    type="number"
                                    step="0.01"
                                    placeholder="0.00"
                                    required
                                    class="w-full h-11 pl-10 pr-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm font-bold text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-left"
                                />
                                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-[#5B6770]">ج.م</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">تاريخ المصروف *</label>
                        <input
                            v-model="form.expense_date"
                            type="date"
                            required
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-left"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">بيان وتفاصيل المصروف <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري)</span></label>
                        <textarea
                            v-model="form.notes"
                            rows="2"
                            placeholder="مثال: فاتورة كهرباء العداد المشترك للمصعد والمداخل..."
                            class="w-full p-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition"
                        ></textarea>
                    </div>

                    <div class="pt-4 flex items-center justify-end gap-3 border-t border-[#D6E0E7]">
                        <button
                            @click="showAddModal = false"
                            type="button"
                            class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold text-[#5B6770]"
                        >
                            إلغاء
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="h-10 px-5 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-bold transition flex items-center gap-2"
                        >
                            <Check class="w-4 h-4" />
                            <span>{{ form.processing ? 'جاري التسجيل...' : 'حفظ المصروف' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

