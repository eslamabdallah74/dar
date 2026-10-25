<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    BarChart3,
    Building2,
    CheckCircle2,
    Coins,
    CreditCard,
    FileText,
    HelpCircle,
    Home,
    Layers,
    LayoutDashboard,
    LogOut,
    MessageSquare,
    Receipt,
    Settings,
    Shield,
    ExternalLink,
    User,
    Users,
    Wrench,
    X,
} from 'lucide-vue-next';

interface NavItem {
    key: string;
    name: string;
    icon: any;
    routeName?: string;
    badge?: string;
}

const props = defineProps<{
    userName?: string;
    userRole?: string;
    planName?: string;
    unitsUsed?: number;
    unitsLimit?: number;
    mobileOpen?: boolean;
    activeTab?: string;
    adminMode?: boolean;
    customNavLinks?: NavItem[];
}>();

const emit = defineEmits<{
    (e: 'closeMobile'): void;
    (e: 'newAction'): void;
    (e: 'selectTab', tab: string): void;
}>();

// روابط تطبيق المستأجرين الافتراضية
const defaultTenantNavLinks: NavItem[] = [
    { key: 'dashboard', name: 'الرئيسية', icon: LayoutDashboard, routeName: 'dashboard' },
    { key: 'properties', name: 'العقارات والوحدات', icon: Building2, routeName: 'properties.index' },
    { key: 'renters', name: 'المستأجرون', icon: Users, routeName: 'renters.index' },
    { key: 'leases', name: 'العقود', icon: FileText, routeName: 'leases.index' },
    { key: 'payments', name: 'المدفوعات', icon: CreditCard, routeName: 'payments.index' },
    { key: 'expenses', name: 'المصروفات', icon: Coins, routeName: 'expenses.index' },
    { key: 'reports', name: 'التقارير المالية', icon: BarChart3, routeName: 'reports.index' },
    { key: 'maintenance', name: 'الصيانة', icon: Wrench, routeName: 'maintenance.index' },
    { key: 'whatsapp', name: 'رسائل واتساب', icon: MessageSquare, routeName: 'whatsapp.index', badge: 'تلقائي' },
    { key: 'subscription', name: 'الاشتراك', icon: Layers, routeName: 'subscription.index' },
    { key: 'settings', name: 'الإعدادات', icon: Settings, routeName: 'settings.index' },
];

// روابط الإدارة المركزية الافتراضية
const defaultAdminNavLinks: NavItem[] = [
    { key: 'overview', name: 'نظرة عامة', icon: LayoutDashboard },
    { key: 'workspaces', name: 'مساحات العمل', icon: Building2 },
    { key: 'users', name: 'المستخدمون', icon: Users },
    { key: 'properties', name: 'العقارات والوحدات', icon: Home },
    { key: 'leases', name: 'العقود والمستأجرين', icon: FileText },
    { key: 'financials', name: 'المالية والمدفوعات', icon: CreditCard },
    { key: 'maintenance', name: 'طلبات الصيانة', icon: Wrench },
    { key: 'subscriptions', name: 'الباقات والاشتراكات', icon: Layers },
    { key: 'logs', name: 'رسائل واتساب والسجلات', icon: MessageSquare },
];

const navLinks = computed(() => {
    if (props.customNavLinks && props.customNavLinks.length > 0) {
        return props.customNavLinks;
    }
    return props.adminMode ? defaultAdminNavLinks : defaultTenantNavLinks;
});

const isLinkActive = (linkKey: string, routeName?: string) => {
    if (props.activeTab) {
        return props.activeTab === linkKey;
    }
    if (routeName) {
        try {
            // @ts-ignore
            return route().current(routeName) || route().current(routeName.replace('.index', '') + '.*');
        } catch {
            return linkKey === 'dashboard';
        }
    }
    return linkKey === 'overview';
};

function handleNavClick(link: NavItem) {
    emit('selectTab', link.key);
    emit('closeMobile');
}

const userMenuOpen = ref(false);
const unitUsagePercentage = computed(() => {
    const unitsLimit = props.unitsLimit ?? 5;

    return unitsLimit > 0
        ? Math.min(100, Math.round(((props.unitsUsed ?? 0) / unitsLimit) * 100))
        : 0;
});

const closeUserMenu = () => {
    userMenuOpen.value = false;
};

onMounted(() => {
    window.addEventListener('click', closeUserMenu);
});

onUnmounted(() => {
    window.removeEventListener('click', closeUserMenu);
});
</script>

<template>
    <aside
        class="w-[230px] bg-[#0F2747] text-white p-6 flex flex-col justify-between shrink-0 h-screen select-none transition-all duration-300 z-50 lg:z-30 overflow-y-auto custom-scrollbar"
        :class="[
            'fixed inset-y-0 right-0 lg:sticky lg:top-0',
            mobileOpen ? 'translate-x-0 shadow-2xl' : 'translate-x-full lg:translate-x-0'
        ]"
    >
        <!-- الجزء العلوي: الشعار + الروابط -->
        <div class="space-y-6">
            <!-- الشعار وهوية دار (Logo) -->
            <div class="flex items-center justify-between">
                <Link :href="adminMode ? route('admin.dashboard') : route('dashboard')" class="flex items-center gap-3 group">
                    <div class="w-12 h-12 rounded-full bg-white flex items-center justify-center p-1.5 shadow-md ring-2 ring-white/20 group-hover:scale-105 group-hover:ring-white/40 transition-all shrink-0">
                        <img
                            src="/images/logo.png"
                            alt="شعار دار"
                            class="w-full h-full object-contain"
                        />
                    </div>
                    <!-- اسم دار وشعار المنصة -->
                    <div class="flex flex-col text-right">
                        <div class="flex items-center gap-1.5">
                            <span class="text-2xl font-black text-white tracking-tight leading-none">دار</span>
                            <span
                                v-if="adminMode"
                                class="px-1.5 py-0.5 rounded text-[9px] font-black bg-amber-400 text-slate-950 uppercase tracking-wider"
                            >
                                إشراف
                            </span>
                        </div>
                        <span class="text-[10px] text-white/70 font-medium mt-1">
                            {{ adminMode ? 'لوحة الإدارة المركزية' : 'إدارة وتأجير العقارات' }}
                        </span>
                    </div>
                </Link>

                <!-- زر إغلاق للموبايل فقط -->
                <button
                    @click="emit('closeMobile')"
                    type="button"
                    class="lg:hidden p-1.5 rounded-lg text-white/70 hover:text-white hover:bg-white/10"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- شارة الإدارة -->
            <div
                v-if="adminMode"
                class="w-[182px] rounded-xl bg-[#17334F] p-3 border border-amber-500/20 text-right space-y-1"
            >
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-amber-300">منظومة الإدارة</span>
                    <Shield class="w-3.5 h-3.5 text-amber-300" />
                </div>
                <p class="text-[11px] text-[#CDDBE7] font-medium">تحكم شامل في كافة البيانات</p>
            </div>

            <!-- قائمة الروابط -->
            <nav class="space-y-1">
                <template v-for="link in navLinks" :key="link.key">
                    <Link
                        v-if="link.routeName"
                        :href="route(link.routeName)"
                        prefetch
                        class="h-[42px] px-3.5 rounded-lg flex items-center justify-between text-sm transition-all group font-medium"
                        :class="isLinkActive(link.key, link.routeName) 
                            ? 'bg-[#0E6E7E] text-white font-semibold shadow-xs' 
                            : 'text-[#CDDBE7] hover:bg-[#17334F] hover:text-white'"
                    >
                        <div class="flex items-center gap-3">
                            <component
                                :is="link.icon"
                                class="h-[18px] w-[18px] shrink-0"
                                :class="isLinkActive(link.key, link.routeName) ? 'text-white' : 'text-[#CDDBE7] group-hover:text-white'"
                            />
                            <span class="text-xs font-semibold">{{ link.name }}</span>
                        </div>

                        <span
                            v-if="link.badge"
                            class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-400/20"
                        >
                            {{ link.badge }}
                        </span>
                    </Link>

                    <button
                        v-else
                        type="button"
                        @click="handleNavClick(link)"
                        class="w-full h-[42px] px-3.5 rounded-lg flex items-center justify-between text-sm transition-all group font-medium text-right cursor-pointer"
                        :class="isLinkActive(link.key) 
                            ? 'bg-[#0E6E7E] text-white font-semibold shadow-xs' 
                            : 'text-[#CDDBE7] hover:bg-[#17334F] hover:text-white'"
                    >
                        <div class="flex items-center gap-3">
                            <component
                                :is="link.icon"
                                class="h-[18px] w-[18px] shrink-0"
                                :class="isLinkActive(link.key) ? 'text-white' : 'text-[#CDDBE7] group-hover:text-white'"
                            />
                            <span class="text-xs font-semibold">{{ link.name }}</span>
                        </div>

                        <span
                            v-if="link.badge"
                            class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-400/20"
                        >
                            {{ link.badge }}
                        </span>
                    </button>
                </template>
            </nav>
        </div>

        <!-- الجزء السفلي: ملخص الخطة أو انتقال للتطبيق + حساب المستخدم -->
        <div class="space-y-4 pt-6 border-t border-white/10">
            <!-- كارت التطبيق أو الخطة -->
            <div v-if="!adminMode" class="w-[182px] rounded-xl bg-[#17334F] p-3.5 border border-white/5 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-white">
                        {{ planName || 'باقة الانطلاق (Starter)' }}
                    </span>
                </div>
                <div class="flex items-center justify-between text-[11px] text-[#CDDBE7]">
                    <span>الوحدات المستخدمة</span>
                    <span>{{ unitsUsed ?? 0 }} / {{ unitsLimit ?? 5 }}</span>
                </div>
                <div class="w-full h-1 bg-white/10 rounded-full overflow-hidden">
                    <div
                        class="h-full bg-[#0E6E7E] rounded-full"
                        :style="{ width: `${unitUsagePercentage}%` }"
                    ></div>
                </div>
            </div>

            <!-- رابط انتقال لتطبيق المستأجرين في وضع الأدمن -->
            <div v-else class="w-[182px] rounded-xl bg-[#17334F] p-3 border border-white/5 space-y-2">
                <div class="text-[11px] font-medium text-[#CDDBE7]">التحويل السريع:</div>
                <Link
                    :href="route('dashboard')"
                    class="flex items-center justify-between text-xs font-bold text-white bg-white/10 hover:bg-white/20 p-2 rounded-lg transition"
                >
                    <span>تطبيق العملاء</span>
                    <ExternalLink class="w-3.5 h-3.5 text-amber-300" />
                </Link>
            </div>

            <!-- بطاقة المستخدم الشخصية مع قائمة منسدلة (User Account) -->
            <div class="relative">
                <button
                    @click.stop="userMenuOpen = !userMenuOpen"
                    type="button"
                    class="w-full flex items-center justify-between p-2 rounded-xl hover:bg-[#17334F] transition text-right group cursor-pointer"
                >
                    <div class="flex items-center gap-2.5">
                        <div
                            class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-xs"
                            :class="adminMode ? 'bg-amber-400 text-slate-950 font-black' : 'bg-[#0E6E7E] text-white'"
                        >
                            {{ userName?.charAt(0) || 'أ' }}
                        </div>
                        <div class="flex flex-col text-right">
                            <span class="text-xs font-semibold text-white truncate max-w-[105px] leading-tight">
                                {{ userName || 'مدير النظام' }}
                            </span>
                            <span class="text-[10px] text-[#CDDBE7] leading-tight">
                                {{ userRole || (adminMode ? 'مدير النظام' : 'مالك الحساب') }}
                            </span>
                        </div>
                    </div>
                    <span class="text-xs text-[#CDDBE7] group-hover:text-white transition">⌄</span>
                </button>

                <!-- نافذة خيارات المستخدم -->
                <div
                    v-if="userMenuOpen"
                    class="absolute bottom-12 right-0 w-48 rounded-xl bg-[#17334F] border border-white/10 py-1.5 shadow-2xl z-50 text-right animate-in fade-in zoom-in-95"
                >
                    <Link
                        v-if="!adminMode"
                        :href="route('profile.edit')"
                        class="flex items-center gap-2 px-3 py-2 text-xs font-medium text-[#CDDBE7] hover:bg-white/5 hover:text-white transition"
                    >
                        <User class="h-4 w-4" />
                        <span>الملف الشخصي</span>
                    </Link>

                    <Link
                        v-if="adminMode"
                        :href="route('dashboard')"
                        class="flex items-center gap-2 px-3 py-2 text-xs font-medium text-emerald-300 hover:bg-white/5 transition"
                    >
                        <ExternalLink class="h-4 w-4" />
                        <span>الانتقال للتطبيق</span>
                    </Link>

                    <div class="my-1 border-t border-white/10"></div>
                    <Link
                        :href="adminMode ? route('admin.logout') : route('logout')"
                        method="post"
                        as="button"
                        class="w-full flex items-center gap-2 px-3 py-2 text-xs font-medium text-rose-400 hover:bg-rose-500/10 transition text-right cursor-pointer"
                    >
                        <LogOut class="h-4 w-4" />
                        <span>تسجيل الخروج</span>
                    </Link>
                </div>
            </div>
        </div>
    </aside>
</template>
