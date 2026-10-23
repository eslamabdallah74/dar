<script setup lang="ts">
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Building2,
    ChevronDown,
    Home,
    Key,
    Layers,
    LogOut,
    Menu,
    MessageSquare,
    Plus,
    User,
    Wallet,
    X,
} from 'lucide-vue-next';

defineProps<{
    workspaceName?: string;
    currency?: string;
}>();

const page = usePage();
const mobileMenuOpen = ref(false);
const userDropdownOpen = ref(false);

const navigationTabs = [
    { name: 'لوحة التحكم', href: route('dashboard'), current: true, icon: Home },
    { name: 'العقارات والوحدات', href: '#', current: false, icon: Building2 },
    { name: 'المستأجرين والعقود', href: '#', current: false, icon: Key },
    { name: 'التحصيلات والمالية', href: '#', current: false, icon: Wallet },
    { name: 'تذكيرات الواتساب', href: '#', current: false, icon: MessageSquare },
];
</script>

<template>
    <div class="min-h-screen bg-[#F3F7FA] text-[#334155] font-sans selection:bg-[#0E6E7E] selection:text-white" dir="rtl">
        <!-- الشريط العلوي الرئيسي (Top Navigation Bar) -->
        <header class="sticky top-0 z-40 bg-white border-b border-[#D6E0E7] shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <!-- الجانب الأيمن: الشعار + محدد مساحة العمل -->
                    <div class="flex items-center gap-6">
                        <!-- الشعار -->
                        <Link :href="route('dashboard')" class="flex items-center gap-2.5 group">
                            <div class="w-9 h-9 rounded-xl bg-[#0F2747] flex items-center justify-center text-white font-black text-base shadow-sm group-hover:bg-[#0E6E7E] transition-colors">
                                دار
                            </div>
                            <div class="flex flex-col">
                                <span class="text-xl font-black text-[#0F2747] tracking-tight leading-none">دار</span>
                                <span class="text-[10px] font-semibold text-[#5B6770] mt-0.5">إدارة العقارات والإيجار</span>
                            </div>
                        </Link>

                        <div class="h-6 w-px bg-[#D6E0E7] hidden sm:block"></div>

                        <!-- شارة مساحة العمل الحالية (Workspace Badge) -->
                        <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-lg border border-[#D6E0E7] bg-[#F8FAFC] text-xs font-bold text-[#0F2747]">
                            <Layers class="h-4 w-4 text-[#0E6E7E]" />
                            <span class="max-w-[150px] truncate">{{ workspaceName || 'مساحة العمل' }}</span>
                            <span class="px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 text-[10px] font-mono font-medium border border-emerald-200">
                                {{ currency || 'EGP' }}
                            </span>
                        </div>
                    </div>

                    <!-- روابط التنقل السريعة (Desktop Nav Tabs) -->
                    <nav class="hidden lg:flex items-center gap-1">
                        <Link
                            v-for="item in navigationTabs"
                            :key="item.name"
                            :href="item.href"
                            class="flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-bold transition-all"
                            :class="item.current 
                                ? 'bg-[#E3F1F4] text-[#0E6E7E]' 
                                : 'text-[#5B6770] hover:text-[#0F2747] hover:bg-zinc-100'"
                        >
                            <component :is="item.icon" class="h-4 w-4" />
                            <span>{{ item.name }}</span>
                        </Link>
                    </nav>

                    <!-- الجانب الأيسر: الإجراء السريع + قائمة المستخدم -->
                    <div class="flex items-center gap-3">
                        <!-- شارة الخطة المجانية -->
                        <div class="hidden md:flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-800 text-[11px] font-bold">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span>الباقة المجانية (5 وحدات)</span>
                        </div>

                        <!-- قائمة المستخدم الشخصية -->
                        <div class="relative">
                            <button
                                @click="userDropdownOpen = !userDropdownOpen"
                                type="button"
                                class="flex items-center gap-2 p-1.5 rounded-xl border border-[#D6E0E7] bg-white hover:bg-zinc-50 transition text-right"
                            >
                                <div class="w-8 h-8 rounded-lg bg-[#0F2747] text-white flex items-center justify-center font-bold text-xs">
                                    {{ page.props.auth?.user?.name?.charAt(0) || 'م' }}
                                </div>
                                <div class="hidden sm:flex flex-col text-right">
                                    <span class="text-xs font-bold text-[#0F2747] max-w-[120px] truncate leading-tight">
                                        {{ page.props.auth?.user?.name }}
                                    </span>
                                    <span class="text-[10px] text-[#5B6770] leading-tight">مالك الحساب</span>
                                </div>
                                <ChevronDown class="h-3.5 w-3.5 text-[#5B6770]" />
                            </button>

                            <!-- نافذة تفضيلات المستخدم -->
                            <div
                                v-if="userDropdownOpen"
                                class="absolute left-0 mt-2 w-48 rounded-xl border border-[#D6E0E7] bg-white py-1.5 shadow-lg z-50 animate-in fade-in zoom-in-95 duration-100"
                            >
                                <div class="px-3 py-2 border-b border-zinc-100">
                                    <p class="text-xs font-bold text-[#0F2747]">{{ page.props.auth?.user?.name }}</p>
                                    <p class="text-[11px] text-[#5B6770] font-mono truncate" dir="ltr">{{ page.props.auth?.user?.email || 'بدون بريد' }}</p>
                                </div>

                                <Link
                                    :href="route('profile.edit')"
                                    class="flex items-center gap-2 px-3 py-2 text-xs font-semibold text-[#334155] hover:bg-zinc-50 transition"
                                >
                                    <User class="h-4 w-4 text-[#5B6770]" />
                                    <span>الملف الشخصي</span>
                                </Link>

                                <div class="my-1 border-t border-zinc-100"></div>

                                <Link
                                    :href="route('logout')"
                                    method="post"
                                    as="button"
                                    class="w-full flex items-center gap-2 px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 transition text-right"
                                >
                                    <LogOut class="h-4 w-4 text-rose-500" />
                                    <span>تسجيل الخروج</span>
                                </Link>
                            </div>
                        </div>

                        <!-- زر القائمة للهواتف -->
                        <button
                            @click="mobileMenuOpen = !mobileMenuOpen"
                            type="button"
                            class="lg:hidden p-2 rounded-lg text-[#0F2747] hover:bg-zinc-100"
                        >
                            <Menu v-if="!mobileMenuOpen" class="h-5 w-5" />
                            <X v-else class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                <!-- قائمة الموبايل المنسدلة -->
                <div v-if="mobileMenuOpen" class="lg:hidden py-3 border-t border-[#D6E0E7] space-y-1">
                    <Link
                        v-for="item in navigationTabs"
                        :key="item.name"
                        :href="item.href"
                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-bold"
                        :class="item.current ? 'bg-[#E3F1F4] text-[#0E6E7E]' : 'text-[#5B6770] hover:bg-zinc-100'"
                    >
                        <component :is="item.icon" class="h-4 w-4" />
                        <span>{{ item.name }}</span>
                    </Link>
                </div>
            </div>
        </header>

        <!-- المحتوى الرئيسي للوحة -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <slot />
        </main>
    </div>
</template>
