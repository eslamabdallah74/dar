<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import LanguageSwitcher from '@/Components/Common/LanguageSwitcher.vue';
import { useLocale } from '@/composables/useLocale';
import {
    BarChart3,
    Building2,
    Coins,
    FileText,
    Home,
    Layers,
    LayoutDashboard,
    LogOut,
    MessageSquare,
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

const { t } = useI18n();
const { isRtl } = useLocale();

// روابط تطبيق المستأجرين الافتراضية
const defaultTenantNavLinks = computed<NavItem[]>(() => [
    { key: 'dashboard', name: t('nav.dashboard'), icon: LayoutDashboard, routeName: 'dashboard' },
    { key: 'properties', name: t('nav.properties'), icon: Building2, routeName: 'properties.index' },
    { key: 'renters', name: t('nav.renters'), icon: Users, routeName: 'renters.index' },
    { key: 'leases', name: t('nav.leases'), icon: FileText, routeName: 'leases.index' },
    { key: 'payments', name: t('nav.payments'), icon: Coins, routeName: 'payments.index' },
    { key: 'expenses', name: t('nav.expenses'), icon: Coins, routeName: 'expenses.index' },
    { key: 'reports', name: t('nav.reports'), icon: BarChart3, routeName: 'reports.index' },
    { key: 'maintenance', name: t('nav.maintenance'), icon: Wrench, routeName: 'maintenance.index' },
    { key: 'whatsapp', name: t('nav.whatsapp'), icon: MessageSquare, routeName: 'whatsapp.index', badge: t('nav.auto_badge') },
    { key: 'subscription', name: t('nav.subscription'), icon: Layers, routeName: 'subscription.index' },
    { key: 'settings', name: t('nav.settings'), icon: Settings, routeName: 'settings.index' },
]);

// روابط الإدارة المركزية الافتراضية
const defaultAdminNavLinks = computed<NavItem[]>(() => [
    { key: 'overview', name: t('nav.admin_overview'), icon: LayoutDashboard },
    { key: 'workspaces', name: t('nav.admin_workspaces'), icon: Building2 },
    { key: 'users', name: t('nav.admin_users'), icon: Users },
    { key: 'properties', name: t('nav.properties'), icon: Home },
    { key: 'leases', name: t('nav.leases'), icon: FileText },
    { key: 'payments', name: t('nav.payments'), icon: Coins },
    { key: 'maintenance', name: t('nav.maintenance'), icon: Wrench },
    { key: 'subscriptions', name: t('nav.admin_subscriptions'), icon: Layers },
    { key: 'logs', name: t('nav.admin_logs'), icon: MessageSquare },
]);

const navLinks = computed(() => {
    if (props.customNavLinks && props.customNavLinks.length > 0) {
        return props.customNavLinks;
    }
    return props.adminMode ? defaultAdminNavLinks.value : defaultTenantNavLinks.value;
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
        class="w-[240px] bg-[#163E3E] text-white p-6 flex flex-col justify-between shrink-0 h-screen select-none transition-all duration-300 z-50 lg:z-30 overflow-y-auto custom-scrollbar"
        :class="[
            'fixed inset-y-0 lg:sticky lg:top-0',
            isRtl ? 'right-0' : 'left-0',
            mobileOpen ? 'translate-x-0 shadow-2xl' : (isRtl ? 'translate-x-full lg:translate-x-0' : '-translate-x-full lg:translate-x-0')
        ]"
    >
        <!-- الجزء العلوي: الشعار + الروابط -->
        <div class="space-y-6">
            <!-- الشعار وهوية Nook Mate (Logo) -->
            <div class="flex items-center justify-between">
                <Link :href="adminMode ? route('admin.dashboard') : route('dashboard')" class="flex items-center gap-3 group">
                    <div class="w-12 h-12 rounded-2xl bg-[#F8F4EA] flex items-center justify-center p-1.5 shadow-md ring-2 ring-white/20 group-hover:scale-105 group-hover:ring-[#F7C33D]/60 transition-all shrink-0">
                        <ApplicationLogo size="sm" alt="Nook Mate" />
                    </div>
                    <!-- اسم Nook Mate وشعار المنصة -->
                    <div class="flex flex-col text-start">
                        <div class="flex items-center gap-1.5">
                            <span dir="ltr" class="whitespace-nowrap text-xl font-black text-white tracking-tight leading-none font-montserrat">Nook <span class="text-[#F7C33D]">Mate</span></span>
                            <span
                                v-if="adminMode"
                                class="px-1.5 py-0.5 rounded text-[9px] font-black bg-[#F7C33D] text-[#163E3E] uppercase tracking-wider"
                            >
                                {{ isRtl ? 'إشراف' : 'Admin' }}
                            </span>
                        </div>
                        <span class="text-[10px] text-[#F8F4EA]/80 font-medium mt-1 font-poppins truncate max-w-[145px]">
                            {{ adminMode ? $t('nav.admin_dashboard') : $t('nav.tagline') }}
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
                class="w-full rounded-xl bg-[#102F2F] p-3 border border-[#F7C33D]/30 text-right space-y-1"
            >
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-[#F7C33D]">منظومة الإدارة</span>
                    <Shield class="w-3.5 h-3.5 text-[#F7C33D]" />
                </div>
                <p class="text-[11px] text-[#F8F4EA]/80 font-medium">تحكم شامل في كافة البيانات</p>
            </div>

            <!-- قائمة الروابط -->
            <nav class="space-y-1">
                <template v-for="link in navLinks" :key="link.key">
                    <Link
                        v-if="link.routeName"
                        :href="route(link.routeName)"
                        prefetch
                        class="h-[42px] px-3.5 rounded-xl flex items-center justify-between text-sm transition-all group font-medium"
                        :class="isLinkActive(link.key, link.routeName) 
                            ? 'bg-[#F7C33D] text-[#163E3E] font-bold shadow-xs' 
                            : 'text-[#F8F4EA]/85 hover:bg-white/10 hover:text-white'"
                    >
                        <div class="flex items-center gap-3">
                            <component
                                :is="link.icon"
                                class="h-[18px] w-[18px] shrink-0"
                                :class="isLinkActive(link.key, link.routeName) ? 'text-[#163E3E]' : 'text-[#F8F4EA]/70 group-hover:text-white'"
                            />
                            <span class="text-xs font-semibold">{{ link.name }}</span>
                        </div>

                        <span
                            v-if="link.badge"
                            class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                            :class="isLinkActive(link.key, link.routeName) ? 'bg-[#163E3E]/20 text-[#163E3E]' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-400/20'"
                        >
                            {{ link.badge }}
                        </span>
                    </Link>

                    <button
                        v-else
                        type="button"
                        @click="handleNavClick(link)"
                        class="w-full h-[42px] px-3.5 rounded-xl flex items-center justify-between text-sm transition-all group font-medium text-right cursor-pointer"
                        :class="isLinkActive(link.key) 
                            ? 'bg-[#F7C33D] text-[#163E3E] font-bold shadow-xs' 
                            : 'text-[#F8F4EA]/85 hover:bg-white/10 hover:text-white'"
                    >
                        <div class="flex items-center gap-3">
                            <component
                                :is="link.icon"
                                class="h-[18px] w-[18px] shrink-0"
                                :class="isLinkActive(link.key) ? 'text-[#163E3E]' : 'text-[#F8F4EA]/70 group-hover:text-white'"
                            />
                            <span class="text-xs font-semibold">{{ link.name }}</span>
                        </div>

                        <span
                            v-if="link.badge"
                            class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                            :class="isLinkActive(link.key) ? 'bg-[#163E3E]/20 text-[#163E3E]' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-400/20'"
                        >
                            {{ link.badge }}
                        </span>
                    </button>
                </template>
            </nav>
        </div>

        <!-- الجزء السفلي: انتقال التطبيق + حساب المستخدم + تبديل اللغة -->
        <div class="space-y-4 pt-6 border-t border-white/10">
            <!-- Language Switcher in sidebar -->
            <LanguageSwitcher variant="sidebar" />

            <!-- رابط انتقال لتطبيق المستأجرين في وضع الأدمن -->
            <div v-if="adminMode" class="w-full rounded-xl bg-[#102F2F] p-3 border border-white/5 space-y-2">
                <div class="text-[11px] font-medium text-[#F8F4EA]/80">{{ $t('nav.switch_to_app') }}:</div>
                <Link
                    :href="route('dashboard')"
                    class="flex items-center justify-between text-xs font-bold text-white bg-white/10 hover:bg-white/20 p-2 rounded-lg transition"
                >
                    <span>{{ $t('nav.switch_to_app') }}</span>
                    <ExternalLink class="w-3.5 h-3.5 text-[#F7C33D]" />
                </Link>
            </div>

            <!-- بطاقة المستخدم الشخصية مع قائمة منسدلة (User Account) -->
            <div class="relative">
                <button
                    @click.stop="userMenuOpen = !userMenuOpen"
                    type="button"
                    class="w-full flex items-center justify-between p-2 rounded-xl hover:bg-white/10 transition text-start group cursor-pointer"
                    :aria-label="$t('nav.profile')"
                >
                    <div class="flex items-center gap-2.5">
                        <div
                            class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-xs"
                            :class="adminMode ? 'bg-[#F7C33D] text-[#163E3E] font-black' : 'bg-[#F7C33D] text-[#163E3E] font-bold'"
                        >
                            {{ userName?.charAt(0) || 'U' }}
                        </div>
                        <div class="flex flex-col text-start">
                            <span class="text-xs font-semibold text-white truncate max-w-[110px] leading-tight">
                                {{ userName || (adminMode ? $t('roles.admin') : $t('roles.owner')) }}
                            </span>
                            <span class="text-[10px] text-[#F8F4EA]/70 leading-tight">
                                {{ userRole || (adminMode ? $t('roles.admin') : $t('roles.owner')) }}
                            </span>
                        </div>
                    </div>
                    <span class="text-xs text-[#F8F4EA]/70 group-hover:text-white transition">⌄</span>
                </button>

                <!-- نافذة خيارات المستخدم -->
                <div
                    v-if="userMenuOpen"
                    class="absolute bottom-12 end-0 w-48 rounded-xl bg-[#102F2F] border border-white/10 py-1.5 shadow-2xl z-50 text-start animate-in fade-in zoom-in-95"
                >
                    <Link
                        v-if="!adminMode"
                        :href="route('profile.edit')"
                        class="flex items-center gap-2 px-3 py-2 text-xs font-medium text-[#F8F4EA]/90 hover:bg-white/10 hover:text-white transition"
                    >
                        <User class="h-4 w-4" />
                        <span>{{ $t('nav.profile') }}</span>
                    </Link>

                    <Link
                        v-if="!adminMode && ($page.props.auth as any)?.user?.is_admin"
                        :href="route('admin.dashboard')"
                        class="flex items-center gap-2 px-3 py-2 text-xs font-semibold text-[#F7C33D] hover:bg-white/10 transition"
                    >
                        <Shield class="h-4 w-4" />
                        <span>{{ $t('nav.admin_dashboard') }}</span>
                    </Link>

                    <Link
                        v-if="adminMode"
                        :href="route('dashboard')"
                        class="flex items-center gap-2 px-3 py-2 text-xs font-medium text-emerald-300 hover:bg-white/10 transition"
                    >
                        <ExternalLink class="h-4 w-4" />
                        <span>{{ $t('nav.switch_to_app') }}</span>
                    </Link>

                    <div class="my-1 border-t border-white/10"></div>
                    <Link
                        :href="adminMode ? route('admin.logout') : route('logout')"
                        method="post"
                        as="button"
                        class="w-full flex items-center gap-2 px-3 py-2 text-xs font-medium text-rose-400 hover:bg-rose-500/10 transition text-start cursor-pointer"
                    >
                        <LogOut class="h-4 w-4" />
                        <span>{{ $t('nav.logout') }}</span>
                    </Link>
                </div>
            </div>
        </div>
    </aside>
</template>
