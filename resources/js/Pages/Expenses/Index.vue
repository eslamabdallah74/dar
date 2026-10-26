<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import {
    AlertCircle,
    Check,
    CheckCircle2,
    Clock,
    Coins,
    CreditCard,
    Edit3,
    Loader2,
    Plus,
    Receipt,
    Search,
    Wrench,
    X,
} from 'lucide-vue-next';

interface ExpenseItem {
    id: number;
    title: string;
    category: string;
    amount: number | null;
    expense_date: string;
    raw_date?: string;
    property_id?: number | null;
    property_name: string;
    unit_id?: number | null;
    unit_name: string;
    notes: string;
    recorder_name: string;
    origin: 'manual' | 'maintenance' | string;
    origin_label: string;
    status: 'pending_amount' | 'ready' | 'recorded' | string;
    status_label: string;
    is_pending_amount: boolean;
    maintenance_request_id?: number | null;
    payment_status?: string;
    is_paid?: boolean;
    paid_at?: string | null;
}

const props = withDefaults(defineProps<{
    expenses: ExpenseItem[];
    properties: Array<{ id: number; name: string }>;
    stats: {
        total_amount: number;
        count: number;
        recorded_count?: number;
        pending_cost_count: number;
        maintenance_count?: number;
        maintenance_expenses_total?: number;
        categories: Record<string, number>;
        applied_count?: number;
        pending_count?: number;
        pending_amount?: number;
        ready_to_apply_count?: number;
    };
    currency?: string;
}>(), {
    currency: 'ج.م',
});

const selectedCategory = ref('all');
const selectedStatus = ref('all');
const searchQuery = ref('');
const showAddModal = ref(false);
const editingExpense = ref<ExpenseItem | null>(null);

const addForm = useForm({
    property_id: props.properties[0]?.id ?? '',
    title: '',
    category: 'صيانة وتشغيل',
    amount: '',
    expense_date: new Date().toISOString().split('T')[0],
    notes: '',
});

const editForm = useForm({
    amount: '' as string | number,
    category: '',
    expense_date: '',
    notes: '',
    property_id: '' as string | number,
});

const filteredExpenses = computed(() => {
    return props.expenses.filter(e => {
        const matchesSearch = (e.notes && e.notes.includes(searchQuery.value))
            || (e.title && e.title.includes(searchQuery.value))
            || (e.category && e.category.includes(searchQuery.value))
            || (e.property_name && e.property_name.includes(searchQuery.value));

        const matchesCat = selectedCategory.value === 'all'
            || e.category === selectedCategory.value;

        const matchesStatus = selectedStatus.value === 'all'
            || (selectedStatus.value === 'maintenance' && e.origin === 'maintenance')
            || (selectedStatus.value === 'pending_amount' && e.is_pending_amount)
            || (selectedStatus.value === 'ready' && (e.status === 'ready' || e.status === 'ready_to_apply'))
            || (selectedStatus.value === 'recorded' && (e.status === 'recorded' || e.status === 'applied'));

        return matchesSearch && matchesCat && matchesStatus;
    });
});

const submitAdd = () => {
    addForm.post(route('expenses.store'), {
        onSuccess: () => {
            addForm.reset();
            showAddModal.value = false;
        },
    });
};

const openEditModal = (expense: ExpenseItem) => {
    editingExpense.value = expense;
    editForm.amount = expense.amount !== null ? expense.amount : '';
    editForm.category = expense.category;
    editForm.expense_date = expense.raw_date || new Date().toISOString().split('T')[0];
    editForm.notes = expense.notes;
    editForm.property_id = expense.property_id ?? '';
};

const submitEdit = () => {
    if (!editingExpense.value) return;

    editForm.put(route('expenses.update', editingExpense.value.id), {
        onSuccess: () => {
            editingExpense.value = null;
        },
    });
};

const isRecordingPayment = ref<number | null>(null);

const handleRecordPayment = (expense: ExpenseItem) => {
    if (!expense.id || expense.is_pending_amount || isRecordingPayment.value !== null) return;

    if (!confirm('هل أنت متأكد من تسجيل سداد هذا المصروف؟')) return;

    isRecordingPayment.value = expense.id;
    router.post(route('expenses.payment.record', expense.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            isRecordingPayment.value = null;
        },
    });
};

const handleReversePayment = (expense: ExpenseItem) => {
    if (!expense.id || isRecordingPayment.value !== null) return;

    if (!confirm('هل تريد إلغاء تسجيل سداد هذا المصروف وإعادته كغير مدفوع؟')) return;

    isRecordingPayment.value = expense.id;
    router.post(route('expenses.payment.reverse', expense.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            isRecordingPayment.value = null;
        },
    });
};
</script>

<template>
    <AppLayout
        title="المصروفات التشغيلية — Nook Mate"
        active-tab="expenses"
        header-title="المصروفات التشغيلية"
        header-subtitle="المسار المالي لتسجيل الفواتير، عقود وتكاليف الصيانة الدورية وتحديثها لحظياً"
        breadcrumb="المصروفات"
    >
        <template #actions>
            <div class="flex items-center gap-2.5">
                <Link
                    :href="route('maintenance.index')"
                    class="h-11 px-4 rounded-xl border border-[#D6E0E7] bg-white hover:bg-[#F8FAFC] text-[#163E3E] text-sm font-semibold transition flex items-center gap-2 shadow-2xs"
                >
                    <Wrench class="h-4 w-4 text-[#163E3E]" />
                    <span>متابعة طلبات الصيانة</span>
                </Link>

                <button
                    @click="showAddModal = true"
                    type="button"
                    class="h-11 px-5 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-sm font-semibold transition shadow-xs flex items-center gap-2 cursor-pointer"
                >
                    <Plus class="h-4 w-4 stroke-[2.5]" />
                    <span>تسجيل مصروف يدوي</span>
                </button>
            </div>
        </template>

        <!-- كروت مؤشرات المصروفات -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">إجمالي المصروفات المالية الفعلية</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-rose-700 font-mono">
                        {{ stats.total_amount.toLocaleString() }}
                        <span class="text-xs font-sans font-normal text-[#5B6770]">{{ currency }}</span>
                    </span>
                    <span class="text-xs text-emerald-700 font-semibold bg-emerald-50 px-2 py-0.5 rounded-md flex items-center gap-1">
                        <CheckCircle2 class="w-3.5 h-3.5" />
                        <span>مسجل ومحتسب</span>
                    </span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">صيانة بانتظار تحديد التكلفة</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-amber-600 font-mono">
                        {{ stats.pending_cost_count }}
                        <span class="text-xs font-sans font-normal text-[#5B6770]">طلب</span>
                    </span>
                    <span class="text-xs text-amber-800 bg-amber-50 px-2 py-0.5 rounded-md font-semibold">
                        مستبعد من الإجمالي
                    </span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">المصروفات الناتجة عن الصيانة</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-indigo-700 font-mono">
                        {{ (stats.maintenance_expenses_total ?? 0).toLocaleString() }}
                        <span class="text-xs font-sans font-normal text-[#5B6770]">{{ currency }}</span>
                    </span>
                    <span class="text-xs text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-md font-semibold">
                        {{ stats.maintenance_count ?? 0 }} بند صيانة
                    </span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">إجمالي قيود المصروفات</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#163E3E] font-mono">{{ stats.count }}</span>
                    <span class="text-xs text-[#5B6770]">قيد بالنظام</span>
                </div>
            </div>
        </div>

        <!-- إشعار الشفافية والتكامل التلقائي -->
        <div class="p-4 rounded-2xl bg-blue-50/70 border border-blue-200/80 text-xs text-blue-900 flex items-start gap-3">
            <Receipt class="w-5 h-5 text-[#163E3E] shrink-0 mt-0.5" />
            <div class="space-y-1">
                <p class="font-bold">تكامل المصروفات وبلاغات الصيانة:</p>
                <p class="text-blue-800 leading-relaxed">
                    يتم إنشاء قيود المصروفات التلقائية من بلاغات الصيانة بدون مبلغ لحين إدخال الفاتورة الفعلية. بمجرد إدخال المبلغ هنا، يدخل المصروف مباشرة في الحسابات والتقارير المالية دون الحاجة لأي إجراء اعتماد يدوي، ويصبح بالإمكان إنهاء وإكمال طلب الصيانة من صفحة الصيانة.
                </p>
            </div>
        </div>

        <!-- شريط البحث والتصفية حسب الفئة والحالة -->
        <div class="flex flex-col gap-3 bg-white p-4 rounded-2xl border border-[#D6E0E7] shadow-2xs">
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                <div class="relative flex-1 max-w-md">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="ابحث ببيان المصروف أو الفئة أو العقار..."
                        class="w-full h-10 pr-10 pl-4 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] placeholder:text-[#5B6770] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right"
                    />
                    <Search class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-[#5B6770]" />
                </div>

                <!-- تصفية حسب حالة المصروف -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
                    <button
                        v-for="st in [
                            { id: 'all', label: 'جميع المصروفات' },
                            { id: 'pending_amount', label: 'بانتظار المبلغ' },
                            { id: 'ready', label: 'جاهز' },
                            { id: 'recorded', label: 'تم تسجيلها' },
                            { id: 'maintenance', label: 'من الصيانة فقط' }
                        ]"
                        :key="st.id"
                        @click="selectedStatus = st.id"
                        type="button"
                        class="px-3 py-1.5 rounded-xl text-xs font-semibold transition cursor-pointer shrink-0"
                        :class="selectedStatus === st.id
                            ? 'bg-[#163E3E] text-white shadow-2xs'
                            : 'bg-[#F8FAFC] text-[#5B6770] hover:text-[#163E3E] border border-[#D6E0E7]'"
                    >
                        {{ st.label }}
                    </button>
                </div>
            </div>

            <!-- تصنيف الفئات -->
            <div class="flex items-center gap-1.5 overflow-x-auto pt-2 border-t border-slate-100">
                <span class="text-xs text-[#5B6770] font-semibold pl-2 shrink-0">الفئة:</span>
                <button
                    v-for="cat in ['all', 'صيانة وتشغيل', 'كهرباء ومياه', 'نظافة وخدمات', 'حراسة وأمن', 'أخرى']"
                    :key="cat"
                    @click="selectedCategory = cat"
                    type="button"
                    class="px-2.5 py-1 rounded-lg text-xs font-medium transition cursor-pointer shrink-0"
                    :class="selectedCategory === cat
                        ? 'bg-slate-800 text-white shadow-2xs font-bold'
                        : 'bg-slate-100 text-slate-600 hover:text-slate-900'"
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
                            <th class="py-3.5 px-6">البيان والمصدر</th>
                            <th class="py-3.5 px-6">التصنيف</th>
                            <th class="py-3.5 px-6">العقار المعني</th>
                            <th class="py-3.5 px-6">الوحدة</th>
                            <th class="py-3.5 px-6">المبلغ</th>
                            <th class="py-3.5 px-6">الحالة</th>
                            <th class="py-3.5 px-6">الملاحظات</th>
                            <th class="py-3.5 px-6 text-center">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#D6E0E7]">
                        <tr
                            v-for="exp in filteredExpenses"
                            :key="exp.id"
                            :class="exp.is_pending_amount
                                ? 'bg-amber-50/70 border-r-4 border-r-amber-500 hover:bg-amber-100/60 transition'
                                : 'hover:bg-[#F8F4EA] transition'"
                        >
                            <td class="py-4 px-6 font-mono text-xs text-[#5B6770]">
                                {{ exp.expense_date }}
                            </td>

                            <td class="py-4 px-6 space-y-1.5">
                                <div class="text-xs font-bold text-[#163E3E]">
                                    {{ exp.title || 'بند مصروف عام' }}
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span
                                        v-if="exp.origin === 'maintenance'"
                                        class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300 flex items-center gap-1"
                                    >
                                        <Wrench class="w-3 h-3 text-amber-700" />
                                        <span>مصدر: صيانة #{{ exp.maintenance_request_id }}</span>
                                    </span>
                                    <span
                                        v-else
                                        class="px-2 py-0.5 rounded-md text-[10px] font-medium bg-slate-100 text-slate-600 border border-slate-200"
                                    >
                                        يدوي مباشر
                                    </span>
                                </div>
                            </td>

                            <td class="py-4 px-6">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-[#F1F5F9] text-[#163E3E] border border-black/5">
                                    {{ exp.category }}
                                </span>
                            </td>

                            <td class="py-4 px-6 text-xs text-[#163E3E] font-bold">
                                {{ exp.property_name }}
                            </td>

                            <td class="py-4 px-6 text-xs text-[#5B6770]">
                                {{ exp.unit_name }}
                            </td>

                            <td class="py-4 px-6 font-mono text-sm">
                                <span v-if="exp.amount !== null && exp.amount > 0" class="font-bold text-rose-700">
                                    {{ exp.amount.toLocaleString('en-US') }} {{ currency }}
                                </span>
                                <div v-else class="flex items-center gap-2">
                                    <span class="text-xs italic text-amber-700 font-bold bg-amber-100 px-2 py-0.5 rounded-md">
                                        غير محدد
                                    </span>
                                    <button
                                        @click="openEditModal(exp)"
                                        type="button"
                                        class="h-7 px-2.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-bold text-[11px] transition flex items-center gap-1 shadow-2xs cursor-pointer"
                                    >
                                        <Coins class="w-3.5 h-3.5" />
                                        <span>إدخال المبلغ</span>
                                    </button>
                                </div>
                            </td>

                            <td class="py-4 px-6 space-y-1">
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold inline-flex items-center gap-1" :class="[
                                    exp.is_pending_amount ? 'bg-amber-100 text-amber-900 border border-amber-300' :
                                    (exp.status === 'ready' || exp.status === 'ready_to_apply') ? 'bg-indigo-50 text-indigo-800 border border-indigo-200' :
                                    'bg-slate-100 text-slate-800 border border-slate-200'
                                ]">
                                    <AlertCircle v-if="exp.is_pending_amount" class="w-3 h-3 text-amber-700" />
                                    <CheckCircle2 v-else class="w-3 h-3 text-emerald-700" />
                                    <span>{{ exp.status_label }}</span>
                                </span>

                                <!-- شارة حالة السداد للمصروفات ذات التكلفة المحددة -->
                                <div v-if="!exp.is_pending_amount" class="pt-0.5">
                                    <span
                                        class="px-2 py-0.5 rounded-md text-[10px] font-bold inline-flex items-center gap-1 border"
                                        :class="exp.is_paid
                                            ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                                            : 'bg-amber-50 text-amber-800 border-amber-200'"
                                    >
                                        <CheckCircle2 v-if="exp.is_paid" class="w-2.5 h-2.5 text-emerald-600" />
                                        <Clock v-else class="w-2.5 h-2.5 text-amber-600" />
                                        <span>{{ exp.is_paid ? 'مدفوع' : 'غير مدفوع' }}</span>
                                    </span>
                                </div>
                            </td>

                            <td class="py-4 px-6 text-xs text-[#5B6770] max-w-xs truncate" :title="exp.notes">
                                {{ exp.notes || '—' }}
                            </td>

                            <td class="py-4 px-6 text-center">
                                <div class="flex items-center justify-center gap-1.5 flex-wrap">
                                    <!-- زر السداد للمصروفات ذات التكلفة المحددة -->
                                    <button
                                        v-if="!exp.is_pending_amount && !exp.is_paid"
                                        @click="handleRecordPayment(exp)"
                                        :disabled="isRecordingPayment === exp.id"
                                        type="button"
                                        class="h-8 px-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition flex items-center gap-1 shadow-2xs cursor-pointer disabled:opacity-50"
                                        title="تسجيل سداد المصروف"
                                    >
                                        <Loader2 v-if="isRecordingPayment === exp.id" class="w-3.5 h-3.5 animate-spin" />
                                        <CreditCard v-else class="w-3.5 h-3.5" />
                                        <span>سداد</span>
                                    </button>

                                    <button
                                        v-else-if="!exp.is_pending_amount && exp.is_paid"
                                        @click="handleReversePayment(exp)"
                                        :disabled="isRecordingPayment === exp.id"
                                        type="button"
                                        class="h-8 px-2 rounded-lg border border-slate-200 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-700 text-xs font-medium text-[#5B6770] transition flex items-center gap-1 cursor-pointer disabled:opacity-50"
                                        title="إلغاء تسجيل السداد"
                                    >
                                        <span>إلغاء السداد</span>
                                    </button>

                                    <button
                                        @click="openEditModal(exp)"
                                        type="button"
                                        class="h-8 px-3 rounded-lg border border-[#D6E0E7] bg-white hover:bg-slate-50 text-xs font-semibold text-[#163E3E] transition flex items-center gap-1.5 cursor-pointer shadow-2xs"
                                    >
                                        <Edit3 class="w-3.5 h-3.5 text-[#163E3E]" />
                                        <span>{{ exp.is_pending_amount ? 'إدخال المبلغ' : 'تعديل' }}</span>
                                    </button>
                                </div>
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
            description="لم يتم العثور على أي مصروفات مطابقة لمعايير البحث والتصفية المحددة."
            action-label="تسجيل مصروف يدوي"
            @action="showAddModal = true"
        />

        <!-- نافذة تسجيل مصروف يدوي جديد -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showAddModal = false" class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-6 z-10 animate-in fade-in zoom-in-95" dir="rtl">
                <div class="flex items-center justify-between pb-4 border-b border-[#D6E0E7] mb-5">
                    <h3 class="text-base font-bold text-[#163E3E]">تسجيل مصروف يدوي جديد</h3>
                    <button @click="showAddModal = false" type="button" class="p-1.5 text-[#5B6770] hover:text-[#163E3E]">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitAdd" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1.5">العقار المعني <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري)</span></label>
                        <select
                            v-model="addForm.property_id"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                        >
                            <option value="">مصروف عام للمنشأة (غير مخصص لعقار)</option>
                            <option v-for="p in properties" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1.5">عنوان أو بيان المصروف *</label>
                        <input
                            v-model="addForm.title"
                            type="text"
                            placeholder="مثال: فاتورة كهرباء، صيانة مضخة مياه..."
                            required
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-[#163E3E] mb-1.5">فئة المصروف *</label>
                            <select
                                v-model="addForm.category"
                                required
                                class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                            >
                                <option value="صيانة وتشغيل">صيانة وتشغيل</option>
                                <option value="كهرباء ومياه">كهرباء ومياه</option>
                                <option value="نظافة وخدمات">نظافة وخدمات</option>
                                <option value="حراسة وأمن">حراسة وأمن</option>
                                <option value="أخرى">أخرى</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-[#163E3E] mb-1.5">المبلغ المدفوع *</label>
                            <div class="relative">
                                <input
                                    v-model="addForm.amount"
                                    type="number"
                                    step="0.01"
                                    placeholder="0.00"
                                    required
                                    class="w-full h-11 pl-10 pr-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm font-bold text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right"
                                />
                                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-[#5B6770]">{{ currency }}</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1.5">تاريخ المصروف *</label>
                        <input
                            v-model="addForm.expense_date"
                            type="date"
                            required
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1.5">ملاحظات وتفاصيل إضافية <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري)</span></label>
                        <textarea
                            v-model="addForm.notes"
                            rows="2"
                            placeholder="اكتب أية تفاصيل أو رقم فاتورة..."
                            class="w-full p-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
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
                            :disabled="addForm.processing"
                            class="h-10 px-5 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold transition flex items-center gap-2 cursor-pointer"
                        >
                            <Check class="w-4 h-4" />
                            <span>{{ addForm.processing ? 'جاري التسجيل...' : 'حفظ المصروف' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- نافذة تعديل / إدخال تكلفة المصروف -->
        <div v-if="editingExpense" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="editingExpense = null" class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-6 z-10 animate-in fade-in zoom-in-95" dir="rtl">
                <div class="flex items-center justify-between pb-4 border-b border-[#D6E0E7] mb-5">
                    <div>
                        <h3 class="text-base font-bold text-[#163E3E]">
                            {{ editingExpense.origin === 'maintenance' ? 'تسجيل تكلفة صيانة' : 'تعديل المصروف' }}
                        </h3>
                        <p v-if="editingExpense.origin === 'maintenance'" class="text-xs text-[#5B6770] mt-0.5">
                            مرتبط بطلب الصيانة: <strong class="text-[#163E3E]">{{ editingExpense.title }}</strong>
                        </p>
                    </div>
                    <button @click="editingExpense = null" type="button" class="p-1.5 text-[#5B6770] hover:text-[#163E3E]">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitEdit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1.5">المبلغ الفعلي (التكلفة) *</label>
                        <div class="relative">
                            <input
                                v-model="editForm.amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                placeholder="أدخل التكلفة الفعلية..."
                                required
                                class="w-full h-11 pl-10 pr-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm font-bold text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right"
                            />
                            <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-[#5B6770]">{{ currency }}</span>
                        </div>
                        <p v-if="editingExpense.origin === 'maintenance'" class="text-[11px] text-[#5B6770] mt-1">
                            عند إدخال المبلغ سيدخل المصروف مباشرة في الحسابات المالية، ويصبح بالإمكان إنهاء طلب الصيانة من صفحة الصيانة.
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-[#163E3E] mb-1.5">الفئة</label>
                            <select
                                v-model="editForm.category"
                                class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                            >
                                <option value="صيانة وتشغيل">صيانة وتشغيل</option>
                                <option value="كهرباء ومياه">كهرباء ومياه</option>
                                <option value="نظافة وخدمات">نظافة وخدمات</option>
                                <option value="حراسة وأمن">حراسة وأمن</option>
                                <option value="أخرى">أخرى</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-[#163E3E] mb-1.5">تاريخ المصروف</label>
                            <input
                                v-model="editForm.expense_date"
                                type="date"
                                required
                                class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1.5">ملاحظات وبيان الفاتورة</label>
                        <textarea
                            v-model="editForm.notes"
                            rows="2"
                            placeholder="مثال: فاتورة فني السباكة وقطع الغيار..."
                            class="w-full p-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                        ></textarea>
                    </div>

                    <div class="pt-4 flex items-center justify-end gap-3 border-t border-[#D6E0E7]">
                        <button
                            @click="editingExpense = null"
                            type="button"
                            class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold text-[#5B6770]"
                        >
                            إلغاء
                        </button>
                        <button
                            type="submit"
                            :disabled="editForm.processing"
                            class="h-10 px-5 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold transition flex items-center gap-2 cursor-pointer"
                        >
                            <Check class="w-4 h-4" />
                            <span>{{ editForm.processing ? 'جاري الحفظ...' : 'حفظ التكلفة' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
