<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import AppSidebar from '@/Components/Layout/AppSidebar.vue';
import AppNavbar from '@/Components/Layout/AppNavbar.vue';
import AppFooter from '@/Components/Layout/AppFooter.vue';
import ModalNewAction from '@/Components/Common/ModalNewAction.vue';
import ModalRecordPayment from '@/Components/Common/ModalRecordPayment.vue';
import { CheckCircle2, AlertCircle, X } from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        title?: string;
        activeTab?: string;
        headerTitle?: string;
        headerSubtitle?: string;
        breadcrumb?: string;
        adminMode?: boolean;
        customNavLinks?: any[];
        planName?: string;
        unitsUsed?: number;
        unitsLimit?: number;
    }>(),
    {
        title: 'دار — إدارة الأملاك والتأجير',
        adminMode: false,
    }
);

const emit = defineEmits<{
    (e: 'selectTab', tab: string): void;
    (e: 'search', query: string): void;
    (e: 'newAction'): void;
}>();

const page = usePage();
const mobileOpen = ref(false);
const newActionOpen = ref(false);
const recordPaymentOpen = ref(false);
const showFlash = ref(true);

const user = computed(() => (page.props.auth as any)?.user ?? { name: 'أحمد حسن', email: 'test@example.com', role: 'مالك المنشأة' });
const quickRenters = computed(() => (page.props as any)?.quickRenters ?? []);
const flash = computed(() => (page.props.flash as any) ?? {});

const openRecordPaymentModal = () => {
    newActionOpen.value = false;
    recordPaymentOpen.value = true;
};

function handleNewAction() {
    if (props.adminMode) {
        emit('newAction');
    } else {
        newActionOpen.value = true;
    }
}
</script>

<template>
    <div class="min-h-screen bg-[#F3F7FA] text-[#0F2747] flex font-sans antialiased select-auto" dir="rtl">
        <Head :title="title" />

        <!-- 1. الشريط الجانبي الرئيسي (App Sidebar) -->
        <AppSidebar
            :user-name="user?.name || (adminMode ? 'مدير النظام' : 'أحمد حسن')"
            :user-role="adminMode ? 'مدير النظام المركزي' : (user?.role || 'مالك الحساب')"
            :plan-name="planName || 'باقة الانطلاق (Starter)'"
            :units-used="unitsUsed ?? 0"
            :units-limit="unitsLimit ?? 5"
            :mobile-open="mobileOpen"
            :active-tab="activeTab"
            :admin-mode="adminMode"
            :custom-nav-links="customNavLinks"
            @close-mobile="mobileOpen = false"
            @new-action="handleNewAction"
            @select-tab="(tab) => emit('selectTab', tab)"
        />

        <!-- خلفية معتمة للموبايل عند فتح القائمة -->
        <div
            v-if="mobileOpen"
            @click="mobileOpen = false"
            class="fixed inset-0 bg-[#0F2747]/60 z-40 lg:hidden backdrop-blur-xs"
        ></div>

        <!-- 2. منطقة العمل الرئيسية (Main Workspace Content) -->
        <div class="flex-1 flex flex-col min-w-0 min-h-screen bg-[#F3F7FA]">
            <!-- شريط الأدوات العلوي الموحد (Global Navbar) -->
            <AppNavbar
                :admin-mode="adminMode"
                @toggle-mobile="mobileOpen = !mobileOpen"
                @new-action="handleNewAction"
                @search="(query) => emit('search', query)"
            />

            <!-- تنبيهات الفلاش (Flash Notifications) متناسقة مع العرض الموحد -->
            <div v-if="(flash.success || flash.error) && showFlash" class="max-w-[1440px] w-full mx-auto px-4 sm:px-8 pt-4">
                <div
                    v-if="flash.success"
                    class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-semibold flex items-center justify-between shadow-xs animate-in fade-in"
                >
                    <div class="flex items-center gap-2.5">
                        <CheckCircle2 class="w-5 h-5 text-emerald-600 shrink-0" />
                        <span>{{ flash.success }}</span>
                    </div>
                    <button @click="showFlash = false" type="button" class="text-emerald-700 hover:text-emerald-950 p-1 cursor-pointer">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div
                    v-if="flash.error"
                    class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-semibold flex items-center justify-between shadow-xs animate-in fade-in"
                >
                    <div class="flex items-center gap-2.5">
                        <AlertCircle class="w-5 h-5 text-rose-600 shrink-0" />
                        <span>{{ flash.error }}</span>
                    </div>
                    <button @click="showFlash = false" type="button" class="text-rose-700 hover:text-rose-950 p-1 cursor-pointer">
                        <X class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- محتوى الصفحة المعروضة (Page Slot) -->
            <main class="flex-1 px-4 sm:px-8 py-6 sm:py-8 max-w-[1440px] w-full mx-auto space-y-8">
                <!-- رأس الصفحة الاختياري إن وُجد (Page Header) -->
                <div v-if="headerTitle" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2">
                    <div>
                        <div v-if="breadcrumb" class="text-xs font-semibold text-[#5B6770] mb-1.5 flex items-center gap-1.5">
                            <span>{{ adminMode ? 'لوحة الإدارة' : 'الرئيسية' }}</span>
                            <span>/</span>
                            <span class="text-[#0E6E7E]">{{ breadcrumb }}</span>
                        </div>
                        <h1 class="text-2xl sm:text-[28px] font-bold text-[#0F2747] tracking-tight">
                            {{ headerTitle }}
                        </h1>
                        <p v-if="headerSubtitle" class="text-sm text-[#5B6770] mt-1">
                            {{ headerSubtitle }}
                        </p>
                    </div>

                    <!-- مكان أزرار إجراءات رأس الصفحة -->
                    <div id="page-header-actions" class="flex items-center gap-3">
                        <slot name="actions" />
                    </div>
                </div>

                <slot />

                <!-- التذييل الموحد (App Footer) متناسق مع العرض الكامل للصفحة -->
                <AppFooter />
            </main>
        </div>

        <!-- النوافذ المنبثقة العامة (Global Modals) -->
        <ModalNewAction
            v-if="!adminMode"
            :show="newActionOpen"
            @close="newActionOpen = false"
            @record-payment="openRecordPaymentModal"
            @add-property="() => { newActionOpen = false; router.visit(route('properties.create')); }"
        />

        <ModalRecordPayment
            v-if="!adminMode"
            :show="recordPaymentOpen"
            :renters="quickRenters"
            @close="recordPaymentOpen = false"
        />
    </div>
</template>
