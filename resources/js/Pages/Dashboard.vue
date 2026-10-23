<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AppSidebar from '@/Components/Layout/AppSidebar.vue';
import AppNavbar from '@/Components/Layout/AppNavbar.vue';
import AppFooter from '@/Components/Layout/AppFooter.vue';
import PrimaryKpiRow from '@/Components/Dashboard/PrimaryKpiRow.vue';
import RentalIncomeChart from '@/Components/Dashboard/RentalIncomeChart.vue';
import OccupancySummaryCard from '@/Components/Dashboard/OccupancySummaryCard.vue';
import AttentionRequiredCard from '@/Components/Dashboard/AttentionRequiredCard.vue';
import UpcomingCollectionsTable from '@/Components/Dashboard/UpcomingCollectionsTable.vue';
import RecentPropertiesGrid from '@/Components/Dashboard/RecentPropertiesGrid.vue';
import RecentActivityFeed from '@/Components/Dashboard/RecentActivityFeed.vue';
import QuickActionsBar from '@/Components/Dashboard/QuickActionsBar.vue';
import WhatsAppSummaryCard from '@/Components/Dashboard/WhatsAppSummaryCard.vue';
import EmptyWorkspaceOnboarding from '@/Components/Dashboard/EmptyWorkspaceOnboarding.vue';
import ModalNewAction from '@/Components/Common/ModalNewAction.vue';
import ModalRecordPayment from '@/Components/Common/ModalRecordPayment.vue';
import {
    Plus,
    CreditCard,
    Building2,
    Calendar,
    Sparkles,
    AlertCircle,
} from 'lucide-vue-next';

interface Props {
    user: {
        name: string;
        email: string;
        role: string;
    };
    workspace: {
        id: number;
        name: string;
        all_workspaces_label?: string;
        currency: string;
        plan_name: string;
        trial_days_remaining: number;
        units_used: number;
        units_limit: number;
        usage_percentage?: number;
    };
    is_empty_workspace?: boolean;
    header: {
        greeting: string;
        subtitle: string;
        breadcrumb?: string;
    };
    checklist?: {
        title: string;
        steps: string[];
        percentage: number;
        action_label: string;
    };
    kpis: {
        properties: { label: string; value: string; subtitle: string };
        units: { label: string; value: string; subtitle: string };
        occupancy: { label: string; value: string; subtitle: string };
        collected: { label: string; value: string; subtitle: string };
        overdue?: { label: string; value: string; subtitle: string; count: number };
        expiring?: { label: string; value: string; subtitle: string };
    };
    occupancy_summary?: {
        total: number;
        occupied: number;
        vacant: number;
        maintenance: number;
        occupancy_rate: number;
    };
    income_chart?: {
        months: string[];
        amounts: number[];
        history: Array<{ month: string; year: number; amount: number }>;
        has_history: boolean;
        total_collected: number;
    };
    attention_items?: Array<{
        id: number;
        type: string;
        title: string;
        desc: string;
        phone?: string;
        amount?: number;
        severity?: string;
        link?: string;
    }>;
    upcoming_dues?: Array<{
        id: number;
        tenant: string;
        renter_id?: number;
        unit: string;
        due_date: string;
        due_date_iso?: string;
        amount: string;
        amount_raw?: number;
        outstanding_balance?: number;
        status: string;
        status_label: string;
        phone?: string;
    }>;
    recent_properties?: Array<{
        id: number;
        name: string;
        property_type: string;
        property_type_label: string;
        address: string;
        units_count: number;
        occupied_count: number;
        vacant_count: number;
        occupancy_rate: number;
    }>;
    recent_activities?: Array<{
        id: string;
        type: string;
        title: string;
        subtitle: string;
        time_ago: string;
        timestamp: number;
    }>;
    whatsapp_summary?: {
        total: number;
        sent: number;
        delivered: number;
        pending: number;
        failed: number;
    };
    subscription_usage?: {
        units_used: number;
        units_limit: number;
        percentage: number;
        plan_name: string;
        days_remaining: number;
    };
    renters?: Array<{
        id: number;
        name: string;
        phone: string;
        active_lease?: {
            id: number;
            unit_name?: string;
            rent_amount: number;
        } | null;
    }>;
    footer?: {
        copyright: string;
        last_updated: string;
    };
}

const props = defineProps<Props>();

// Mobile drawer state
const mobileSidebarOpen = ref(false);

// Real Modals
const newActionOpen = ref(false);
const recordPaymentOpen = ref(false);
const paymentInitialRenterId = ref<number | undefined>();
const paymentInitialAmount = ref<number | undefined>();
const paymentInitialRentChargeId = ref<number | undefined>();

function handleRecordPayment(dueItem?: any) {
    if (dueItem) {
        paymentInitialRentChargeId.value = dueItem.id;
        paymentInitialAmount.value = dueItem.amount_raw || parseFloat(String(dueItem.amount || '').replace(/[^0-9.]/g, '') || '0');
        const matched = props.renters?.find(r => r.name === dueItem.tenant || r.id === dueItem.renter_id);
        paymentInitialRenterId.value = matched?.id;
    } else {
        paymentInitialRentChargeId.value = undefined;
        paymentInitialAmount.value = undefined;
        paymentInitialRenterId.value = undefined;
    }
    recordPaymentOpen.value = true;
}

function handleSendWhatsApp(dueItem: any) {
    if (!dueItem?.phone) return;
    let phone = dueItem.phone.replace(/[^0-9]/g, '');
    if (phone.startsWith('01')) {
        phone = '2' + phone;
    } else if (!phone.startsWith('20') && phone.startsWith('1')) {
        phone = '20' + phone;
    }
    const unitLabel = dueItem.unit || dueItem.desc || 'الوحدة';
    const amountLabel = dueItem.amount ? `بمبلغ ${dueItem.amount}` : '';
    const msg = encodeURIComponent(`السلام عليكم ورحمة الله وبركاته يا فندم، نذكّركم باستحقاق إيجار (${unitLabel}) ${amountLabel}. شاكرين حسن تعاونكم.`);
    window.open(`https://wa.me/${phone}?text=${msg}`, '_blank');
}

function handleAttentionAction(item: any) {
    if (item.type === 'overdue') {
        if (item.phone) {
            handleSendWhatsApp(item);
        } else {
            handleRecordPayment(item);
        }
    } else if (item.type === 'lease_expiring') {
        router.visit(route('leases.index'));
    } else if (item.type === 'maintenance') {
        router.visit(route('maintenance.index'));
    } else {
        router.visit(route('dashboard'));
    }
}
</script>

<template>
    <div class="min-h-screen bg-[#F3F7FA] text-[#334155] font-sans selection:bg-[#0E6E7E] selection:text-white flex" dir="rtl">
        <Head title="الرئيسية — دار لإدارة العقارات" />

        <!-- الشريط الجانبي الرئيسي (AppSidebar) -->
        <AppSidebar
            :workspace-name="workspace?.name"
            :all-workspaces-label="workspace?.all_workspaces_label"
            :user-name="user?.name"
            :user-role="user?.role"
            :plan-name="workspace?.plan_name"
            :trial-days-remaining="workspace?.trial_days_remaining"
            :units-used="workspace?.units_used"
            :units-limit="workspace?.units_limit"
            :mobile-open="mobileSidebarOpen"
            :active-tab="'dashboard'"
            @close-mobile="mobileSidebarOpen = false"
            @new-action="newActionOpen = true"
        />

        <!-- منطقة العمل الرئيسية -->
        <div class="flex-1 flex flex-col min-w-0 min-h-screen">
            <!-- شريط الأدوات العلوي الموحد (AppNavbar) -->
            <AppNavbar
                @toggle-mobile="mobileSidebarOpen = !mobileSidebarOpen"
                @new-action="newActionOpen = true"
            />

            <!-- محتوى لوحة التحكم (Dashboard Content) -->
            <main class="flex-1 p-4 sm:p-6 lg:p-8 space-y-6 max-w-[1280px] w-full mx-auto">
                <!-- قسم الترحيب الرئيسي (Welcome Hero Section) -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-[#E8EFF4]">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-black text-[#0F2747] tracking-tight">
                            {{ header?.greeting || 'مرحباً، أهلاً بك في دار 👋' }}
                        </h1>
                        <p class="text-xs sm:text-sm text-[#5B6770] font-medium mt-1">
                            {{ header?.subtitle }}
                        </p>
                    </div>

                    <!-- الأزرار الرئيسية في الهيدر -->
                    <div class="flex items-center gap-2.5 shrink-0 self-start sm:self-auto">
                        <!-- زر إضافة عقار جديد الأساسي -->
                        <Link
                            :href="route('properties.index', { new: 1 })"
                            class="h-11 px-5 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-sm font-bold transition shadow-xs flex items-center justify-center gap-2"
                        >
                            <Plus class="h-4 w-4 stroke-[2.5]" />
                            <span>إضافة عقار جديد</span>
                        </Link>

                        <!-- زر تسجيل دفعة السريع -->
                        <button
                            @click="handleRecordPayment()"
                            type="button"
                            class="h-11 px-4 rounded-xl border border-[#D6E0E7] bg-white hover:bg-[#F8FAFC] text-[#0F2747] text-sm font-semibold transition shadow-2xs flex items-center justify-center gap-2"
                        >
                            <CreditCard class="h-4 w-4 text-[#0E6E7E]" />
                            <span>تسجيل دفعة</span>
                        </button>
                    </div>
                </div>

                <!-- شريط الإجراءات السريعة (Quick Actions Bar) -->
                <QuickActionsBar
                    @record-payment="handleRecordPayment()"
                />

                <!-- حالة مساحة العمل الجديدة والفارغة (First-time empty workspace) -->
                <EmptyWorkspaceOnboarding
                    v-if="is_empty_workspace"
                    :user-name="user?.name"
                />

                <!-- العرض الكامل للوحة التحكم عند وجود عقارات ووحدات -->
                <template v-else>
                    <!-- المؤشرات الأربعة الرئيسية (KPI Cards) -->
                    <PrimaryKpiRow :kpis="kpis" />

                    <!-- شريط تنبيه المتأخرات إن وُجدت مبالغ متأخرة -->
                    <div
                        v-if="kpis?.overdue && (kpis.overdue.count || 0) > 0"
                        class="p-4 rounded-2xl bg-rose-50/80 border border-rose-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-rose-900"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center shrink-0">
                                <AlertCircle class="h-5 w-5" />
                            </div>
                            <div>
                                <span class="text-xs font-bold block">تنبيه مستحقات متأخرة</span>
                                <span class="text-xs text-rose-800 font-medium">
                                    لديك {{ kpis.overdue.count }} دفعات إيجار متأخرة بإجمالي مطلوب {{ kpis.overdue.value }}.
                                </span>
                            </div>
                        </div>

                        <Link
                            :href="route('payments.index')"
                            class="px-3.5 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition shadow-xs shrink-0 self-start sm:self-auto"
                        >
                            مراجعة المتأخرات
                        </Link>
                    </div>

                    <!-- الصف الأول: رسم الإيرادات الشهرية (8 أعمدة) + ملخص الإشغال (4 أعمدة) -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
                        <!-- رسم الإيرادات الإيجارية الشهرية خلال 6 أشهر -->
                        <div class="lg:col-span-8 flex flex-col">
                            <RentalIncomeChart
                                :months="income_chart?.months"
                                :amounts="income_chart?.amounts"
                                :history="income_chart?.history"
                                :has-history="income_chart?.has_history"
                                :total-collected="income_chart?.total_collected"
                            />
                        </div>

                        <!-- كارت ملخص وتوزيع إشغال الوحدات -->
                        <div class="lg:col-span-4 flex flex-col">
                            <OccupancySummaryCard
                                :summary="occupancy_summary"
                            />
                        </div>
                    </div>

                    <!-- الصف الثاني: جدول الاستحقاقات القادمة (8 أعمدة) + كارت تحتاج انتباهك (4 أعمدة) -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
                        <!-- جدول الاستحقاقات القادمة مع زر تسجيل الدفعة السريع وتذكير واتساب -->
                        <div class="lg:col-span-8 flex flex-col">
                            <UpcomingCollectionsTable
                                :items="upcoming_dues"
                                @record-payment="handleRecordPayment"
                                @send-whats-app="handleSendWhatsApp"
                            />
                        </div>

                        <!-- كارت التنبيهات التي تحتاج انتباه المالك مع الحالة الإيجابية -->
                        <div class="lg:col-span-4 flex flex-col">
                            <AttentionRequiredCard
                                :items="attention_items"
                                @view-all="router.visit(route('payments.index'))"
                                @action="handleAttentionAction"
                            />
                        </div>
                    </div>

                    <!-- قسم أحدث العقارات المسجلة -->
                    <RecentPropertiesGrid
                        :properties="recent_properties"
                    />

                    <!-- الصف الثالث: سجل الأنشطة الأخيرة (6 أعمدة) + ملخص رسائل واتساب (6 أعمدة) -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
                        <!-- سجل النشاط الإداري والمالي الأخير -->
                        <div class="lg:col-span-6 flex flex-col">
                            <RecentActivityFeed
                                :activities="recent_activities"
                            />
                        </div>

                        <!-- ملخص رسائل وتذكيرات واتساب -->
                        <div class="lg:col-span-6 flex flex-col">
                            <WhatsAppSummaryCard
                                :summary="whatsapp_summary"
                            />
                        </div>
                    </div>
                </template>

                <!-- تذييل الصفحة الموحد (AppFooter) -->
                <AppFooter
                    :copyright="footer?.copyright"
                    :last-updated="footer?.last_updated"
                />
            </main>
        </div>

        <!-- خلفية داكنة للموبايل عند فتح الشريط الجانبي -->
        <div
            v-if="mobileSidebarOpen"
            @click="mobileSidebarOpen = false"
            class="lg:hidden fixed inset-0 bg-[#0F2747]/60 backdrop-blur-xs z-40 transition-opacity"
        ></div>

        <!-- النوافذ المنبثقة العامة الحقيقية (Real Modals) -->
        <ModalNewAction
            :show="newActionOpen"
            @close="newActionOpen = false"
            @record-payment="() => { newActionOpen = false; handleRecordPayment(); }"
        />

        <ModalRecordPayment
            :show="recordPaymentOpen"
            :renters="renters"
            :initial-renter-id="paymentInitialRenterId"
            :initial-amount="paymentInitialAmount"
            :initial-rent-charge-id="paymentInitialRentChargeId"
            @close="recordPaymentOpen = false"
        />
    </div>
</template>
