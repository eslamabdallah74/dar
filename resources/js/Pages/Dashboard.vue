<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import FigmaSidebar from '@/Components/Dashboard/FigmaSidebar.vue';
import FigmaGlobalToolbar from '@/Components/Dashboard/FigmaGlobalToolbar.vue';
import FigmaChecklistCard from '@/Components/Dashboard/FigmaChecklistCard.vue';
import FigmaKpiRow from '@/Components/Dashboard/FigmaKpiRow.vue';
import FigmaOccupancyTrendChart from '@/Components/Dashboard/FigmaOccupancyTrendChart.vue';
import FigmaNeedsAttention from '@/Components/Dashboard/FigmaNeedsAttention.vue';
import FigmaUpcomingDuesTable from '@/Components/Dashboard/FigmaUpcomingDuesTable.vue';
import FigmaOperationalKpis from '@/Components/Dashboard/FigmaOperationalKpis.vue';
import FigmaFooter from '@/Components/Dashboard/FigmaFooter.vue';
import ModalNewAction from '@/Components/Common/ModalNewAction.vue';
import ModalRecordPayment from '@/Components/Common/ModalRecordPayment.vue';
import { CreditCard, FilePlus, Home, Plus, X } from 'lucide-vue-next';

interface Props {
    user: {
        name: string;
        email: string;
        role: string;
    };
    workspace: {
        id: number;
        name: string;
        all_workspaces_label: string;
        currency: string;
        plan_name: string;
        trial_days_remaining: number;
        units_used: number;
        units_limit: number;
    };
    header: {
        greeting: string;
        subtitle: string;
        breadcrumb: string;
    };
    checklist: {
        title: string;
        steps: string[];
        percentage: number;
        action_label: string;
    };
    top_kpis: {
        properties_units: { title: string; value: string; subtitle: string };
        occupancy: { title: string; value: string; subtitle: string };
        collections: { title: string; value: string; subtitle: string };
        expiring: { title: string; value: string; subtitle: string };
    };
    occupancy_trend: {
        title: string;
        subtitle: string;
        current_rate: string;
        badge: string;
        months: string[];
        rates: number[];
    };
    attention_items: Array<{
        id: number;
        type: string;
        title: string;
        desc: string;
        phone?: string;
        amount?: number;
        severity?: string;
    }>;
    upcoming_dues: Array<{
        id: number;
        tenant: string;
        unit: string;
        due_date: string;
        amount: string;
        amount_raw?: number;
        status: string;
        status_label: string;
        phone?: string;
    }>;
    bottom_kpis: {
        maintenance_open: { title: string; value: string; subtitle: string };
        maintenance_expenses: { title: string; value: string; subtitle: string };
        operating_expenses: { title: string; value: string; subtitle: string };
    };
    footer: {
        copyright: string;
        last_updated: string;
    };
    is_empty_workspace?: boolean;
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
}

const props = defineProps<Props>();

// حالة قائمة الموبايل
const mobileSidebarOpen = ref(false);

// نوافذ الإجراءات السريعة الحقيقية
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
    if (!dueItem.phone) return;
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
        <Head title="الرئيسية — دار" />

        <!-- الشريط الجانبي المطابق لفيجما بدقة 100% (Primary navigation - 224px) -->
        <FigmaSidebar
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

        <!-- مساحة العمل الرئيسية (Workspace - 1216px) -->
        <div class="flex-1 flex flex-col min-w-0 min-h-screen">
            <!-- شريط الأدوات العلوي المطابق لفيجما (Global toolbar - 80px) -->
            <FigmaGlobalToolbar
                @toggle-mobile="mobileSidebarOpen = !mobileSidebarOpen"
                @new-action="newActionOpen = true"
            />

            <!-- محتوى الصفحة (Page content - padding 32px, gap 24px) -->
            <main class="flex-1 p-4 sm:p-8 space-y-6 max-w-[1216px] w-full mx-auto">
                <!-- مسار التصفح (Breadcrumb) -->
                <nav class="text-xs text-[#5B6770] font-medium">
                    {{ header?.breadcrumb || 'مساحة العمل / صباح الخير، أحمد' }}
                </nav>

                <!-- رأس الصفحة وزر تسجيل الدفعة (Page heading) -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-1">
                    <div>
                        <h1 class="text-2xl font-bold text-[#0F2747] tracking-tight">
                            {{ header?.greeting || 'صباح الخير، أحمد' }}
                        </h1>
                        <p class="text-[13px] text-[#5B6770] font-medium mt-1">
                            {{ header?.subtitle || 'كل عقاراتك بنظرة واحدة • الخميس، 15 أكتوبر 2026' }}
                        </p>
                    </div>

                    <!-- زر تسجيل دفعة الرئيسي المطابق لفيجما (Button primary default - height 44px) -->
                    <button
                        @click="handleRecordPayment()"
                        type="button"
                        class="h-11 px-5 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-sm font-semibold transition shadow-xs flex items-center justify-center gap-2 shrink-0 self-start sm:self-auto"
                    >
                        <CreditCard class="h-4 w-4" />
                        <span>تسجيل دفعة</span>
                    </button>
                </div>

                <!-- بطاقة استكمال التهيئة (Onboarding Checklist Card) -->
                <FigmaChecklistCard
                    :title="checklist?.title"
                    :steps="checklist?.steps"
                    :percentage="checklist?.percentage"
                    :action-label="checklist?.action_label"
                    @action="router.visit(route('properties.index'))"
                />

                <!-- شبكة المؤشرات الأربعة الرئيسية (Key metrics - 4 columns) -->
                <FigmaKpiRow :kpis="top_kpis" />

                <!-- الصف الأوسط المتطابق لفيجما: اتجاه الإشغال + تحتاج انتباهك (Content columns - gap 24px) -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
                    <!-- العمود الأيمن (8 من 12): اتجاه الإشغال (Occupancy Trend Chart) -->
                    <div class="lg:col-span-8 flex flex-col">
                        <FigmaOccupancyTrendChart
                            :title="occupancy_trend?.title"
                            :subtitle="occupancy_trend?.subtitle"
                            :current-rate="occupancy_trend?.current_rate"
                            :badge="occupancy_trend?.badge"
                            :months="occupancy_trend?.months"
                            :rates="occupancy_trend?.rates"
                        />
                    </div>

                    <!-- العمود الأيسر (4 من 12): تحتاج انتباهك (Needs Attention) -->
                    <div class="lg:col-span-4 flex flex-col">
                        <FigmaNeedsAttention
                            :items="attention_items"
                            @view-all="router.visit(route('payments.index'))"
                            @action="handleAttentionAction"
                        />
                    </div>
                </div>

                <!-- جدول الاستحقاقات القادمة المطابق لفيجما بدقة (Upcoming Due Dates Table) -->
                <FigmaUpcomingDuesTable
                    :items="upcoming_dues"
                    @record-payment="handleRecordPayment"
                    @send-whats-app="handleSendWhatsApp"
                />

                <!-- المؤشرات التشغيلية الثلاثة السفلية (Bottom Operational KPIs - 3 columns) -->
                <FigmaOperationalKpis :kpis="bottom_kpis" />

                <!-- تذييل الصفحة المطابق لفيجما (Page footer) -->
                <FigmaFooter
                    :copyright="footer?.copyright"
                    :last-updated="footer?.last_updated"
                />
            </main>
        </div>

        <!-- خلفية داكنة للموبايل عند فتح السايدبار -->
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
