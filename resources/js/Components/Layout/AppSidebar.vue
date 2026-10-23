<script setup lang="ts">
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
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
    User,
    Users,
    Wrench,
    X,
} from 'lucide-vue-next';

const props = defineProps<{
    workspaceName?: string;
    allWorkspacesLabel?: string;
    userName?: string;
    userRole?: string;
    planName?: string;
    trialDaysRemaining?: number;
    unitsUsed?: number;
    unitsLimit?: number;
    mobileOpen?: boolean;
    activeTab?: string;
}>();

const emit = defineEmits<{
    (e: 'closeMobile'): void;
    (e: 'newAction'): void;
}>();

// الروابط المعتمدة بالترتيب الدقيق المطلوب
const navLinks = [
    { key: 'dashboard', name: 'الرئيسية', icon: LayoutDashboard, routeName: 'dashboard' },
    { key: 'properties', name: 'العقارات والوحدات', icon: Building2, routeName: 'properties.index' },
    { key: 'renters', name: 'المستأجرون', icon: Users, routeName: 'renters.index' },
    { key: 'leases', name: 'العقود', icon: FileText, routeName: 'leases.index' },
    { key: 'payments', name: 'المدفوعات', icon: CreditCard, routeName: 'payments.index' },
    { key: 'expenses', name: 'المصروفات', icon: Coins, routeName: 'expenses.index' },
    { key: 'maintenance', name: 'الصيانة', icon: Wrench, routeName: 'maintenance.index' },
    { key: 'whatsapp', name: 'رسائل واتساب', icon: MessageSquare, routeName: 'whatsapp.index', badge: 'تلقائي' },
    { key: 'subscription', name: 'الاشتراك', icon: Layers, routeName: 'subscription.index' },
    { key: 'settings', name: 'الإعدادات', icon: Settings, routeName: 'settings.index' },
];

const isLinkActive = (linkKey: string, routeName: string) => {
    if (props.activeTab) {
        return props.activeTab === linkKey;
    }
    try {
        // @ts-ignore
        return route().current(routeName) || route().current(routeName.replace('.index', '') + '.*');
    } catch {
        return linkKey === 'dashboard';
    }
};

const userMenuOpen = ref(false);
</script>

<template>
    <aside
        class="w-[224px] bg-[#0F2747] text-white p-6 flex flex-col justify-between shrink-0 min-h-screen select-none transition-all duration-300 z-50"
        :class="[
            'lg:relative fixed inset-y-0 right-0',
            mobileOpen ? 'translate-x-0 shadow-2xl' : 'translate-x-full lg:translate-x-0'
        ]"
    >
        <!-- الجزء العلوي: الشعار + مبدل المحفظة + الروابط -->
        <div class="space-y-6">
            <!-- الشعار وهوية دار (Logo) -->
            <div class="flex items-center justify-between">
                <Link :href="route('dashboard')" class="flex items-center gap-3 group">
                    <div class="w-12 h-12 rounded-full bg-white flex items-center justify-center p-1.5 shadow-md ring-2 ring-white/20 group-hover:scale-105 group-hover:ring-white/40 transition-all shrink-0">
                        <img
                            src="/images/logo.png"
                            alt="شعار دار"
                            class="w-full h-full object-contain"
                        />
                    </div>
                    <!-- اسم دار وشعار المنصة -->
                    <div class="flex flex-col text-right">
                        <span class="text-2xl font-black text-white tracking-tight leading-none">دار</span>
                        <span class="text-[10px] text-white/70 font-medium mt-1">إدارة وتأجير العقارات</span>
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

            <!-- مبدل مساحات العمل (Workspace Switcher - 176px x 66px) -->
            <div class="w-[176px] rounded-xl bg-[#17334F] p-3 border border-white/5 cursor-pointer hover:bg-[#1D3E5E] transition group">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-white truncate max-w-[130px]">
                        {{ workspaceName || 'عقارات النور' }}
                    </span>
                    <span class="text-xs text-[#CDDBE7] group-hover:text-white transition">⌄</span>
                </div>
                <span class="text-xs text-[#CDDBE7] font-medium block mt-1">
                    {{ allWorkspacesLabel || 'كل العقارات ⌄' }}
                </span>
            </div>

            <!-- قائمة الروابط العشرة (Navigation Links - height 44px each) -->
            <nav class="space-y-1">
                <Link
                    v-for="link in navLinks"
                    :key="link.key"
                    :href="route(link.routeName)"
                    prefetch
                    class="h-[44px] px-3.5 rounded-lg flex items-center justify-between text-sm transition-all group font-medium"
                    :class="isLinkActive(link.key, link.routeName) 
                        ? 'bg-[#0E6E7E] text-white font-semibold shadow-xs' 
                        : 'text-[#CDDBE7] hover:bg-[#17334F] hover:text-white'"
                >
                    <div class="flex items-center gap-3">
                        <component
                            :is="link.icon"
                            class="h-[20px] w-[20px] shrink-0"
                            :class="isLinkActive(link.key, link.routeName) ? 'text-white' : 'text-[#CDDBE7] group-hover:text-white'"
                        />
                        <span>{{ link.name }}</span>
                    </div>

                    <span
                        v-if="link.badge"
                        class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-400/20"
                    >
                        {{ link.badge }}
                    </span>
                </Link>
            </nav>
        </div>

        <!-- الجزء السفلي: ملخص الخطة + حساب المالك -->
        <div class="space-y-4 pt-6 border-t border-white/10">
            <!-- كارت ملخص الخطة (Plan Summary - 176px) -->
            <div class="w-[176px] rounded-xl bg-[#17334F] p-3.5 border border-white/5 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-white">
                        {{ planName || 'Growth • تجريبية' }}
                    </span>
                </div>
                <p class="text-[11px] text-[#CDDBE7]">
                    متبقي ⁦{{ trialDaysRemaining || 10 }}⁩ أيام للتجربة
                </p>
                <!-- شريط الاستخدام 4px -->
                <div class="w-full h-1 bg-white/10 rounded-full overflow-hidden">
                    <div
                        class="h-full bg-[#0E6E7E] rounded-full"
                        :style="{ width: `${Math.round(((unitsUsed || 18) / (unitsLimit || 24)) * 100)}%` }"
                    ></div>
                </div>
            </div>

            <!-- بطاقة المستخدم الشخصية مع قائمة منسدلة (User Account) -->
            <div class="relative">
                <button
                    @click="userMenuOpen = !userMenuOpen"
                    type="button"
                    class="w-full flex items-center justify-between p-2 rounded-xl hover:bg-[#17334F] transition text-right group"
                >
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-[#0E6E7E] text-white flex items-center justify-center font-bold text-xs">
                            {{ userName?.charAt(0) || 'أ' }}
                        </div>
                        <div class="flex flex-col text-right">
                            <span class="text-xs font-semibold text-white truncate max-w-[100px] leading-tight">
                                {{ userName || 'أحمد حسن' }}
                            </span>
                            <span class="text-[11px] text-[#CDDBE7] leading-tight">
                                {{ userRole || 'مالك الحساب' }}
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
                        :href="route('profile.edit')"
                        class="flex items-center gap-2 px-3 py-2 text-xs font-medium text-[#CDDBE7] hover:bg-white/5 hover:text-white transition"
                    >
                        <User class="h-4 w-4" />
                        <span>الملف الشخصي</span>
                    </Link>
                    <div class="my-1 border-t border-white/10"></div>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="w-full flex items-center gap-2 px-3 py-2 text-xs font-medium text-rose-400 hover:bg-rose-500/10 transition text-right"
                    >
                        <LogOut class="h-4 w-4" />
                        <span>تسجيل الخروج</span>
                    </Link>
                </div>
            </div>
        </div>
    </aside>
</template>
