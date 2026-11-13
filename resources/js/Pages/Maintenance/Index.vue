<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { useForm, router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import MaintenanceDetailsDrawer from '@/Components/Maintenance/MaintenanceDetailsDrawer.vue';
import { useLocale } from '@/composables/useLocale';
import {
    AlertCircle,
    Building2,
    Calendar,
    Check,
    CheckCircle2,
    Clock,
    ExternalLink,
    Eye,
    Home,
    Layers,
    Loader2,
    MessageSquare,
    Paperclip,
    Plus,
    Receipt,
    RotateCcw,
    Search,
    Wrench,
    X,
} from 'lucide-vue-next';

interface LinkedExpense {
    id: number;
    amount: number | null;
    has_amount: boolean;
    status: 'pending_amount' | 'ready' | 'recorded' | string;
    payment_status?: string;
    is_paid?: boolean;
    paid_at?: string | null;
    status_label: string;
    is_pending_amount: boolean;
    is_ready: boolean;
    is_recorded: boolean;
}

interface MaintenanceItem {
    id: number;
    title: string;
    description: string;
    status: 'pending' | 'open' | 'in_progress' | 'completed' | 'closed' | string;
    status_label: string;
    is_pending: boolean;
    is_in_progress: boolean;
    is_completed: boolean;
    can_be_completed: boolean;
    property_id: number;
    property_name: string;
    unit_id: number | null;
    unit_name: string;
    is_common_area?: boolean;
    renter_name: string;
    reported_at: string;
    raw_reported_at?: string;
    started_at?: string | null;
    raw_started_at?: string;
    resolved_at: string | null;
    attachments_count?: number;
    notes_count?: number;
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
        pending?: number;
        open?: number;
        in_progress: number;
        completed?: number;
        closed?: number;
        total: number;
        pending_cost?: number;
        pending_cost_count?: number;
        recorded_cost_count?: number;
    };
    currency?: string;
    initial_selected_id?: number | null;
}>(), {
    currency: 'ج.م',
    initial_selected_id: null,
});

// Flash messages
const page = usePage();
const flashError = computed(() => (page.props.flash as any)?.error ?? (page.props.errors as any)?.status);
const flashSuccess = computed(() => (page.props.flash as any)?.success);

const { t, isRtl, dir } = useLocale();

// Filter states
const searchQuery = ref('');
const selectedPropertyId = ref<string | number>('all');
const selectedUnitFilter = ref<string>('all'); // 'all' | 'common' | unitId
const statusFilter = ref<string>('all'); // 'all' | 'pending' | 'in_progress' | 'completed'
const costFilter = ref<string>('all'); // 'all' | 'awaiting_cost' | 'recorded_unpaid' | 'paid'
const startDate = ref<string>('');
const endDate = ref<string>('');

const statusFilterOptions = computed(() => [
    { id: 'all', label: t('maintenance.filter_all') },
    { id: 'pending', label: t('maintenance.status_new') },
    { id: 'in_progress', label: t('maintenance.kpi_in_progress') },
    { id: 'completed', label: t('maintenance.kpi_completed') },
]);

// Drawer states
const selectedMaintenanceId = ref<number | null>(null);
const showDetailsDrawer = ref(false);

const openDetailsDrawer = (id: number) => {
    selectedMaintenanceId.value = id;
    showDetailsDrawer.value = true;
};

const handleDrawerUpdated = () => {
    router.reload({ only: ['requests', 'stats'] });
};

onMounted(() => {
    if (props.initial_selected_id) {
        openDetailsDrawer(props.initial_selected_id);
    }
});

// Modal & Form states
const showAddModal = ref(false);
const locationType = ref<'unit' | 'common'>('unit');
const updatingId = ref<number | null>(null);
const createFileInput = ref<HTMLInputElement | null>(null);
const createFilePreviews = ref<Array<{ file: File; name: string; isImage: boolean; isPdf: boolean; url?: string }>>([]);

const form = useForm({
    property_id: props.properties[0]?.id ?? '',
    unit_id: '' as string | number,
    title: '',
    description: '',
    attachments: [] as File[],
});

// Available units strictly for the currently selected property in the modal
const modalAvailableUnits = computed(() => {
    const selected = props.properties.find(p => p.id === Number(form.property_id));
    return selected?.units ?? [];
});

// Watch property change in modal to clear unit if it does not belong
watch(() => form.property_id, () => {
    if (locationType.value === 'common') {
        form.unit_id = '';
    } else {
        const belongs = modalAvailableUnits.value.some(u => u.id === Number(form.unit_id));
        if (!belongs) {
            form.unit_id = modalAvailableUnits.value[0]?.id ?? '';
        }
    }
});

// Watch location type toggle in modal
watch(locationType, (newType) => {
    if (newType === 'common') {
        form.unit_id = '';
    } else {
        form.unit_id = modalAvailableUnits.value[0]?.id ?? '';
    }
});

// Available units for the filter bar based on selected filter property
const filterAvailableUnits = computed(() => {
    if (selectedPropertyId.value === 'all') {
        return [];
    }
    const prop = props.properties.find(p => p.id === Number(selectedPropertyId.value));
    return prop?.units ?? [];
});

// Reset unit filter when property filter changes
watch(selectedPropertyId, () => {
    selectedUnitFilter.value = 'all';
});

// Filter active status flag
const hasActiveFilters = computed(() => {
    return searchQuery.value !== ''
        || selectedPropertyId.value !== 'all'
        || selectedUnitFilter.value !== 'all'
        || statusFilter.value !== 'all'
        || costFilter.value !== 'all'
        || startDate.value !== ''
        || endDate.value !== '';
});

const resetFilters = () => {
    searchQuery.value = '';
    selectedPropertyId.value = 'all';
    selectedUnitFilter.value = 'all';
    statusFilter.value = 'all';
    costFilter.value = 'all';
    startDate.value = '';
    endDate.value = '';
};

// Filtered requests computation
const filteredRequests = computed(() => {
    return props.requests.filter(r => {
        // 1. Text Search (title, description, unit, property)
        if (searchQuery.value.trim() !== '') {
            const query = searchQuery.value.trim().toLowerCase();
            const matchesText = (r.title && r.title.toLowerCase().includes(query))
                || (r.description && r.description.toLowerCase().includes(query))
                || (r.property_name && r.property_name.toLowerCase().includes(query))
                || (r.unit_name && r.unit_name.toLowerCase().includes(query));
            if (!matchesText) return false;
        }

        // 2. Property Filter
        if (selectedPropertyId.value !== 'all' && r.property_id !== Number(selectedPropertyId.value)) {
            return false;
        }

        // 3. Unit / Location Filter
        if (selectedUnitFilter.value === 'common') {
            if (r.unit_id !== null) return false;
        } else if (selectedUnitFilter.value !== 'all') {
            if (r.unit_id !== Number(selectedUnitFilter.value)) return false;
        }

        // 4. Status Filter ('pending' | 'in_progress' | 'completed')
        if (statusFilter.value === 'pending') {
            if (r.status !== 'pending' && r.status !== 'open') return false;
        } else if (statusFilter.value === 'in_progress') {
            if (r.status !== 'in_progress') return false;
        } else if (statusFilter.value === 'completed') {
            if (r.status !== 'completed' && r.status !== 'closed') return false;
        }

        // 5. Cost-recording Status Filter
        if (costFilter.value === 'awaiting_cost') {
            if (r.expense && r.expense.has_amount) return false;
        } else if (costFilter.value === 'recorded_unpaid') {
            if (!r.expense || !r.expense.has_amount || r.expense.is_paid) return false;
        } else if (costFilter.value === 'paid') {
            if (!r.expense || !r.expense.is_paid) return false;
        }

        // 6. Date Range Filter (based on raw_reported_at)
        if (startDate.value && r.raw_reported_at && r.raw_reported_at < startDate.value) {
            return false;
        }
        if (endDate.value && r.raw_reported_at && r.raw_reported_at > endDate.value) {
            return false;
        }

        return true;
    });
});

// Helper labels
const getMaintenanceStatusBadge = (status: string) => {
    switch (status) {
        case 'pending':
        case 'open':
            return {
                label: t('maintenance.status_new'),
                classes: 'bg-rose-50 text-rose-700 border-rose-200/80',
                dot: 'bg-rose-500',
            };
        case 'in_progress':
            return {
                label: t('maintenance.status_in_progress'),
                classes: 'bg-amber-50 text-amber-800 border-amber-200/80',
                dot: 'bg-amber-500',
            };
        case 'completed':
        case 'closed':
            return {
                label: t('maintenance.status_completed'),
                classes: 'bg-emerald-50 text-emerald-800 border-emerald-200/80',
                dot: 'bg-emerald-500',
            };
        default:
            return {
                label: status,
                classes: 'bg-slate-50 text-slate-700 border-slate-200',
                dot: 'bg-slate-400',
            };
    }
};

const getExpenseStatusBadge = (expense: LinkedExpense | null) => {
    if (!expense || !expense.has_amount || expense.is_pending_amount) {
        return {
            label: t('maintenance.filter_awaiting_cost'),
            classes: 'bg-amber-50 text-amber-800 border-amber-300/80',
            icon: AlertCircle,
            type: 'awaiting',
        };
    }

    if (expense.is_paid) {
        return {
            label: t('maintenance.filter_paid'),
            classes: 'bg-emerald-50 text-emerald-800 border-emerald-300/80',
            icon: CheckCircle2,
            type: 'paid',
        };
    }

    return {
        label: t('maintenance.filter_recorded_unpaid'),
        classes: 'bg-indigo-50 text-indigo-900 border-indigo-200',
        icon: Receipt,
        type: 'unpaid',
    };
};

// Actions
const handleCreateFiles = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (!target.files || target.files.length === 0) return;

    const files = Array.from(target.files);
    files.forEach(f => {
        const isImage = f.type.startsWith('image/');
        const isPdf = f.type === 'application/pdf';
        const url = isImage ? URL.createObjectURL(f) : undefined;
        createFilePreviews.value.push({ file: f, name: f.name, isImage, isPdf, url });
        form.attachments.push(f);
    });

    if (createFileInput.value) {
        createFileInput.value.value = '';
    }
};

const removeCreateFile = (idx: number) => {
    const item = createFilePreviews.value[idx];
    if (item?.url) URL.revokeObjectURL(item.url);
    createFilePreviews.value.splice(idx, 1);
    form.attachments.splice(idx, 1);
};

const openModal = () => {
    form.reset();
    createFilePreviews.value.forEach(p => { if (p.url) URL.revokeObjectURL(p.url); });
    createFilePreviews.value = [];
    form.property_id = props.properties[0]?.id ?? '';
    locationType.value = 'unit';
    form.unit_id = modalAvailableUnits.value[0]?.id ?? '';
    showAddModal.value = true;
};

const submitCreate = () => {
    if (locationType.value === 'common') {
        form.unit_id = '';
    }

    form.post(route('maintenance.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            createFilePreviews.value.forEach(p => { if (p.url) URL.revokeObjectURL(p.url); });
            createFilePreviews.value = [];
            showAddModal.value = false;
        },
    });
};

const updateRequestStatus = (id: number, newStatus: string) => {
    if (updatingId.value !== null) return;
    updatingId.value = id;

    router.patch(route('maintenance.update-status', id), {
        status: newStatus,
    }, {
        preserveScroll: true,
        onFinish: () => {
            updatingId.value = null;
        },
    });
};
</script>

<template>
    <AppLayout
        :title="$t('maintenance.title') + ' — Nook Mate'"
        active-tab="maintenance"
        :header-title="$t('maintenance.title')"
        :header-subtitle="$t('maintenance.subtitle')"
        :breadcrumb="$t('maintenance.breadcrumb')"
    >
        <!-- رأس الصفحة والإجراء الأساسي -->
        <template #actions>
            <button
                @click="openModal"
                type="button"
                class="h-11 px-5 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-sm font-semibold transition shadow-xs flex items-center gap-2 cursor-pointer focus:outline-hidden focus:ring-2 focus:ring-[#163E3E] focus:ring-offset-2"
            >
                <Plus class="h-4 w-4 stroke-[2.5]" />
                <span>{{ $t('maintenance.new_request') }}</span>
            </button>
        </template>

        <!-- تنبيهات النظام في أعلى الصفحة -->
        <div v-if="flashError" class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-sm text-rose-900 flex items-center gap-3">
            <AlertCircle class="w-5 h-5 text-rose-600 shrink-0" />
            <span class="font-medium">{{ flashError }}</span>
        </div>

        <div v-if="flashSuccess" class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-sm text-emerald-900 flex items-center gap-3">
            <CheckCircle2 class="w-5 h-5 text-emerald-600 shrink-0" />
            <span class="font-medium">{{ flashSuccess }}</span>
        </div>

        <!-- 2. كروت ملخص المؤشرات التشغيلية (Summary Cards) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- طلبات جديدة -->
            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2 hover:border-[#163E3E]/30 transition">
                <div class="flex items-center justify-between text-xs font-semibold text-[#5B6770]">
                    <span>{{ $t('maintenance.kpi_new') }}</span>
                    <Clock class="w-4 h-4 text-rose-500" />
                </div>
                <div class="flex items-baseline justify-between pt-1">
                    <span class="text-3xl font-bold text-rose-600 font-mono">
                        {{ stats.pending ?? stats.open ?? 0 }}
                    </span>
                    <span class="text-xs text-rose-700 bg-rose-50 px-2.5 py-1 rounded-md font-bold">
                        {{ $t('maintenance.status_new') }}
                    </span>
                </div>
            </div>

            <!-- جاري الإصلاح -->
            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2 hover:border-[#163E3E]/30 transition">
                <div class="flex items-center justify-between text-xs font-semibold text-[#5B6770]">
                    <span>{{ $t('maintenance.kpi_in_progress') }}</span>
                    <Wrench class="w-4 h-4 text-amber-600" />
                </div>
                <div class="flex items-baseline justify-between pt-1">
                    <span class="text-3xl font-bold text-amber-700 font-mono">
                        {{ stats.in_progress ?? 0 }}
                    </span>
                    <span class="text-xs text-amber-800 bg-amber-50 px-2.5 py-1 rounded-md font-bold">
                        {{ $t('maintenance.status_in_progress') }}
                    </span>
                </div>
            </div>

            <!-- مكتملة -->
            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2 hover:border-[#163E3E]/30 transition">
                <div class="flex items-center justify-between text-xs font-semibold text-[#5B6770]">
                    <span>{{ $t('maintenance.kpi_completed') }}</span>
                    <CheckCircle2 class="w-4 h-4 text-emerald-600" />
                </div>
                <div class="flex items-baseline justify-between pt-1">
                    <span class="text-3xl font-bold text-emerald-700 font-mono">
                        {{ stats.completed ?? stats.closed ?? 0 }}
                    </span>
                    <span class="text-xs text-emerald-800 bg-emerald-50 px-2.5 py-1 rounded-md font-bold">
                        {{ $t('maintenance.status_completed') }}
                    </span>
                </div>
            </div>

            <!-- بانتظار تسجيل التكلفة -->
            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2 hover:border-[#163E3E]/30 transition">
                <div class="flex items-center justify-between text-xs font-semibold text-[#5B6770]">
                    <span>{{ $t('maintenance.kpi_pending_cost') }}</span>
                    <Receipt class="w-4 h-4 text-amber-600" />
                </div>
                <div class="flex items-baseline justify-between pt-1">
                    <span class="text-3xl font-bold text-amber-600 font-mono">
                        {{ stats.pending_cost ?? stats.pending_cost_count ?? 0 }}
                    </span>
                    <Link
                        :href="route('expenses.index')"
                        class="text-xs text-[#163E3E] hover:underline flex items-center gap-1 font-bold bg-[#F8FAFC] border border-[#D6E0E7] px-2.5 py-1 rounded-md"
                        :title="$t('maintenance.expenses_link')"
                    >
                        <span>{{ $t('maintenance.expenses_link') }}</span>
                        <ExternalLink class="w-3 h-3" />
                    </Link>
                </div>
            </div>
        </div>

        <!-- 3. شريط البحث والتصفية المتكامل (Search and Filters) -->
        <div class="bg-white p-5 rounded-2xl border border-[#D6E0E7] shadow-2xs space-y-4">
            <!-- الصف الأول: حقل البحث الرئيسي + أزرار حالة الصيانة -->
            <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
                <div class="relative flex-1 max-w-lg">
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="$t('maintenance.search_placeholder')"
                        :aria-label="$t('maintenance.search_placeholder')"
                        class="w-full h-11 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] placeholder:text-[#5B6770] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-start"
                        :class="isRtl ? 'pr-11 pl-4' : 'pl-11 pr-4'"
                    />
                    <Search
                        class="pointer-events-none absolute top-1/2 -translate-y-1/2 h-4 w-4 text-[#5B6770]"
                        :class="isRtl ? 'right-3.5' : 'left-3.5'"
                    />
                    <button
                        v-if="searchQuery"
                        @click="searchQuery = ''"
                        type="button"
                        class="absolute top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-1 cursor-pointer"
                        :class="isRtl ? 'left-3' : 'right-3'"
                        :aria-label="$t('common.cancel')"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <!-- تصفية حسب حالة الصيانة: جديدة / جاري الإصلاح / مكتملة -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 lg:pb-0">
                    <button
                        v-for="st in statusFilterOptions"
                        :key="st.id"
                        @click="statusFilter = st.id"
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer shrink-0"
                        :class="statusFilter === st.id
                            ? 'bg-[#163E3E] text-white shadow-xs'
                            : 'bg-[#F8FAFC] text-[#5B6770] hover:text-[#163E3E] border border-[#D6E0E7]'"
                    >
                        {{ st.label }}
                    </button>
                </div>
            </div>

            <!-- الصف الثاني: القوائم المنسدلة (العقار، الوحدة/المكان، حالة التكلفة، التواريخ) -->
            <div class="pt-3 border-t border-[#D6E0E7]/60 flex flex-wrap items-center gap-3">
                <!-- تصفية العقار -->
                <div class="flex items-center gap-2 min-w-[190px]">
                    <Building2 class="w-4 h-4 text-[#5B6770] shrink-0" />
                    <select
                        v-model="selectedPropertyId"
                        :aria-label="$t('maintenance.filter_all_properties')"
                        class="w-full h-9 px-3 rounded-lg border border-[#D6E0E7] bg-[#F8FAFC] text-xs font-semibold text-[#163E3E] focus:border-[#163E3E] focus:bg-white transition"
                    >
                        <option value="all">{{ $t('maintenance.filter_all_properties') }}</option>
                        <option v-for="p in properties" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </div>

                <!-- تصفية الوحدة / الأماكن المشتركة -->
                <div class="flex items-center gap-2 min-w-[190px]">
                    <Layers class="w-4 h-4 text-[#5B6770] shrink-0" />
                    <select
                        v-model="selectedUnitFilter"
                        :aria-label="$t('maintenance.filter_all_units')"
                        class="w-full h-9 px-3 rounded-lg border border-[#D6E0E7] bg-[#F8FAFC] text-xs font-semibold text-[#163E3E] focus:border-[#163E3E] focus:bg-white transition"
                    >
                        <option value="all">{{ $t('maintenance.filter_all_units') }}</option>
                        <option value="common">{{ $t('maintenance.filter_common_areas_only') }}</option>
                        <option v-for="u in filterAvailableUnits" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                </div>

                <!-- تصفية حالة تسجيل وسداد التكلفة -->
                <div class="flex items-center gap-2 min-w-[210px]">
                    <Receipt class="w-4 h-4 text-[#5B6770] shrink-0" />
                    <select
                        v-model="costFilter"
                        :aria-label="$t('maintenance.filter_all_cost_statuses')"
                        class="w-full h-9 px-3 rounded-lg border border-[#D6E0E7] bg-[#F8FAFC] text-xs font-semibold text-[#163E3E] focus:border-[#163E3E] focus:bg-white transition"
                    >
                        <option value="all">{{ $t('maintenance.filter_all_cost_statuses') }}</option>
                        <option value="awaiting_cost">{{ $t('maintenance.filter_awaiting_cost') }}</option>
                        <option value="recorded_unpaid">{{ $t('maintenance.filter_recorded_unpaid') }}</option>
                        <option value="paid">{{ $t('maintenance.filter_paid') }}</option>
                    </select>
                </div>

                <!-- تصفية حسب التاريخ -->
                <div class="flex items-center gap-1.5 text-xs text-[#5B6770]">
                    <Calendar class="w-4 h-4 text-[#5B6770] shrink-0" />
                    <input
                        v-model="startDate"
                        type="date"
                        aria-label="Start date"
                        class="h-9 px-2 rounded-lg border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-[#163E3E]"
                    />
                    <span>{{ $t('maintenance.date_to') }}</span>
                    <input
                        v-model="endDate"
                        type="date"
                        aria-label="End date"
                        class="h-9 px-2 rounded-lg border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-[#163E3E]"
                    />
                </div>

                <!-- زر تفريغ الفلاتر -->
                <button
                    v-if="hasActiveFilters"
                    @click="resetFilters"
                    type="button"
                    class="h-9 px-3 rounded-lg text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 transition flex items-center gap-1 cursor-pointer"
                    :class="isRtl ? 'mr-auto' : 'ml-auto'"
                >
                    <RotateCcw class="w-3.5 h-3.5" />
                    <span>{{ $t('maintenance.reset_filter') }}</span>
                </button>
            </div>

            <!-- شريط بيان عدد النتائج المطابقة -->
            <div class="flex items-center justify-between text-xs text-[#5B6770] pt-1">
                <span>
                    {{ $t('maintenance.showing_count', { count: filteredRequests.length, total: requests.length }) }}
                </span>
                <span v-if="hasActiveFilters" class="text-amber-700 bg-amber-50 px-2 py-0.5 rounded-md font-semibold text-[11px]">
                    {{ $t('maintenance.filter_active') }}
                </span>
            </div>
        </div>

        <!-- 4. قائمة كروت طلبات الصيانة (Maintenance List) -->
        <div v-if="filteredRequests.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <div
                v-for="req in filteredRequests"
                :key="req.id"
                class="rounded-3xl border border-[#D6E0E7] bg-white p-5 shadow-2xs hover:border-[#163E3E]/40 hover:shadow-md transition-all flex flex-col justify-between"
            >
                <div class="space-y-4">
                    <!-- الرأس: العقار والمكان + شارة حالة الصيانة -->
                    <div class="flex items-start justify-between gap-2">
                        <div class="space-y-1">
                            <span class="text-xs text-[#5B6770] font-bold flex items-center gap-1.5">
                                <Building2 class="w-3.5 h-3.5 text-[#5B6770]" />
                                <span>{{ req.property_name }}</span>
                            </span>

                            <!-- وسم المكان: وحدة أم مرافق عامة مشتركة -->
                            <div class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-xs font-bold"
                                :class="req.is_common_area
                                    ? 'bg-blue-50 text-blue-900 border border-blue-200'
                                    : 'bg-slate-100 text-[#163E3E] border border-slate-200'"
                            >
                                <component :is="req.is_common_area ? Layers : Home" class="w-3.5 h-3.5" />
                                <span>{{ req.is_common_area ? $t('maintenance.common_area') : req.unit_name }}</span>
                            </div>
                        </div>

                        <!-- شارة حالة الصيانة: جديدة / جاري الإصلاح / مكتملة -->
                        <span
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border shrink-0"
                            :class="getMaintenanceStatusBadge(req.status).classes"
                        >
                            <span class="w-2 h-2 rounded-full" :class="getMaintenanceStatusBadge(req.status).dot"></span>
                            <span>{{ req.status_label }}</span>
                        </span>
                    </div>

                    <!-- العنوان والوصف -->
                    <div class="space-y-1">
                        <h4
                            @click="openDetailsDrawer(req.id)"
                            class="text-base font-bold text-[#163E3E] leading-snug cursor-pointer hover:text-amber-800 transition"
                            :title="$t('maintenance.view_details_and_attachments')"
                        >
                            {{ req.title }}
                        </h4>
                        <p class="text-xs text-[#5B6770] line-clamp-2 leading-relaxed">
                            {{ req.description || $t('maintenance.no_description') }}
                        </p>

                        <!-- شارات المرفقات والملاحظات إن وُجدت -->
                        <div v-if="(req.attachments_count && req.attachments_count > 0) || (req.notes_count && req.notes_count > 0)" class="flex items-center gap-2 pt-1">
                            <span
                                v-if="req.attachments_count && req.attachments_count > 0"
                                class="inline-flex items-center gap-1 text-[11px] font-bold text-[#163E3E] bg-[#E8F0F0] px-2 py-0.5 rounded-md"
                            >
                                <Paperclip class="w-3 h-3 text-[#163E3E]" />
                                <span>{{ $t('maintenance.attachments_badge', { count: req.attachments_count }) }}</span>
                            </span>
                            <span
                                v-if="req.notes_count && req.notes_count > 0"
                                class="inline-flex items-center gap-1 text-[11px] font-bold text-[#5B6770] bg-slate-100 px-2 py-0.5 rounded-md"
                            >
                                <MessageSquare class="w-3 h-3 text-[#5B6770]" />
                                <span>{{ $t('maintenance.notes_badge', { count: req.notes_count }) }}</span>
                            </span>
                        </div>
                    </div>

                    <!-- التواريخ والبيانات الإدارية -->
                    <div class="p-3 rounded-xl bg-[#F8FAFC] border border-[#D6E0E7] text-xs space-y-1.5">
                        <div class="flex items-center justify-between text-[#5B6770]">
                            <span>{{ $t('maintenance.lbl_reported_at') }}</span>
                            <span class="font-mono text-[#163E3E] font-semibold">{{ req.reported_at }}</span>
                        </div>
                        <div v-if="req.started_at" class="flex items-center justify-between text-[#5B6770]">
                            <span>{{ $t('maintenance.lbl_started_at') }}</span>
                            <span class="font-mono text-amber-800 font-semibold">{{ req.started_at }}</span>
                        </div>
                        <div v-if="req.resolved_at" class="flex items-center justify-between text-[#5B6770]">
                            <span>{{ $t('maintenance.lbl_resolved_at') }}</span>
                            <span class="font-mono text-emerald-800 font-semibold">{{ req.resolved_at }}</span>
                        </div>
                        <div class="flex items-center justify-between text-[#5B6770]">
                            <span>{{ $t('maintenance.lbl_reporter') }}</span>
                            <span class="text-[#163E3E] font-medium">{{ req.renter_name }}</span>
                        </div>
                    </div>

                    <!-- الصندوق المالي: حالة وقيمة المصروف المرتبط -->
                    <div
                        class="p-4 rounded-2xl border text-xs space-y-2.5 transition"
                        :class="req.expense?.has_amount
                            ? 'bg-emerald-50/70 border-emerald-200 text-emerald-950'
                            : 'bg-amber-50/70 border-amber-200 text-amber-950'"
                    >
                        <div class="flex items-center justify-between">
                            <span class="font-bold flex items-center gap-1.5">
                                <Receipt class="w-4 h-4" :class="req.expense?.has_amount ? 'text-emerald-700' : 'text-amber-700'" />
                                <span>{{ $t('maintenance.financial_cost') }}</span>
                            </span>

                            <!-- شارة التكلفة المحددة: بانتظار تسجيل التكلفة / التكلفة مسجلة — غير مدفوعة / تم دفع المصروف -->
                            <span
                                class="font-bold text-[11px] px-2.5 py-0.5 rounded-full border inline-flex items-center gap-1"
                                :class="getExpenseStatusBadge(req.expense).classes"
                            >
                                <component :is="getExpenseStatusBadge(req.expense).icon" class="w-3 h-3" />
                                <span>{{ getExpenseStatusBadge(req.expense).label }}</span>
                            </span>
                        </div>

                        <!-- في حال تم إدخال المبلغ -->
                        <div v-if="req.expense?.has_amount" class="flex items-center justify-between pt-2 border-t border-emerald-200/80">
                            <div>
                                <span class="text-[#5B6770] text-[11px] block">{{ $t('maintenance.recorded_amount') }}</span>
                                <span class="font-bold font-mono text-emerald-800 text-base">
                                    {{ req.expense?.amount?.toLocaleString('en-US') }} {{ currency }}
                                </span>
                            </div>

                            <Link
                                :href="route('expenses.index')"
                                class="h-7 px-3 rounded-lg border border-emerald-300 bg-white hover:bg-emerald-100/60 text-emerald-950 text-xs font-bold transition flex items-center gap-1 shadow-2xs"
                                :title="$t('maintenance.open_expense')"
                            >
                                <span>{{ $t('maintenance.open_expense') }}</span>
                                <ExternalLink class="w-3 h-3" />
                            </Link>
                        </div>

                        <!-- في حال لم يتم إدخال المبلغ بعد -->
                        <div v-else class="space-y-2 pt-1 border-t border-amber-200/80">
                            <p class="text-[11px] text-amber-900 leading-relaxed font-medium">
                                {{ $t('maintenance.auto_linked_hint') }}
                            </p>
                            <Link
                                :href="route('expenses.index')"
                                class="w-full h-8 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs transition flex items-center justify-center gap-1.5 shadow-2xs"
                            >
                                <ExternalLink class="w-3.5 h-3.5" />
                                <span>{{ $t('maintenance.open_expense') }}</span>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- 6. أزرار ومسار الإجراء التشغيلي التالي (Status Actions) -->
                <div class="pt-4 mt-4 border-t border-[#D6E0E7] space-y-2.5">
                    <!-- طلبات جديدة (Pending): الإجراء هو "بدء الإصلاح" -->
                    <div v-if="req.status === 'open' || req.status === 'pending'" class="flex items-center justify-between">
                        <span class="text-xs text-[#5B6770]">{{ $t('maintenance.next_action') }}</span>
                        <button
                            @click="updateRequestStatus(req.id, 'in_progress')"
                            :disabled="updatingId === req.id"
                            type="button"
                            class="h-9 px-4 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs disabled:opacity-60"
                        >
                            <Loader2 v-if="updatingId === req.id" class="w-3.5 h-3.5 animate-spin" />
                            <Wrench v-else class="w-3.5 h-3.5" />
                            <span>{{ updatingId === req.id ? $t('maintenance.starting_repair') : $t('maintenance.start_repair') }}</span>
                        </button>
                    </div>

                    <!-- جاري الإصلاح (In Progress): الإجراء هو "إنهاء الصيانة" أو المنع إذا كانت التكلفة فارغة -->
                    <div v-else-if="req.status === 'in_progress'" class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-[#5B6770]">{{ $t('maintenance.next_action') }}</span>
                            <button
                                @click="updateRequestStatus(req.id, 'completed')"
                                :disabled="!req.can_be_completed || updatingId === req.id"
                                type="button"
                                class="h-9 px-4 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-xs"
                                :class="req.can_be_completed
                                    ? 'bg-emerald-700 hover:bg-emerald-800 text-white cursor-pointer'
                                    : 'bg-slate-100 text-slate-400 border border-slate-200 cursor-not-allowed'"
                            >
                                <Loader2 v-if="updatingId === req.id" class="w-3.5 h-3.5 animate-spin" />
                                <Check v-else class="w-3.5 h-3.5" />
                                <span>{{ updatingId === req.id ? $t('maintenance.completing_maintenance') : $t('maintenance.complete_maintenance') }}</span>
                            </button>
                        </div>

                        <!-- توضيح المنع عندما تكون التكلفة مفقودة -->
                        <div
                            v-if="!req.can_be_completed"
                            class="p-2.5 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-950 font-semibold flex items-center justify-between gap-2"
                        >
                            <div class="flex items-center gap-1.5">
                                <AlertCircle class="w-4 h-4 text-amber-700 shrink-0" />
                                <span>{{ $t('maintenance.cannot_complete_without_cost') }}</span>
                            </div>
                            <Link
                                :href="route('expenses.index')"
                                class="text-xs text-[#163E3E] font-bold hover:underline shrink-0"
                            >
                                {{ $t('maintenance.open_expense') }}
                            </Link>
                        </div>
                    </div>

                    <!-- مكتملة (Completed) -->
                    <div v-else class="flex items-center justify-between text-xs">
                        <span class="text-emerald-700 font-bold flex items-center gap-1 bg-emerald-50 px-3 py-1.5 rounded-xl border border-emerald-200/80">
                            <CheckCircle2 class="w-4 h-4 text-emerald-600" />
                            <span>{{ $t('maintenance.completed_and_closed') }}</span>
                        </span>
                        <Link
                            :href="route('expenses.index')"
                            class="text-xs text-[#163E3E] font-semibold hover:underline flex items-center gap-1"
                        >
                            <span>{{ $t('maintenance.view_expense_record') }}</span>
                            <ExternalLink class="w-3 h-3" />
                        </Link>
                    </div>

                    <!-- زر عرض التفاصيل الكاملة والمرفقات -->
                    <button
                        @click="openDetailsDrawer(req.id)"
                        type="button"
                        class="w-full h-9 rounded-xl border border-[#D6E0E7] bg-white hover:bg-[#F8FAFC] text-xs font-bold text-[#163E3E] transition flex items-center justify-center gap-2 shadow-2xs cursor-pointer mt-1"
                    >
                        <Eye class="w-4 h-4 text-[#163E3E]" />
                        <span>{{ $t('maintenance.view_details_and_attachments') }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 7. حالة الفراغ وعدم وجود طلبات (Empty State) -->
        <EmptyState
            v-else
            type="wrench"
            :title="hasActiveFilters ? $t('maintenance.empty_filtered_title') : $t('maintenance.empty_title')"
            :description="hasActiveFilters
                ? $t('maintenance.empty_filtered_desc')
                : $t('maintenance.empty_desc')"
            :action-label="hasActiveFilters ? $t('maintenance.reset_filter') : $t('maintenance.new_request')"
            @action="hasActiveFilters ? resetFilters() : openModal()"
        />

        <!-- 5. نافذة تسجيل طلب صيانة جديد (Creation Modal) -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showAddModal = false" class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs"></div>
            <div :dir="dir" class="relative w-full max-w-lg bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-6 z-10 animate-in fade-in zoom-in-95 text-start">
                <!-- رأس النافذة -->
                <div class="flex items-center justify-between pb-4 border-b border-[#D6E0E7] mb-5">
                    <div>
                        <h3 class="text-lg font-bold text-[#163E3E]">{{ $t('maintenance.modal_new_title') }}</h3>
                        <p class="text-xs text-[#5B6770] mt-0.5">{{ $t('maintenance.modal_new_subtitle') }}</p>
                    </div>
                    <button @click="showAddModal = false" type="button" :aria-label="$t('common.cancel')" class="p-1.5 text-[#5B6770] hover:text-[#163E3E] rounded-lg cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitCreate" class="space-y-4">
                    <!-- العقار المعني -->
                    <div>
                        <label for="create-maintenance-property" class="block text-xs font-bold text-[#163E3E] mb-1.5">{{ $t('maintenance.modal_property_label') }}</label>
                        <select
                            id="create-maintenance-property"
                            v-model="form.property_id"
                            :aria-label="$t('maintenance.modal_property_label')"
                            required
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                        >
                            <option v-for="p in properties" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                        <p v-if="form.errors.property_id" class="text-xs text-rose-600 mt-1">{{ form.errors.property_id }}</p>
                    </div>

                    <!-- محدد نوع المكان: صيانة وحدة vs صيانة الأماكن المشتركة -->
                    <div>
                        <span class="block text-xs font-bold text-[#163E3E] mb-1.5">{{ $t('maintenance.modal_scope_label') }}</span>
                        <div class="grid grid-cols-2 gap-3">
                            <button
                                @click="locationType = 'unit'"
                                type="button"
                                class="p-3 rounded-xl border text-start transition cursor-pointer flex flex-col gap-1"
                                :class="locationType === 'unit'
                                    ? 'border-[#163E3E] bg-[#163E3E]/5 ring-1 ring-[#163E3E]'
                                    : 'border-[#D6E0E7] bg-[#F8FAFC] hover:bg-white text-[#5B6770]'"
                            >
                                <div class="flex items-center gap-1.5 font-bold text-xs" :class="locationType === 'unit' ? 'text-[#163E3E]' : 'text-[#5B6770]'">
                                    <Home class="w-4 h-4" />
                                    <span>{{ $t('maintenance.modal_scope_unit') }}</span>
                                </div>
                                <span class="text-[11px] text-[#5B6770] leading-snug">{{ $t('maintenance.modal_scope_unit_hint') }}</span>
                            </button>

                            <button
                                @click="locationType = 'common'"
                                type="button"
                                class="p-3 rounded-xl border text-start transition cursor-pointer flex flex-col gap-1"
                                :class="locationType === 'common'
                                    ? 'border-[#163E3E] bg-[#163E3E]/5 ring-1 ring-[#163E3E]'
                                    : 'border-[#D6E0E7] bg-[#F8FAFC] hover:bg-white text-[#5B6770]'"
                            >
                                <div class="flex items-center gap-1.5 font-bold text-xs" :class="locationType === 'common' ? 'text-[#163E3E]' : 'text-[#5B6770]'">
                                    <Building2 class="w-4 h-4" />
                                    <span>{{ $t('maintenance.modal_scope_common') }}</span>
                                </div>
                                <span class="text-[11px] text-[#5B6770] leading-snug">{{ $t('maintenance.modal_scope_common_hint') }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- اختيار الوحدة (يظهر فقط إذا كان النطاق صيانة وحدة) -->
                    <div v-if="locationType === 'unit'">
                        <label for="create-maintenance-unit" class="block text-xs font-bold text-[#163E3E] mb-1.5">{{ $t('maintenance.modal_unit_label') }}</label>
                        <select
                            id="create-maintenance-unit"
                            v-model="form.unit_id"
                            :aria-label="$t('maintenance.modal_unit_label')"
                            required
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                        >
                            <option value="" disabled>{{ $t('maintenance.modal_unit_placeholder') }}</option>
                            <option v-for="u in modalAvailableUnits" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                        <p v-if="form.errors.unit_id" class="text-xs text-rose-600 mt-1">{{ form.errors.unit_id }}</p>
                    </div>

                    <!-- إشعار الأماكن المشتركة -->
                    <div v-else class="p-3 rounded-xl bg-blue-50/70 border border-blue-200 text-xs text-blue-900 flex items-center gap-2">
                        <Layers class="w-4 h-4 text-blue-700 shrink-0" />
                        <span>{{ $t('maintenance.modal_common_notice') }}</span>
                    </div>

                    <!-- عنوان طلب الصيانة -->
                    <div>
                        <label for="create-maintenance-title" class="block text-xs font-bold text-[#163E3E] mb-1.5">{{ $t('maintenance.modal_title_label') }}</label>
                        <input
                            id="create-maintenance-title"
                            v-model="form.title"
                            type="text"
                            :aria-label="$t('maintenance.modal_title_label')"
                            :placeholder="$t('maintenance.modal_title_placeholder')"
                            required
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-start"
                        />
                        <p v-if="form.errors.title" class="text-xs text-rose-600 mt-1">{{ form.errors.title }}</p>
                    </div>

                    <!-- الوصف والتفاصيل -->
                    <div>
                        <label for="create-maintenance-desc" class="block text-xs font-bold text-[#163E3E] mb-1.5">
                            {{ $t('maintenance.modal_desc_label') }} <span class="text-[#8A9AA7] font-normal text-[11px]">({{ $t('common.optional') }})</span>
                        </label>
                        <textarea
                            id="create-maintenance-desc"
                            v-model="form.description"
                            :aria-label="$t('maintenance.modal_desc_label')"
                            rows="3"
                            :placeholder="$t('maintenance.modal_desc_placeholder')"
                            class="w-full p-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                        ></textarea>
                        <p v-if="form.errors.description" class="text-xs text-rose-600 mt-1">{{ form.errors.description }}</p>
                    </div>

                    <!-- المرفقات والصور المبدئية -->
                    <div>
                        <span class="block text-xs font-bold text-[#163E3E] mb-1.5">
                            {{ $t('maintenance.modal_files_label') }} <span class="text-[#8A9AA7] font-normal text-[11px]">({{ $t('common.optional') }})</span>
                        </span>
                        <div class="p-3.5 rounded-xl border border-dashed border-[#D6E0E7] bg-[#F8FAFC] text-center space-y-2">
                            <input
                                ref="createFileInput"
                                type="file"
                                multiple
                                accept="image/jpeg,image/png,image/webp,application/pdf"
                                @change="handleCreateFiles"
                                class="hidden"
                            />
                            <button
                                @click="createFileInput?.click()"
                                type="button"
                                class="h-8 px-3 rounded-lg border border-[#D6E0E7] bg-white text-xs font-bold text-[#163E3E] hover:bg-[#F8FAFC] transition inline-flex items-center gap-1 cursor-pointer shadow-2xs"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>{{ $t('maintenance.modal_attach_btn') }}</span>
                            </button>
                            <p class="text-[10px] text-[#5B6770]">{{ $t('maintenance.modal_files_hint') }}</p>
                        </div>

                        <!-- قائمة المعاينة المبدئية -->
                        <div v-if="createFilePreviews.length > 0" class="mt-2.5 grid grid-cols-3 gap-2">
                            <div
                                v-for="(prev, pIdx) in createFilePreviews"
                                :key="pIdx"
                                class="relative rounded-xl border border-[#D6E0E7] p-2 bg-white text-center flex flex-col items-center gap-1.5 group"
                            >
                                <button
                                    @click="removeCreateFile(pIdx)"
                                    type="button"
                                    class="absolute -top-1 -left-1 p-0.5 bg-rose-50 text-rose-600 rounded-full border border-rose-200 cursor-pointer shadow-2xs"
                                    :aria-label="$t('common.cancel')"
                                >
                                    <X class="w-3 h-3" />
                                </button>
                                <img v-if="prev.isImage && prev.url" :src="prev.url" class="w-full h-14 object-cover rounded-lg" />
                                <div v-else class="w-full h-14 rounded-lg bg-red-50 text-red-600 flex items-center justify-center font-bold text-[10px]">
                                    PDF
                                </div>
                                <span class="text-[10px] text-[#163E3E] truncate w-full font-medium">{{ prev.name }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- تنبيه الشفافية المالية: عدم طلب التكلفة عند الإنشاء -->
                    <div class="p-3.5 rounded-2xl bg-[#DEF7EC]/70 border border-[#BCF0DA] text-xs text-[#163E3E] space-y-1">
                        <div class="flex items-center gap-1.5 font-bold">
                            <Receipt class="w-4 h-4 text-[#163E3E]" />
                            <span>{{ $t('maintenance.modal_cost_callout_title') }}</span>
                        </div>
                        <p class="text-[11px] text-[#5B6770] leading-relaxed">
                            {{ $t('maintenance.modal_cost_callout_desc') }}
                        </p>
                    </div>

                    <!-- أزرار الإجراء في أسفل النافذة -->
                    <div class="pt-4 flex items-center justify-end gap-3 border-t border-[#D6E0E7]">
                        <button
                            @click="showAddModal = false"
                            type="button"
                            class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold text-[#5B6770] hover:bg-[#F8FAFC] cursor-pointer"
                        >
                            {{ $t('common.cancel') }}
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="h-10 px-5 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold transition flex items-center gap-2 shadow-xs cursor-pointer disabled:opacity-60"
                        >
                            <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
                            <Check v-else class="w-4 h-4" />
                            <span>{{ form.processing ? $t('maintenance.modal_submitting') : $t('maintenance.modal_submit') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- درج تفاصيل طلب الصيانة والمرفقات والملاحظات والسجل -->
        <MaintenanceDetailsDrawer
            :is-open="showDetailsDrawer"
            :maintenance-id="selectedMaintenanceId"
            :currency="currency"
            @close="showDetailsDrawer = false"
            @updated="handleDrawerUpdated"
        />
    </AppLayout>
</template>
