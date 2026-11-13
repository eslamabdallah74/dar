<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import ResourceTableHeading from '@/Components/Common/ResourceTableHeading.vue';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { useLocale } from '@/composables/useLocale';
import {
    AlertCircle,
    Check,
    CheckCircle2,
    Clock,
    Coins,
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
    origin: string;
    origin_label: string;
    status: string;
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
    currency_symbol?: string;
}>(), {
    currency: 'EGP',
    currency_symbol: 'ج.م',
});

const { t, isRtl, dir } = useLocale();

const selectedCategory = ref('all');
const selectedStatus = ref('all');
const searchQuery = ref('');
const showAddModal = ref(false);
const editingExpense = ref<ExpenseItem | null>(null);

const statusOptions = computed(() => [
    { id: 'all', label: t('expenses.status_all') },
    { id: 'pending_amount', label: t('expenses.status_pending_amount') },
    { id: 'ready', label: t('expenses.status_ready') },
    { id: 'recorded', label: t('expenses.status_recorded') },
    { id: 'maintenance', label: t('expenses.status_maintenance') },
]);

const categoryOptions = computed(() => [
    { id: 'all', label: t('expenses.cat_all') },
    { id: 'صيانة وتشغيل', label: t('expenses.cat_maintenance') },
    { id: 'كهرباء ومياه', label: t('expenses.cat_utilities') },
    { id: 'نظافة وخدمات', label: t('expenses.cat_cleaning') },
    { id: 'حراسة وأمن', label: t('expenses.cat_security') },
    { id: 'أخرى', label: t('expenses.cat_other') },
]);

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
        const matchesSearch = Boolean(
            e.notes?.includes(searchQuery.value)
            || e.title?.includes(searchQuery.value)
            || e.category?.includes(searchQuery.value)
            || e.property_name?.includes(searchQuery.value),
        );

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
    editForm.amount = expense.amount ?? '';
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
const confirmDialog = useConfirmDialog();

const expenseDetails = (expense: ExpenseItem) => [
    { label: t('expenses.details_expense'), value: expense.title || t('expenses.general_expense_item') },
    { label: t('expenses.details_amount'), value: `${(expense.amount ?? 0).toLocaleString('en-US')} ${props.currency_symbol}` },
];

const handleRecordPayment = async (expense: ExpenseItem) => {
    if (!expense.id || expense.is_pending_amount || isRecordingPayment.value !== null) return;

    const confirmed = await confirmDialog.confirm({
        icon: Coins,
        title: t('expenses.confirm_payment_title'),
        message: t('expenses.confirm_payment_message'),
        details: expenseDetails(expense),
        confirmLabel: t('expenses.confirm_payment_btn'),
    });
    if (!confirmed) return;

    isRecordingPayment.value = expense.id;
    router.post(route('expenses.payment.record', expense.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            isRecordingPayment.value = null;
        },
    });
};

const handleReversePayment = async (expense: ExpenseItem) => {
    if (!expense.id || isRecordingPayment.value !== null) return;

    const confirmed = await confirmDialog.confirm({
        tone: 'danger',
        title: t('expenses.confirm_reverse_title'),
        message: t('expenses.confirm_reverse_message'),
        details: expenseDetails(expense),
        confirmLabel: t('expenses.confirm_reverse_btn'),
        cancelLabel: t('expenses.confirm_keep_paid'),
    });
    if (!confirmed) return;

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
        :title="$t('expenses.title') + ' — Nook Mate'"
        active-tab="expenses"
        :header-title="$t('expenses.title')"
        :header-subtitle="$t('expenses.subtitle')"
        :breadcrumb="$t('expenses.breadcrumb')"
    >
        <template #actions>
            <div class="flex items-center gap-2.5">
                <Link
                    :href="route('maintenance.index')"
                    class="h-11 px-4 rounded-xl border border-[#D6E0E7] bg-white hover:bg-[#F8FAFC] text-[#163E3E] text-sm font-semibold transition flex items-center gap-2 shadow-2xs"
                >
                    <Wrench class="h-4 w-4 text-[#163E3E]" />
                    <span>{{ $t('expenses.track_maintenance') }}</span>
                </Link>

                <button
                    @click="showAddModal = true"
                    type="button"
                    class="h-11 px-5 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-sm font-semibold transition shadow-xs flex items-center gap-2 cursor-pointer"
                >
                    <Plus class="h-4 w-4 stroke-[2.5]" />
                    <span>{{ $t('expenses.add_manual_expense') }}</span>
                </button>
            </div>
        </template>

        <!-- كروت مؤشرات المصروفات -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">{{ $t('expenses.kpi_total_actual') }}</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-rose-700 font-mono">
                        {{ stats.total_amount.toLocaleString() }}
                        <span class="text-xs font-sans font-normal text-[#5B6770]">{{ currency_symbol }}</span>
                    </span>
                    <span class="text-xs text-emerald-700 font-semibold bg-emerald-50 px-2 py-0.5 rounded-md flex items-center gap-1">
                        <CheckCircle2 class="w-3.5 h-3.5" />
                        <span>{{ $t('expenses.kpi_recorded_accounted') }}</span>
                    </span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">{{ $t('expenses.kpi_pending_cost') }}</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-amber-600 font-mono">
                        {{ stats.pending_cost_count }}
                        <span class="text-xs font-sans font-normal text-[#5B6770]">{{ $t('expenses.kpi_request') }}</span>
                    </span>
                    <span class="text-xs text-amber-800 bg-amber-50 px-2 py-0.5 rounded-md font-semibold">
                        {{ $t('expenses.kpi_excluded_from_total') }}
                    </span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">{{ $t('expenses.kpi_from_maintenance') }}</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-indigo-700 font-mono">
                        {{ (stats.maintenance_expenses_total ?? 0).toLocaleString() }}
                        <span class="text-xs font-sans font-normal text-[#5B6770]">{{ currency_symbol }}</span>
                    </span>
                    <span class="text-xs text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-md font-semibold">
                        {{ stats.maintenance_count ?? 0 }} {{ $t('expenses.kpi_maintenance_item') }}
                    </span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">{{ $t('expenses.kpi_total_records') }}</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#163E3E] font-mono">{{ stats.count }}</span>
                    <span class="text-xs text-[#5B6770]">{{ $t('expenses.kpi_records_in_system') }}</span>
                </div>
            </div>
        </div>

        <!-- إشعار الشفافية والتكامل التلقائي -->
        <div class="p-4 rounded-2xl bg-blue-50/70 border border-blue-200/80 text-xs text-blue-900 flex items-start gap-3">
            <Receipt class="w-5 h-5 text-[#163E3E] shrink-0 mt-0.5" />
            <div class="space-y-1">
                <p class="font-bold">{{ $t('expenses.transparency_title') }}</p>
                <p class="text-blue-800 leading-relaxed">
                    {{ $t('expenses.transparency_desc') }}
                </p>
            </div>
        </div>

        <!-- شريط البحث والتصفية حسب الفئة والحالة -->
        <div class="flex flex-col gap-3 bg-white p-4 rounded-2xl border border-[#D6E0E7] shadow-2xs">
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                <div class="relative flex-1 max-w-md">
                    <label for="expenses-search-query" class="sr-only">{{ $t('expenses.search_label') }}</label>
                    <input
                        id="expenses-search-query"
                        v-model="searchQuery"
                        type="text"
                        :aria-label="$t('expenses.search_placeholder')"
                        :placeholder="$t('expenses.search_placeholder')"
                        class="w-full h-10 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] placeholder:text-[#5B6770] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-start"
                        :class="isRtl ? 'pr-10 pl-4' : 'pl-10 pr-4'"
                    />
                    <Search
                        class="pointer-events-none absolute top-1/2 -translate-y-1/2 h-4 w-4 text-[#5B6770]"
                        :class="isRtl ? 'right-3.5' : 'left-3.5'"
                    />
                </div>

                <!-- تصفية حسب حالة المصروف -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
                    <button
                        v-for="st in statusOptions"
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
                <span class="text-xs text-[#5B6770] font-semibold pl-2 shrink-0">{{ $t('expenses.category_label') }}</span>
                <button
                    v-for="cat in categoryOptions"
                    :key="cat.id"
                    @click="selectedCategory = cat.id"
                    type="button"
                    class="px-2.5 py-1 rounded-lg text-xs font-medium transition cursor-pointer shrink-0"
                    :class="selectedCategory === cat.id
                        ? 'bg-slate-800 text-white shadow-2xs font-bold'
                        : 'bg-slate-100 text-slate-600 hover:text-slate-900'"
                >
                    {{ cat.label }}
                </button>
            </div>
        </div>

        <!-- جدول المصروفات -->
        <div v-if="filteredExpenses.length > 0" class="rounded-3xl border border-[#D6E0E7] bg-white overflow-hidden shadow-2xs">
            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm">
                    <thead>
                        <tr class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] text-xs font-bold">
                            <th class="py-3.5 px-6">{{ $t('expenses.th_date') }}</th>
                            <th class="py-3.5 px-6">{{ $t('expenses.th_title_source') }}</th>
                            <th class="py-3.5 px-6">{{ $t('expenses.th_category') }}</th>
                            <th class="py-3.5 px-6">
                                <ResourceTableHeading :href="route('properties.index')">{{ $t('expenses.th_property') }}</ResourceTableHeading>
                            </th>
                            <th class="py-3.5 px-6">
                                <ResourceTableHeading :href="route('properties.index')">{{ $t('expenses.th_unit') }}</ResourceTableHeading>
                            </th>
                            <th class="py-3.5 px-6">{{ $t('expenses.th_amount') }}</th>
                            <th class="py-3.5 px-6">{{ $t('expenses.th_status') }}</th>
                            <th class="py-3.5 px-6">{{ $t('expenses.th_notes') }}</th>
                            <th class="py-3.5 px-6 text-center">{{ $t('expenses.th_actions') }}</th>
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
                                    {{ exp.title || $t('expenses.general_expense_item') }}
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span
                                        v-if="exp.origin === 'maintenance'"
                                        class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300 flex items-center gap-1"
                                    >
                                        <Wrench class="w-3 h-3 text-amber-700" />
                                        <span>{{ $t('expenses.source_maintenance', { id: exp.maintenance_request_id }) }}</span>
                                    </span>
                                    <span
                                        v-else
                                        class="px-2 py-0.5 rounded-md text-[10px] font-medium bg-slate-100 text-slate-600 border border-slate-200"
                                    >
                                        {{ $t('expenses.source_manual') }}
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
                                    {{ exp.amount.toLocaleString('en-US') }} {{ currency_symbol }}
                                </span>
                                <div v-else class="flex items-center gap-2">
                                    <span class="text-xs italic text-amber-700 font-bold bg-amber-100 px-2 py-0.5 rounded-md">
                                        {{ $t('expenses.amount_not_set') }}
                                    </span>
                                    <button
                                        @click="openEditModal(exp)"
                                        type="button"
                                        class="h-7 px-2.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-bold text-[11px] transition flex items-center gap-1 shadow-2xs cursor-pointer"
                                    >
                                        <Coins class="w-3.5 h-3.5" />
                                        <span>{{ $t('expenses.enter_amount') }}</span>
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
                                        <span>{{ exp.is_paid ? $t('expenses.paid') : $t('expenses.unpaid') }}</span>
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
                                        :title="$t('expenses.record_payment')"
                                    >
                                        <Loader2 v-if="isRecordingPayment === exp.id" class="w-3.5 h-3.5 animate-spin" />
                                        <Coins v-else class="w-3.5 h-3.5" />
                                        <span>{{ $t('expenses.record_payment') }}</span>
                                    </button>

                                    <button
                                        v-else-if="!exp.is_pending_amount && exp.is_paid"
                                        @click="handleReversePayment(exp)"
                                        :disabled="isRecordingPayment === exp.id"
                                        type="button"
                                        class="h-8 px-2 rounded-lg border border-slate-200 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-700 text-xs font-medium text-[#5B6770] transition flex items-center gap-1 cursor-pointer disabled:opacity-50"
                                        :title="$t('expenses.reverse_payment')"
                                    >
                                        <span>{{ $t('expenses.reverse_payment') }}</span>
                                    </button>

                                    <button
                                        @click="openEditModal(exp)"
                                        type="button"
                                        class="h-8 px-3 rounded-lg border border-[#D6E0E7] bg-white hover:bg-slate-50 text-xs font-semibold text-[#163E3E] transition flex items-center gap-1.5 cursor-pointer shadow-2xs"
                                    >
                                        <Edit3 class="w-3.5 h-3.5 text-[#163E3E]" />
                                        <span>{{ exp.is_pending_amount ? $t('expenses.enter_amount') : $t('expenses.edit') }}</span>
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
            :title="$t('expenses.empty_title')"
            :description="$t('expenses.empty_desc')"
            :action-label="$t('expenses.add_manual_expense')"
            @action="showAddModal = true"
        />

        <!-- نافذة تسجيل مصروف يدوي جديد -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showAddModal = false" class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs"></div>
            <div :dir="dir" class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-6 z-10 animate-in fade-in zoom-in-95 text-start">
                <div class="flex items-center justify-between pb-4 border-b border-[#D6E0E7] mb-5">
                    <h3 class="text-base font-bold text-[#163E3E]">{{ $t('expenses.modal_add_title') }}</h3>
                    <button @click="showAddModal = false" type="button" :aria-label="$t('common.cancel')" class="p-1.5 text-[#5B6770] hover:text-[#163E3E] cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitAdd" class="space-y-4">
                    <div>
                        <label for="add-expense-property" class="block text-xs font-bold text-[#163E3E] mb-1.5">
                            {{ $t('expenses.modal_property_label') }} <span class="text-[#8A9AA7] font-normal text-[11px]">({{ $t('common.optional') }})</span>
                        </label>
                        <select
                            id="add-expense-property"
                            v-model="addForm.property_id"
                            :aria-label="$t('expenses.modal_property_label')"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                        >
                            <option value="">{{ $t('expenses.modal_property_general') }}</option>
                            <option v-for="p in properties" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                        <p v-if="addForm.errors.property_id" class="text-xs text-rose-600 mt-1">{{ addForm.errors.property_id }}</p>
                    </div>

                    <div>
                        <label for="add-expense-title" class="block text-xs font-bold text-[#163E3E] mb-1.5">{{ $t('expenses.modal_title_label') }}</label>
                        <input
                            id="add-expense-title"
                            v-model="addForm.title"
                            type="text"
                            :aria-label="$t('expenses.modal_title_label')"
                            :placeholder="$t('expenses.modal_title_placeholder')"
                            required
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-start"
                        />
                        <p v-if="addForm.errors.title" class="text-xs text-rose-600 mt-1">{{ addForm.errors.title }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="add-expense-category" class="block text-xs font-bold text-[#163E3E] mb-1.5">{{ $t('expenses.modal_category_label') }}</label>
                            <select
                                id="add-expense-category"
                                v-model="addForm.category"
                                :aria-label="$t('expenses.modal_category_label')"
                                required
                                class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                            >
                                <option value="صيانة وتشغيل">{{ $t('expenses.cat_maintenance') }}</option>
                                <option value="كهرباء ومياه">{{ $t('expenses.cat_utilities') }}</option>
                                <option value="نظافة وخدمات">{{ $t('expenses.cat_cleaning') }}</option>
                                <option value="حراسة وأمن">{{ $t('expenses.cat_security') }}</option>
                                <option value="أخرى">{{ $t('expenses.cat_other') }}</option>
                            </select>
                            <p v-if="addForm.errors.category" class="text-xs text-rose-600 mt-1">{{ addForm.errors.category }}</p>
                        </div>

                        <div>
                            <label for="add-expense-amount" class="block text-xs font-bold text-[#163E3E] mb-1.5">{{ $t('expenses.modal_amount_label') }}</label>
                            <div class="relative">
                                <input
                                    id="add-expense-amount"
                                    v-model="addForm.amount"
                                    type="number"
                                    step="0.01"
                                    :aria-label="$t('expenses.modal_amount_label')"
                                    placeholder="0.00"
                                    required
                                    class="w-full h-11 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm font-bold text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-start"
                                    :class="isRtl ? 'pl-10 pr-3' : 'pr-10 pl-3'"
                                />
                                <span
                                    class="absolute top-1/2 -translate-y-1/2 text-xs text-[#5B6770]"
                                    :class="isRtl ? 'left-2.5' : 'right-2.5'"
                                >
                                    {{ currency_symbol }}
                                </span>
                            </div>
                            <p v-if="addForm.errors.amount" class="text-xs text-rose-600 mt-1">{{ addForm.errors.amount }}</p>
                        </div>
                    </div>

                    <div>
                        <label for="add-expense-date" class="block text-xs font-bold text-[#163E3E] mb-1.5">{{ $t('expenses.modal_date_label') }}</label>
                        <input
                            id="add-expense-date"
                            v-model="addForm.expense_date"
                            type="date"
                            :aria-label="$t('expenses.modal_date_label')"
                            required
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-start"
                        />
                        <p v-if="addForm.errors.expense_date" class="text-xs text-rose-600 mt-1">{{ addForm.errors.expense_date }}</p>
                    </div>

                    <div>
                        <label for="add-expense-notes" class="block text-xs font-bold text-[#163E3E] mb-1.5">
                            {{ $t('expenses.modal_notes_label') }} <span class="text-[#8A9AA7] font-normal text-[11px]">({{ $t('common.optional') }})</span>
                        </label>
                        <textarea
                            id="add-expense-notes"
                            v-model="addForm.notes"
                            :aria-label="$t('expenses.modal_notes_label')"
                            rows="2"
                            :placeholder="$t('expenses.modal_notes_placeholder')"
                            class="w-full p-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                        ></textarea>
                        <p v-if="addForm.errors.notes" class="text-xs text-rose-600 mt-1">{{ addForm.errors.notes }}</p>
                    </div>

                    <div class="pt-4 flex items-center justify-end gap-3 border-t border-[#D6E0E7]">
                        <button
                            @click="showAddModal = false"
                            type="button"
                            class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold text-[#5B6770] cursor-pointer"
                        >
                            {{ $t('common.cancel') }}
                        </button>
                        <button
                            type="submit"
                            :disabled="addForm.processing"
                            class="h-10 px-5 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold transition flex items-center gap-2 cursor-pointer disabled:opacity-50"
                        >
                            <Check class="w-4 h-4" />
                            <span>{{ addForm.processing ? $t('expenses.modal_saving') : $t('expenses.modal_save') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- نافذة تعديل / إدخال تكلفة المصروف -->
        <div v-if="editingExpense" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="editingExpense = null" class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs"></div>
            <div :dir="dir" class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-6 z-10 animate-in fade-in zoom-in-95 text-start">
                <div class="flex items-center justify-between pb-4 border-b border-[#D6E0E7] mb-5">
                    <div>
                        <h3 class="text-base font-bold text-[#163E3E]">
                            {{ editingExpense.origin === 'maintenance' ? $t('expenses.modal_edit_maintenance_title') : $t('expenses.modal_edit_title') }}
                        </h3>
                        <p v-if="editingExpense.origin === 'maintenance'" class="text-xs text-[#5B6770] mt-0.5">
                            {{ $t('expenses.modal_linked_request') }} <strong class="text-[#163E3E]">{{ editingExpense.title }}</strong>
                        </p>
                    </div>
                    <button @click="editingExpense = null" type="button" :aria-label="$t('common.cancel')" class="p-1.5 text-[#5B6770] hover:text-[#163E3E] cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitEdit" class="space-y-4">
                    <div>
                        <label for="edit-expense-amount" class="block text-xs font-bold text-[#163E3E] mb-1.5">{{ $t('expenses.modal_actual_cost_label') }}</label>
                        <div class="relative">
                            <input
                                id="edit-expense-amount"
                                v-model="editForm.amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                :aria-label="$t('expenses.modal_actual_cost_label')"
                                :placeholder="$t('expenses.modal_actual_cost_placeholder')"
                                required
                                class="w-full h-11 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm font-bold text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-start"
                                :class="isRtl ? 'pl-10 pr-3' : 'pr-10 pl-3'"
                            />
                            <span
                                class="absolute top-1/2 -translate-y-1/2 text-xs text-[#5B6770]"
                                :class="isRtl ? 'left-2.5' : 'right-2.5'"
                            >
                                {{ currency_symbol }}
                            </span>
                        </div>
                        <p v-if="editForm.errors.amount" class="text-xs text-rose-600 mt-1">{{ editForm.errors.amount }}</p>
                        <p v-if="editingExpense.origin === 'maintenance'" class="text-[11px] text-[#5B6770] mt-1">
                            {{ $t('expenses.modal_maintenance_cost_hint') }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="edit-expense-category" class="block text-xs font-bold text-[#163E3E] mb-1.5">{{ $t('expenses.th_category') }}</label>
                            <select
                                id="edit-expense-category"
                                v-model="editForm.category"
                                :aria-label="$t('expenses.th_category')"
                                class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                            >
                                <option value="صيانة وتشغيل">{{ $t('expenses.cat_maintenance') }}</option>
                                <option value="كهرباء ومياه">{{ $t('expenses.cat_utilities') }}</option>
                                <option value="نظافة وخدمات">{{ $t('expenses.cat_cleaning') }}</option>
                                <option value="حراسة وأمن">{{ $t('expenses.cat_security') }}</option>
                                <option value="أخرى">{{ $t('expenses.cat_other') }}</option>
                            </select>
                            <p v-if="editForm.errors.category" class="text-xs text-rose-600 mt-1">{{ editForm.errors.category }}</p>
                        </div>

                        <div>
                            <label for="edit-expense-date" class="block text-xs font-bold text-[#163E3E] mb-1.5">{{ $t('expenses.th_date') }}</label>
                            <input
                                id="edit-expense-date"
                                v-model="editForm.expense_date"
                                type="date"
                                :aria-label="$t('expenses.th_date')"
                                required
                                class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-start"
                            />
                            <p v-if="editForm.errors.expense_date" class="text-xs text-rose-600 mt-1">{{ editForm.errors.expense_date }}</p>
                        </div>
                    </div>

                    <div>
                        <label for="edit-expense-notes" class="block text-xs font-bold text-[#163E3E] mb-1.5">{{ $t('expenses.th_notes') }}</label>
                        <textarea
                            id="edit-expense-notes"
                            v-model="editForm.notes"
                            :aria-label="$t('expenses.th_notes')"
                            rows="2"
                            :placeholder="$t('expenses.modal_edit_notes_placeholder')"
                            class="w-full p-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                        ></textarea>
                        <p v-if="editForm.errors.notes" class="text-xs text-rose-600 mt-1">{{ editForm.errors.notes }}</p>
                    </div>

                    <div class="pt-4 flex items-center justify-end gap-3 border-t border-[#D6E0E7]">
                        <button
                            @click="editingExpense = null"
                            type="button"
                            class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold text-[#5B6770] cursor-pointer"
                        >
                            {{ $t('common.cancel') }}
                        </button>
                        <button
                            type="submit"
                            :disabled="editForm.processing"
                            class="h-10 px-5 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold transition flex items-center gap-2 cursor-pointer disabled:opacity-50"
                        >
                            <Check class="w-4 h-4" />
                            <span>{{ editForm.processing ? $t('expenses.modal_saving_cost') : $t('expenses.modal_save_cost') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
