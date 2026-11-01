<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import {
    Building2,
    Check,
    ChevronDown,
    Clock,
    ExternalLink,
    LogOut,
    Menu,
    Plus,
    Search,
    ShieldAlert,
    ShieldCheck,
    User as UserIcon,
    Wrench,
} from 'lucide-vue-next';

const props = defineProps<{
    searchQuery?: string;
    adminMode?: boolean;
    adminTitle?: string;
}>();

const emit = defineEmits<{
    (e: 'toggleMobile'): void;
    (e: 'newAction'): void;
    (e: 'search', query: string): void;
}>();

const page = usePage();
const term = ref(props.searchQuery || '');

// Dropdown states
const workspaceDropdownOpen = ref(false);
const notificationsDropdownOpen = ref(false);
const userDropdownOpen = ref(false);

const user = computed(() => (page.props.auth as any)?.user ?? { name: 'أحمد حسن', email: 'test@example.com', role: 'مالك المنشأة' });
const currentWorkspace = computed(() => (page.props.auth as any)?.workspace ?? { id: 1, name: 'عقارات النور', currency: 'EGP' });
const workspaces = computed(() => (page.props.auth as any)?.workspaces ?? [
    { id: currentWorkspace.value.id, name: currentWorkspace.value.name, currency: currentWorkspace.value.currency, is_current: true }
]);
const notifications = computed(() => (page.props.auth as any)?.notifications ?? { unread_count: 0, overdue_count: 0, expiring_count: 0, maintenance_count: 0 });

const userInitials = computed(() => {
    const name = user.value?.name || 'أ';
    return name.trim().charAt(0);
});

function handleSearch() {
    if (props.adminMode) {
        emit('search', term.value.trim());
        return;
    }
    if (!term.value.trim()) return;
    router.visit(route('renters.index', { search: term.value.trim() }));
}

function switchWorkspace(workspaceId: number) {
    workspaceDropdownOpen.value = false;
    router.post(route('workspaces.switch'), {
        workspace_id: workspaceId,
    }, {
        preserveScroll: true,
    });
}

function handleLogout() {
    userDropdownOpen.value = false;
    if (props.adminMode) {
        router.post(route('admin.logout'));
    } else {
        router.post(route('logout'));
    }
}

// Close dropdowns on outside click
function closeAllDropdowns(e: MouseEvent) {
    const target = e.target as HTMLElement;
    if (!target.closest('.dropdown-container')) {
        workspaceDropdownOpen.value = false;
        notificationsDropdownOpen.value = false;
        userDropdownOpen.value = false;
    }
}

onMounted(() => {
    window.addEventListener('click', closeAllDropdowns);
});

onUnmounted(() => {
    window.removeEventListener('click', closeAllDropdowns);
});
</script>

<template>
    <header class="sticky top-0 z-40 flex h-16 items-center justify-between gap-2 border-b border-[#D6E0E7] bg-white px-3 shadow-2xs select-none sm:h-20 sm:gap-4 sm:px-8">
        <!-- الجانب الأيمن (في RTL): زر الموبايل + محدد مساحة العمل + البحث -->
        <div class="flex min-w-0 max-w-[850px] flex-1 items-center gap-2 sm:gap-4">
            <!-- زر فتح القائمة للموبايل -->
            <button
                @click="emit('toggleMobile')"
                type="button"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-[#D6E0E7] text-[#163E3E] transition hover:bg-[#F8F4EA] lg:hidden"
                aria-label="القائمة الجانبية"
            >
                <Menu class="h-5 w-5" />
            </button>

            <!-- محدد مساحة العمل الحالية (في وضع العميل) -->
            <div v-if="!adminMode" class="relative dropdown-container">
                <button
                    @click.stop="workspaceDropdownOpen = !workspaceDropdownOpen; notificationsDropdownOpen = false; userDropdownOpen = false;"
                    type="button"
                    class="flex h-10 w-10 shrink-0 items-center justify-center gap-2.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-right shadow-2xs transition hover:border-[#163E3E] hover:bg-white sm:h-11 sm:w-auto sm:justify-start sm:px-3.5"
                    aria-label="تغيير مساحة العمل"
                    :aria-expanded="workspaceDropdownOpen"
                >
                    <div class="w-6 h-6 rounded-lg bg-[#163E3E]/10 text-[#163E3E] flex items-center justify-center shrink-0">
                        <Building2 class="w-3.5 h-3.5" />
                    </div>
                    <div class="hidden min-w-[90px] max-w-[140px] flex-col truncate text-right leading-tight sm:flex">
                        <span class="truncate text-xs font-bold text-[#163E3E] transition-colors">
                            {{ currentWorkspace.name }}
                        </span>
                        <span class="text-[10px] text-[#5B6770]">مساحة العمل الحالية</span>
                    </div>
                    <ChevronDown class="hidden h-3.5 w-3.5 text-[#5B6770] transition-transform duration-200 sm:block" :class="{ 'rotate-180': workspaceDropdownOpen }" />
                </button>

                <!-- قائمة مساحات العمل المنسدلة -->
                <div
                    v-if="workspaceDropdownOpen"
                    class="absolute right-0 top-13 w-64 rounded-2xl bg-white border border-[#D6E0E7] shadow-xl p-2 z-50 animate-in fade-in zoom-in-95"
                >
                    <div class="px-3 py-2 border-b border-[#D6E0E7] text-[11px] font-bold text-[#5B6770]">
                        مساحات العمل المصرح لك بالدخول إليها
                    </div>

                    <div class="py-1 space-y-1 max-h-56 overflow-y-auto">
                        <button
                            v-for="ws in workspaces"
                            :key="ws.id"
                            @click="switchWorkspace(ws.id)"
                            type="button"
                            class="w-full px-3 py-2.5 rounded-xl flex items-center justify-between text-right text-xs font-semibold transition cursor-pointer"
                            :class="ws.is_current ? 'bg-[#163E3E] text-white' : 'hover:bg-[#F8F4EA] text-[#163E3E]'"
                        >
                            <div class="flex items-center gap-2 truncate">
                                <Building2 class="w-3.5 h-3.5 shrink-0" />
                                <span class="truncate">{{ ws.name }}</span>
                            </div>
                            <Check v-if="ws.is_current" class="w-4 h-4 text-[#F7C33D] shrink-0" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- شارة الإدارة المركزية (في وضع الأدمن) -->
            <div v-else class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-[#F7C33D]/40 bg-[#FEF7E6] shadow-2xs sm:h-11 sm:w-auto sm:justify-start sm:gap-2.5 sm:px-3.5">
                <ShieldCheck class="w-5 h-5 text-[#163E3E] shrink-0" />
                <div class="hidden flex-col text-right leading-tight sm:flex">
                    <span class="text-xs font-black text-[#163E3E]">لوحة الإدارة المركزية</span>
                    <span class="hidden xl:block text-[10px] text-[#163E3E]/80 font-medium">سلطة الإشراف والتحكم الكامل</span>
                </div>
            </div>

            <!-- حقل البحث -->
            <div class="relative hidden w-full min-w-0 md:block">
                <input
                    v-model="term"
                    @input="adminMode && emit('search', term)"
                    @keydown.enter="handleSearch"
                    type="text"
                    :placeholder="adminMode ? 'بحث فوري في مساحات العمل، المستخدمين، العقارات...' : 'ابحث عن عقار، مستأجر أو عقد…'"
                    class="w-full h-11 pr-11 pl-4 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] placeholder:text-[#5B6770] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition shadow-2xs font-sans text-right"
                />
                <Search class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-[#5B6770]" />
            </div>
        </div>

        <!-- الجانب الأيسر: التنبيهات + المستخدم + زر جديد / العودة للتطبيق -->
        <div class="flex shrink-0 items-center gap-1.5 sm:gap-4">
            <!-- رابط سريع لتطبيق العملاء في وضع الأدمن -->
            <Link
                v-if="adminMode"
                :href="route('dashboard')"
                class="hidden sm:flex items-center gap-1.5 text-xs font-semibold text-[#163E3E] hover:text-[#102F2F] bg-[#E8F0F0] hover:bg-[#D4EAEF] px-3 py-2 rounded-xl transition"
            >
                <ExternalLink class="w-3.5 h-3.5" />
                <span>تطبيق العملاء</span>
            </Link>

            <!-- قائمة التنبيهات (في وضع العميل) -->
            <div v-if="!adminMode" class="relative dropdown-container">
                <button
                    @click.stop="notificationsDropdownOpen = !notificationsDropdownOpen; workspaceDropdownOpen = false; userDropdownOpen = false;"
                    type="button"
                    class="flex h-10 shrink-0 items-center gap-1.5 rounded-xl border border-transparent px-1.5 text-[#163E3E] transition hover:border-[#D6E0E7] hover:bg-[#F8F4EA] sm:h-11 sm:px-2"
                    aria-label="مركز التنبيهات"
                    :aria-expanded="notificationsDropdownOpen"
                >
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#F8F4EA] ring-1 ring-[#E5DEC9]">
                        <ApplicationLogo size="sm" />
                    </span>
                    <span
                        v-if="notifications.unread_count > 0"
                        class="flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white"
                    >
                        {{ notifications.unread_count }}
                    </span>
                </button>

                <!-- القائمة المنسدلة للتنبيهات -->
                <div
                    v-if="notificationsDropdownOpen"
                    class="fixed inset-x-3 top-16 z-50 w-auto max-w-[calc(100vw-1.5rem)] rounded-2xl border border-[#D6E0E7] bg-white p-3 text-right shadow-xl animate-in fade-in zoom-in-95 sm:absolute sm:inset-x-auto sm:left-0 sm:top-13 sm:w-80"
                >
                    <div class="flex items-center justify-between pb-2 border-b border-[#D6E0E7] mb-2">
                        <span class="text-xs font-bold text-[#163E3E]">مركز التنبيهات</span>
                        <span v-if="notifications.unread_count > 0" class="text-[11px] font-semibold text-rose-600 bg-rose-50 px-2 py-0.5 rounded-full">
                            {{ notifications.unread_count }} مهام تتطلب المتابعة
                        </span>
                        <span v-else class="text-[11px] text-emerald-600 font-semibold">
                            لا توجد تنبيهات
                        </span>
                    </div>

                    <div v-if="notifications.unread_count > 0" class="space-y-1.5 text-xs">
                        <Link
                            v-if="notifications.overdue_count > 0"
                            :href="route('payments.index')"
                            @click="notificationsDropdownOpen = false"
                            class="p-2.5 rounded-xl bg-rose-50/70 hover:bg-rose-100/70 border border-rose-200/80 flex items-start gap-2.5 transition group"
                        >
                            <ShieldAlert class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" />
                            <div class="flex-1">
                                <span class="font-bold text-rose-950 block">متأخرات في سداد الإيجار</span>
                                <span class="text-[11px] text-rose-800">{{ notifications.overdue_count }} فواتير تجاوزت موعد الاستحقاق</span>
                            </div>
                        </Link>

                        <Link
                            v-if="notifications.expiring_count > 0"
                            :href="route('leases.index')"
                            @click="notificationsDropdownOpen = false"
                            class="p-2.5 rounded-xl bg-amber-50/70 hover:bg-amber-100/70 border border-amber-200/80 flex items-start gap-2.5 transition group"
                        >
                            <Clock class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                            <div class="flex-1">
                                <span class="font-bold text-amber-950 block">عقود إيجار تنتهي قريباً</span>
                                <span class="text-[11px] text-amber-800">{{ notifications.expiring_count }} عقود تنتهي خلال 30 يوماً</span>
                            </div>
                        </Link>

                        <Link
                            v-if="notifications.maintenance_count > 0"
                            :href="route('maintenance.index')"
                            @click="notificationsDropdownOpen = false"
                            class="p-2.5 rounded-xl bg-sky-50/70 hover:bg-sky-100/70 border border-sky-200/80 flex items-start gap-2.5 transition group"
                        >
                            <Wrench class="w-4 h-4 text-sky-600 shrink-0 mt-0.5" />
                            <div class="flex-1">
                                <span class="font-bold text-sky-950 block">طلبات صيانة قيد المتابعة</span>
                                <span class="text-[11px] text-sky-800">{{ notifications.maintenance_count }} طلبات صيانة مفتوحة بحاجة لفني</span>
                            </div>
                        </Link>
                    </div>

                    <div v-else class="py-6 text-center text-xs text-[#5B6770]">
                        <Check class="w-6 h-6 text-emerald-500 mx-auto mb-1" />
                        <span>جميع العمليات منتظمة ولا توجد متأخرات أو تنبيهات حالياً.</span>
                    </div>
                </div>
            </div>

            <!-- قائمة المستخدم (User Profile Menu Dropdown) -->
            <div class="relative dropdown-container">
                <button
                    @click.stop="userDropdownOpen = !userDropdownOpen; workspaceDropdownOpen = false; notificationsDropdownOpen = false;"
                    type="button"
                    class="flex h-10 shrink-0 items-center gap-2 rounded-xl border border-[#D6E0E7] bg-white px-2.5 shadow-2xs transition hover:border-[#163E3E] sm:h-11 sm:gap-2.5 sm:px-3"
                    aria-label="قائمة المستخدم"
                    :aria-expanded="userDropdownOpen"
                >
                    <div
                        class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-xs shrink-0 transition-colors"
                        :class="adminMode ? 'bg-[#F7C33D] text-[#163E3E] font-black' : 'bg-[#163E3E] text-[#F7C33D] group-hover:bg-[#102F2F]'"
                    >
                        {{ userInitials }}
                    </div>
                    <div class="hidden sm:flex flex-col text-right leading-tight max-w-[110px] truncate">
                        <span class="text-xs font-bold text-[#163E3E] truncate">{{ user.name }}</span>
                        <span class="text-[10px] text-[#5B6770] truncate">{{ adminMode ? 'مدير النظام المركزي' : (user.role || 'مالك المنشأة') }}</span>
                    </div>
                    <ChevronDown class="w-3.5 h-3.5 text-[#5B6770] transition-transform duration-200" :class="{ 'rotate-180': userDropdownOpen }" />
                </button>

                <!-- القائمة المنسدلة للمستخدم -->
                <div
                    v-if="userDropdownOpen"
                    class="absolute left-0 top-13 w-56 rounded-2xl bg-white border border-[#D6E0E7] shadow-xl p-2 z-50 animate-in fade-in zoom-in-95 text-right"
                >
                    <div class="px-3 py-2 border-b border-[#D6E0E7] mb-1">
                        <div class="text-xs font-bold text-[#163E3E]">{{ user.name }}</div>
                        <div class="text-[11px] text-[#5B6770] truncate">{{ user.email }}</div>
                    </div>

                    <div class="space-y-1 text-xs font-semibold">
                        <Link
                            v-if="!adminMode"
                            :href="route('profile.edit')"
                            @click="userDropdownOpen = false"
                            class="w-full px-3 py-2 rounded-xl flex items-center gap-2.5 text-[#163E3E] hover:bg-[#F8F4EA] transition"
                        >
                            <UserIcon class="w-4 h-4 text-[#5B6770]" />
                            <span>الملف الشخصي والحساب</span>
                        </Link>

                        <Link
                            v-if="!adminMode && (user as any)?.is_admin"
                            :href="route('admin.dashboard')"
                            @click="userDropdownOpen = false"
                            class="w-full px-3 py-2 rounded-xl flex items-center gap-2.5 text-[#163E3E] font-bold hover:bg-[#F8F4EA] transition"
                        >
                            <ShieldCheck class="w-4 h-4 text-[#F7C33D]" />
                            <span>لوحة الإدارة المركزية</span>
                        </Link>

                        <Link
                            v-if="adminMode"
                            :href="route('dashboard')"
                            @click="userDropdownOpen = false"
                            class="w-full px-3 py-2 rounded-xl flex items-center gap-2.5 text-emerald-700 hover:bg-emerald-50 transition"
                        >
                            <ExternalLink class="w-4 h-4" />
                            <span>الانتقال لتطبيق العميل</span>
                        </Link>

                        <button
                            @click="handleLogout"
                            type="button"
                            class="w-full px-3 py-2 rounded-xl flex items-center gap-2.5 text-rose-600 hover:bg-rose-50 transition cursor-pointer border-t border-[#D6E0E7] mt-1 pt-2"
                        >
                            <LogOut class="w-4 h-4" />
                            <span>تسجيل الخروج {{ adminMode ? 'الإداري' : '' }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- زر + جديد (في وضع العميل) أو زر إجراء في وضع الأدمن -->
            <button
                @click="emit('newAction')"
                type="button"
                class="flex h-10 shrink-0 items-center gap-2 rounded-xl bg-[#163E3E] px-3 text-sm font-semibold text-white shadow-xs transition hover:bg-[#102F2F] sm:h-11 sm:px-5"
                :aria-label="adminMode ? 'إضافة بيانات' : 'إجراء جديد'"
            >
                <Plus class="h-4 w-4 stroke-[2.5]" />
                <span class="hidden sm:inline">{{ adminMode ? 'إضافة بيانات' : 'جديد' }}</span>
            </button>
        </div>
    </header>
</template>
