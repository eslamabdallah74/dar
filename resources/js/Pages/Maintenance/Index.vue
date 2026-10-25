<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import {
    AlertCircle,
    Building2,
    Calendar,
    Check,
    CheckCircle2,
    Clock,
    DollarSign,
    ExternalLink,
    HelpCircle,
    Home,
    Plus,
    Receipt,
    Search,
    User,
    Wrench,
    X,
} from 'lucide-vue-next';

interface LinkedExpense {
    id: number;
    amount: number | null;
    status: 'pending_amount' | 'ready_to_apply' | 'applied';
    status_label: string;
    is_applied: boolean;
    is_ready_to_apply: boolean;
    is_pending_amount: boolean;
}

interface MaintenanceItem {
    id: number;
    title: string;
    description: string;
    status: string;
    status_label: string;
    property_id: number;
    property_name: string;
    unit_id: number | null;
    unit_name: string;
    renter_name: string;
    reported_at: string;
    raw_reported_at?: string;
    resolved_at: string | null;
    expense: LinkedExpense | null;
}

const props = withDefaults(defineProps<{
    requests: MaintenanceItem[];
    properties: Array<{
        id: number;
        name: string;
        units: Array<{ id: number; name: string }>;
    }>;
    stats: {
        open: number;
        in_progress: number;
        closed: number;
        total: number;
        ready_to_apply_expenses_count: number;
        pending_amount_expenses_count: number;
        applied_expenses_count: number;
    };
    currency?: string;
}>(), {
    currency: 'ج.م',
});

const statusFilter = ref('all');
const expenseStatusFilter = ref('all');
const searchQuery = ref('');
const showAddModal = ref(false);
const isApplying = ref(false);

const form = useForm({
    property_id: props.properties[0]?.id ?? '',
    unit_id: '',
    title: '',
    description: '',
});

const availableUnits = computed(() => {
    const selectedProp = props.properties.find(p => p.id === Number(form.property_id));
    return selectedProp?.units ?? [];
});

const filteredRequests = computed(() => {
    return props.requests.filter(r => {
        const matchesSearch = r.title.includes(searchQuery.value)
            || r.description.includes(searchQuery.value)
            || r.unit_name.includes(searchQuery.value)
            || r.property_name.includes(searchQuery.value);

        const matchesStatus = statusFilter.value === 'all'
            || r.status === statusFilter.value;

        const matchesExpenseStatus = expenseStatusFilter.value === 'all'
            || (r.expense && r.expense.status === expenseStatusFilter.value);

        return matchesSearch && matchesStatus && matchesExpenseStatus;
    });
});

const submit = () => {
    form.post(route('maintenance.store'), {
        onSuccess: () => {
            form.reset();
            showAddModal.value = false;
        },
    });
};

const updateRequestStatus = (id: number, newStatus: string) => {
    router.patch(route('maintenance.update-status', id), {
        status: newStatus,
    });
};

const applyAllExpenses = () => {
    isApplying.value = true;
    router.post(route('maintenance.apply-expenses'), {}, {
        preserveScroll: true,
        onFinish: () => {
            isApplying.value = false;
        },
    });
};

const applySingleExpense = (maintenanceId: number) => {
    router.post(route('maintenance.apply-expenses'), {
        maintenance_request_id: maintenanceId,
    }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout
        title="الصيانة وبلاغات الأعطال — دار"
        active-tab="maintenance"
        header-title="طلبات وبلاغات الصيانة"
        header-subtitle="متابعة أوامر العمل، صيانة الوحدات والمرافق وتحديث حالات الإنجاز وربط المصروفات"
        breadcrumb="الصيانة"
    >
        <template #actions>
            <div class="flex items-center gap-2.5">
                <!-- زر تطبيق المصروفات -->
                <button
                    @click="applyAllExpenses"
                    type="button"
                    :disabled="isApplying"
                    class="h-11 px-5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold transition shadow-xs flex items-center gap-2 cursor-pointer disabled:opacity-50"
                    title="اعتماد وتطبيق مصروفات الصيانة المحددة التكلفة في الحسابات المالية"
                >
                    <CheckCircle2 class="h-4 w-4" />
                    <span>تطبيق المصروفات</span>
                    <span
                        v-if="stats.ready_to_apply_expenses_count > 0"
                        class="bg-white/20 text-white text-xs px-2 py-0.5 rounded-full font-mono font-bold"
                    >
                        {{ stats.ready_to_apply_expenses_count }} جاهز
                    </span>
                </button>

                <!-- زر طلب صيانة جديد -->
                <button
                    @click="showAddModal = true"
                    type="button"
                    class="h-11 px-5 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-sm font-semibold transition shadow-xs flex items-center gap-2 cursor-pointer"
                >
                    <Plus class="h-4 w-4 stroke-[2.5]" />
                    <span>طلب صيانة جديد</span>
                </button>
            </div>
        </template>

        <!-- كروت مؤشرات الصيانة والمصروفات المرتبطة -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">بلاغات جديدة مفتوحة</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-rose-600 font-mono">{{ stats.open }}</span>
                    <span class="text-xs text-rose-700 bg-rose-50 px-2 py-0.5 rounded-md font-semibold">
                        بانتظار الفني
                    </span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">قيد المعالجة والإصلاح</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-amber-700 font-mono">{{ stats.in_progress }}</span>
                    <span class="text-xs text-amber-800 bg-amber-50 px-2 py-0.5 rounded-md font-semibold">
                        جاري العمل
                    </span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">مصروفات جاهزة للتطبيق</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-indigo-700 font-mono">{{ stats.ready_to_apply_expenses_count }}</span>
                    <span class="text-xs text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-md font-semibold">
                        تم تحديد التكلفة
                    </span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">بانتظار تحديد التكلفة</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#5B6770] font-mono">{{ stats.pending_amount_expenses_count }}</span>
                    <Link :href="route('expenses.index')" class="text-xs text-[#0E6E7E] hover:underline flex items-center gap-1 font-semibold">
                        <span>إدخال التكلفة</span>
                        <ExternalLink class="w-3 h-3" />
                    </Link>
                </div>
            </div>
        </div>

        <!-- تنبيه تكامل الصيانة مع المصروفات -->
        <div v-if="stats.ready_to_apply_expenses_count > 0 || stats.pending_amount_expenses_count > 0" class="p-4 rounded-2xl bg-amber-50/70 border border-amber-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-amber-900">
            <div class="flex items-start gap-2.5">
                <Receipt class="w-5 h-5 text-amber-700 shrink-0 mt-0.5" />
                <div class="space-y-0.5">
                    <p class="font-bold">حالة تكامل مصروفات الصيانة:</p>
                    <p class="text-amber-800">
                        يتم إنشاء قيد مصروف تلقائياً لكل طلب صيانة بدون مبلغ لحين ورود الفاتورة.
                        <span v-if="stats.ready_to_apply_expenses_count > 0">
                            يوجد <strong>{{ stats.ready_to_apply_expenses_count }}</strong> طلب صيانة تم إدخال تكلفتها وجاهزة للاعتماد المالي.
                        </span>
                        <span v-if="stats.pending_amount_expenses_count > 0">
                            يوجد <strong>{{ stats.pending_amount_expenses_count }}</strong> طلب بحاجة لتحديد تكلفتها من صفحة المصروفات أولاً.
                        </span>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <Link
                    :href="route('expenses.index')"
                    class="h-8 px-3 rounded-lg border border-amber-300 bg-white hover:bg-amber-100/50 text-amber-900 font-bold transition flex items-center gap-1"
                >
                    <span>صفحة المصروفات</span>
                    <ExternalLink class="w-3 h-3" />
                </Link>
                <button
                    v-if="stats.ready_to_apply_expenses_count > 0"
                    @click="applyAllExpenses"
                    :disabled="isApplying"
                    type="button"
                    class="h-8 px-3 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white font-bold transition flex items-center gap-1 cursor-pointer"
                >
                    <Check class="w-3.5 h-3.5" />
                    <span>تطبيق المصروفات الآن</span>
                </button>
            </div>
        </div>

        <!-- شريط البحث والتصفية -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-white p-4 rounded-2xl border border-[#D6E0E7] shadow-2xs">
            <div class="relative flex-1 max-w-md">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="ابحث بعنوان البلاغ، الوحدة، العقار أو الوصف..."
                    class="w-full h-10 pr-10 pl-4 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] placeholder:text-[#5B6770] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-right"
                />
                <Search class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-[#5B6770]" />
            </div>

            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
                <button
                    v-for="f in [
                        { id: 'all', label: 'الكل' },
                        { id: 'open', label: 'جديد' },
                        { id: 'in_progress', label: 'قيد المعالجة' },
                        { id: 'closed', label: 'مكتمل' }
                    ]"
                    :key="f.id"
                    @click="statusFilter = f.id"
                    type="button"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition cursor-pointer shrink-0"
                    :class="statusFilter === f.id
                        ? 'bg-[#0E6E7E] text-white shadow-2xs'
                        : 'bg-[#F8FAFC] text-[#5B6770] hover:text-[#0F2747] border border-[#D6E0E7]'"
                >
                    {{ f.label }}
                </button>

                <div class="h-4 w-px bg-slate-200 mx-1"></div>

                <button
                    v-for="ef in [
                        { id: 'all', label: 'كل المصروفات' },
                        { id: 'ready_to_apply', label: 'جاهز للتطبيق' },
                        { id: 'pending_amount', label: 'بانتظار التكلفة' },
                        { id: 'applied', label: 'معتمد' }
                    ]"
                    :key="ef.id"
                    @click="expenseStatusFilter = ef.id"
                    type="button"
                    class="px-3 py-1.5 rounded-xl text-xs font-medium transition cursor-pointer shrink-0"
                    :class="expenseStatusFilter === ef.id
                        ? 'bg-indigo-700 text-white shadow-2xs font-bold'
                        : 'bg-[#F8FAFC] text-[#5B6770] hover:text-[#0F2747] border border-[#D6E0E7]'"
                >
                    {{ ef.label }}
                </button>
            </div>
        </div>

        <!-- قائمة كروت بلاغات الصيانة -->
        <div v-if="filteredRequests.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <div
                v-for="req in filteredRequests"
                :key="req.id"
                class="rounded-3xl border border-[#D6E0E7] bg-white p-5 shadow-2xs hover:border-[#0E6E7E]/40 hover:shadow-md transition-all flex flex-col justify-between"
            >
                <div class="space-y-3.5">
                    <!-- الرأس: الحالة + العقار -->
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-[#5B6770] font-semibold">{{ req.property_name }}</span>
                        <StatusBadge :status="req.status" :label="req.status_label" size="sm" />
                    </div>

                    <!-- العنوان والوصف -->
                    <div>
                        <h4 class="text-sm font-bold text-[#0F2747] leading-snug mb-1">
                            {{ req.title }}
                        </h4>
                        <p class="text-xs text-[#5B6770] line-clamp-2 leading-relaxed">
                            {{ req.description || 'لا يوجد وصف إضافي' }}
                        </p>
                    </div>

                    <!-- الوحدة والمستأجر -->
                    <div class="p-3 rounded-xl bg-[#F8FAFC] border border-[#D6E0E7] space-y-1 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-[#5B6770]">الوحدة:</span>
                            <span class="font-bold text-[#0F2747]">{{ req.unit_name }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-[#5B6770]">مقدّم البلاغ:</span>
                            <span class="text-[#0F2747]">{{ req.renter_name }}</span>
                        </div>
                    </div>

                    <!-- حالة المصروف المرتبط بالصيانة -->
                    <div class="p-3 rounded-xl border text-xs space-y-2 transition" :class="[
                        req.expense?.status === 'applied' ? 'bg-emerald-50/70 border-emerald-200 text-emerald-900' :
                        req.expense?.status === 'ready_to_apply' ? 'bg-indigo-50/70 border-indigo-200 text-indigo-900' :
                        'bg-amber-50/60 border-amber-200 text-amber-900'
                    ]">
                        <div class="flex items-center justify-between">
                            <span class="font-bold flex items-center gap-1.5">
                                <Receipt class="w-3.5 h-3.5" />
                                <span>قيد المصروف:</span>
                            </span>
                            <span class="font-semibold text-[11px] px-2 py-0.5 rounded-full" :class="[
                                req.expense?.status === 'applied' ? 'bg-emerald-200/60 text-emerald-800' :
                                req.expense?.status === 'ready_to_apply' ? 'bg-indigo-200/60 text-indigo-800' :
                                'bg-amber-200/60 text-amber-800'
                            ]">
                                {{ req.expense?.status_label ?? 'غير مربوط' }}
                            </span>
                        </div>

                        <!-- في حالة تم التطبيق -->
                        <div v-if="req.expense?.status === 'applied'" class="flex items-center justify-between text-xs pt-1 border-t border-emerald-200/60">
                            <span class="text-[#5B6770]">المبلغ المعتمد:</span>
                            <span class="font-bold font-mono text-emerald-700">{{ req.expense?.amount?.toLocaleString() }} {{ currency }}</span>
                        </div>

                        <!-- في حالة جاهز للتطبيق ومحدد المبلغ -->
                        <div v-else-if="req.expense?.status === 'ready_to_apply'" class="space-y-2 pt-1 border-t border-indigo-200/60">
                            <div class="flex items-center justify-between">
                                <span class="text-[#5B6770]">المبلغ المدخل:</span>
                                <span class="font-bold font-mono text-indigo-700">{{ req.expense?.amount?.toLocaleString() }} {{ currency }}</span>
                            </div>
                            <button
                                @click="applySingleExpense(req.id)"
                                type="button"
                                class="w-full h-7 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-[11px] transition flex items-center justify-center gap-1.5 cursor-pointer shadow-2xs"
                            >
                                <Check class="w-3.5 h-3.5" />
                                <span>تطبيق المصروف الآن</span>
                            </button>
                        </div>

                        <!-- في حالة بانتظار تحديد التكلفة -->
                        <div v-else class="space-y-1.5 pt-1 border-t border-amber-200/60">
                            <div class="flex items-center justify-between text-[#5B6770]">
                                <span>المبلغ:</span>
                                <span class="font-semibold italic text-amber-800">لم يحدد بعد</span>
                            </div>
                            <Link
                                :href="route('expenses.index')"
                                class="w-full h-7 rounded-lg bg-amber-100 hover:bg-amber-200 text-amber-900 font-bold text-[11px] transition flex items-center justify-center gap-1.5 border border-amber-300"
                            >
                                <ExternalLink class="w-3 h-3" />
                                <span>تحديد التكلفة من المصروفات</span>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- ذيل الكارت: تاريخ البلاغ وأزرار تحويل الحالة -->
                <div class="pt-4 mt-3 border-t border-[#D6E0E7] flex items-center justify-between">
                    <span class="text-[11px] text-[#5B6770] font-mono">
                        {{ req.reported_at }}
                    </span>

                    <div class="flex items-center gap-1.5">
                        <button
                            v-if="req.status === 'open'"
                            @click="updateRequestStatus(req.id, 'in_progress')"
                            type="button"
                            class="h-7 px-3 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-800 text-[11px] font-bold transition border border-amber-200 cursor-pointer"
                        >
                            بدء المعالجة
                        </button>

                        <button
                            v-if="req.status === 'in_progress'"
                            @click="updateRequestStatus(req.id, 'closed')"
                            type="button"
                            class="h-7 px-3 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-[11px] font-bold transition border border-emerald-200 cursor-pointer"
                        >
                            إغلاق البلاغ بنجاح
                        </button>

                        <span
                            v-if="req.status === 'closed'"
                            class="text-[11px] text-emerald-700 font-semibold flex items-center gap-1"
                        >
                            <CheckCircle2 class="w-3.5 h-3.5" />
                            <span>مكتمل</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <EmptyState
            v-else
            type="wrench"
            title="لا توجد بلاغات صيانة مسجلة"
            description="جميع الوحدات والمرافق في حالة تشغيلية ممتازة دون أي بلاغات مطابقة للتصفية الحالية."
            action-label="تسجيل بلاغ صيانة جديد"
            @action="showAddModal = true"
        />

        <!-- نافذة إضافة بلاغ صيانة جديد -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showAddModal = false" class="fixed inset-0 bg-[#0F2747]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-6 z-10 animate-in fade-in zoom-in-95" dir="rtl">
                <div class="flex items-center justify-between pb-4 border-b border-[#D6E0E7] mb-5">
                    <h3 class="text-base font-bold text-[#0F2747]">تسجيل بلاغ صيانة جديد</h3>
                    <button @click="showAddModal = false" type="button" class="p-1.5 text-[#5B6770] hover:text-[#0F2747]">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">العقار / المبنى *</label>
                        <select
                            v-model="form.property_id"
                            required
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition"
                        >
                            <option v-for="p in properties" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">الوحدة المتأثرة (اختياري)</label>
                        <select
                            v-model="form.unit_id"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition"
                        >
                            <option value="">مرافق عامة مشتركة (المصعد، المداخل...)</option>
                            <option v-for="u in availableUnits" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">عنوان المشكلة أو العطل *</label>
                        <input
                            v-model="form.title"
                            type="text"
                            placeholder="مثال: تسريب مياه في محبس المطبخ، عطل مكيف..."
                            required
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">الوصف والتفاصيل <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري)</span></label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            placeholder="اكتب وصفاً أو تفاصيل إضافية للمساعدة إن وجدت..."
                            class="w-full p-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition"
                        ></textarea>
                    </div>

                    <div class="p-3 rounded-xl bg-blue-50/70 border border-blue-200 text-xs text-blue-900 space-y-1">
                        <div class="flex items-center gap-1.5 font-bold">
                            <Receipt class="w-4 h-4 text-blue-700" />
                            <span>المصروف التلقائي</span>
                        </div>
                        <p class="text-blue-800 text-[11px]">
                            سيتم إنشاء بند مصروف تلقائي في صفحة المصروفات مرتبط بهذا الطلب بدون مبلغ، لتتمكن من إدخال الفاتورة لاحقاً وتطبيقها.
                        </p>
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
                            <span>{{ form.processing ? 'جاري التسجيل...' : 'تسجيل البلاغ' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
