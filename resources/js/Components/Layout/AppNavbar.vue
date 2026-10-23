<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import {
    Bell,
    Building2,
    Check,
    ChevronDown,
    Clock,
    CreditCard,
    FileText,
    LogOut,
    Menu,
    Plus,
    Search,
    ShieldAlert,
    User as UserIcon,
    Wrench,
} from 'lucide-vue-next';

const props = defineProps<{
    searchQuery?: string;
}>();

const emit = defineEmits<{
    (e: 'toggleMobile'): void;
    (e: 'newAction'): void;
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
    router.post(route('logout'));
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
    <header class="h-20 bg-white border-b border-[#D6E0E7] px-4 sm:px-8 flex items-center justify-between sticky top-0 z-40 shadow-2xs select-none">
        <!-- الجانب الأيمن (في RTL): زر الموبايل + محدد مساحة العمل + البحث -->
        <div class="flex items-center gap-3 sm:gap-4 flex-1 max-w-[850px]">
            <!-- زر فتح القائمة للموبايل -->
            <button
                @click="emit('toggleMobile')"
                type="button"
                class="lg:hidden p-2 rounded-xl text-[#0F2747] hover:bg-[#F3F7FA] transition border border-[#D6E0E7] cursor-pointer"
                aria-label="القائمة الجانبية"
            >
                <Menu class="h-5 w-5" />
            </button>

            <!-- محدد مساحة العمل الحالية (Workspace Selector Dropdown) -->
            <div class="relative dropdown-container">
                <button
                    @click.stop="workspaceDropdownOpen = !workspaceDropdownOpen; notificationsDropdownOpen = false; userDropdownOpen = false;"
                    type="button"
                    class="h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] hover:bg-white hover:border-[#0E6E7E] text-right flex items-center gap-2.5 transition cursor-pointer shadow-2xs group"
                >
                    <div class="w-6 h-6 rounded-lg bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center shrink-0">
                        <Building2 class="w-3.5 h-3.5" />
                    </div>
                    <div class="flex flex-col text-right leading-tight min-w-[90px] max-w-[140px] truncate">
                        <span class="text-xs font-bold text-[#0F2747] truncate group-hover:text-[#0E6E7E] transition-colors">
                            {{ currentWorkspace.name }}
                        </span>
                        <span class="text-[10px] text-[#5B6770]">مساحة العمل الحالية</span>
                    </div>
                    <ChevronDown class="w-3.5 h-3.5 text-[#5B6770] transition-transform duration-200" :class="{ 'rotate-180': workspaceDropdownOpen }" />
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
                            :class="ws.is_current ? 'bg-[#E3F1F4] text-[#0E6E7E]' : 'hover:bg-[#F3F7FA] text-[#0F2747]'"
                        >
                            <div class="flex items-center gap-2 truncate">
                                <Building2 class="w-3.5 h-3.5 shrink-0" />
                                <span class="truncate">{{ ws.name }}</span>
                            </div>
                            <Check v-if="ws.is_current" class="w-4 h-4 text-[#0E6E7E] shrink-0" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- حقل البحث العام -->
            <div class="relative w-full hidden md:block">
                <input
                    v-model="term"
                    @keydown.enter="handleSearch"
                    type="text"
                    placeholder="ابحث عن عقار، مستأجر أو عقد…"
                    class="w-full h-11 pr-11 pl-4 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] placeholder:text-[#5B6770] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition shadow-2xs font-sans text-right"
                />
                <Search class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-[#5B6770]" />
            </div>
        </div>

        <!-- الجانب الأيسر: التنبيهات + المستخدم + زر جديد -->
        <div class="flex items-center gap-2.5 sm:gap-4 shrink-0 mr-4">
            <!-- قائمة التنبيهات (Notifications Dropdown) -->
            <div class="relative dropdown-container">
                <button
                    @click.stop="notificationsDropdownOpen = !notificationsDropdownOpen; workspaceDropdownOpen = false; userDropdownOpen = false;"
                    type="button"
                    class="p-2.5 rounded-xl text-[#5B6770] hover:text-[#0F2747] hover:bg-[#F3F7FA] transition relative cursor-pointer"
                    title="التنبيهات"
                >
                    <Bell class="h-5 w-5" />
                    <!-- شارة الإشعارات غير المقروءة تظهر فقط إذا كانت > 0 -->
                    <span
                        v-if="notifications.unread_count > 0"
                        class="absolute top-1 right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-rose-600 text-white text-[10px] font-bold flex items-center justify-center ring-2 ring-white animate-pulse"
                    >
                        {{ notifications.unread_count }}
                    </span>
                </button>

                <!-- القائمة المنسدلة للتنبيهات -->
                <div
                    v-if="notificationsDropdownOpen"
                    class="absolute left-0 sm:right-auto sm:left-0 top-13 w-80 rounded-2xl bg-white border border-[#D6E0E7] shadow-xl p-3 z-50 animate-in fade-in zoom-in-95 text-right"
                >
                    <div class="flex items-center justify-between pb-2 border-b border-[#D6E0E7] mb-2">
                        <span class="text-xs font-bold text-[#0F2747]">مركز التنبيهات</span>
                        <span v-if="notifications.unread_count > 0" class="text-[11px] font-semibold text-rose-600 bg-rose-50 px-2 py-0.5 rounded-full">
                            {{ notifications.unread_count }} مهام تتطلب المتابعة
                        </span>
                        <span v-else class="text-[11px] text-emerald-600 font-semibold">
                            لا توجد تنبيهات
                        </span>
                    </div>

                    <!-- قائمة التنبيهات الفعلية -->
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
                    class="h-11 px-2.5 sm:px-3 rounded-xl border border-[#D6E0E7] bg-white hover:border-[#0E6E7E] flex items-center gap-2.5 transition cursor-pointer shadow-2xs group"
                >
                    <!-- الأفاتار بالأحرف الأولى -->
                    <div class="w-7 h-7 rounded-lg bg-[#0F2747] text-white flex items-center justify-center font-bold text-xs shrink-0 group-hover:bg-[#0E6E7E] transition-colors">
                        {{ userInitials }}
                    </div>
                    <div class="hidden sm:flex flex-col text-right leading-tight max-w-[110px] truncate">
                        <span class="text-xs font-bold text-[#0F2747] truncate">{{ user.name }}</span>
                        <span class="text-[10px] text-[#5B6770] truncate">{{ user.role || 'مالك المنشأة' }}</span>
                    </div>
                    <ChevronDown class="w-3.5 h-3.5 text-[#5B6770] transition-transform duration-200" :class="{ 'rotate-180': userDropdownOpen }" />
                </button>

                <!-- القائمة المنسدلة للمستخدم -->
                <div
                    v-if="userDropdownOpen"
                    class="absolute left-0 top-13 w-56 rounded-2xl bg-white border border-[#D6E0E7] shadow-xl p-2 z-50 animate-in fade-in zoom-in-95 text-right"
                >
                    <div class="px-3 py-2 border-b border-[#D6E0E7] mb-1">
                        <div class="text-xs font-bold text-[#0F2747]">{{ user.name }}</div>
                        <div class="text-[11px] text-[#5B6770] truncate">{{ user.email }}</div>
                    </div>

                    <div class="space-y-1 text-xs font-semibold">
                        <Link
                            :href="route('profile.edit')"
                            @click="userDropdownOpen = false"
                            class="w-full px-3 py-2 rounded-xl flex items-center gap-2.5 text-[#0F2747] hover:bg-[#F3F7FA] transition"
                        >
                            <UserIcon class="w-4 h-4 text-[#5B6770]" />
                            <span>الملف الشخصي والحساب</span>
                        </Link>

                        <Link
                            :href="route('settings.index')"
                            @click="userDropdownOpen = false"
                            class="w-full px-3 py-2 rounded-xl flex items-center gap-2.5 text-[#0F2747] hover:bg-[#F3F7FA] transition"
                        >
                            <Building2 class="w-4 h-4 text-[#5B6770]" />
                            <span>إعدادات المحفظة</span>
                        </Link>

                        <button
                            @click="handleLogout"
                            type="button"
                            class="w-full px-3 py-2 rounded-xl flex items-center gap-2.5 text-rose-600 hover:bg-rose-50 transition cursor-pointer border-t border-[#D6E0E7] mt-1 pt-2"
                        >
                            <LogOut class="w-4 h-4" />
                            <span>تسجيل الخروج</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- زر + جديد الرئيسي -->
            <button
                @click="emit('newAction')"
                type="button"
                class="h-11 px-4 sm:px-5 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-sm font-semibold transition shadow-xs flex items-center gap-2 shrink-0 cursor-pointer"
            >
                <Plus class="h-4 w-4 stroke-[2.5]" />
                <span class="hidden sm:inline">جديد</span>
            </button>
        </div>
    </header>
</template>
