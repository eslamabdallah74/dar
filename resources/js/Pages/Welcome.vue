<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
    laravelVersion?: string;
    phpVersion?: string;
}>();

// FAQ Accordion State
const activeFaq = ref<number | null>(0);
function toggleFaq(index: number) {
    activeFaq.value = activeFaq.value === index ? null : index;
}

// Mobile Menu State
const mobileMenuOpen = ref(false);
</script>

<template>
    <Head title="دار — نظام لأدارة الايجار" />

    <div class="min-h-screen bg-[#F3F7FA] text-[#334155] font-sans selection:bg-[#0E6E7E] selection:text-white" dir="rtl">
        <!-- ========================================== -->
        <!-- 1. NAVIGATION BAR (Nav)                    -->
        <!-- ========================================== -->
        <header class="sticky top-0 z-50 bg-white/95 backdrop-blur border-b border-[#D6E0E7] transition-all">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-20">
                    <!-- Brand Logo -->
                    <div class="flex items-center gap-8">
                        <Link href="/" class="flex items-center gap-3 group">
                            <div class="w-11 h-11 rounded-xl bg-[#0F2747] flex items-center justify-center text-white font-black text-lg shadow-md group-hover:bg-[#0E6E7E] transition-colors">
                                دار
                            </div>
                            <div class="flex flex-col">
                                <span class="text-2xl font-black text-[#0F2747] tracking-tight">دار</span>
                                <span class="text-[11px] font-semibold text-[#5B6770] -mt-1">نظام لأدارة الايجار</span>
                            </div>
                        </Link>

                        <!-- Desktop Navigation Links -->
                        <nav class="hidden md:flex items-center gap-7 text-[15px] font-semibold text-[#5B6770]">
                            <a href="#features" class="hover:text-[#0E6E7E] transition-colors">المميزات</a>
                            <a href="#how-it-works" class="hover:text-[#0E6E7E] transition-colors">كيف يعمل</a>
                            <a href="#pricing" class="hover:text-[#0E6E7E] transition-colors">الأسعار</a>
                            <a href="#faq" class="hover:text-[#0E6E7E] transition-colors">الأسئلة الشائعة</a>
                            <a href="#contact" class="hover:text-[#0E6E7E] transition-colors">تواصل معنا</a>
                        </nav>
                    </div>

                    <!-- Auth Actions -->
                    <div class="hidden sm:flex items-center gap-4">
                        <template v-if="$page.props.auth?.user">
                            <Link
                                :href="route('dashboard')"
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#0E6E7E] text-white font-bold text-sm shadow-sm hover:bg-[#0B5A68] transition"
                            >
                                <span>لوحة التحكم</span>
                                <svg class="w-4 h-4 rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M5 12h14M12 5l7 7-7 7"/>
                                </svg>
                            </Link>
                        </template>
                        <template v-else>
                            <Link
                                v-if="canLogin"
                                :href="route('login')"
                                class="px-4 py-2.5 rounded-xl text-sm font-bold text-[#0F2747] hover:bg-[#E8EFF4] transition"
                            >
                                تسجيل الدخول
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="px-5 py-2.5 rounded-xl text-sm font-bold bg-[#0E6E7E] text-white hover:bg-[#0B5A68] shadow-sm hover:shadow transition"
                            >
                                ابدأ مجانًا
                            </Link>
                        </template>
                    </div>

                    <!-- Mobile Hamburger Button -->
                    <div class="flex sm:hidden">
                        <button
                            @click="mobileMenuOpen = !mobileMenuOpen"
                            type="button"
                            class="p-2 rounded-lg text-[#5B6770] hover:text-[#0F2747] hover:bg-[#E8EFF4] focus:outline-none"
                        >
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path v-if="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Mobile Menu Dropdown -->
                <div v-show="mobileMenuOpen" class="sm:hidden border-t border-[#D6E0E7] py-4 space-y-3">
                    <a @click="mobileMenuOpen = false" href="#features" class="block px-3 py-2 text-base font-semibold text-[#5B6770] hover:bg-[#E8EFF4] rounded-lg">المميزات</a>
                    <a @click="mobileMenuOpen = false" href="#how-it-works" class="block px-3 py-2 text-base font-semibold text-[#5B6770] hover:bg-[#E8EFF4] rounded-lg">كيف يعمل</a>
                    <a @click="mobileMenuOpen = false" href="#pricing" class="block px-3 py-2 text-base font-semibold text-[#5B6770] hover:bg-[#E8EFF4] rounded-lg">الأسعار</a>
                    <a @click="mobileMenuOpen = false" href="#faq" class="block px-3 py-2 text-base font-semibold text-[#5B6770] hover:bg-[#E8EFF4] rounded-lg">الأسئلة الشائعة</a>
                    <div class="pt-3 border-t border-[#D6E0E7] flex flex-col gap-2">
                        <Link v-if="canLogin" :href="route('login')" class="w-full text-center py-2.5 text-sm font-bold text-[#0F2747] bg-[#E8EFF4] rounded-xl">تسجيل الدخول</Link>
                        <Link v-if="canRegister" :href="route('register')" class="w-full text-center py-2.5 text-sm font-bold text-white bg-[#0E6E7E] rounded-xl">ابدأ مجانًا</Link>
                    </div>
                </div>
            </div>
        </header>

        <!-- ========================================== -->
        <!-- 2. HERO SECTION (Hero from Figma)          -->
        <!-- ========================================== -->
        <section class="relative pt-12 pb-20 lg:pt-20 lg:pb-28 overflow-hidden">
            <!-- Subtle background decorative gradients -->
            <div class="absolute -top-32 left-1/2 -translate-x-1/2 w-[800px] h-[350px] bg-gradient-to-b from-[#E3F1F4]/70 to-transparent blur-3xl pointer-events-none -z-10"></div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">
                    <!-- Right Column: Hero Content & CTAs -->
                    <div class="lg:col-span-7 flex flex-col items-start text-right">
                        <!-- Figma Pill Badge -->
                        <div class="inline-flex items-center gap-2.5 px-4 py-1.5 rounded-full bg-[#E3F1F4] border border-[#0E6E7E]/20 text-[#0E6E7E] text-xs sm:text-sm font-bold mb-6">
                            <span class="w-2 h-2 rounded-full bg-[#0E6E7E] animate-pulse"></span>
                            <span>منصة SaaS عربية لإدارة العقارات والإيجارات</span>
                        </div>

                        <!-- Figma Hero Main Title -->
                        <h1 class="text-3xl sm:text-5xl lg:text-6xl font-extrabold text-[#0F2747] tracking-tight leading-[1.2] mb-6">
                            إدارة عقاراتك وإيجاراتك <br class="hidden sm:inline" />
                            <span class="text-[#0E6E7E]">من مكان واحد</span>
                        </h1>

                        <!-- Figma Subtitle -->
                        <p class="text-base sm:text-lg text-[#334155] leading-relaxed max-w-2xl mb-8 font-normal">
                            عقارات، مستأجرون، عقود، مستحقات وصيانة في منصة واحدة، مع تذكيرات واتساب تلقائية قبل موعد الدفع وتنبيه قبل انتهاء العقد.
                        </p>

                        <!-- CTA Buttons -->
                        <div class="w-full sm:w-auto flex flex-col sm:flex-row items-stretch sm:items-center gap-4 mb-10">
                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="inline-flex justify-center items-center gap-3 px-8 py-4 rounded-xl bg-[#0E6E7E] text-white font-bold text-base shadow-lg shadow-[#0E6E7E]/20 hover:bg-[#0B5A68] hover:shadow-md transition transform active:scale-[0.99]"
                            >
                                <span>ابدأ تجربتك المجانية</span>
                                <svg class="w-5 h-5 rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M5 12h14M12 5l7 7-7 7"/>
                                </svg>
                            </Link>
                            <a
                                href="#how-it-works"
                                class="inline-flex justify-center items-center gap-2.5 px-7 py-4 rounded-xl bg-white border border-[#D6E0E7] text-[#0F2747] font-bold text-base hover:bg-[#F3F7FA] hover:border-[#B6C5D0] transition shadow-sm"
                            >
                                <svg class="w-5 h-5 text-[#0E6E7E]" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                                <span>شاهد كيف تعمل</span>
                            </a>
                        </div>

                        <!-- Trust Features Row (from Figma) -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-6 border-t border-[#D6E0E7] w-full text-xs sm:text-sm font-semibold text-[#5B6770]">
                            <div class="flex items-center gap-2">
                                <div class="w-5 h-5 rounded-full bg-[#E3F1F4] flex items-center justify-center text-[#0E6E7E]">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                        <path d="M20 6L9 17l-5-5"/>
                                    </svg>
                                </div>
                                <span>واجهة عربية بالكامل</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-5 h-5 rounded-full bg-[#E3F1F4] flex items-center justify-center text-[#0E6E7E]">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                        <path d="M20 6L9 17l-5-5"/>
                                    </svg>
                                </div>
                                <span>عقار · شقة · غرفة · سرير</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-5 h-5 rounded-full bg-[#E3F1F4] flex items-center justify-center text-[#0E6E7E]">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                        <path d="M20 6L9 17l-5-5"/>
                                    </svg>
                                </div>
                                <span>تجربة مجانية حتى 5 وحدات</span>
                            </div>
                        </div>
                    </div>

                    <!-- Left Column: Figma Dashboard Preview Card (1:1 from Figma) -->
                    <div class="lg:col-span-5 relative">
                        <div class="bg-white rounded-2xl sm:rounded-3xl border border-[#D6E0E7] shadow-xl p-5 sm:p-6 transition-all relative">
                            <!-- Card Header Bar -->
                            <div class="flex items-center justify-between pb-4 mb-5 border-b border-[#D6E0E7]">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg bg-[#0F2747] text-white flex items-center justify-center">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="3" width="7" height="7"/>
                                            <rect x="14" y="3" width="7" height="7"/>
                                            <rect x="14" y="14" width="7" height="7"/>
                                            <rect x="3" y="14" width="7" height="7"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold text-[#0F2747]">لوحة المتابعة</h3>
                                        <p class="text-[11px] text-[#5B6770]">مباشر · عقارات القاهرة</p>
                                    </div>
                                </div>
                                <span class="px-2.5 py-1 rounded-md bg-[#F3F7FA] text-[#0F2747] text-xs font-bold border border-[#D6E0E7]">
                                    أكتوبر 2026
                                </span>
                            </div>

                            <!-- Top 3 Metrics (from Figma) -->
                            <div class="grid grid-cols-3 gap-2.5 mb-5">
                                <!-- Metric 1: نسبة الإشغال -->
                                <div class="bg-[#F3F7FA] rounded-xl p-3 border border-[#D6E0E7]/60 text-center">
                                    <span class="block text-[11px] font-medium text-[#5B6770] mb-1">نسبة الإشغال</span>
                                    <span class="block text-xl font-extrabold text-[#0E6E7E]">78%</span>
                                </div>
                                <!-- Metric 2: المستحق (جنيه) -->
                                <div class="bg-[#F3F7FA] rounded-xl p-3 border border-[#D6E0E7]/60 text-center">
                                    <span class="block text-[11px] font-medium text-[#5B6770] mb-1">المستحق (جنيه)</span>
                                    <span class="block text-xl font-extrabold text-[#0F2747]">9,500</span>
                                </div>
                                <!-- Metric 3: عقود تنتهي قريبًا -->
                                <div class="bg-[#F3F7FA] rounded-xl p-3 border border-[#D6E0E7]/60 text-center">
                                    <span class="block text-[11px] font-medium text-[#5B6770] mb-1">عقود تنتهي قريبًا</span>
                                    <span class="block text-xl font-extrabold text-[#DC2626]">3</span>
                                </div>
                            </div>

                            <!-- Occupancy Trend Bar (Figma: الإشغال خلال 6 أشهر) -->
                            <div class="mb-5 bg-[#F3F7FA] rounded-xl p-3 border border-[#D6E0E7]/60">
                                <div class="flex items-center justify-between text-xs font-bold mb-2">
                                    <span class="text-[#0F2747]">الإشغال خلال 6 أشهر</span>
                                    <span class="text-[#0E6E7E] text-[11px]">↑ +12% نمو مستمر</span>
                                </div>
                                <div class="grid grid-cols-6 gap-1.5 items-end h-12 pt-2">
                                    <div class="flex flex-col items-center gap-1">
                                        <div class="w-full bg-[#D6E0E7] rounded-t h-6"></div>
                                        <span class="text-[9px] text-[#5B6770]">مايو</span>
                                    </div>
                                    <div class="flex flex-col items-center gap-1">
                                        <div class="w-full bg-[#D6E0E7] rounded-t h-7"></div>
                                        <span class="text-[9px] text-[#5B6770]">يونيو</span>
                                    </div>
                                    <div class="flex flex-col items-center gap-1">
                                        <div class="w-full bg-[#D6E0E7] rounded-t h-8"></div>
                                        <span class="text-[9px] text-[#5B6770]">يوليو</span>
                                    </div>
                                    <div class="flex flex-col items-center gap-1">
                                        <div class="w-full bg-[#B6C5D0] rounded-t h-9"></div>
                                        <span class="text-[9px] text-[#5B6770]">أغسطس</span>
                                    </div>
                                    <div class="flex flex-col items-center gap-1">
                                        <div class="w-full bg-[#0E6E7E]/60 rounded-t h-10"></div>
                                        <span class="text-[9px] text-[#5B6770]">سبتمبر</span>
                                    </div>
                                    <div class="flex flex-col items-center gap-1">
                                        <div class="w-full bg-[#0E6E7E] rounded-t h-12"></div>
                                        <span class="text-[9px] font-bold text-[#0E6E7E]">أكتوبر</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Live Tenant List with Status Badges (from Figma) -->
                            <div class="space-y-2 mb-4">
                                <span class="block text-xs font-bold text-[#5B6770] px-1">سجل المستأجرين الأخير</span>

                                <!-- Tenant 1: أحمد محمد -->
                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-white border border-[#D6E0E7] hover:border-[#B6C5D0] transition">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-full bg-[#E3F1F4] text-[#0E6E7E] font-bold text-xs flex items-center justify-center">أ</div>
                                        <div>
                                            <span class="block text-xs font-bold text-[#0F2747]">أحمد محمد</span>
                                            <span class="block text-[10px] text-[#5B6770]">شقة 3B · عمارة الياسمين</span>
                                        </div>
                                    </div>
                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-[#ECFDF5] text-[#059669] border border-[#A7F3D0]">
                                        مدفوع
                                    </span>
                                </div>

                                <!-- Tenant 2: سارة علي -->
                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-white border border-[#D6E0E7] hover:border-[#B6C5D0] transition">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-full bg-[#FFFBEB] text-[#D97706] font-bold text-xs flex items-center justify-center">س</div>
                                        <div>
                                            <span class="block text-xs font-bold text-[#0F2747]">سارة علي</span>
                                            <span class="block text-[10px] text-[#5B6770]">غرفة 2 · سرير A</span>
                                        </div>
                                    </div>
                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-[#FFFBEB] text-[#D97706] border border-[#FDE68A]">
                                        يستحق قريبًا
                                    </span>
                                </div>

                                <!-- Tenant 3: خالد حسن -->
                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-white border border-[#D6E0E7] hover:border-[#B6C5D0] transition">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-full bg-[#FEF2F2] text-[#DC2626] font-bold text-xs flex items-center justify-center">خ</div>
                                        <div>
                                            <span class="block text-xs font-bold text-[#0F2747]">خالد حسن</span>
                                            <span class="block text-[10px] text-[#5B6770]">شقة 1A · برج النصر</span>
                                        </div>
                                    </div>
                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-[#FEF2F2] text-[#DC2626] border border-[#FECACA]">
                                        متأخر
                                    </span>
                                </div>

                                <!-- Available unit: سرير B -->
                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-[#F3F7FA] border border-dashed border-[#B6C5D0]">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-[#0E6E7E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 17h20M6 8v9"/>
                                        </svg>
                                        <span class="text-xs font-semibold text-[#0F2747]">غرفة 2 · سرير B</span>
                                    </div>
                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-[#E3F1F4] text-[#0E6E7E]">
                                        متاح للحجز
                                    </span>
                                </div>
                            </div>

                            <!-- WhatsApp Notification Banner Toast (from Figma) -->
                            <div class="mt-4 p-3 rounded-xl bg-[#E8EFF4] border border-[#0E6E7E]/30 flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-[#25D366] text-white flex items-center justify-center shrink-0 shadow-sm">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.664-.698c.969.587 1.771.888 2.796.888 3.182 0 5.767-2.587 5.767-5.766.001-3.181-2.585-5.775-5.767-5.775zm3.366 8.163c-.141.399-.824.773-1.144.821-.309.047-.706.071-2.072-.497-1.745-.726-2.871-2.493-2.958-2.609-.088-.115-.706-.94-.706-1.792 0-.853.444-1.272.603-1.446.159-.175.347-.219.462-.219.116 0 .232.001.332.006.106.005.249-.04.389.297.144.347.491 1.2.534 1.288.043.088.072.19.014.306-.058.115-.087.189-.173.291-.087.101-.183.226-.261.303-.087.087-.178.181-.077.354.101.173.449.741.963 1.199.662.59 1.221.773 1.394.86.173.087.275.073.376-.044.101-.116.433-.506.549-.68.116-.173.231-.144.389-.087.159.058 1.011.477 1.184.564.173.087.289.13.332.203.043.072.043.419-.098.818z"/>
                                    </svg>
                                </div>
                                <div class="text-right">
                                    <span class="block text-xs font-bold text-[#0F2747]">تم إرسال تذكير واتساب</span>
                                    <span class="block text-[11px] text-[#5B6770]">إلى أحمد محمد · موعد الإيجار يوم 15</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- 3. AUDIENCE SECTION (Audience from Figma)  -->
        <!-- ========================================== -->
        <section class="py-16 bg-white border-y border-[#D6E0E7]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-3xl mx-auto mb-12">
                    <h2 class="text-2xl sm:text-4xl font-extrabold text-[#0F2747] mb-3">مصمَّم لكل من يؤجّر</h2>
                    <p class="text-base text-[#5B6770]">سواء كنت مالكًا فرديًا أو تدير محفظة عقارية ضخمة، دار يقدم لك النموذج المثالي لتنظيم عملك.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Segment 1: ملاك العقارات -->
                    <div class="p-6 rounded-2xl bg-[#F3F7FA] border border-[#D6E0E7] hover:border-[#0E6E7E] transition hover:shadow-md group">
                        <div class="w-12 h-12 rounded-xl bg-white text-[#0E6E7E] flex items-center justify-center mb-5 shadow-sm group-hover:bg-[#0E6E7E] group-hover:text-white transition-colors">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 21h18M9 8h1M9 12h1M9 16h1M14 8h1M14 12h1M14 16h1M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-[#0F2747] mb-2">ملاك العقارات</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            لأصحاب العمارات والشقق المستقلة الساعين لتنظيم التحصيل وتوثيق العقود وتفادي التأخيرات.
                        </p>
                    </div>

                    <!-- Segment 2: مديرو العقارات -->
                    <div class="p-6 rounded-2xl bg-[#F3F7FA] border border-[#D6E0E7] hover:border-[#0E6E7E] transition hover:shadow-md group">
                        <div class="w-12 h-12 rounded-xl bg-white text-[#0E6E7E] flex items-center justify-center mb-5 shadow-sm group-hover:bg-[#0E6E7E] group-hover:text-white transition-colors">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-[#0F2747] mb-2">مديرو العقارات</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            أدوات احترافية لشركات إدارة الأملاك مع كشوفات حساب مفصلة لكل مالك ومتابعة فرق الصيانة.
                        </p>
                    </div>

                    <!-- Segment 3: السكن المشترك والغرف -->
                    <div class="p-6 rounded-2xl bg-[#F3F7FA] border border-[#D6E0E7] hover:border-[#0E6E7E] transition hover:shadow-md group">
                        <div class="w-12 h-12 rounded-xl bg-white text-[#0E6E7E] flex items-center justify-center mb-5 shadow-sm group-hover:bg-[#0E6E7E] group-hover:text-white transition-colors">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                <polyline points="9 22 9 12 15 12 15 22"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-[#0F2747] mb-2">السكن المشترك والغرف</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            تقسيم الشقق الكبيرة وتأجير الغرف المنفصلة مع إدارة تكاليف المرافق المشتركة بسهولة.
                        </p>
                    </div>

                    <!-- Segment 4: التأجير بالسرير -->
                    <div class="p-6 rounded-2xl bg-[#F3F7FA] border border-[#D6E0E7] hover:border-[#0E6E7E] transition hover:shadow-md group">
                        <div class="w-12 h-12 rounded-xl bg-white text-[#0E6E7E] flex items-center justify-center mb-5 shadow-sm group-hover:bg-[#0E6E7E] group-hover:text-white transition-colors">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 17h20M6 8v9"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-[#0F2747] mb-2">التأجير بالسرير</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            نظام متطور لدور سكن الطلاب ومساحات العمالة، يتيح حجز ومتابعة كل سرير بعقد مستقل وتنبيه مخصص.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- 4. FEATURES SECTION (المميزات)             -->
        <!-- ========================================== -->
        <section id="features" class="py-20 bg-[#F3F7FA]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <span class="inline-block px-3.5 py-1 rounded-full bg-[#E3F1F4] text-[#0E6E7E] text-xs font-bold mb-3">
                        كفاءة ودقة متناهية
                    </span>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-[#0F2747] mb-4">
                        كل الأدوات التي تحتاجها لتشغيل عقاراتك بسلاسة
                    </h2>
                    <p class="text-base text-[#5B6770]">
                        ودّع جداول إكسل المعقدة والأوراق الضائعة، دار يضع محفظتك العقارية تحت سيطرتك بنقرات بسيطة.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- Feature 1: هيكلة مرنة ومتعددة المستويات -->
                    <div class="bg-white p-7 rounded-2xl border border-[#D6E0E7] hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center mb-6">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="18" height="18" rx="2"/>
                                <path d="M3 9h18M9 21V9"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-[#0F2747] mb-3">هيكلة وحدات مرنة</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            أضف عقارك وقسمه حسب نموذجك الواقعي: مبنى كامل، أدوار، شقق، غرف، أو أسرة، مع متابعة الإشغال والشاغر لكل وحدة لحظيًا.
                        </p>
                    </div>

                    <!-- Feature 2: تذكيرات واتساب الذكية -->
                    <div class="bg-white p-7 rounded-2xl border border-[#D6E0E7] hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-[#ECFDF5] text-[#059669] flex items-center justify-center mb-6">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.664-.698c.969.587 1.771.888 2.796.888 3.182 0 5.767-2.587 5.767-5.766.001-3.181-2.585-5.775-5.767-5.775zm3.366 8.163c-.141.399-.824.773-1.144.821-.309.047-.706.071-2.072-.497-1.745-.726-2.871-2.493-2.958-2.609-.088-.115-.706-.94-.706-1.792 0-.853.444-1.272.603-1.446.159-.175.347-.219.462-.219.116 0 .232.001.332.006.106.005.249-.04.389.297.144.347.491 1.2.534 1.288.043.088.072.19.014.306-.058.115-.087.189-.173.291-.087.101-.183.226-.261.303-.087.087-.178.181-.077.354.101.173.449.741.963 1.199.662.59 1.221.773 1.394.86.173.087.275.073.376-.044.101-.116.433-.506.549-.68.116-.173.231-.144.389-.087.159.058 1.011.477 1.184.564.173.087.289.13.332.203.043.072.043.419-.098.818z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-[#0F2747] mb-3">تنبيهات واتساب الآلية</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            وفّر الحرج والوقت في الاتصالات. المنصة ترسل فواتير وإشعارات مهذبة للمستأجر على واتساب تلقائيًا قبل موعد الاستحقاق ومتابعة المتأخرات.
                        </p>
                    </div>

                    <!-- Feature 3: تتبع العقود والتجديدات -->
                    <div class="bg-white p-7 rounded-2xl border border-[#D6E0E7] hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center mb-6">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                                <polyline points="10 9 9 9 8 9"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-[#0F2747] mb-3">أرشفة العقود والتجديد</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            إدارة تواريخ بداية ونهاية العقود، وقيم التأمين، مع تنبيهات مسبقة قبل 30 أو 60 يومًا من انتهاء العقد لتجديده أو إعادة عرض الوحدة.
                        </p>
                    </div>

                    <!-- Feature 4: التحصيل وكشوف الحساب -->
                    <div class="bg-white p-7 rounded-2xl border border-[#D6E0E7] hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-[#FFFBEB] text-[#D97706] flex items-center justify-center mb-6">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="1" x2="12" y2="23"/>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-[#0F2747] mb-3">سجل الدفعات والمستحقات</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            تسجيل المدفوعات الجزئية والكاملة، طباعة إيصالات السداد، وتصدير كشف حساب شامل لكل مستأجر بضغطة زر واحدة.
                        </p>
                    </div>

                    <!-- Feature 5: إدارة المصروفات والصيانة -->
                    <div class="bg-white p-7 rounded-2xl border border-[#D6E0E7] hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-[#FEF2F2] text-[#DC2626] flex items-center justify-center mb-6">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-[#0F2747] mb-3">طلبات الصيانة والمصروفات</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            استقبل بلاغات الصيانة، وسجّل فواتير السباكة والكهرباء والإصلاحات لتخصم تلقائيًا وتظهر صافي عائد كل عقار بدقة.
                        </p>
                    </div>

                    <!-- Feature 6: تقارير مالية وتحليلات إشغال -->
                    <div class="bg-white p-7 rounded-2xl border border-[#D6E0E7] hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center mb-6">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="20" x2="18" y2="10"/>
                                <line x1="12" y1="20" x2="12" y2="4"/>
                                <line x1="6" y1="20" x2="6" y2="14"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-[#0F2747] mb-3">تقارير مالية وإحصائيات</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            رسوم بيانية توضح معدلات الإشغال الشهرية، التدفق النقدي، المبالغ المحصلة والمتبقية، لدعم قراراتك الاستثمارية.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- 5. HOW IT WORKS (كيف يعمل)                 -->
        <!-- ========================================== -->
        <section id="how-it-works" class="py-20 bg-white border-t border-[#D6E0E7]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <span class="inline-block px-3.5 py-1 rounded-full bg-[#E3F1F4] text-[#0E6E7E] text-xs font-bold mb-3">
                        بساطة في 3 خطوات
                    </span>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-[#0F2747] mb-4">
                        ابدأ تشغيل عقاراتك خلال دقائق معدودة
                    </h2>
                    <p class="text-base text-[#5B6770]">
                        تصميم سلس وبسيط لا يتطلب أي خبرة تقنية سابقة.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 relative">
                    <!-- Step 1 -->
                    <div class="p-8 rounded-2xl bg-[#F3F7FA] border border-[#D6E0E7] relative flex flex-col items-start">
                        <div class="w-10 h-10 rounded-full bg-[#0F2747] text-white font-extrabold text-lg flex items-center justify-center mb-6 shadow-sm">
                            1
                        </div>
                        <h3 class="text-xl font-bold text-[#0F2747] mb-3">أضف عقاراتك ووحداتك</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed mb-4">
                            سجّل أسماء العمارات أو الشقق، وحدد عدد الغرف والأسرة وقيمة الإيجار المستهدفة لكل وحدة.
                        </p>
                        <div class="mt-auto pt-4 text-xs font-bold text-[#0E6E7E] flex items-center gap-1.5">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M12 8v8M8 12h8"/>
                            </svg>
                            <span>إضافة سريعة بدون تعقيد</span>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="p-8 rounded-2xl bg-[#F3F7FA] border border-[#D6E0E7] relative flex flex-col items-start">
                        <div class="w-10 h-10 rounded-full bg-[#0E6E7E] text-white font-extrabold text-lg flex items-center justify-center mb-6 shadow-sm">
                            2
                        </div>
                        <h3 class="text-xl font-bold text-[#0F2747] mb-3">سجّل عقود المستأجرين</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed mb-4">
                            أدخل بيانات المستأجر، رقم الواتساب، فترة العقد، وقيمة الإيجار الشهري وتاريخ يوم الاستحقاق.
                        </p>
                        <div class="mt-auto pt-4 text-xs font-bold text-[#0E6E7E] flex items-center gap-1.5">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 6L9 17l-5-5"/>
                            </svg>
                            <span>أرشفة وحساب فوري</span>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="p-8 rounded-2xl bg-[#F3F7FA] border border-[#D6E0E7] relative flex flex-col items-start">
                        <div class="w-10 h-10 rounded-full bg-[#0F2747] text-white font-extrabold text-lg flex items-center justify-center mb-6 shadow-sm">
                            3
                        </div>
                        <h3 class="text-xl font-bold text-[#0F2747] mb-3">تابع التحصيل والإشغال</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed mb-4">
                            يتولى دار إرسال تنبيهات واتساب في الموعد المحدد، ويحدث كشوفات السداد ونسب الإشغال آليًا.
                        </p>
                        <div class="mt-auto pt-4 text-xs font-bold text-[#0E6E7E] flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-[#25D366]" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.664-.698c.969.587 1.771.888 2.796.888 3.182 0 5.767-2.587 5.767-5.766.001-3.181-2.585-5.775-5.767-5.775zm3.366 8.163c-.141.399-.824.773-1.144.821-.309.047-.706.071-2.072-.497-1.745-.726-2.871-2.493-2.958-2.609-.088-.115-.706-.94-.706-1.792 0-.853.444-1.272.603-1.446.159-.175.347-.219.462-.219.116 0 .232.001.332.006.106.005.249-.04.389.297.144.347.491 1.2.534 1.288.043.088.072.19.014.306-.058.115-.087.189-.173.291-.087.101-.183.226-.261.303-.087.087-.178.181-.077.354.101.173.449.741.963 1.199.662.59 1.221.773 1.394.86.173.087.275.073.376-.044.101-.116.433-.506.549-.68.116-.173.231-.144.389-.087.159.058 1.011.477 1.184.564.173.087.289.13.332.203.043.072.043.419-.098.818z"/>
                            </svg>
                            <span>واتساب تلقائي بالكامل</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- 6. PRICING PLANS (الأسعار)                 -->
        <!-- ========================================== -->
        <section id="pricing" class="py-20 bg-[#F3F7FA] border-t border-[#D6E0E7]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <span class="inline-block px-3.5 py-1 rounded-full bg-[#E3F1F4] text-[#0E6E7E] text-xs font-bold mb-3">
                        خطط واضحة بدون رسوم خفية
                    </span>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-[#0F2747] mb-4">
                        اختر الباقة المناسبة لحجم أعمالك
                    </h2>
                    <p class="text-base text-[#5B6770]">
                        ابدأ مجانًا بدون بطاقة بنكية، ورقِّ خطتك مع نمو عقاراتك ووحداتك.
                    </p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-stretch">
                    <!-- Plan 1: الباقة المجانية (Free Tier from Figma Hero) -->
                    <div class="bg-white rounded-3xl p-8 border border-[#D6E0E7] flex flex-col justify-between hover:shadow-md transition">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-xl font-bold text-[#0F2747]">الباقة المجانية</h3>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-[#E8EFF4] text-[#0F2747]">للبدء</span>
                            </div>
                            <p class="text-sm text-[#5B6770] mb-6">مثالية لصغار الملاك الراغبين في تجربة النظام.</p>
                            
                            <div class="flex items-baseline gap-1 mb-8">
                                <span class="text-4xl font-extrabold text-[#0F2747]">0</span>
                                <span class="text-base font-semibold text-[#5B6770]">ج.م / شهريًا</span>
                            </div>

                            <ul class="space-y-3.5 text-sm font-medium text-[#334155] mb-8">
                                <li class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-[#0E6E7E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span><strong>حتى 5 وحدات تأجيرية</strong> مجانًا للأبد</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-[#0E6E7E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>إدارة العقود والمستأجرين</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-[#0E6E7E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>سجل مدفوعات ومستحقات</span>
                                </li>
                                <li class="flex items-center gap-3 text-[#B6C5D0]">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    <span>تذكيرات واتساب التلقائية</span>
                                </li>
                            </ul>
                        </div>

                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="w-full py-3.5 rounded-xl border border-[#D6E0E7] text-center font-bold text-sm text-[#0F2747] hover:bg-[#F3F7FA] transition"
                        >
                            ابدأ مجانًا الآن
                        </Link>
                    </div>

                    <!-- Plan 2: باقة الملاك المحترفين (Pro - Popular) -->
                    <div class="bg-white rounded-3xl p-8 border-2 border-[#0E6E7E] flex flex-col justify-between shadow-xl relative lg:-translate-y-2">
                        <div class="absolute -top-4 right-8 px-4 py-1 rounded-full bg-[#0E6E7E] text-white text-xs font-bold shadow-sm">
                            الأكثر طلبًا
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-4 mt-1">
                                <h3 class="text-xl font-bold text-[#0F2747]">باقة الملاك</h3>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-[#E3F1F4] text-[#0E6E7E]">شاملة</span>
                            </div>
                            <p class="text-sm text-[#5B6770] mb-6">للملاك وأصحاب العمارات الساعين لأتمتة كاملة للتحصيل.</p>
                            
                            <div class="flex items-baseline gap-1 mb-8">
                                <span class="text-4xl font-extrabold text-[#0E6E7E]">249</span>
                                <span class="text-base font-semibold text-[#5B6770]">ج.م / شهريًا</span>
                            </div>

                            <ul class="space-y-3.5 text-sm font-medium text-[#334155] mb-8">
                                <li class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-[#0E6E7E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span><strong>عدد وحدات غير محدود</strong></span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-[#0E6E7E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span><strong>تذكيرات واتساب الآلية</strong> للمستأجرين</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-[#0E6E7E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>تصدير كشوفات حساب وتقارير PDF</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-[#0E6E7E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>إدارة فواتير الصيانة والمصروفات</span>
                                </li>
                            </ul>
                        </div>

                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="w-full py-3.5 rounded-xl bg-[#0E6E7E] text-center font-bold text-sm text-white hover:bg-[#0B5A68] shadow-md transition"
                        >
                            جرّب مجانًا لمدة 14 يومًا
                        </Link>
                    </div>

                    <!-- Plan 3: إدارة الأملاك والشركات -->
                    <div class="bg-white rounded-3xl p-8 border border-[#D6E0E7] flex flex-col justify-between hover:shadow-md transition">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-xl font-bold text-[#0F2747]">إدارة الأملاك</h3>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-[#E8EFF4] text-[#0F2747]">للشركات</span>
                            </div>
                            <p class="text-sm text-[#5B6770] mb-6">لشركات إدارة العقارات والسكن المشترك ذي الحجم الكبير.</p>
                            
                            <div class="flex items-baseline gap-1 mb-8">
                                <span class="text-4xl font-extrabold text-[#0F2747]">599</span>
                                <span class="text-base font-semibold text-[#5B6770]">ج.م / شهريًا</span>
                            </div>

                            <ul class="space-y-3.5 text-sm font-medium text-[#334155] mb-8">
                                <li class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-[#0E6E7E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>كل مميزات باقة الملاك</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-[#0E6E7E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>فصل الحسابات لكبار الملاك</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-[#0E6E7E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>مستخدمون متعددون وصلاحيات مخصصة</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-[#0E6E7E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>دعم فني مخصص عبر واتساب والهاتف</span>
                                </li>
                            </ul>
                        </div>

                        <a
                            href="#contact"
                            class="w-full py-3.5 rounded-xl border border-[#D6E0E7] text-center font-bold text-sm text-[#0F2747] hover:bg-[#F3F7FA] transition"
                        >
                            تواصل مع المبيعات
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- 7. FAQ ACCORDION (الأسئلة الشائعة)        -->
        <!-- ========================================== -->
        <section id="faq" class="py-20 bg-white border-t border-[#D6E0E7]">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-14">
                    <span class="inline-block px-3.5 py-1 rounded-full bg-[#E3F1F4] text-[#0E6E7E] text-xs font-bold mb-3">
                        إجابات سريعة
                    </span>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-[#0F2747] mb-3">
                        الأسئلة الأكثر شيوعًا
                    </h2>
                    <p class="text-base text-[#5B6770]">
                        كل ما تريد معرفته عن منصة دار قبل البدء.
                    </p>
                </div>

                <div class="space-y-4">
                    <!-- Q1 -->
                    <div class="border border-[#D6E0E7] rounded-2xl overflow-hidden transition">
                        <button
                            @click="toggleFaq(0)"
                            type="button"
                            class="w-full p-5 text-right flex items-center justify-between gap-4 bg-white hover:bg-[#F3F7FA] transition"
                        >
                            <span class="text-base font-bold text-[#0F2747]">هل التجربة المجانية تتطلب بطاقة بنكية؟</span>
                            <span class="w-7 h-7 rounded-full bg-[#E8EFF4] text-[#0F2747] flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': activeFaq === 0 }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 9l6 6 6-6"/>
                                </svg>
                            </span>
                        </button>
                        <div v-show="activeFaq === 0" class="px-5 pb-5 pt-1 text-sm text-[#5B6770] leading-relaxed border-t border-[#D6E0E7]/60 bg-[#F3F7FA]/50">
                            لا نهائيًا، يمكنك التسجيل والبدء فورًا في إدارة حتى 5 وحدات مجانًا بالكامل دون الحاجة لإدخال أي بطاقة دفع أو التزام مالي.
                        </div>
                    </div>

                    <!-- Q2 -->
                    <div class="border border-[#D6E0E7] rounded-2xl overflow-hidden transition">
                        <button
                            @click="toggleFaq(1)"
                            type="button"
                            class="w-full p-5 text-right flex items-center justify-between gap-4 bg-white hover:bg-[#F3F7FA] transition"
                        >
                            <span class="text-base font-bold text-[#0F2747]">كيف تعمل تذكيرات واتساب التلقائية؟</span>
                            <span class="w-7 h-7 rounded-full bg-[#E8EFF4] text-[#0F2747] flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': activeFaq === 1 }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 9l6 6 6-6"/>
                                </svg>
                            </span>
                        </button>
                        <div v-show="activeFaq === 1" class="px-5 pb-5 pt-1 text-sm text-[#5B6770] leading-relaxed border-t border-[#D6E0E7]/60 bg-[#F3F7FA]/50">
                            ترسل المنصة إشعارًا مهذبًا ومخصصًا للمستأجر عبر واتساب قبل موعد استحقاق الإيجار بأيام محددة تختارها، متضمنًا اسم الوحدة والمبلغ المستحق وتاريخ السداد.
                        </div>
                    </div>

                    <!-- Q3 -->
                    <div class="border border-[#D6E0E7] rounded-2xl overflow-hidden transition">
                        <button
                            @click="toggleFaq(2)"
                            type="button"
                            class="w-full p-5 text-right flex items-center justify-between gap-4 bg-white hover:bg-[#F3F7FA] transition"
                        >
                            <span class="text-base font-bold text-[#0F2747]">هل يمكنني إدارة التأجير بالسرير أو الغرفة المنفصلة؟</span>
                            <span class="w-7 h-7 rounded-full bg-[#E8EFF4] text-[#0F2747] flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': activeFaq === 2 }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 9l6 6 6-6"/>
                                </svg>
                            </span>
                        </button>
                        <div v-show="activeFaq === 2" class="px-5 pb-5 pt-1 text-sm text-[#5B6770] leading-relaxed border-t border-[#D6E0E7]/60 bg-[#F3F7FA]/50">
                            نعم! صُمم النظام بهيكلية شجرية مرنة: عقار -> شقة -> غرفة -> سرير، مما يتيح لك إنشاء عقود ومتابعة دفعات لكل سرير على حدة بدقة متناهية.
                        </div>
                    </div>

                    <!-- Q4 -->
                    <div class="border border-[#D6E0E7] rounded-2xl overflow-hidden transition">
                        <button
                            @click="toggleFaq(3)"
                            type="button"
                            class="w-full p-5 text-right flex items-center justify-between gap-4 bg-white hover:bg-[#F3F7FA] transition"
                        >
                            <span class="text-base font-bold text-[#0F2747]">هل بياناتي وعقود مستأجريّ آمنة؟</span>
                            <span class="w-7 h-7 rounded-full bg-[#E8EFF4] text-[#0F2747] flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': activeFaq === 3 }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 9l6 6 6-6"/>
                                </svg>
                            </span>
                        </button>
                        <div v-show="activeFaq === 3" class="px-5 pb-5 pt-1 text-sm text-[#5B6770] leading-relaxed border-t border-[#D6E0E7]/60 bg-[#F3F7FA]/50">
                            نعتمد على سيرفرات مشفرة بالكامل ونسخ احتياطي يومي، مع عزل تام لبيانات كل مساحة عمل لضمان سرية وخصوصية سجلاتك المالية وعقودك.
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- 8. FINAL CTA BANNER & FOOTER               -->
        <!-- ========================================== -->
        <section id="contact" class="py-16 bg-[#0F2747] text-white relative overflow-hidden">
            <div class="absolute inset-0 bg-radial-gradient from-[#0E6E7E]/30 to-transparent pointer-events-none"></div>
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
                <span class="inline-block px-4 py-1 rounded-full bg-[#0E6E7E]/30 text-[#E3F1F4] border border-[#0E6E7E]/40 text-xs font-bold mb-4">
                    انضم إلى مئات الملاك الناجحين
                </span>
                <h2 class="text-3xl sm:text-5xl font-extrabold mb-6 leading-tight">
                    جاهز لتنظيم عقاراتك وضمان تحصيلك في الموعد؟
                </h2>
                <p class="text-base sm:text-lg text-[#E3F1F4]/80 max-w-2xl mx-auto mb-10">
                    ابدأ تجربتك المجانية اليوم واكتشف كيف يُبسّط دار كل تفاصيل إدارة وتأجير العقارات.
                </p>
                <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="w-full sm:w-auto px-8 py-4 rounded-xl bg-[#0E6E7E] text-white font-bold text-base hover:bg-[#0B5A68] shadow-lg transition"
                    >
                        ابدأ مجانًا حتى 5 وحدات
                    </Link>
                    <a
                        href="mailto:contact@dar-app.com"
                        class="w-full sm:w-auto px-7 py-4 rounded-xl border border-white/20 hover:bg-white/10 text-white font-bold text-base transition"
                    >
                        تحدث مع فريقنا
                    </a>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="bg-[#09182C] text-[#B6C5D0] py-12 border-t border-white/10 text-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-10">
                    <!-- Brand info -->
                    <div class="md:col-span-2 space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-[#0E6E7E] flex items-center justify-center text-white font-bold text-base">
                                دار
                            </div>
                            <span class="text-2xl font-black text-white">دار</span>
                        </div>
                        <p class="text-xs text-[#B6C5D0] leading-relaxed max-w-sm">
                            منصة عربية متكاملة لإدارة وتأجير العقارات السكنية، الشقق المشتركة، سكن الطلاب، وإدارة الأملاك، مع أتمتة تنبيهات واتساب وحسابات الإشغال.
                        </p>
                    </div>

                    <!-- Links -->
                    <div>
                        <h4 class="text-white font-bold mb-4 text-sm">روابط سريعة</h4>
                        <ul class="space-y-2.5 text-xs">
                            <li><a href="#features" class="hover:text-white transition">المميزات</a></li>
                            <li><a href="#how-it-works" class="hover:text-white transition">كيف يعمل</a></li>
                            <li><a href="#pricing" class="hover:text-white transition">الأسعار</a></li>
                            <li><a href="#faq" class="hover:text-white transition">الأسئلة الشائعة</a></li>
                        </ul>
                    </div>

                    <!-- Legal / Support -->
                    <div>
                        <h4 class="text-white font-bold mb-4 text-sm">الدعم والقانونية</h4>
                        <ul class="space-y-2.5 text-xs">
                            <li><a href="#" class="hover:text-white transition">شروط الاستخدام</a></li>
                            <li><a href="#" class="hover:text-white transition">سياسة الخصوصية</a></li>
                            <li><a href="mailto:support@dar-app.com" class="hover:text-white transition">الدعم الفني</a></li>
                        </ul>
                    </div>
                </div>

                <div class="pt-8 border-t border-white/10 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs">
                    <span>جميع الحقوق محفوظة © 2026 منصة دار.</span>
                    <span class="text-[#5B6770]">نظام لأدارة الايجار</span>
                </div>
            </div>
        </footer>
    </div>
</template>
