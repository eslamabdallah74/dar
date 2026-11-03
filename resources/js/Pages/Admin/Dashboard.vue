<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryKpiRow from '@/Components/Dashboard/PrimaryKpiRow.vue';
import OperationalKpiRow from '@/Components/Dashboard/OperationalKpiRow.vue';
import RentalIncomeChart from '@/Components/Dashboard/RentalIncomeChart.vue';
import OccupancySummaryCard from '@/Components/Dashboard/OccupancySummaryCard.vue';
import AttentionRequiredCard from '@/Components/Dashboard/AttentionRequiredCard.vue';
import RecentActivityFeed from '@/Components/Dashboard/RecentActivityFeed.vue';
import AdminDashboardPagination from '@/Components/Dashboard/AdminDashboardPagination.vue';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Checkbox from '@/Components/Checkbox.vue';

import {
    Building2,
    Check,
    Coins,
    Edit3,
    Eye,
    FileText,
    Home,
    Layers,
    LayoutDashboard,
    LogIn,
    MessageSquare,
    Plus,
    RefreshCw,
    Shield,
    ShieldCheck,
    Sliders,
    Sparkles,
    Trash2,
    Users,
    Wrench,
    X,
} from 'lucide-vue-next';
import { useConfirmDialog } from '@/composables/useConfirmDialog';

type DashboardTab = 'overview' | 'workspaces' | 'users' | 'properties' | 'leases' | 'payments' | 'maintenance' | 'subscriptions' | 'logs';

interface PaginatedRecords {
    data: any[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

interface DashboardTableData {
    records: PaginatedRecords;
    plans?: any[];
}

interface Props {
    activeTab: DashboardTab;
    initialPage: number;
    initialSearch: string;
    adminUser: {
        name: string;
        email: string;
        role: string;
    };
    kpis: any;
    platformSummary: {
        total_workspaces: number;
        total_users: number;
        total_leases: number;
        total_renters: number;
        total_mrr: string;
        total_overdue: string;
        overdue_count: number;
        whatsapp_delivery_rate: number;
        whatsapp_stats: {
            total: number;
            delivered: number;
            sent: number;
            pending: number;
            failed: number;
        };
    };
    operationalKpis: any;
    incomeChart: any;
    occupancySummary: any;
    attentionItems: any[];
    recentActivities: any[];
    tabCounts: Record<string, number>;
    workspaceSummary: {
        total: number;
        active_subscriptions: number;
        pending_plan_requests: number;
    };
    tableData?: DashboardTableData;
}

const props = defineProps<Props>();

const currentTab = ref<DashboardTab>(props.activeTab);
const searchQuery = ref(props.initialSearch);
const loadedTab = ref<DashboardTab | null>(null);
const tableLoading = ref(false);
const tableError = ref('');
let searchTimeout: ReturnType<typeof setTimeout> | undefined;

const controlTabs = computed(() => [
    { key: 'overview' as const, name: 'نظرة عامة', icon: LayoutDashboard },
    { key: 'workspaces' as const, name: 'مساحات العمل', count: props.tabCounts.workspaces, icon: Building2 },
    { key: 'users' as const, name: 'المستخدمون', count: props.tabCounts.users, icon: Users },
    { key: 'properties' as const, name: 'العقارات والوحدات', count: props.tabCounts.properties, icon: Home },
    { key: 'leases' as const, name: 'العقود', count: props.tabCounts.leases, icon: FileText },
    { key: 'payments' as const, name: 'المدفوعات', count: props.tabCounts.payments, icon: Coins },
    { key: 'maintenance' as const, name: 'طلبات الصيانة', count: props.tabCounts.maintenance, icon: Wrench },
    { key: 'subscriptions' as const, name: 'الباقات والاشتراكات', count: props.tabCounts.subscriptions, icon: Layers },
    { key: 'logs' as const, name: 'رسائل واتساب', count: props.tabCounts.logs, icon: MessageSquare },
]);

const currentRecords = computed(() => loadedTab.value === currentTab.value ? props.tableData?.records : undefined);
const tabRows = computed(() => currentRecords.value?.data ?? []);
const plans = computed(() => props.tableData?.plans ?? []);

function handleGlobalSearch(query: string) {
    searchQuery.value = query;
}

function loadTabData(tab: DashboardTab, page = 1): void {
    if (tab === 'overview') {
        return;
    }

    tableLoading.value = true;
    tableError.value = '';
    router.get(route('admin.dashboard'), {
        tab,
        page,
        search: searchQuery.value,
    }, {
        only: ['tableData'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: () => {
            loadedTab.value = tab;
        },
        onError: () => {
            tableError.value = 'تعذر تحميل البيانات. يرجى المحاولة مرة أخرى.';
        },
        onFinish: () => {
            tableLoading.value = false;
        },
    });
}

function selectTab(tab: string): void {
    if (!isDashboardTab(tab)) {
        return;
    }

    currentTab.value = tab;
    if (tab !== 'overview') {
        loadTabData(tab);
    }
}

function isDashboardTab(tab: string): tab is DashboardTab {
    return tab === 'overview'
        || tab === 'workspaces'
        || tab === 'users'
        || tab === 'properties'
        || tab === 'leases'
        || tab === 'payments'
        || tab === 'maintenance'
        || tab === 'subscriptions'
        || tab === 'logs';
}

function changePage(page: number): void {
    loadTabData(currentTab.value, page);
}

function refreshDashboard(): void {
    const only = [
        'kpis',
        'platformSummary',
        'operationalKpis',
        'incomeChart',
        'occupancySummary',
        'attentionItems',
        'recentActivities',
        'tabCounts',
        'workspaceSummary',
    ];

    if (currentTab.value !== 'overview') {
        only.push('tableData');
        tableLoading.value = true;
    }

    router.reload({
        only,
        onSuccess: () => {
            if (currentTab.value !== 'overview') {
                loadedTab.value = currentTab.value;
            }
        },
        onFinish: () => {
            tableLoading.value = false;
        },
    });
}

watch(searchQuery, () => {
    if (currentTab.value === 'overview') {
        return;
    }

    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => loadTabData(currentTab.value), 300);
});

onMounted(() => {
    if (currentTab.value !== 'overview') {
        loadTabData(currentTab.value, props.initialPage);
    }
});

onUnmounted(() => {
    clearTimeout(searchTimeout);
});

// Modals State
const showCreateUserModal = ref(false);
const showEditUserModal = ref(false);
const showEditWorkspaceModal = ref(false);
const showSubscriptionModal = ref(false);
const showLimitsModal = ref(false);

const selectedUser = ref<any>(null);
const selectedWorkspace = ref<any>(null);

// Forms
const limitsForm = useForm({
    workspace_id: 0,
    parent_unit_limit: 1 as number | string,
    active_rental_limit: 1 as number | string,
    is_parent_unit_unlimited: false,
    is_active_rental_unlimited: false,
});
const createUserForm = useForm({
    name: '',
    email: '',
    password: '',
    is_admin: false,
});

const editUserForm = useForm({
    id: 0,
    name: '',
    email: '',
    password: '',
    is_admin: false,
});

const editWorkspaceForm = useForm({
    id: 0,
    name: '',
    currency: 'EGP',
    timezone: 'Africa/Cairo',
});

const subscriptionForm = useForm({
    workspace_id: 0,
    plan_id: 1,
    status: 'active',
});

// Actions
function refreshCurrentTabData(): void {
    if (currentTab.value !== 'overview') {
        loadTabData(currentTab.value, currentRecords.value?.current_page ?? 1);
    }
}

function impersonate(workspaceId: number) {
    router.post(route('admin.workspaces.impersonate', workspaceId));
}

function openCreateUser() {
    createUserForm.reset();
    showCreateUserModal.value = true;
}

function submitCreateUser() {
    createUserForm.post(route('admin.users.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateUserModal.value = false;
            createUserForm.reset();
            refreshCurrentTabData();
        },
    });
}

function openEditUser(user: any) {
    selectedUser.value = user;
    editUserForm.id = user.id;
    editUserForm.name = user.name;
    editUserForm.email = user.email;
    editUserForm.password = '';
    editUserForm.is_admin = user.is_admin;
    showEditUserModal.value = true;
}

function submitEditUser() {
    editUserForm.put(route('admin.users.update', editUserForm.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEditUserModal.value = false;
            editUserForm.reset();
            refreshCurrentTabData();
        },
    });
}

function toggleAdminRole(userId: number) {
    router.patch(route('admin.users.toggle-admin', userId), {}, {
        preserveScroll: true,
        onSuccess: refreshCurrentTabData,
    });
}

const confirmDialog = useConfirmDialog();

async function deleteUser(userId: number, name: string) {
    const confirmed = await confirmDialog.confirm({
        tone: 'danger',
        title: 'حذف هذا المستخدم؟',
        message: 'يُحذف حساب المستخدم ولن يتمكن من تسجيل الدخول مرة أخرى.',
        details: [{ label: 'المستخدم', value: name }],
        confirmLabel: 'حذف المستخدم',
    });

    if (confirmed) {
        router.delete(route('admin.users.destroy', userId), {
            preserveScroll: true,
            onSuccess: refreshCurrentTabData,
        });
    }
}

function openEditWorkspace(ws: any) {
    selectedWorkspace.value = ws;
    editWorkspaceForm.id = ws.id;
    editWorkspaceForm.name = ws.name;
    editWorkspaceForm.currency = ws.currency || 'EGP';
    editWorkspaceForm.timezone = ws.timezone || 'Africa/Cairo';
    showEditWorkspaceModal.value = true;
}

function submitEditWorkspace() {
    editWorkspaceForm.put(route('admin.workspaces.update', editWorkspaceForm.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEditWorkspaceModal.value = false;
            refreshCurrentTabData();
        },
    });
}

async function deleteWorkspace(wsId: number, name: string) {
    const confirmed = await confirmDialog.confirm({
        tone: 'danger',
        title: 'حذف مساحة العمل؟',
        message: 'تُحذف مساحة العمل نهائياً ولا يمكن التراجع عن ذلك.',
        details: [{ label: 'مساحة العمل', value: name }],
        confirmLabel: 'حذف مساحة العمل',
    });

    if (confirmed) {
        router.delete(route('admin.workspaces.destroy', wsId), {
            preserveScroll: true,
            onSuccess: refreshCurrentTabData,
        });
    }
}

function openChangePlan(ws: any) {
    selectedWorkspace.value = ws;
    subscriptionForm.workspace_id = ws.id;
    const currentPlan = plans.value.find(p => p.slug === ws.plan_slug) || plans.value[0];
    subscriptionForm.plan_id = currentPlan ? currentPlan.id : 1;
    subscriptionForm.status = ws.subscription_status || 'active';
    showSubscriptionModal.value = true;
}

function openEditLimits(ws: any) {
    selectedWorkspace.value = ws;
    limitsForm.workspace_id = ws.id;
    limitsForm.is_parent_unit_unlimited = ws.parent_unit_limit === null;
    limitsForm.parent_unit_limit = ws.parent_unit_limit !== null ? ws.parent_unit_limit : Math.max(1, ws.created_parent_units_count ?? 1);
    limitsForm.is_active_rental_unlimited = ws.active_rental_limit === null;
    limitsForm.active_rental_limit = ws.active_rental_limit !== null ? ws.active_rental_limit : Math.max(1, ws.active_rented_parent_units_count ?? 1);
    showLimitsModal.value = true;
}

function handleKpiSelect(key: 'properties' | 'units' | 'occupancy' | 'collected') {
    if (key === 'properties' || key === 'units') {
        selectTab('properties');
    } else if (key === 'occupancy') {
        selectTab('leases');
    } else if (key === 'collected') {
        selectTab('payments');
    }
}

function submitLimits() {
    limitsForm.put(route('admin.workspaces.limits.update', limitsForm.workspace_id), {
        preserveScroll: true,
        onSuccess: () => {
            showLimitsModal.value = false;
            refreshCurrentTabData();
        },
    });
}

function submitSubscription() {
    subscriptionForm.post(route('admin.workspaces.subscription', subscriptionForm.workspace_id), {
        preserveScroll: true,
        onSuccess: () => {
            showSubscriptionModal.value = false;
            refreshCurrentTabData();
        },
    });
}

function quickApprovePlan(ws: any) {
    router.post(route('admin.workspaces.plan-requests.approve', ws.id), {}, {
        preserveScroll: true,
        onSuccess: refreshCurrentTabData,
    });
}

function isPlanBelowWorkspaceUsage(plan: any, ws: any): boolean {
    if (!ws || !plan) {
        return false;
    }
    const planParent = plan.parent_unit_limit ?? plan.unit_limit;
    const planActive = plan.active_rental_limit ?? plan.unit_limit;

    const currentParents = Number(ws.created_parent_units_count ?? 0);
    const currentActive = Number(ws.active_rented_parent_units_count ?? 0);

    if (planParent !== null && planParent !== undefined && currentParents > Number(planParent)) {
        return true;
    }
    if (planActive !== null && planActive !== undefined && currentActive > Number(planActive)) {
        return true;
    }
    return false;
}

function updateLeaseStatus(leaseId: number, status: string) {
    router.patch(route('admin.leases.status', leaseId), { status }, {
        preserveScroll: true,
        onSuccess: refreshCurrentTabData,
    });
}

function updateMaintenanceStatus(maintenanceId: number, status: string) {
    router.patch(route('admin.maintenance.status', maintenanceId), { status }, {
        preserveScroll: true,
        onSuccess: refreshCurrentTabData,
    });
}
</script>

<template>
    <AppLayout
        title="Nook Mate — لوحة الإدارة المركزية والتحكم الشامل"
        header-title="لوحة الإدارة المركزية والرقابة العامة"
        header-subtitle="رؤية شاملة وتحكم في كافة مساحات العمل والمستخدمين والعمليات المالية والاشتراكات"
        breadcrumb="لوحة القيادة المركزية"
        :admin-mode="true"
        :active-tab="currentTab"
        @select-tab="selectTab"
        @search="handleGlobalSearch"
        @new-action="openCreateUser"
    >
        <template #actions>
            <div class="flex items-center gap-2.5">
                <button
                    @click="refreshDashboard"
                    type="button"
                    class="group flex h-10 items-center gap-1.5 rounded-xl border border-[#D6E0E7] bg-white px-3.5 text-xs font-semibold text-[#163E3E] transition hover:border-[#B8CECA] hover:bg-[#F0F6F4] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#163E3E] focus-visible:ring-offset-2 active:scale-[0.98]"
                    title="تحديث البيانات"
                    aria-label="تحديث البيانات"
                >
                    <RefreshCw class="h-3.5 w-3.5 text-[#5B6770] transition-transform duration-300 group-hover:rotate-180" />
                    <span>تحديث البيانات</span>
                </button>

                <PrimaryButton
                    @click="openCreateUser"
                    class="!py-2.5 !text-xs !bg-[#163E3E] !text-white !shadow-none hover:!bg-[#17334F] cursor-pointer"
                >
                    <Plus class="w-4 h-4 ml-1.5" />
                    <span>إضافة مستخدم</span>
                </PrimaryButton>
            </div>
        </template>

        <!-- 1. شريط مؤشرات حالة المنصة الفورية (Platform Vital Indicators Bar) -->
        <div class="rounded-2xl border border-amber-200/80 bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-white p-5 shadow-2xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-400 text-slate-950 flex items-center justify-center font-black shadow-xs">
                        <ShieldCheck class="w-5 h-5" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-bold text-[#163E3E]">مؤشرات المنصة والخدمات المركزية</h2>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                متصل ونشط
                            </span>
                        </div>
                        <p class="text-xs text-[#5B6770] font-medium mt-0.5">
                            المسؤول المسجل حالياً: <strong class="text-[#163E3E]">{{ adminUser.name }}</strong> ({{ adminUser.email }})
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-[#5B6770]">تصفح سريع للبيانات:</span>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="فلترة الجداول..."
                        aria-label="فلترة الجداول"
                        class="h-9 px-3 rounded-lg border border-[#D6E0E7] bg-white text-xs text-[#163E3E] focus:ring-1 focus:ring-[#163E3E] focus:border-[#163E3E] text-right"
                    />
                </div>
            </div>

            <!-- بطاقات الإحصائيات السريعة للمنصة -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 pt-2 border-t border-amber-200/50">
                <div class="p-3 rounded-xl bg-white border border-[#D6E0E7] shadow-2xs text-right">
                    <span class="text-[11px] font-medium text-[#5B6770] block">مساحات العمل</span>
                    <span class="text-xl font-bold text-[#163E3E] block mt-0.5">{{ platformSummary.total_workspaces }}</span>
                </div>
                <div class="p-3 rounded-xl bg-white border border-[#D6E0E7] shadow-2xs text-right">
                    <span class="text-[11px] font-medium text-[#5B6770] block">المستخدمين المسجلين</span>
                    <span class="text-xl font-bold text-[#163E3E] block mt-0.5">{{ platformSummary.total_users }}</span>
                </div>
                <div class="p-3 rounded-xl bg-white border border-[#D6E0E7] shadow-2xs text-right">
                    <span class="text-[11px] font-medium text-[#5B6770] block">إجمالي العقود</span>
                    <span class="text-xl font-bold text-[#163E3E] block mt-0.5">{{ platformSummary.total_leases }}</span>
                </div>
                <div class="p-3 rounded-xl bg-white border border-[#D6E0E7] shadow-2xs text-right">
                    <span class="text-[11px] font-medium text-[#5B6770] block">إيراد الباقات الشهري</span>
                    <span class="text-xl font-bold text-emerald-700 block mt-0.5">{{ platformSummary.total_mrr }} ج.م</span>
                </div>
                <div class="p-3 rounded-xl bg-white border border-[#D6E0E7] shadow-2xs text-right">
                    <span class="text-[11px] font-medium text-[#5B6770] block">تسليم رسائل واتساب</span>
                    <span class="text-xl font-bold text-sky-700 block mt-0.5">{{ platformSummary.whatsapp_delivery_rate }}%</span>
                </div>
                <div class="p-3 rounded-xl bg-white border border-[#D6E0E7] shadow-2xs text-right">
                    <span class="text-[11px] font-medium text-[#5B6770] block">إجمالي المتأخرات</span>
                    <span class="text-xl font-bold text-rose-700 block mt-0.5">{{ platformSummary.total_overdue }} ج.م</span>
                </div>
            </div>
        </div>

        <!-- 2. أزرار التبويبات التحكمية الرئيسية (Control Tabs Navigation) -->
        <div class="flex flex-wrap items-center gap-2 pb-4 border-b border-[#D6E0E7]">
            <button
                v-for="tab in controlTabs"
                :key="tab.key"
                @click="selectTab(tab.key)"
                type="button"
                class="min-h-11 shrink-0 px-3 sm:px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer"
                :class="currentTab === tab.key
                    ? 'bg-[#163E3E] text-white shadow-xs'
                    : 'bg-white border border-[#D6E0E7] text-[#5B6770] hover:bg-[#F8F4EA] hover:text-[#163E3E]'"
            >
                <component :is="tab.icon" class="w-4 h-4 shrink-0" />
                <span>{{ tab.name }}</span>
                <span
                    v-if="tab.count !== undefined"
                    class="px-1.5 py-0.5 rounded-full text-[10px]"
                    :class="currentTab === tab.key ? 'bg-white/20 text-white' : 'bg-[#E8EFF4] text-[#163E3E]'"
                >
                    {{ tab.count }}
                </span>
            </button>
        </div>

        <div v-if="tableError" class="flex items-center justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
            <span>{{ tableError }}</span>
            <button type="button" class="font-semibold underline" @click="loadTabData(currentTab)">إعادة المحاولة</button>
        </div>

        <div v-else-if="tableLoading" class="rounded-xl border border-[#D6E0E7] bg-white px-4 py-3 text-sm text-[#5B6770]" role="status" aria-live="polite">
            جارٍ تحميل بيانات {{ controlTabs.find((tab) => tab.key === currentTab)?.name }}...
        </div>

        <!-- TAB 1: نظرة عامة ورسوم بيانية (Overview Tab) -->
        <div v-if="currentTab === 'overview'" class="space-y-8 animate-in fade-in">
            <!-- المؤشرات الأربعة الرئيسية (إعادة استخدام PrimaryKpiRow المعتمد مع تبديل التبويبات عند النقر) -->
            <PrimaryKpiRow
                :kpis="kpis"
                :disable-links="true"
                @select-kpi="handleKpiSelect"
            />

            <!-- صف الرسم البياني ومخطط الإشغال (إعادة استخدام RentalIncomeChart و OccupancySummaryCard) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- الرسم البياني للإيرادات لكافة المنصة -->
                <div class="lg:col-span-2">
                    <RentalIncomeChart
                        :months="incomeChart.months"
                        :amounts="incomeChart.amounts"
                        :history="incomeChart.history"
                        :has-history="incomeChart.has_history"
                        :total-collected="incomeChart.total_collected"
                        :currency-symbol="incomeChart.currency_symbol"
                        :currency-label="incomeChart.currency_label"
                    />
                </div>

                <!-- بطاقة ملخص الإشغال العام -->
                <div class="lg:col-span-1">
                    <OccupancySummaryCard
                        :summary="occupancySummary"
                        subtitle="توزيع كافة الوحدات القابلة للتأجير بالمنصة"
                    />
                </div>
            </div>

            <!-- مؤشرات المصروفات والتشغيل (إعادة استخدام OperationalKpiRow) -->
            <OperationalKpiRow :kpis="operationalKpis" />

            <!-- صف التنبيهات العاجلة وسجل العمليات الحي -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <AttentionRequiredCard
                    title="تنبيهات المنصة العاجلة"
                    :items="attentionItems"
                    @view-all="selectTab('leases')"
                    @action="(item) => selectTab(item.type === 'overdue' ? 'payments' : 'leases')"
                />
                <RecentActivityFeed
                    :activities="recentActivities"
                />
            </div>
        </div>

        <!-- TAB 2: إدارة مساحات العمل (Workspaces Tab) -->
        <div v-if="currentTab === 'workspaces'" class="space-y-4 animate-in fade-in">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="text-xl font-bold tracking-tight text-[#163E3E]">مساحات العمل المسجلة</h3>
                    <p class="mt-1 text-sm leading-6 text-[#5B6770]">إدارة المساحات والاشتراكات وحدود الاستخدام من مكان واحد.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="inline-flex items-center gap-1.5 rounded-xl border border-[#D6E0E7] bg-white px-3 py-2 font-semibold text-[#163E3E]">
                        <Building2 class="w-3.5 h-3.5 text-[#163E3E]" />
                        <span>إجمالي المساحات:</span>
                        <strong class="text-[#163E3E]">{{ workspaceSummary.total }}</strong>
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 font-semibold text-emerald-800">
                        <Check class="w-3.5 h-3.5 text-emerald-600" />
                        <span>اشتراكات نشطة:</span>
                        <strong class="text-emerald-900">{{ workspaceSummary.active_subscriptions }}</strong>
                    </span>
                    <span
                        v-if="workspaceSummary.pending_plan_requests > 0"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 font-semibold text-amber-900"
                    >
                        <Sparkles class="w-3.5 h-3.5 text-amber-600" />
                        <span>طلبات ترقية معلقة:</span>
                        <strong class="text-amber-900">{{ workspaceSummary.pending_plan_requests }}</strong>
                    </span>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-[#D6E0E7] bg-white shadow-sm shadow-[#163E3E]/5">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1160px] text-right text-xs">
                        <thead class="border-b border-[#D6E0E7] bg-[#F4F7F7] text-[11px] font-semibold text-[#5B6770]">
                            <tr>
                                <th class="w-[30%] px-4 py-3.5">مساحة العمل والمالك</th>
                                <th class="w-[25%] px-4 py-3.5">الاستخدام</th>
                                <th class="px-4 py-3.5">الباقة</th>
                                <th class="px-4 py-3.5">الحالة</th>
                                <th class="px-4 py-3.5 whitespace-nowrap">تاريخ الإنشاء</th>
                                <th class="px-4 py-3.5 text-center">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E8EFF4]">
                            <tr v-for="ws in tabRows" :key="ws.id" class="group transition-colors duration-150 hover:bg-[#F6F9F8]">
                                <td class="px-4 py-4">
                                    <div class="flex items-start gap-3">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E8F0F0] text-[#163E3E]">
                                            <Building2 class="w-4 h-4" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <span class="max-w-[260px] truncate text-sm font-bold text-[#163E3E]" :title="ws.name">{{ ws.name }}</span>
                                            </div>
                                            <div class="mt-1 text-[11px] text-[#5B6770]">
                                                <span class="font-medium text-[#344C4C]">{{ ws.owner_name }}</span>
                                                <span class="mx-1 text-[#A0ACAC]">·</span>
                                                <span dir="ltr" class="inline-block max-w-[190px] truncate align-bottom">{{ ws.owner_email }}</span>
                                            </div>
                                            <div class="mt-1 font-mono text-[10px] text-[#7A8989]">#{{ ws.id }} · {{ ws.slug }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="grid min-w-[270px] grid-cols-2 gap-3">
                                        <div class="space-y-1.5">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="text-[10px] font-medium text-[#5B6770]">وحدات رئيسية</span>
                                                <span class="whitespace-nowrap font-mono text-xs font-semibold tabular-nums text-[#163E3E]">
                                                    {{ ws.created_parent_units_count }}
                                                    <span class="font-sans font-normal text-[#9AA7A7]">/</span>
                                                    <span :class="ws.parent_unit_limit === null ? 'text-emerald-700' : 'text-[#5B6770]'">
                                                        {{ ws.parent_unit_limit === null ? '∞' : ws.parent_unit_limit }}
                                                    </span>
                                                </span>
                                            </div>
                                            <div v-if="ws.parent_unit_limit !== null" class="h-1.5 overflow-hidden rounded-full bg-[#E8EFF4]">
                                                <div
                                                    class="h-full rounded-full transition-all duration-300"
                                                    :class="ws.created_parent_units_count >= ws.parent_unit_limit ? 'bg-rose-500' : (ws.created_parent_units_count / ws.parent_unit_limit >= 0.8 ? 'bg-amber-500' : 'bg-[#163E3E]')"
                                                    :style="{ width: `${Math.min(100, Math.round((ws.created_parent_units_count / ws.parent_unit_limit) * 100))}%` }"
                                                />
                                            </div>
                                        </div>
                                        <div class="space-y-1.5 border-r border-[#E8EFF4] pr-3">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="text-[10px] font-medium text-[#5B6770]">تأجير نشط</span>
                                                <span class="whitespace-nowrap font-mono text-xs font-semibold tabular-nums text-[#163E3E]">
                                                    {{ ws.active_rented_parent_units_count }}
                                                    <span class="font-sans font-normal text-[#9AA7A7]">/</span>
                                                    <span :class="ws.active_rental_limit === null ? 'text-emerald-700' : 'text-[#5B6770]'">
                                                        {{ ws.active_rental_limit === null ? '∞' : ws.active_rental_limit }}
                                                    </span>
                                                </span>
                                            </div>
                                            <div v-if="ws.active_rental_limit !== null" class="h-1.5 overflow-hidden rounded-full bg-[#E8EFF4]">
                                                <div
                                                    class="h-full rounded-full transition-all duration-300"
                                                    :class="ws.active_rented_parent_units_count >= ws.active_rental_limit ? 'bg-rose-500' : (ws.active_rented_parent_units_count / ws.active_rental_limit >= 0.8 ? 'bg-amber-500' : 'bg-emerald-600')"
                                                    :style="{ width: `${Math.min(100, Math.round((ws.active_rented_parent_units_count / ws.active_rental_limit) * 100))}%` }"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="space-y-1">
                                        <span class="inline-block rounded-md border border-[#D4E2E2] bg-[#F0F5F4] px-2.5 py-1 text-[11px] font-semibold text-[#163E3E]">
                                            {{ ws.plan_name }}
                                        </span>
                                        <div v-if="ws.plan_request_status === 'pending' && ws.requested_plan_name" class="flex items-center gap-1 rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-[10px] font-semibold text-amber-800">
                                            <Sparkles class="w-3 h-3 text-amber-600 shrink-0" />
                                            <span>مطلوب: {{ ws.requested_plan_name }}</span>
                                        </div>
                                        <div v-else-if="ws.plan_request_status === 'approved' && ws.requested_plan_name" class="text-[10px] font-medium text-emerald-700">
                                            ✓ معتمد: {{ ws.requested_plan_name }}
                                        </div>
                                        <div v-else-if="ws.plan_request_status === 'rejected' && ws.requested_plan_name" class="text-[10px] font-medium text-rose-600">
                                            ✕ مرفوض: {{ ws.requested_plan_name }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <StatusBadge :status="ws.subscription_status || ws.status" />
                                </td>
                                <td class="whitespace-nowrap px-4 py-4 font-mono text-[11px] tabular-nums text-[#5B6770]">{{ ws.created_at }}</td>
                                <td class="px-4 py-4">
                                    <div class="flex min-w-[310px] items-center justify-center gap-1.5">
                                        <button
                                            v-if="ws.plan_request_status === 'pending' && ws.requested_plan_name"
                                            @click="quickApprovePlan(ws)"
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg bg-emerald-700 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2 active:scale-[0.98]"
                                            :title="`الموافقة ومنح ${ws.requested_plan_name}`"
                                            aria-label="الموافقة ومنح الباقة"
                                        >
                                            <Check class="w-3.5 h-3.5" />
                                            <span>منح</span>
                                        </button>
                                        <button
                                            @click="impersonate(ws.id)"
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-emerald-800 transition hover:bg-emerald-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2 active:scale-[0.98]"
                                            title="دخول مساحة العمل كمدير"
                                            aria-label="دخول مساحة العمل كمدير"
                                        >
                                            <LogIn class="w-3.5 h-3.5" />
                                            <span>دخول</span>
                                        </button>
                                        <Link
                                            :href="route('admin.workspaces.show', ws.id)"
                                            class="inline-flex items-center gap-1 rounded-lg bg-[#163E3E] px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-[#235555] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#163E3E] focus-visible:ring-offset-2 active:scale-[0.98]"
                                            title="عرض تفاصيل مساحة العمل"
                                            aria-label="عرض تفاصيل مساحة العمل"
                                        >
                                            <Eye class="w-3.5 h-3.5" />
                                            <span>تفاصيل</span>
                                        </Link>
                                        <button
                                            @click="openEditLimits(ws)"
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg border border-[#D6E0E7] bg-white px-2.5 py-1.5 text-xs font-semibold text-[#465B5B] transition hover:bg-[#F4F7F7] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#163E3E] focus-visible:ring-offset-2 active:scale-[0.98]"
                                            title="تعديل حدود الإنشاء والتأجير"
                                            aria-label="تعديل حدود الإنشاء والتأجير"
                                        >
                                            <Sliders class="w-3.5 h-3.5" />
                                            <span>الحدود</span>
                                        </button>
                                        <div class="inline-flex items-center rounded-lg border border-[#D6E0E7] bg-[#F8FAFA] p-0.5">
                                            <button
                                                @click="openChangePlan(ws)"
                                                type="button"
                                                class="rounded-md p-1.5 text-[#5B6770] transition hover:bg-white hover:text-[#163E3E] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#163E3E] focus-visible:ring-offset-1 active:scale-[0.98]"
                                                title="ترقية / تغيير الباقة"
                                                aria-label="ترقية / تغيير الباقة"
                                            >
                                                <Layers class="w-3.5 h-3.5" />
                                            </button>
                                            <button
                                                @click="openEditWorkspace(ws)"
                                                type="button"
                                                class="rounded-md p-1.5 text-[#5B6770] transition hover:bg-white hover:text-[#163E3E] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#163E3E] focus-visible:ring-offset-1 active:scale-[0.98]"
                                                title="تعديل البيانات"
                                                aria-label="تعديل بيانات مساحة العمل"
                                            >
                                                <Edit3 class="w-3.5 h-3.5" />
                                            </button>
                                            <button
                                                @click="deleteWorkspace(ws.id, ws.name)"
                                                type="button"
                                                class="rounded-md p-1.5 text-rose-600 transition hover:bg-rose-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-600 focus-visible:ring-offset-1 active:scale-[0.98]"
                                                title="حذف مساحة العمل"
                                                aria-label="حذف مساحة العمل"
                                            >
                                                <Trash2 class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!tableLoading && loadedTab === 'workspaces' && tabRows.length === 0">
                                <td colspan="6" class="px-4 py-14 text-center">
                                    <div class="mx-auto flex max-w-sm flex-col items-center gap-2">
                                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#F0F5F4] text-[#587070]">
                                            <Building2 class="h-5 w-5" />
                                        </div>
                                        <p class="font-semibold text-[#344C4C]">لا توجد مساحات مطابقة</p>
                                        <p class="text-xs leading-5 text-[#718080]">جرّب البحث باسم المساحة أو المالك أو البريد الإلكتروني.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <AdminDashboardPagination
                v-if="loadedTab === 'workspaces' && currentRecords"
                :current-page="currentRecords.current_page"
                :last-page="currentRecords.last_page"
                :from="currentRecords.from"
                :to="currentRecords.to"
                :total="currentRecords.total"
                :loading="tableLoading"
                @change-page="changePage"
            />
        </div>

        <!-- TAB 3: إدارة المستخدمين (Users Tab) -->
        <div v-if="currentTab === 'users'" class="space-y-4 animate-in fade-in">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-[#163E3E]">المستخدمون والمسؤولون (Platform Users)</h3>
                    <p class="text-xs text-[#5B6770]">إدارة كاملة للمستخدمين، وتعيين أو سحب صلاحيات الإدارة المركزية.</p>
                </div>
                <PrimaryButton @click="openCreateUser" class="!py-2 !text-xs !bg-[#163E3E] !text-white !shadow-none hover:!bg-[#17334F]">
                    <Plus class="w-3.5 h-3.5 ml-1" />
                    <span>مستخدم جديد</span>
                </PrimaryButton>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white overflow-hidden shadow-2xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] font-bold">
                            <tr>
                                <th class="p-3.5">الاسم</th>
                                <th class="p-3.5">البريد الإلكتروني</th>
                                <th class="p-3.5 text-center">الرتبة / الصلاحية</th>
                                <th class="p-3.5 text-center">حالة التحقق</th>
                                <th class="p-3.5">تاريخ التسجيل</th>
                                <th class="p-3.5 text-center">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E8EFF4]">
                            <tr v-for="u in tabRows" :key="u.id" class="hover:bg-[#F8FAFC] transition">
                                <td class="p-3.5 font-bold text-[#163E3E]">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-xs"
                                            :class="u.is_admin ? 'bg-amber-400 text-slate-950 font-black' : 'bg-[#E8F0F0] text-[#163E3E]'"
                                        >
                                            {{ u.name.charAt(0) }}
                                        </div>
                                        <span>{{ u.name }}</span>
                                    </div>
                                </td>
                                <td class="p-3.5 font-mono text-[#163E3E]">{{ u.email }}</td>
                                <td class="p-3.5 text-center">
                                    <span
                                        class="px-2.5 py-0.5 rounded-full text-[10px] font-black inline-flex items-center gap-1"
                                        :class="u.is_admin ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-slate-100 text-slate-700'"
                                    >
                                        <Shield v-if="u.is_admin" class="w-3 h-3 text-amber-600" />
                                        {{ u.is_admin ? 'مدير نظام (Admin)' : 'مستخدم عادي' }}
                                    </span>
                                </td>
                                <td class="p-3.5 text-center">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                        :class="u.email_verified ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
                                    >
                                        {{ u.email_verified ? 'مفعل' : 'غير مفعل' }}
                                    </span>
                                </td>
                                <td class="p-3.5 text-[#5B6770]">{{ u.created_at }}</td>
                                <td class="p-3.5">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button
                                            @click="toggleAdminRole(u.id)"
                                            type="button"
                                            class="px-2.5 py-1 rounded-lg text-[11px] font-bold transition cursor-pointer"
                                            :class="u.is_admin ? 'bg-amber-50 text-amber-900 hover:bg-amber-100' : 'bg-slate-100 text-slate-800 hover:bg-slate-200'"
                                            title="ترقية أو سحب صلاحية الأدمن"
                                        >
                                            {{ u.is_admin ? 'سحب الإشراف' : 'ترقية لأدمن' }}
                                        </button>
                                        <button
                                            @click="openEditUser(u)"
                                            type="button"
                                            class="p-1.5 rounded-lg text-[#5B6770] hover:text-[#163E3E] hover:bg-[#F8F4EA] transition cursor-pointer"
                                            title="تعديل المستخدم"
                                        >
                                            <Edit3 class="w-4 h-4" />
                                        </button>
                                        <button
                                            @click="deleteUser(u.id, u.name)"
                                            type="button"
                                            class="p-1.5 rounded-lg text-rose-600 hover:bg-rose-50 transition cursor-pointer"
                                            title="حذف المستخدم"
                                        >
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!tableLoading && loadedTab === 'users' && tabRows.length === 0">
                                <td colspan="6" class="p-8 text-center text-[#5B6770]">لا يوجد مستخدمون مطابقون لبحثك.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <AdminDashboardPagination
                v-if="loadedTab === 'users' && currentRecords"
                :current-page="currentRecords.current_page"
                :last-page="currentRecords.last_page"
                :from="currentRecords.from"
                :to="currentRecords.to"
                :total="currentRecords.total"
                :loading="tableLoading"
                @change-page="changePage"
            />
        </div>

        <!-- TAB 4: العقارات والوحدات (Properties Tab) -->
        <div v-if="currentTab === 'properties'" class="space-y-4 animate-in fade-in">
            <div>
                <h3 class="text-lg font-bold text-[#163E3E]">العقارات والوحدات عبر المنصة</h3>
                <p class="text-xs text-[#5B6770]">مراقبة كافة المباني والوحدات السكنية والتجارية في كل مساحات العمل.</p>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white overflow-hidden shadow-2xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] font-bold">
                            <tr>
                                <th class="p-3.5">اسم العقار</th>
                                <th class="p-3.5">مساحة العمل التابع لها</th>
                                <th class="p-3.5">نوع العقار</th>
                                <th class="p-3.5">المدينة والعنوان</th>
                                <th class="p-3.5 text-center">عدد الوحدات</th>
                                <th class="p-3.5">تاريخ الإضافة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E8EFF4]">
                            <tr v-for="p in tabRows" :key="p.id" class="hover:bg-[#F8FAFC] transition">
                                <td class="p-3.5 font-bold text-[#163E3E]">{{ p.name }}</td>
                                <td class="p-3.5">
                                    <span class="px-2 py-0.5 rounded-md bg-[#E8F0F0] text-[#163E3E] font-semibold text-[11px]">
                                        {{ p.workspace_name }}
                                    </span>
                                </td>
                                <td class="p-3.5 font-medium">{{ p.property_type }}</td>
                                <td class="p-3.5 text-[#5B6770]">{{ p.city }} • {{ p.address }}</td>
                                <td class="p-3.5 text-center font-bold text-[#163E3E]">{{ p.units_count }}</td>
                                <td class="p-3.5 text-[#5B6770]">{{ p.created_at }}</td>
                            </tr>
                            <tr v-if="!tableLoading && loadedTab === 'properties' && tabRows.length === 0">
                                <td colspan="6" class="p-8 text-center text-[#5B6770]">لا توجد عقارات مطابقة لبحثك.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <AdminDashboardPagination
                v-if="loadedTab === 'properties' && currentRecords"
                :current-page="currentRecords.current_page"
                :last-page="currentRecords.last_page"
                :from="currentRecords.from"
                :to="currentRecords.to"
                :total="currentRecords.total"
                :loading="tableLoading"
                @change-page="changePage"
            />
        </div>

        <!-- TAB 5: العقود والمستأجرون (Leases Tab) -->
        <div v-if="currentTab === 'leases'" class="space-y-4 animate-in fade-in">
            <div>
                <h3 class="text-lg font-bold text-[#163E3E]">العقود الإيجارية (Platform Leases)</h3>
                <p class="text-xs text-[#5B6770]">مراقبة سريان العقود، القيم الإيجارية، وتغيير حالات العقود مركزياً.</p>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white overflow-hidden shadow-2xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] font-bold">
                            <tr>
                                <th class="p-3.5">رقم العقد</th>
                                <th class="p-3.5">مساحة العمل</th>
                                <th class="p-3.5">المستأجر</th>
                                <th class="p-3.5">الوحدة</th>
                                <th class="p-3.5">الإيجار الشهري</th>
                                <th class="p-3.5">الفترة</th>
                                <th class="p-3.5">الحالة</th>
                                <th class="p-3.5 text-center">التحكم في الحالة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E8EFF4]">
                            <tr v-for="l in tabRows" :key="l.id" class="hover:bg-[#F8FAFC] transition">
                                <td class="p-3.5 font-bold font-mono text-[#163E3E]">{{ l.lease_number }}</td>
                                <td class="p-3.5 font-medium text-[#163E3E]">{{ l.workspace_name }}</td>
                                <td class="p-3.5 font-semibold text-[#163E3E]">{{ l.renter_name }}</td>
                                <td class="p-3.5 text-[#5B6770]">{{ l.unit_name }}</td>
                                <td class="p-3.5 font-bold text-[#163E3E]">{{ l.rent_amount.toLocaleString() }} ج.م</td>
                                <td class="p-3.5 text-[11px] text-[#5B6770]">{{ l.starts_on }} ← {{ l.ends_on }}</td>
                                <td class="p-3.5">
                                    <StatusBadge :status="l.status" />
                                </td>
                                <td class="p-3.5 text-center">
                                    <select
                                        :value="l.status"
                                        @change="updateLeaseStatus(l.id, ($event.target as HTMLSelectElement).value)"
                                        aria-label="تحديث حالة العقد"
                                        class="h-9 min-w-[144px] rounded-lg border border-[#D6E0E7] bg-white px-3 text-xs font-semibold text-[#163E3E] transition hover:border-[#B8CECA] hover:bg-[#F8FAFA] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#163E3E] focus-visible:ring-offset-2"
                                    >
                                        <option value="active">نشط (Active)</option>
                                        <option value="draft">مسودة (Draft)</option>
                                        <option value="expired">منتهي (Expired)</option>
                                        <option value="terminated">ملغى (Terminated)</option>
                                    </select>
                                </td>
                            </tr>
                            <tr v-if="!tableLoading && loadedTab === 'leases' && tabRows.length === 0">
                                <td colspan="8" class="p-8 text-center text-[#5B6770]">لا توجد عقود مطابقة لبحثك.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <AdminDashboardPagination
                v-if="loadedTab === 'leases' && currentRecords"
                :current-page="currentRecords.current_page"
                :last-page="currentRecords.last_page"
                :from="currentRecords.from"
                :to="currentRecords.to"
                :total="currentRecords.total"
                :loading="tableLoading"
                @change-page="changePage"
            />
        </div>

        <!-- TAB 6: المالية والمدفوعات (Payments Tab) -->
        <div v-if="currentTab === 'payments'" class="space-y-4 animate-in fade-in">
            <div>
                <h3 class="text-lg font-bold text-[#163E3E]">سجل الدفعات والتحصيلات في المنصة</h3>
                <p class="text-xs text-[#5B6770]">كافة المعاملات المالية وسندات القبض المسجلة في مختلف مساحات العمل.</p>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white overflow-hidden shadow-2xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] font-bold">
                            <tr>
                                <th class="p-3.5">رقم السند</th>
                                <th class="p-3.5">مساحة العمل</th>
                                <th class="p-3.5">المستأجر</th>
                                <th class="p-3.5">المبلغ المدفوع</th>
                                <th class="p-3.5">طريقة السداد</th>
                                <th class="p-3.5">المرجع</th>
                                <th class="p-3.5">تاريخ ووقت التحصيل</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E8EFF4]">
                            <tr v-for="p in tabRows" :key="p.id" class="hover:bg-[#F8FAFC] transition">
                                <td class="p-3.5 font-bold font-mono text-[#163E3E]">{{ p.payment_number }}</td>
                                <td class="p-3.5 font-semibold text-[#163E3E]">{{ p.workspace_name }}</td>
                                <td class="p-3.5 font-semibold text-[#163E3E]">{{ p.renter_name }}</td>
                                <td class="p-3.5 font-bold text-emerald-700">{{ p.amount.toLocaleString() }} ج.م</td>
                                <td class="p-3.5 font-medium">{{ p.method }}</td>
                                <td class="p-3.5 font-mono text-[#5B6770]">{{ p.reference }}</td>
                                <td class="p-3.5 text-[#5B6770]">{{ p.paid_at }}</td>
                            </tr>
                            <tr v-if="!tableLoading && loadedTab === 'payments' && tabRows.length === 0">
                                <td colspan="7" class="p-8 text-center text-[#5B6770]">لا توجد دفعات مطابقة لبحثك.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <AdminDashboardPagination
                v-if="loadedTab === 'payments' && currentRecords"
                :current-page="currentRecords.current_page"
                :last-page="currentRecords.last_page"
                :from="currentRecords.from"
                :to="currentRecords.to"
                :total="currentRecords.total"
                :loading="tableLoading"
                @change-page="changePage"
            />
        </div>

        <!-- TAB 7: طلبات الصيانة (Maintenance Tab) -->
        <div v-if="currentTab === 'maintenance'" class="space-y-4 animate-in fade-in">
            <div>
                <h3 class="text-lg font-bold text-[#163E3E]">طلبات الصيانة المركزية</h3>
                <p class="text-xs text-[#5B6770]">متابعة كافة طلبات الصيانة والإصلاح في الوحدات العقارية والتحكم بحالاتها.</p>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white overflow-hidden shadow-2xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] font-bold">
                            <tr>
                                <th class="p-3.5">عنوان الطلب</th>
                                <th class="p-3.5">مساحة العمل</th>
                                <th class="p-3.5">الأولوية</th>
                                <th class="p-3.5">التكلفة التقديرية</th>
                                <th class="p-3.5">الحالة</th>
                                <th class="p-3.5">تاريخ الإنشاء</th>
                                <th class="p-3.5 text-center">تحديث الحالة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E8EFF4]">
                            <tr v-for="mr in tabRows" :key="mr.id" class="hover:bg-[#F8FAFC] transition">
                                <td class="p-3.5 font-bold text-[#163E3E]">{{ mr.title }}</td>
                                <td class="p-3.5 text-[#163E3E] font-medium">{{ mr.workspace_name }}</td>
                                <td class="p-3.5">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                        :class="mr.priority === 'urgent' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-800'"
                                    >
                                        {{ mr.priority }}
                                    </span>
                                </td>
                                <td class="p-3.5 font-bold">{{ mr.estimated_cost.toLocaleString() }} ج.م</td>
                                <td class="p-3.5">
                                    <StatusBadge :status="mr.status" />
                                </td>
                                <td class="p-3.5 text-[#5B6770]">{{ mr.created_at }}</td>
                                <td class="p-3.5 text-center">
                                    <select
                                        :value="mr.status === 'open' ? 'pending' : (mr.status === 'closed' ? 'completed' : mr.status)"
                                        @change="updateMaintenanceStatus(mr.id, ($event.target as HTMLSelectElement).value)"
                                        aria-label="تحديث حالة طلب الصيانة"
                                        class="h-9 min-w-[144px] rounded-lg border border-[#D6E0E7] bg-white px-3 text-xs font-semibold text-[#163E3E] transition hover:border-[#B8CECA] hover:bg-[#F8FAFA] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#163E3E] focus-visible:ring-offset-2"
                                    >
                                        <option value="pending">قيد الانتظار (Pending)</option>
                                        <option value="in_progress">قيد التنفيذ (In Progress)</option>
                                        <option value="completed">مكتمل (Completed)</option>
                                    </select>
                                </td>
                            </tr>
                            <tr v-if="!tableLoading && loadedTab === 'maintenance' && tabRows.length === 0">
                                <td colspan="7" class="p-8 text-center text-[#5B6770]">لا توجد طلبات صيانة مطابقة لبحثك.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <AdminDashboardPagination
                v-if="loadedTab === 'maintenance' && currentRecords"
                :current-page="currentRecords.current_page"
                :last-page="currentRecords.last_page"
                :from="currentRecords.from"
                :to="currentRecords.to"
                :total="currentRecords.total"
                :loading="tableLoading"
                @change-page="changePage"
            />
        </div>

        <!-- TAB 8: الباقات والاشتراكات (Subscriptions Tab) -->
        <div v-if="currentTab === 'subscriptions'" class="space-y-6 animate-in fade-in">
            <div>
                <h3 class="text-lg font-bold text-[#163E3E]">باقات الاشتراك الرسمية للمنصة</h3>
                <p class="text-xs text-[#5B6770]">إدارة تسعير الخطط والحدود القصوى للوحدات.</p>
            </div>

            <!-- كروت الباقات -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div
                    v-for="plan in tabRows"
                    :key="plan.id"
                    class="p-5 rounded-2xl border border-[#D6E0E7] bg-white shadow-2xs space-y-3"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-bold text-[#163E3E]">{{ plan.name }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700">نشطة</span>
                    </div>
                    <div class="text-2xl font-black text-[#163E3E]">
                        {{ plan.monthly_price }} <span class="text-xs font-medium text-[#5B6770]">ج.م / شهرياً</span>
                    </div>
                    <div class="text-xs text-[#5B6770] font-medium pt-2 border-t border-[#E8EFF4]">
                        حد الوحدات: <strong class="text-[#163E3E]">{{ plan.unit_limit ? plan.unit_limit + ' وحدة' : 'غير محدود' }}</strong>
                    </div>
                </div>
                <p v-if="!tableLoading && loadedTab === 'subscriptions' && tabRows.length === 0" class="col-span-full rounded-xl border border-dashed border-[#D6E0E7] bg-white p-8 text-center text-sm text-[#5B6770]">
                    لا توجد باقات مطابقة لبحثك.
                </p>
            </div>
            <AdminDashboardPagination
                v-if="loadedTab === 'subscriptions' && currentRecords"
                :current-page="currentRecords.current_page"
                :last-page="currentRecords.last_page"
                :from="currentRecords.from"
                :to="currentRecords.to"
                :total="currentRecords.total"
                :loading="tableLoading"
                @change-page="changePage"
            />
        </div>

        <!-- TAB 9: سجل رسائل واتساب (WhatsApp Logs Tab) -->
        <div v-if="currentTab === 'logs'" class="space-y-4 animate-in fade-in">
            <div>
                <h3 class="text-lg font-bold text-[#163E3E]">سجل رسائل واتساب الرسمية (WhatsApp Cloud API)</h3>
                <p class="text-xs text-[#5B6770]">مراقبة تسليم قوالب التذكيرات التلقائية وحالات الرسائل عبر المنصة.</p>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white overflow-hidden shadow-2xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] font-bold">
                            <tr>
                                <th class="p-3.5">المستأجر</th>
                                <th class="p-3.5">رقم الهاتف</th>
                                <th class="p-3.5">مساحة العمل</th>
                                <th class="p-3.5">نوع القالب</th>
                                <th class="p-3.5">الحالة</th>
                                <th class="p-3.5">موعد الجدولة</th>
                                <th class="p-3.5">وقت الإرسال</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E8EFF4]">
                            <tr v-for="log in tabRows" :key="log.id" class="hover:bg-[#F8FAFC] transition">
                                <td class="p-3.5 font-bold text-[#163E3E]">{{ log.renter_name }}</td>
                                <td class="p-3.5 font-mono text-[#5B6770]" dir="ltr">{{ log.recipient_phone }}</td>
                                <td class="p-3.5 text-[#163E3E] font-medium">{{ log.workspace_name }}</td>
                                <td class="p-3.5 font-mono text-xs">{{ log.template_name }}</td>
                                <td class="p-3.5">
                                    <StatusBadge :status="log.status" />
                                </td>
                                <td class="p-3.5 text-[#5B6770]">{{ log.scheduled_at }}</td>
                                <td class="p-3.5 text-[#5B6770]">{{ log.sent_at }}</td>
                            </tr>
                            <tr v-if="!tableLoading && loadedTab === 'logs' && tabRows.length === 0">
                                <td colspan="7" class="p-8 text-center text-[#5B6770]">لا توجد سجلات رسائل واتساب مسجلة حالياً.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <AdminDashboardPagination
                v-if="loadedTab === 'logs' && currentRecords"
                :current-page="currentRecords.current_page"
                :last-page="currentRecords.last_page"
                :from="currentRecords.from"
                :to="currentRecords.to"
                :total="currentRecords.total"
                :loading="tableLoading"
                @change-page="changePage"
            />
        </div>

        <!-- مودال إضافة مستخدم جديد (Create User Modal) -->
        <Modal :show="showCreateUserModal" @close="showCreateUserModal = false" max-width="md">
            <div class="p-6 text-right" dir="rtl">
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7]">
                    <h3 class="text-base font-bold text-[#163E3E]">إضافة مستخدم جديد للنظام</h3>
                    <button @click="showCreateUserModal = false" type="button" class="text-[#5B6770] hover:text-[#163E3E] p-1">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitCreateUser" class="mt-4 space-y-4">
                    <div>
                        <InputLabel for="new_name" value="اسم المستخدم" />
                        <TextInput id="new_name" v-model="createUserForm.name" required class="w-full mt-1" placeholder="مثال: يوسف أحمد" />
                        <InputError :message="createUserForm.errors.name" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="new_email" value="البريد الإلكتروني" />
                        <TextInput id="new_email" type="email" v-model="createUserForm.email" required class="w-full mt-1" placeholder="user@example.com" />
                        <InputError :message="createUserForm.errors.email" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="new_password" value="كلمة المرور" />
                        <TextInput id="new_password" type="password" v-model="createUserForm.password" required class="w-full mt-1" placeholder="••••••••" />
                        <InputError :message="createUserForm.errors.password" class="mt-1" />
                    </div>

                    <div class="p-3 rounded-xl bg-amber-50 border border-amber-200">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <Checkbox v-model:checked="createUserForm.is_admin" />
                            <span class="text-xs font-bold text-amber-950">تعيين هذا المستخدم كمدير نظام (Super Admin)</span>
                        </label>
                        <p class="text-[11px] text-amber-800 mt-1 mr-6">سيمتلك هذا الحساب صلاحية الدخول للوحة الإدارة والتحكم في كافة البيانات.</p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-[#D6E0E7]">
                        <SecondaryButton @click="showCreateUserModal = false" type="button">إلغاء</SecondaryButton>
                        <PrimaryButton :disabled="createUserForm.processing">حفظ المستخدم</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- مودال تعديل مستخدم (Edit User Modal) -->
        <Modal :show="showEditUserModal" @close="showEditUserModal = false" max-width="md">
            <div class="p-6 text-right" dir="rtl">
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7]">
                    <h3 class="text-base font-bold text-[#163E3E]">تعديل بيانات المستخدم</h3>
                    <button @click="showEditUserModal = false" type="button" class="text-[#5B6770] hover:text-[#163E3E] p-1">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitEditUser" class="mt-4 space-y-4">
                    <div>
                        <InputLabel for="edit_name" value="اسم المستخدم" />
                        <TextInput id="edit_name" v-model="editUserForm.name" required class="w-full mt-1" />
                        <InputError :message="editUserForm.errors.name" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="edit_email" value="البريد الإلكتروني" />
                        <TextInput id="edit_email" type="email" v-model="editUserForm.email" required class="w-full mt-1" />
                        <InputError :message="editUserForm.errors.email" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="edit_password" value="كلمة المرور الجديدة (اتركه فارغاً للإبقاء على الحالية)" />
                        <TextInput id="edit_password" type="password" v-model="editUserForm.password" class="w-full mt-1" placeholder="اتركه فارغاً إذا لا ترغب بتغييرها" />
                        <InputError :message="editUserForm.errors.password" class="mt-1" />
                    </div>

                    <div class="p-3 rounded-xl bg-amber-50 border border-amber-200">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <Checkbox v-model:checked="editUserForm.is_admin" />
                            <span class="text-xs font-bold text-amber-950">صلاحية مدير نظام (Super Admin)</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-[#D6E0E7]">
                        <SecondaryButton @click="showEditUserModal = false" type="button">إلغاء</SecondaryButton>
                        <PrimaryButton :disabled="editUserForm.processing">حفظ التغييرات</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- مودال تعديل مساحة العمل (Edit Workspace Modal) -->
        <Modal :show="showEditWorkspaceModal" @close="showEditWorkspaceModal = false" max-width="md">
            <div class="p-6 text-right" dir="rtl">
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7]">
                    <h3 class="text-base font-bold text-[#163E3E]">تعديل مساحة العمل</h3>
                    <button @click="showEditWorkspaceModal = false" type="button" class="text-[#5B6770] hover:text-[#163E3E] p-1">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitEditWorkspace" class="mt-4 space-y-4">
                    <div>
                        <InputLabel for="ws_name" value="اسم مساحة العمل" />
                        <TextInput id="ws_name" v-model="editWorkspaceForm.name" required class="w-full mt-1" />
                        <InputError :message="editWorkspaceForm.errors.name" class="mt-1" />
                    </div>

                    <div>
                        <label for="ws_currency" class="block text-xs font-semibold text-[#163E3E] mb-1.5">العملة الرسمية</label>
                        <select
                            id="ws_currency"
                            v-model="editWorkspaceForm.currency"
                            aria-label="العملة الرسمية"
                            class="w-full mt-1 h-10 px-3 rounded-xl border border-[#D6E0E7] bg-white text-sm"
                        >
                            <option value="EGP">جنيه مصري (EGP)</option>
                            <option value="SAR">ريال سعودي (SAR)</option>
                            <option value="AED">درهم إماراتي (AED)</option>
                            <option value="USD">دولار أمريكي (USD)</option>
                        </select>
                        <InputError :message="editWorkspaceForm.errors.currency" class="mt-1" />
                    </div>

                    <div>
                        <label for="ws_timezone" class="block text-xs font-semibold text-[#163E3E] mb-1.5">المنطقة الزمنية</label>
                        <select
                            id="ws_timezone"
                            v-model="editWorkspaceForm.timezone"
                            aria-label="المنطقة الزمنية"
                            class="w-full mt-1 h-10 px-3 rounded-xl border border-[#D6E0E7] bg-white text-sm"
                        >
                            <option value="Africa/Cairo">القاهرة (Africa/Cairo)</option>
                            <option value="Asia/Riyadh">الرياض (Asia/Riyadh)</option>
                            <option value="Asia/Dubai">دبي (Asia/Dubai)</option>
                        </select>
                        <InputError :message="editWorkspaceForm.errors.timezone" class="mt-1" />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-[#D6E0E7]">
                        <SecondaryButton @click="showEditWorkspaceModal = false" type="button">إلغاء</SecondaryButton>
                        <PrimaryButton :disabled="editWorkspaceForm.processing">حفظ البيانات</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- مودال ترقية وتغيير الباقة (Subscription Modal) -->
        <Modal :show="showSubscriptionModal" @close="showSubscriptionModal = false" max-width="md">
            <div class="p-6 text-right" dir="rtl">
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7]">
                    <h3 class="text-base font-bold text-[#163E3E]">تغيير باقة مساحة العمل</h3>
                    <button @click="showSubscriptionModal = false" type="button" class="text-[#5B6770] hover:text-[#163E3E] p-1">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitSubscription" class="mt-4 space-y-4">
                    <div>
                        <InputLabel value="مساحة العمل" />
                        <div class="p-3 rounded-xl bg-[#F8FAFC] border border-[#D6E0E7] font-bold text-[#163E3E] text-sm">
                            {{ selectedWorkspace?.name }}
                        </div>
                    </div>

                    <div class="p-3 bg-[#F8FAFC] rounded-xl border border-[#E8EFF4] text-xs text-[#5B6770] space-y-1">
                        <div class="flex justify-between">
                            <span>الوحدات الرئيسية المنشأة:</span>
                            <strong class="text-[#163E3E]">{{ selectedWorkspace?.created_parent_units_count ?? 0 }}</strong>
                        </div>
                        <div class="flex justify-between">
                            <span>العقارات المؤجرة حالياً:</span>
                            <strong class="text-[#163E3E]">{{ selectedWorkspace?.active_rented_parent_units_count ?? 0 }}</strong>
                        </div>
                    </div>

                    <div>
                        <label for="sub_plan" class="block text-xs font-semibold text-[#163E3E] mb-1.5">اختر الباقة المطلوبة</label>
                        <select
                            id="sub_plan"
                            v-model="subscriptionForm.plan_id"
                            aria-label="اختر الباقة المطلوبة"
                            class="w-full mt-1 h-10 px-3 rounded-xl border border-[#D6E0E7] bg-white text-sm"
                        >
                            <option
                                v-for="plan in plans"
                                :key="plan.id"
                                :value="plan.id"
                                :disabled="isPlanBelowWorkspaceUsage(plan, selectedWorkspace)"
                            >
                                {{ plan.name }} ({{ plan.monthly_price }} ج.م)
                                {{ isPlanBelowWorkspaceUsage(plan, selectedWorkspace) ? '— غير متاح (أقل من الاستخدام الحالي)' : '' }}
                            </option>
                        </select>
                        <InputError :message="subscriptionForm.errors.plan_id" class="mt-1.5" />
                    </div>

                    <div>
                        <label for="sub_status" class="block text-xs font-semibold text-[#163E3E] mb-1.5">حالة الاشتراك</label>
                        <select
                            id="sub_status"
                            v-model="subscriptionForm.status"
                            aria-label="حالة الاشتراك"
                            class="w-full mt-1 h-10 px-3 rounded-xl border border-[#D6E0E7] bg-white text-sm"
                        >
                            <option value="active">نشط ومفعل (Active)</option>
                            <option value="suspended">معلق (Suspended)</option>
                            <option value="cancelled">ملغي (Cancelled)</option>
                            <option value="expired">منتهي (Expired)</option>
                        </select>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-[#D6E0E7]">
                        <SecondaryButton @click="showSubscriptionModal = false" type="button">إلغاء</SecondaryButton>
                        <PrimaryButton :disabled="subscriptionForm.processing">تحديث الاشتراك</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- مودال إدارة وتعديل الحدود المستقلة لمساحة العمل -->
        <Modal :show="showLimitsModal" @close="showLimitsModal = false" max-width="md">
            <div class="p-6 text-right" dir="rtl">
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7]">
                    <h3 class="text-base font-bold text-[#163E3E]">إدارة حدود مساحة العمل المستقلة</h3>
                    <button @click="showLimitsModal = false" type="button" class="text-[#5B6770] hover:text-[#163E3E] p-1">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitLimits" class="mt-4 space-y-4">
                    <div>
                        <InputLabel value="مساحة العمل" />
                        <div class="p-3 rounded-xl bg-[#F8FAFC] border border-[#D6E0E7] font-bold text-[#163E3E] text-sm flex items-center justify-between">
                            <span>{{ selectedWorkspace?.name }}</span>
                            <span class="text-xs text-[#5B6770]">{{ selectedWorkspace?.owner_name }}</span>
                        </div>
                    </div>

                    <!-- حد إنشاء الوحدات الرئيسية -->
                    <div class="p-3.5 rounded-xl border border-[#D6E0E7] bg-white space-y-2">
                        <div class="flex items-center justify-between">
                            <InputLabel for="parent_unit_limit" value="الحد الأقصى لإنشاء الوحدات الرئيسية" class="font-bold text-[#163E3E]" />
                            <div class="flex items-center gap-1.5">
                                <Checkbox id="dashboard_is_parent_unit_unlimited" v-model:checked="limitsForm.is_parent_unit_unlimited" />
                                <label for="dashboard_is_parent_unit_unlimited" class="text-xs text-[#163E3E] font-semibold cursor-pointer">
                                    غير محدود (Unlimited)
                                </label>
                            </div>
                        </div>
                        <p class="text-[11px] text-[#5B6770]">
                            ينطبق على الشقق والمحلات الرئيسية. لا يفرض قيوداً على الأوض أو الأسِرَّة (Child Units).
                            (المنشأ حالياً: {{ selectedWorkspace?.created_parent_units_count ?? 0 }})
                        </p>
                        <div v-if="!limitsForm.is_parent_unit_unlimited">
                            <TextInput
                                id="parent_unit_limit"
                                type="number"
                                :min="selectedWorkspace?.created_parent_units_count ?? 0"
                                v-model="limitsForm.parent_unit_limit"
                                class="w-full h-10 px-3"
                                required
                            />
                            <p class="text-[10px] text-[#5B6770] mt-1">
                                الحد الأدنى المسموح به: {{ selectedWorkspace?.created_parent_units_count ?? 0 }} وحدة
                            </p>
                        </div>
                        <div v-else class="text-xs font-bold text-emerald-700 bg-emerald-50 p-2 rounded-lg text-center">
                            تم تفعيل السعة غير المحدودة لإنشاء الوحدات الرئيسية (∞)
                        </div>
                        <InputError :message="limitsForm.errors.parent_unit_limit" />
                    </div>

                    <!-- حد العقود التأجيرية النشطة -->
                    <div class="p-3.5 rounded-xl border border-[#D6E0E7] bg-white space-y-2">
                        <div class="flex items-center justify-between">
                            <InputLabel for="active_rental_limit" value="الحد الأقصى للتأجير النشط المتزامن" class="font-bold text-[#163E3E]" />
                            <div class="flex items-center gap-1.5">
                                <Checkbox id="dashboard_is_active_rental_unlimited" v-model:checked="limitsForm.is_active_rental_unlimited" />
                                <label for="dashboard_is_active_rental_unlimited" class="text-xs text-[#163E3E] font-semibold cursor-pointer">
                                    غير محدود (Unlimited)
                                </label>
                            </div>
                        </div>
                        <p class="text-[11px] text-[#5B6770]">
                            يحتسب بعدد العقارات الرئيسية المؤجرة فعلياً. تأجير عدة غرف بنفس الشقة يستهلك خانة تأجير واحدة فقط.
                            (المشغول حالياً: {{ selectedWorkspace?.active_rented_parent_units_count ?? 0 }})
                        </p>
                        <div v-if="!limitsForm.is_active_rental_unlimited">
                            <TextInput
                                id="active_rental_limit"
                                type="number"
                                :min="selectedWorkspace?.active_rented_parent_units_count ?? 0"
                                v-model="limitsForm.active_rental_limit"
                                class="w-full h-10 px-3"
                                required
                            />
                            <p class="text-[10px] text-[#5B6770] mt-1">
                                الحد الأدنى المسموح به: {{ selectedWorkspace?.active_rented_parent_units_count ?? 0 }} عقار
                            </p>
                        </div>
                        <div v-else class="text-xs font-bold text-emerald-700 bg-emerald-50 p-2 rounded-lg text-center">
                            تم تفعيل السعة غير المحدودة للعقود التأجيرية المتزامنة (∞)
                        </div>
                        <InputError :message="limitsForm.errors.active_rental_limit" />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-[#D6E0E7]">
                        <SecondaryButton @click="showLimitsModal = false" type="button">إلغاء</SecondaryButton>
                        <PrimaryButton :disabled="limitsForm.processing">حفظ الحدود المحدثة</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AppLayout>
</template>
