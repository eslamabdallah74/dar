<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryKpiRow from '@/Components/Dashboard/PrimaryKpiRow.vue';
import OperationalKpiRow from '@/Components/Dashboard/OperationalKpiRow.vue';
import RentalIncomeChart from '@/Components/Dashboard/RentalIncomeChart.vue';
import OccupancySummaryCard from '@/Components/Dashboard/OccupancySummaryCard.vue';
import AttentionRequiredCard from '@/Components/Dashboard/AttentionRequiredCard.vue';
import RecentActivityFeed from '@/Components/Dashboard/RecentActivityFeed.vue';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Checkbox from '@/Components/Checkbox.vue';

import {
    Activity,
    AlertCircle,
    AlertTriangle,
    ArrowUpRight,
    Building2,
    Calendar,
    CheckCircle2,
    Coins,
    CreditCard,
    DollarSign,
    Edit3,
    ExternalLink,
    FileText,
    Home,
    Layers,
    LayoutDashboard,
    LogIn,
    MessageSquare,
    Plus,
    RefreshCw,
    Search,
    Shield,
    ShieldAlert,
    ShieldCheck,
    Sliders,
    Sparkles,
    Trash2,
    Users,
    Wrench,
    X,
} from 'lucide-vue-next';

interface Props {
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
    workspaces: any[];
    users: any[];
    properties: any[];
    leases: any[];
    payments: any[];
    plans: any[];
    messageLogs: any[];
    maintenanceRequests: any[];
}

const props = defineProps<Props>();

// Current selected tab for data control
const currentTab = ref<'overview' | 'workspaces' | 'users' | 'properties' | 'leases' | 'payments' | 'maintenance' | 'subscriptions' | 'logs'>('overview');
const searchQuery = ref('');

// Tabs metadata
const controlTabs = [
    { key: 'overview', name: 'نظرة عامة', icon: LayoutDashboard },
    { key: 'workspaces', name: 'مساحات العمل', count: props.workspaces.length, icon: Building2 },
    { key: 'users', name: 'المستخدمون', count: props.users.length, icon: Users },
    { key: 'properties', name: 'العقارات والوحدات', count: props.properties.length, icon: Home },
    { key: 'leases', name: 'العقود', count: props.leases.length, icon: FileText },
    { key: 'payments', name: 'المدفوعات', count: props.payments.length, icon: CreditCard },
    { key: 'maintenance', name: 'طلبات الصيانة', count: props.maintenanceRequests.length, icon: Wrench },
    { key: 'subscriptions', name: 'الباقات والاشتراكات', count: props.plans.length, icon: Layers },
    { key: 'logs', name: 'رسائل واتساب', count: props.messageLogs.length, icon: MessageSquare },
];

function handleGlobalSearch(query: string) {
    searchQuery.value = query.toLowerCase();
}

// Filtered lists
const filteredWorkspaces = computed(() => {
    if (!searchQuery.value) return props.workspaces;
    return props.workspaces.filter(w =>
        w.name.toLowerCase().includes(searchQuery.value) ||
        w.owner_name.toLowerCase().includes(searchQuery.value) ||
        w.owner_email.toLowerCase().includes(searchQuery.value)
    );
});

const filteredUsers = computed(() => {
    if (!searchQuery.value) return props.users;
    return props.users.filter(u =>
        u.name.toLowerCase().includes(searchQuery.value) ||
        u.email.toLowerCase().includes(searchQuery.value)
    );
});

const filteredProperties = computed(() => {
    if (!searchQuery.value) return props.properties;
    return props.properties.filter(p =>
        p.name.toLowerCase().includes(searchQuery.value) ||
        p.workspace_name.toLowerCase().includes(searchQuery.value) ||
        p.city.toLowerCase().includes(searchQuery.value)
    );
});

const filteredLeases = computed(() => {
    if (!searchQuery.value) return props.leases;
    return props.leases.filter(l =>
        l.lease_number.toLowerCase().includes(searchQuery.value) ||
        l.renter_name.toLowerCase().includes(searchQuery.value) ||
        l.workspace_name.toLowerCase().includes(searchQuery.value)
    );
});

const filteredPayments = computed(() => {
    if (!searchQuery.value) return props.payments;
    return props.payments.filter(p =>
        p.payment_number.toLowerCase().includes(searchQuery.value) ||
        p.renter_name.toLowerCase().includes(searchQuery.value) ||
        p.workspace_name.toLowerCase().includes(searchQuery.value)
    );
});

// Modals State
const showCreateUserModal = ref(false);
const showEditUserModal = ref(false);
const showEditWorkspaceModal = ref(false);
const showSubscriptionModal = ref(false);

const selectedUser = ref<any>(null);
const selectedWorkspace = ref<any>(null);

// Forms
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
function impersonate(workspaceId: number) {
    router.post(route('admin.workspaces.impersonate', workspaceId));
}

function openCreateUser() {
    createUserForm.reset();
    showCreateUserModal.value = true;
}

function submitCreateUser() {
    createUserForm.post(route('admin.users.store'), {
        onSuccess: () => {
            showCreateUserModal.value = false;
            createUserForm.reset();
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
        onSuccess: () => {
            showEditUserModal.value = false;
            editUserForm.reset();
        },
    });
}

function toggleAdminRole(userId: number) {
    router.patch(route('admin.users.toggle-admin', userId));
}

function deleteUser(userId: number, name: string) {
    if (confirm(`هل أنت متأكد من حذف المستخدم (${name})؟`)) {
        router.delete(route('admin.users.destroy', userId));
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
        onSuccess: () => {
            showEditWorkspaceModal.value = false;
        },
    });
}

function deleteWorkspace(wsId: number, name: string) {
    if (confirm(`تحذير: هل أنت متأكد تمامًا من حذف مساحة العمل (${name}) وكافة بياناتها؟`)) {
        router.delete(route('admin.workspaces.destroy', wsId));
    }
}

function openChangePlan(ws: any) {
    selectedWorkspace.value = ws;
    subscriptionForm.workspace_id = ws.id;
    const currentPlan = props.plans.find(p => p.slug === ws.plan_slug) || props.plans[0];
    subscriptionForm.plan_id = currentPlan ? currentPlan.id : 1;
    subscriptionForm.status = ws.subscription_status || 'active';
    showSubscriptionModal.value = true;
}

function submitSubscription() {
    subscriptionForm.post(route('admin.workspaces.subscription', subscriptionForm.workspace_id), {
        onSuccess: () => {
            showSubscriptionModal.value = false;
        },
    });
}

function updateLeaseStatus(leaseId: number, status: string) {
    router.patch(route('admin.leases.status', leaseId), { status });
}

function updateMaintenanceStatus(maintenanceId: number, status: string) {
    router.patch(route('admin.maintenance.status', maintenanceId), { status });
}
</script>

<template>
    <AppLayout
        title="دار — لوحة الإدارة المركزية والتحكم الشامل"
        header-title="لوحة الإدارة المركزية والرقابة العامة"
        header-subtitle="رؤية شاملة وتحكم في كافة مساحات العمل والمستخدمين والعمليات المالية والاشتراكات"
        breadcrumb="لوحة القيادة المركزية"
        :admin-mode="true"
        :active-tab="currentTab"
        @select-tab="(tab: any) => currentTab = tab"
        @search="handleGlobalSearch"
        @new-action="openCreateUser"
    >
        <template #actions>
            <div class="flex items-center gap-2.5">
                <button
                    @click="router.reload()"
                    type="button"
                    class="h-10 px-3.5 rounded-xl border border-[#D6E0E7] bg-white text-xs font-semibold text-[#0F2747] hover:bg-[#F3F7FA] flex items-center gap-1.5 transition shadow-2xs cursor-pointer"
                    title="تحديث البيانات"
                >
                    <RefreshCw class="w-3.5 h-3.5 text-[#5B6770]" />
                    <span>تحديث حي</span>
                </button>

                <PrimaryButton
                    @click="openCreateUser"
                    class="!py-2.5 !text-xs !bg-[#0F2747] !text-white !shadow-none hover:!bg-[#17334F] cursor-pointer"
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
                            <h2 class="text-base font-bold text-[#0F2747]">مؤشرات المنصة والخدمات المركزية</h2>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                متصل ونشط
                            </span>
                        </div>
                        <p class="text-xs text-[#5B6770] font-medium mt-0.5">
                            المسؤول المسجل حالياً: <strong class="text-[#0F2747]">{{ adminUser.name }}</strong> ({{ adminUser.email }})
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-[#5B6770]">تصفح سريع للبيانات:</span>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="فلترة الجداول..."
                        class="h-9 px-3 rounded-lg border border-[#D6E0E7] bg-white text-xs text-[#0F2747] focus:ring-1 focus:ring-[#0E6E7E] focus:border-[#0E6E7E] text-right"
                    />
                </div>
            </div>

            <!-- بطاقات الإحصائيات السريعة للمنصة -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 pt-2 border-t border-amber-200/50">
                <div class="p-3 rounded-xl bg-white border border-[#D6E0E7] shadow-2xs text-right">
                    <span class="text-[11px] font-medium text-[#5B6770] block">مساحات العمل</span>
                    <span class="text-xl font-bold text-[#0F2747] block mt-0.5">{{ platformSummary.total_workspaces }}</span>
                </div>
                <div class="p-3 rounded-xl bg-white border border-[#D6E0E7] shadow-2xs text-right">
                    <span class="text-[11px] font-medium text-[#5B6770] block">المستخدمين المسجلين</span>
                    <span class="text-xl font-bold text-[#0F2747] block mt-0.5">{{ platformSummary.total_users }}</span>
                </div>
                <div class="p-3 rounded-xl bg-white border border-[#D6E0E7] shadow-2xs text-right">
                    <span class="text-[11px] font-medium text-[#5B6770] block">إجمالي العقود</span>
                    <span class="text-xl font-bold text-[#0F2747] block mt-0.5">{{ platformSummary.total_leases }}</span>
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
                @click="currentTab = tab.key as any"
                type="button"
                class="min-h-11 shrink-0 px-3 sm:px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer"
                :class="currentTab === tab.key
                    ? 'bg-[#0F2747] text-white shadow-xs'
                    : 'bg-white border border-[#D6E0E7] text-[#5B6770] hover:bg-[#F3F7FA] hover:text-[#0F2747]'"
            >
                <component :is="tab.icon" class="w-4 h-4 shrink-0" />
                <span>{{ tab.name }}</span>
                <span
                    v-if="tab.count !== undefined"
                    class="px-1.5 py-0.5 rounded-full text-[10px]"
                    :class="currentTab === tab.key ? 'bg-white/20 text-white' : 'bg-[#E8EFF4] text-[#0F2747]'"
                >
                    {{ tab.count }}
                </span>
            </button>
        </div>

        <!-- TAB 1: نظرة عامة ورسوم بيانية (Overview Tab) -->
        <div v-show="currentTab === 'overview'" class="space-y-8 animate-in fade-in">
            <!-- المؤشرات الأربعة الرئيسية (إعادة استخدام PrimaryKpiRow المعتمد) -->
            <PrimaryKpiRow :kpis="kpis" />

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
                    <OccupancySummaryCard :summary="occupancySummary" />
                </div>
            </div>

            <!-- مؤشرات المصروفات والتشغيل (إعادة استخدام OperationalKpiRow) -->
            <OperationalKpiRow :kpis="operationalKpis" />

            <!-- صف التنبيهات العاجلة وسجل العمليات الحي -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <AttentionRequiredCard
                    title="تنبيهات المنصة العاجلة"
                    :items="attentionItems"
                />
                <RecentActivityFeed
                    :activities="recentActivities"
                />
            </div>
        </div>

        <!-- TAB 2: إدارة مساحات العمل (Workspaces Tab) -->
        <div v-show="currentTab === 'workspaces'" class="space-y-4 animate-in fade-in">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-[#0F2747]">مساحات العمل المسجلة (Workspaces)</h3>
                    <p class="text-xs text-[#5B6770]">عرض شامل والتحكم في كل مساحة عمل، ترقية الباقات، أو الدخول كمدير نظام.</p>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white overflow-hidden shadow-2xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] font-bold">
                            <tr>
                                <th class="p-3.5">مساحة العمل</th>
                                <th class="p-3.5">المالك المسجل</th>
                                <th class="p-3.5 text-center">العقارات</th>
                                <th class="p-3.5 text-center">الوحدات</th>
                                <th class="p-3.5 text-center">العقود</th>
                                <th class="p-3.5">الباقة الحالية</th>
                                <th class="p-3.5">الحالة</th>
                                <th class="p-3.5">تاريخ الإنشاء</th>
                                <th class="p-3.5 text-center">الإجراءات والتحكم</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E8EFF4]">
                            <tr v-for="ws in filteredWorkspaces" :key="ws.id" class="hover:bg-[#F8FAFC] transition">
                                <td class="p-3.5 font-bold text-[#0F2747]">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center font-bold">
                                            <Building2 class="w-4 h-4" />
                                        </div>
                                        <span>{{ ws.name }}</span>
                                    </div>
                                </td>
                                <td class="p-3.5">
                                    <div class="font-semibold text-[#0F2747]">{{ ws.owner_name }}</div>
                                    <div class="text-[11px] text-[#5B6770]">{{ ws.owner_email }}</div>
                                </td>
                                <td class="p-3.5 text-center font-bold">{{ ws.properties_count }}</td>
                                <td class="p-3.5 text-center font-bold">{{ ws.units_count }}</td>
                                <td class="p-3.5 text-center font-bold">{{ ws.leases_count }}</td>
                                <td class="p-3.5">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-[#E8F3F5] text-[#0E6E7E]">
                                        {{ ws.plan_name }}
                                    </span>
                                </td>
                                <td class="p-3.5">
                                    <StatusBadge :status="ws.subscription_status" />
                                </td>
                                <td class="p-3.5 text-[#5B6770]">{{ ws.created_at }}</td>
                                <td class="p-3.5">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button
                                            @click="impersonate(ws.id)"
                                            type="button"
                                            class="px-2.5 py-1.5 rounded-lg bg-emerald-50 text-emerald-800 hover:bg-emerald-100 font-bold transition flex items-center gap-1 cursor-pointer"
                                            title="دخول مساحة العمل كمدير"
                                        >
                                            <LogIn class="w-3.5 h-3.5" />
                                            <span>دخول</span>
                                        </button>
                                        <button
                                            @click="openChangePlan(ws)"
                                            type="button"
                                            class="px-2.5 py-1.5 rounded-lg bg-[#E8F3F5] text-[#0E6E7E] hover:bg-[#D4EAEF] font-bold transition flex items-center gap-1 cursor-pointer"
                                            title="ترقية / تغيير الباقة"
                                        >
                                            <Layers class="w-3.5 h-3.5" />
                                            <span>الباقة</span>
                                        </button>
                                        <button
                                            @click="openEditWorkspace(ws)"
                                            type="button"
                                            class="p-1.5 rounded-lg text-[#5B6770] hover:text-[#0F2747] hover:bg-[#F3F7FA] transition cursor-pointer"
                                            title="تعديل البيانات"
                                        >
                                            <Edit3 class="w-4 h-4" />
                                        </button>
                                        <button
                                            @click="deleteWorkspace(ws.id, ws.name)"
                                            type="button"
                                            class="p-1.5 rounded-lg text-rose-600 hover:bg-rose-50 transition cursor-pointer"
                                            title="حذف مساحة العمل"
                                        >
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredWorkspaces.length === 0">
                                <td colspan="9" class="p-8 text-center text-[#5B6770]">لا توجد مساحات عمل مطابقة لبحثك.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 3: إدارة المستخدمين (Users Tab) -->
        <div v-show="currentTab === 'users'" class="space-y-4 animate-in fade-in">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-[#0F2747]">المستخدمون والمسؤولون (Platform Users)</h3>
                    <p class="text-xs text-[#5B6770]">إدارة كاملة للمستخدمين، وتعيين أو سحب صلاحيات الإدارة المركزية.</p>
                </div>
                <PrimaryButton @click="openCreateUser" class="!py-2 !text-xs !bg-[#0F2747] !text-white !shadow-none hover:!bg-[#17334F]">
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
                                <th class="p-3.5 text-center">المحافظ المملوكة</th>
                                <th class="p-3.5 text-center">حالة التحقق</th>
                                <th class="p-3.5">تاريخ التسجيل</th>
                                <th class="p-3.5 text-center">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E8EFF4]">
                            <tr v-for="u in filteredUsers" :key="u.id" class="hover:bg-[#F8FAFC] transition">
                                <td class="p-3.5 font-bold text-[#0F2747]">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-xs"
                                            :class="u.is_admin ? 'bg-amber-400 text-slate-950 font-black' : 'bg-[#E3F1F4] text-[#0E6E7E]'"
                                        >
                                            {{ u.name.charAt(0) }}
                                        </div>
                                        <span>{{ u.name }}</span>
                                    </div>
                                </td>
                                <td class="p-3.5 font-mono text-[#0F2747]">{{ u.email }}</td>
                                <td class="p-3.5 text-center">
                                    <span
                                        class="px-2.5 py-0.5 rounded-full text-[10px] font-black inline-flex items-center gap-1"
                                        :class="u.is_admin ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-slate-100 text-slate-700'"
                                    >
                                        <Shield v-if="u.is_admin" class="w-3 h-3 text-amber-600" />
                                        {{ u.is_admin ? 'مدير نظام (Admin)' : 'مستخدم عادي' }}
                                    </span>
                                </td>
                                <td class="p-3.5 text-center font-bold">{{ u.owned_workspaces_count }}</td>
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
                                            class="p-1.5 rounded-lg text-[#5B6770] hover:text-[#0F2747] hover:bg-[#F3F7FA] transition cursor-pointer"
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
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 4: العقارات والوحدات (Properties Tab) -->
        <div v-show="currentTab === 'properties'" class="space-y-4 animate-in fade-in">
            <div>
                <h3 class="text-lg font-bold text-[#0F2747]">العقارات والوحدات عبر المنصة</h3>
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
                            <tr v-for="p in filteredProperties" :key="p.id" class="hover:bg-[#F8FAFC] transition">
                                <td class="p-3.5 font-bold text-[#0F2747]">{{ p.name }}</td>
                                <td class="p-3.5">
                                    <span class="px-2 py-0.5 rounded-md bg-[#E8F3F5] text-[#0E6E7E] font-semibold text-[11px]">
                                        {{ p.workspace_name }}
                                    </span>
                                </td>
                                <td class="p-3.5 font-medium">{{ p.property_type }}</td>
                                <td class="p-3.5 text-[#5B6770]">{{ p.city }} • {{ p.address }}</td>
                                <td class="p-3.5 text-center font-bold text-[#0F2747]">{{ p.units_count }}</td>
                                <td class="p-3.5 text-[#5B6770]">{{ p.created_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 5: العقود والمستأجرون (Leases Tab) -->
        <div v-show="currentTab === 'leases'" class="space-y-4 animate-in fade-in">
            <div>
                <h3 class="text-lg font-bold text-[#0F2747]">العقود الإيجارية (Platform Leases)</h3>
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
                            <tr v-for="l in filteredLeases" :key="l.id" class="hover:bg-[#F8FAFC] transition">
                                <td class="p-3.5 font-bold font-mono text-[#0F2747]">{{ l.lease_number }}</td>
                                <td class="p-3.5 font-medium text-[#0E6E7E]">{{ l.workspace_name }}</td>
                                <td class="p-3.5 font-semibold text-[#0F2747]">{{ l.renter_name }}</td>
                                <td class="p-3.5 text-[#5B6770]">{{ l.unit_name }}</td>
                                <td class="p-3.5 font-bold text-[#0F2747]">{{ l.rent_amount.toLocaleString() }} ج.م</td>
                                <td class="p-3.5 text-[11px] text-[#5B6770]">{{ l.starts_on }} ← {{ l.ends_on }}</td>
                                <td class="p-3.5">
                                    <StatusBadge :status="l.status" />
                                </td>
                                <td class="p-3.5 text-center">
                                    <select
                                        :value="l.status"
                                        @change="updateLeaseStatus(l.id, ($event.target as HTMLSelectElement).value)"
                                        class="h-7 px-2 rounded-lg border border-[#D6E0E7] bg-white text-[11px] font-semibold text-[#0F2747] cursor-pointer"
                                    >
                                        <option value="active">نشط (Active)</option>
                                        <option value="draft">مسودة (Draft)</option>
                                        <option value="expired">منتهي (Expired)</option>
                                        <option value="terminated">ملغى (Terminated)</option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 6: المالية والمدفوعات (Payments Tab) -->
        <div v-show="currentTab === 'payments'" class="space-y-4 animate-in fade-in">
            <div>
                <h3 class="text-lg font-bold text-[#0F2747]">سجل الدفعات والتحصيلات في المنصة</h3>
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
                            <tr v-for="p in filteredPayments" :key="p.id" class="hover:bg-[#F8FAFC] transition">
                                <td class="p-3.5 font-bold font-mono text-[#0F2747]">{{ p.payment_number }}</td>
                                <td class="p-3.5 font-semibold text-[#0E6E7E]">{{ p.workspace_name }}</td>
                                <td class="p-3.5 font-semibold text-[#0F2747]">{{ p.renter_name }}</td>
                                <td class="p-3.5 font-bold text-emerald-700">{{ p.amount.toLocaleString() }} ج.م</td>
                                <td class="p-3.5 font-medium">{{ p.method }}</td>
                                <td class="p-3.5 font-mono text-[#5B6770]">{{ p.reference }}</td>
                                <td class="p-3.5 text-[#5B6770]">{{ p.paid_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 7: طلبات الصيانة (Maintenance Tab) -->
        <div v-show="currentTab === 'maintenance'" class="space-y-4 animate-in fade-in">
            <div>
                <h3 class="text-lg font-bold text-[#0F2747]">طلبات الصيانة المركزية</h3>
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
                            <tr v-for="mr in maintenanceRequests" :key="mr.id" class="hover:bg-[#F8FAFC] transition">
                                <td class="p-3.5 font-bold text-[#0F2747]">{{ mr.title }}</td>
                                <td class="p-3.5 text-[#0E6E7E] font-medium">{{ mr.workspace_name }}</td>
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
                                        :value="mr.status"
                                        @change="updateMaintenanceStatus(mr.id, ($event.target as HTMLSelectElement).value)"
                                        class="h-7 px-2 rounded-lg border border-[#D6E0E7] bg-white text-[11px] font-semibold text-[#0F2747] cursor-pointer"
                                    >
                                        <option value="open">مفتوح (Open)</option>
                                        <option value="in_progress">قيد التنفيذ (In Progress)</option>
                                        <option value="completed">مكتمل (Completed)</option>
                                        <option value="cancelled">ملغي (Cancelled)</option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 8: الباقات والاشتراكات (Subscriptions Tab) -->
        <div v-show="currentTab === 'subscriptions'" class="space-y-6 animate-in fade-in">
            <div>
                <h3 class="text-lg font-bold text-[#0F2747]">باقات الاشتراك الرسمية للمنصة</h3>
                <p class="text-xs text-[#5B6770]">إدارة تسعير الخطط والحدود القصوى للوحدات.</p>
            </div>

            <!-- كروت الباقات -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div
                    v-for="plan in plans"
                    :key="plan.id"
                    class="p-5 rounded-2xl border border-[#D6E0E7] bg-white shadow-2xs space-y-3"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-bold text-[#0F2747]">{{ plan.name }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700">نشطة</span>
                    </div>
                    <div class="text-2xl font-black text-[#0E6E7E]">
                        {{ plan.monthly_price }} <span class="text-xs font-medium text-[#5B6770]">ج.م / شهرياً</span>
                    </div>
                    <div class="text-xs text-[#5B6770] font-medium pt-2 border-t border-[#E8EFF4]">
                        حد الوحدات: <strong class="text-[#0F2747]">{{ plan.unit_limit ? plan.unit_limit + ' وحدة' : 'غير محدود' }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 9: سجل رسائل واتساب (WhatsApp Logs Tab) -->
        <div v-show="currentTab === 'logs'" class="space-y-4 animate-in fade-in">
            <div>
                <h3 class="text-lg font-bold text-[#0F2747]">سجل رسائل واتساب الرسمية (WhatsApp Cloud API)</h3>
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
                            <tr v-for="log in messageLogs" :key="log.id" class="hover:bg-[#F8FAFC] transition">
                                <td class="p-3.5 font-bold text-[#0F2747]">{{ log.renter_name }}</td>
                                <td class="p-3.5 font-mono text-[#5B6770]" dir="ltr">{{ log.recipient_phone }}</td>
                                <td class="p-3.5 text-[#0E6E7E] font-medium">{{ log.workspace_name }}</td>
                                <td class="p-3.5 font-mono text-xs">{{ log.template_name }}</td>
                                <td class="p-3.5">
                                    <StatusBadge :status="log.status" />
                                </td>
                                <td class="p-3.5 text-[#5B6770]">{{ log.scheduled_at }}</td>
                                <td class="p-3.5 text-[#5B6770]">{{ log.sent_at }}</td>
                            </tr>
                            <tr v-if="messageLogs.length === 0">
                                <td colspan="7" class="p-8 text-center text-[#5B6770]">لا توجد سجلات رسائل واتساب مسجلة حالياً.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- مودال إضافة مستخدم جديد (Create User Modal) -->
        <Modal :show="showCreateUserModal" @close="showCreateUserModal = false" max-width="md">
            <div class="p-6 text-right" dir="rtl">
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7]">
                    <h3 class="text-base font-bold text-[#0F2747]">إضافة مستخدم جديد للنظام</h3>
                    <button @click="showCreateUserModal = false" type="button" class="text-[#5B6770] hover:text-[#0F2747] p-1">
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
                    <h3 class="text-base font-bold text-[#0F2747]">تعديل بيانات المستخدم</h3>
                    <button @click="showEditUserModal = false" type="button" class="text-[#5B6770] hover:text-[#0F2747] p-1">
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
                    <h3 class="text-base font-bold text-[#0F2747]">تعديل مساحة العمل</h3>
                    <button @click="showEditWorkspaceModal = false" type="button" class="text-[#5B6770] hover:text-[#0F2747] p-1">
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
                        <InputLabel for="ws_currency" value="العملة الرسمية" />
                        <select
                            id="ws_currency"
                            v-model="editWorkspaceForm.currency"
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
                        <InputLabel for="ws_timezone" value="المنطقة الزمنية" />
                        <select
                            id="ws_timezone"
                            v-model="editWorkspaceForm.timezone"
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
                    <h3 class="text-base font-bold text-[#0F2747]">تغيير باقة مساحة العمل</h3>
                    <button @click="showSubscriptionModal = false" type="button" class="text-[#5B6770] hover:text-[#0F2747] p-1">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitSubscription" class="mt-4 space-y-4">
                    <div>
                        <InputLabel value="مساحة العمل" />
                        <div class="p-3 rounded-xl bg-[#F8FAFC] border border-[#D6E0E7] font-bold text-[#0F2747] text-sm">
                            {{ selectedWorkspace?.name }}
                        </div>
                    </div>

                    <div>
                        <InputLabel for="sub_plan" value="اختر الباقة المطلوبة" />
                        <select
                            id="sub_plan"
                            v-model="subscriptionForm.plan_id"
                            class="w-full mt-1 h-10 px-3 rounded-xl border border-[#D6E0E7] bg-white text-sm"
                        >
                            <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                                {{ plan.name }} ({{ plan.monthly_price }} ج.م)
                            </option>
                        </select>
                    </div>

                    <div>
                        <InputLabel for="sub_status" value="حالة الاشتراك" />
                        <select
                            id="sub_status"
                            v-model="subscriptionForm.status"
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
    </AppLayout>
</template>
