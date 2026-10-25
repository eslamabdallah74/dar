<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import AppSidebar from '@/Components/Layout/AppSidebar.vue';
import AppNavbar from '@/Components/Layout/AppNavbar.vue';
import AppFooter from '@/Components/Layout/AppFooter.vue';
import ModalNewAction from '@/Components/Common/ModalNewAction.vue';
import ModalRecordPayment from '@/Components/Common/ModalRecordPayment.vue';
import GlobalToast from '@/Components/Common/GlobalToast.vue';
import { CheckCircle2, AlertCircle, AlertOctagon, X } from 'lucide-vue-next';

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
        unitsLimit?: number | null;
    }>(),
    {
        title: 'Nook Mate — Find Your Spot. Rent the Space You Need.',
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
const pageErrors = computed(() => {
    const errs = (page.props.errors as any) || {};
    return Object.keys(errs).length > 0 ? errs : null;
});
const showErrors = ref(true);

watch(
    () => page.props.errors,
    (newErrors) => {
        if (newErrors && Object.keys(newErrors).length > 0) {
            showErrors.value = true;
        }
    },
    { deep: true, immediate: true }
);

watch(
    () => [flash.value.success, flash.value.error],
    ([newSuccess, newError]) => {
        if (newSuccess || newError) {
            showFlash.value = true;
        }
    }
);

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
    <div class="min-h-screen bg-[#F8F4EA] text-[#163E3E] flex font-sans antialiased select-auto" dir="rtl">
        <Head :title="title" />

        <!-- 1. الشريط الجانبي الرئيسي (App Sidebar) -->
        <AppSidebar
            :user-name="user?.name || (adminMode ? 'مدير النظام' : 'أحمد حسن')"
            :user-role="adminMode ? 'مدير النظام المركزي' : (user?.role || 'مالك الحساب')"
            :plan-name="planName || 'باقة الانطلاق (Starter)'"
            :units-used="unitsUsed ?? 0"
            :units-limit="unitsLimit"
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
            class="fixed inset-0 bg-[#163E3E]/60 z-40 lg:hidden backdrop-blur-xs"
        ></div>

        <!-- 2. منطقة العمل الرئيسية (Main Workspace Content) -->
        <div class="flex-1 flex flex-col min-w-0 min-h-screen bg-[#F8F4EA]">
            <!-- شريط الأدوات العلوي الموحد (Global Navbar) -->
            <AppNavbar
                :admin-mode="adminMode"
                @toggle-mobile="mobileOpen = !mobileOpen"
                @new-action="handleNewAction"
                @search="(query) => emit('search', query)"
            />

            <!-- تنبيه الأخطاء الصادرة من النظام والتحقق الرقابي (Validation & System Errors) -->
            <div v-if="pageErrors && showErrors" class="max-w-[1440px] w-full mx-auto px-4 sm:px-8 pt-4">
                <div
                    class="p-4 sm:p-5 rounded-2xl bg-[#FEF2F2] border-2 border-rose-400 text-rose-950 shadow-lg flex items-start justify-between gap-3.5 border-r-8 border-r-rose-600"
                    style="background-color: #FEF2F2;"
                >
                    <div class="flex items-start gap-3.5 flex-1 min-w-0">
                        <div
                            class="p-2.5 rounded-xl bg-rose-100 text-rose-700 border border-rose-300 shrink-0 mt-0.5"
                            style="background-color: #FFE4E6;"
                        >
                            <AlertOctagon class="w-6 h-6 text-rose-600" />
                        </div>
                        <div class="space-y-1.5 flex-1 min-w-0">
                            <h4 class="text-sm font-bold text-rose-950">
                                تعذر إتمام العملية لوجود ملاحظات رقابية / بيانات غير مكتملة:
                            </h4>
                            <ul class="space-y-1.5 text-xs sm:text-sm text-rose-900 font-semibold">
                                <li v-for="(errMsg, errField) in pageErrors" :key="errField" class="flex items-start gap-2 leading-relaxed">
                                    <span class="text-rose-600 font-bold shrink-0">•</span>
                                    <span>{{ errMsg }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <button
                        @click="showErrors = false"
                        type="button"
                        class="text-rose-600 hover:text-rose-950 hover:bg-rose-200 p-1.5 rounded-lg transition-colors cursor-pointer shrink-0"
                        aria-label="إغلاق التنبيه"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>
            </div>

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
                            <span class="text-[#163E3E]">{{ breadcrumb }}</span>
                        </div>
                        <h1 class="text-2xl sm:text-[28px] font-bold text-[#163E3E] tracking-tight">
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

        <!-- نظام الإشعارات والتنبيهات الشامل (Global Toast Center) -->
        <GlobalToast />
    </div>
</template>
