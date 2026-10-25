<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

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
    <Head>
        <title>Nook Mate — اعثر على مساحتك واستأجر ما تحتاج إليه</title>
        <meta name="description" content="Nook Mate — اعثر على مساحتك واستأجر ما تحتاج إليه. منصة سحابية لإدارة وتأجير العقارات والشقق والغرف والأسرة بنظام الصناديق المتداخلة." />
        <meta property="og:title" content="Nook Mate — اعثر على مساحتك واستأجر ما تحتاج إليه" />
        <meta property="og:description" content="أدر عقاراتك، وحداتك، غرفك وأسرتك مع تذكيرات واتساب التلقائية وسندات القبض الفورية." />
        <meta property="og:image" content="/images/logo.png" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="Nook Mate — اعثر على مساحتك واستأجر ما تحتاج إليه" />
        <meta name="twitter:description" content="منصة سحابية متكاملة لإدارة وتأجير العقارات، عقود الإيجار، وسندات القبض مع تذكيرات واتساب." />
        <meta name="twitter:image" content="/images/logo.png" />

        <!-- Structured Data (JSON-LD) -->
        <component :is="'script'" type="application/ld+json">
            {{ JSON.stringify({
                "@context": "https://schema.org",
                "@graph": [
                    {
                        "@type": "Organization",
                        "@id": "https://nookmate.com/#organization",
                        "name": "Nook Mate",
                        "alternateName": "نوك ميت لإدارة العقارات",
                        "url": "https://nookmate.com",
                        "logo": {
                            "@type": "ImageObject",
                            "url": "/images/logo.png"
                        },
                        "description": "منصة سحابية متكاملة لإدارة وتأجير العقارات السكنية والشقق المشتركة وسكن الطلاب بنظام الصناديق المتداخلة"
                    },
                    {
                        "@type": "SoftwareApplication",
                        "@id": "https://nookmate.com/#software",
                        "name": "Nook Mate",
                        "applicationCategory": "BusinessApplication",
                        "operatingSystem": "متصفحات الويب الحديثة",
                        "offers": [
                            {
                                "@type": "Offer",
                                "name": "الباقة المجانية",
                                "price": "0",
                                "priceCurrency": "EGP",
                                "description": "إدارة حتى 5 وحدات سكنية مجاناً مدى الحياة"
                            },
                            {
                                "@type": "Offer",
                                "name": "باقة النمو",
                                "price": "299",
                                "priceCurrency": "EGP",
                                "description": "إدارة حتى 25 وحدة سكنية مع تذكيرات واتساب وتوثيق العقود"
                            },
                            {
                                "@type": "Offer",
                                "name": "باقة المحترفين",
                                "price": "799",
                                "priceCurrency": "EGP",
                                "description": "إدارة حتى 100 وحدة سكنية مع تخصيص كامل ودعم متقدم"
                            }
                        ]
                    },
                    {
                        "@type": "FAQPage",
                        "@id": "https://nookmate.com/#faq",
                        "mainEntity": [
                            {
                                "@type": "Question",
                                "name": "هل يمكنني استخدام Nook Mate مجانًا؟",
                                "acceptedAnswer": {
                                    "@type": "Answer",
                                    "text": "نعم، يمكنك استخدام الباقة المجانية لإدارة حتى 5 وحدات دون الحاجة إلى بطاقة دفع."
                                }
                            },
                            {
                                "@type": "Question",
                                "name": "كيف تعمل تذكيرات واتساب التلقائية؟",
                                "acceptedAnswer": {
                                    "@type": "Answer",
                                    "text": "ترسل المنصة إشعارًا مهذبًا ومخصصًا للمستأجر عبر واتساب قبل موعد استحقاق الإيجار بأيام محددة تختارها، متضمنًا اسم الوحدة والمبلغ المستحق وتاريخ السداد."
                                }
                            },
                            {
                                "@type": "Question",
                                "name": "هل يمكنني إدارة التأجير بالسرير أو الغرفة المنفصلة؟",
                                "acceptedAnswer": {
                                    "@type": "Answer",
                                    "text": "نعم! صُمم نظام الصناديق المتداخلة بهيكلية متدرجة: عقار أو شقة، ثم غرفة، ثم سرير، مما يتيح لك تأجير كل مساحة بدقة متناهية."
                                }
                            },
                            {
                                "@type": "Question",
                                "name": "هل بياناتي وعقود مستأجريّ آمنة؟",
                                "acceptedAnswer": {
                                    "@type": "Answer",
                                    "text": "نعتمد على سيرفرات مشفرة بالكامل ونسخ احتياطي يومي، مع عزل تام لبيانات كل مساحة عمل لضمان سرية وخصوصية سجلاتك المالية وعقودك."
                                }
                            }
                        ]
                    }
                ]
            }) }}
        </component>
    </Head>

    <div class="min-h-screen bg-[#F8F4EA] text-[#334155] font-sans selection:bg-[#F7C33D] selection:text-[#163E3E]" dir="rtl">
        <!-- ========================================== -->
        <!-- 1. NAVIGATION BAR (Nav)                    -->
        <!-- ========================================== -->
        <header class="sticky top-0 z-50 bg-[#F8F4EA]/95 backdrop-blur border-b border-[#E2DDD2] transition-all">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-20">
                    <!-- Brand Logo -->
                    <div class="flex items-center gap-8">
                        <Link href="/" class="flex items-center gap-3.5 group">
                            <div class="w-12 h-12 rounded-2xl bg-white flex items-center justify-center p-1.5 shadow-md border border-[#E2DDD2] group-hover:scale-105 group-hover:shadow-lg transition-all shrink-0">
                                <ApplicationLogo size="sm" alt="Nook Mate" />
                            </div>
                            <div class="flex flex-col text-right">
                                <div dir="ltr" class="flex items-center gap-1.5 tracking-tight font-black text-2xl font-montserrat">
                                    <span class="text-[#163E3E]">Nook</span><span class="text-[#F7C33D]">Mate</span>
                                </div>
                                <span class="text-[10px] font-medium text-[#5B6770] font-poppins -mt-0.5">اعثر على مساحتك</span>
                            </div>
                        </Link>

                        <!-- Desktop Navigation Links -->
                        <nav class="hidden md:flex items-center gap-7 text-[15px] font-semibold text-[#5B6770]">
                            <a href="#concept" class="hover:text-[#163E3E] transition-colors">نظام الصناديق المتداخلة</a>
                            <a href="#features" class="hover:text-[#163E3E] transition-colors">المميزات</a>
                            <a href="#how-it-works" class="hover:text-[#163E3E] transition-colors">كيف يعمل</a>
                            <a href="#pricing" class="hover:text-[#163E3E] transition-colors">الأسعار</a>
                            <a href="#faq" class="hover:text-[#163E3E] transition-colors">الأسئلة الشائعة</a>
                            <a href="#contact" class="hover:text-[#163E3E] transition-colors">تواصل معنا</a>
                        </nav>
                    </div>

                    <!-- Auth Actions -->
                    <div class="hidden sm:flex items-center gap-4">
                        <template v-if="$page.props.auth?.user">
                            <Link
                                :href="route('dashboard')"
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#163E3E] text-white font-bold text-sm shadow-sm hover:bg-[#102F2F] transition"
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
                                class="px-4 py-2.5 rounded-xl text-sm font-bold text-[#163E3E] hover:bg-[#163E3E]/10 transition"
                            >
                                تسجيل الدخول
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="px-5 py-2.5 rounded-xl text-sm font-bold bg-[#163E3E] text-white hover:bg-[#102F2F] shadow-sm hover:shadow transition"
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
                            class="p-2 rounded-lg text-[#5B6770] hover:text-[#163E3E] hover:bg-white/60 focus:outline-none"
                        >
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path v-if="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Mobile Menu Dropdown -->
                <div v-show="mobileMenuOpen" class="sm:hidden border-t border-[#E2DDD2] py-4 space-y-3">
                    <a @click="mobileMenuOpen = false" href="#concept" class="block px-3 py-2 text-base font-semibold text-[#5B6770] hover:bg-white/60 rounded-lg">نظام الصناديق المتداخلة</a>
                    <a @click="mobileMenuOpen = false" href="#features" class="block px-3 py-2 text-base font-semibold text-[#5B6770] hover:bg-white/60 rounded-lg">المميزات</a>
                    <a @click="mobileMenuOpen = false" href="#how-it-works" class="block px-3 py-2 text-base font-semibold text-[#5B6770] hover:bg-white/60 rounded-lg">كيف يعمل</a>
                    <a @click="mobileMenuOpen = false" href="#pricing" class="block px-3 py-2 text-base font-semibold text-[#5B6770] hover:bg-white/60 rounded-lg">الأسعار</a>
                    <a @click="mobileMenuOpen = false" href="#faq" class="block px-3 py-2 text-base font-semibold text-[#5B6770] hover:bg-white/60 rounded-lg">الأسئلة الشائعة</a>
                    <div class="pt-3 border-t border-[#E2DDD2] flex flex-col gap-2">
                        <Link v-if="canLogin" :href="route('login')" class="w-full text-center py-2.5 text-sm font-bold text-[#163E3E] bg-white rounded-xl">تسجيل الدخول</Link>
                        <Link v-if="canRegister" :href="route('register')" class="w-full text-center py-2.5 text-sm font-bold text-white bg-[#163E3E] rounded-xl">ابدأ مجانًا</Link>
                    </div>
                </div>
            </div>
        </header>

        <!-- ========================================== -->
        <!-- 2. HERO SECTION                            -->
        <!-- ========================================== -->
        <section class="overflow-hidden bg-[#F8F4EA]">
            <div class="relative">
                <img
                    src="/images/banner.png"
                    alt="منصة Nook Mate لإدارة العقارات على الحاسوب والهاتف"
                    fetchpriority="high"
                    class="block aspect-[16/9] w-full object-cover object-[76%_center] lg:aspect-[1983/793] lg:object-fill"
                />

                <div class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 lg:absolute lg:inset-0 lg:flex lg:items-center lg:px-8 lg:py-0" dir="ltr">
                    <div class="max-w-xl text-right" dir="rtl">
                        <h1 class="mb-4 text-3xl font-extrabold leading-[1.2] tracking-tight text-[#163E3E] sm:text-5xl lg:text-6xl font-montserrat">
                            اعثر على مساحتك
                            <span class="block text-[#F7C33D] mt-1">واستأجر ما تحتاج إليه</span>
                        </h1>

                        <p class="mb-7 max-w-2xl text-base font-normal leading-relaxed text-[#334155] sm:text-lg">
                            نظام متكامل لإدارة وتأجير العقارات: شقق كاملة، غرف مستقلة، وأسرة مفردة بعقود وسندات قبض وتذكيرات واتساب تلقائية.
                        </p>

                        <div class="mb-8 flex w-full flex-col items-stretch gap-3 sm:w-auto sm:flex-row sm:items-center">
                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="inline-flex items-center justify-center gap-3 rounded-xl bg-[#163E3E] px-8 py-4 text-base font-bold text-white shadow-lg shadow-[#163E3E]/20 transition hover:bg-[#102F2F] hover:shadow-md active:scale-[0.99]"
                            >
                                <span>ابدأ تجربتك المجانية</span>
                                <svg class="h-5 w-5 rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M5 12h14M12 5l7 7-7 7"/>
                                </svg>
                            </Link>
                            <a
                                href="#concept"
                                class="inline-flex items-center justify-center gap-2.5 rounded-xl border border-[#E2DDD2] bg-white px-7 py-4 text-base font-bold text-[#163E3E] shadow-sm transition hover:border-[#C5BCAC] hover:bg-[#F8F4EA]"
                            >
                                <svg class="h-5 w-5 text-[#F7C33D]" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                                <span>شاهد كيف يعمل</span>
                            </a>
                        </div>

                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- 3. CONCEPT & AUDIENCE SECTION              -->
        <!-- ========================================== -->
        <section id="concept" class="py-16 bg-white border-y border-[#E2DDD2]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-3xl mx-auto mb-12">
                    <span class="inline-block px-3.5 py-1 rounded-full bg-[#163E3E]/10 text-[#163E3E] text-xs font-bold mb-3 font-poppins">
                        نظام الصناديق المتداخلة
                    </span>
                    <h2 class="text-2xl sm:text-4xl font-extrabold text-[#163E3E] mb-3">
                        استأجر وأدر المساحة التي تحتاجها بالضبط
                    </h2>
                    <p class="text-base text-[#5B6770]">
                        من الشقة الكاملة إلى الغرفة المستقلة وحتى السرير الفردي، يمنحك <bdi dir="ltr">Nook Mate</bdi> مرونة تأجير متناهية الدقة.
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- الشقق الكاملة -->
                    <div class="p-6 rounded-2xl bg-[#163E3E]/5 border border-[#163E3E]/20 hover:border-[#163E3E] transition hover:shadow-md group">
                        <div class="w-12 h-12 rounded-xl bg-[#163E3E] text-white flex items-center justify-center mb-5 shadow-sm group-hover:scale-105 transition-transform">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 21h18M9 8h1M9 12h1M9 16h1M14 8h1M14 12h1M14 16h1M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/>
                            </svg>
                        </div>
                        <div class="flex items-center justify-between mb-1">
                            <h3 class="text-lg font-bold text-[#163E3E]">الشقق الكاملة</h3>
                        </div>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            تأجير الشقق والعمارات السكنية بالكامل بعقد موحد وإيصالات سداد شهرية أو سنوية.
                        </p>
                    </div>

                    <!-- الغرف المستقلة -->
                    <div class="p-6 rounded-2xl bg-[#F8F4EA] border border-[#E2DDD2] hover:border-[#163E3E] transition hover:shadow-md group">
                        <div class="w-12 h-12 rounded-xl bg-white text-[#163E3E] border border-[#E2DDD2] flex items-center justify-center mb-5 shadow-sm group-hover:scale-105 transition-transform">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                            </svg>
                        </div>
                        <div class="flex items-center justify-between mb-1">
                            <h3 class="text-lg font-bold text-[#163E3E]">الغرف المستقلة</h3>
                        </div>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            تقسيم الشقق الكبيرة وتأجير الغرف المنفصلة مع إدارة تكاليف المرافق المشتركة بسهولة.
                        </p>
                    </div>

                    <!-- التأجير بالسرير -->
                    <div class="p-6 rounded-2xl bg-[#FEF7E6] border border-[#F7C33D]/30 hover:border-[#F7C33D] transition hover:shadow-md group">
                        <div class="w-12 h-12 rounded-xl bg-[#F7C33D] text-[#163E3E] flex items-center justify-center mb-5 shadow-sm group-hover:scale-105 transition-transform">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 17h20M6 8v9"/>
                            </svg>
                        </div>
                        <div class="flex items-center justify-between mb-1">
                            <h3 class="text-lg font-bold text-[#163E3E]">التأجير بالسرير</h3>
                        </div>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            نظام متطور لسكن الطلاب والشباب، يتيح حجز ومتابعة كل سرير بعقد مستقل وتنبيه مخصص.
                        </p>
                    </div>

                    <!-- الأتمتة والذكاء -->
                    <div class="p-6 rounded-2xl bg-[#163E3E]/5 border border-[#163E3E]/20 hover:border-[#163E3E] transition hover:shadow-md group">
                        <div class="w-12 h-12 rounded-xl bg-[#163E3E] text-white flex items-center justify-center mb-5 shadow-sm group-hover:scale-105 transition-transform">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M12 2a14.5 14.5 0 0 0 0 20M2 12h20"/>
                            </svg>
                        </div>
                        <div class="flex items-center justify-between mb-1">
                            <h3 class="text-lg font-bold text-[#163E3E]">الأتمتة والذكاء</h3>
                        </div>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            إشعارات واتساب تلقائية، سندات قبض إلكترونية مشفرة، وتتبع مستمر للتدفقات النقدية.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- 4. FEATURES SECTION (المميزات)             -->
        <!-- ========================================== -->
        <section id="features" class="py-20 bg-[#F8F4EA]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <span class="inline-block px-3.5 py-1 rounded-full bg-[#163E3E]/10 text-[#163E3E] text-xs font-bold mb-3">
                        كفاءة ودقة متناهية
                    </span>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-[#163E3E] mb-4">
                        كل الأدوات التي تحتاجها لتشغيل عقاراتك بسلاسة
                    </h2>
                    <p class="text-base text-[#5B6770]">
                        ودّع جداول الحساب المعقدة والأوراق الضائعة، <bdi dir="ltr">Nook Mate</bdi> يضع محفظتك العقارية تحت سيطرتك بنقرات بسيطة.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- Feature 1: هيكلة مرنة ومتعددة المستويات -->
                    <div class="bg-white p-7 rounded-2xl border border-[#E2DDD2] hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-[#163E3E]/10 text-[#163E3E] flex items-center justify-center mb-6">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="18" height="18" rx="2"/>
                                <path d="M3 9h18M9 21V9"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-[#163E3E] mb-3">هيكلة وحدات مرنة</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            أضف عقارك وقسمه حسب نموذجك الواقعي: شقة كاملة، أو غرف مستقلة، أو أسرة مفردة، مع تتبع الإشغال والشاغر لحظيًا.
                        </p>
                    </div>

                    <!-- Feature 2: تذكيرات واتساب الذكية -->
                    <div class="bg-white p-7 rounded-2xl border border-[#E2DDD2] hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-6">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.664-.698c.969.587 1.771.888 2.796.888 3.182 0 5.767-2.587 5.767-5.766.001-3.181-2.585-5.775-5.767-5.775zm3.366 8.163c-.141.399-.824.773-1.144.821-.309.047-.706.071-2.072-.497-1.745-.726-2.871-2.493-2.958-2.609-.088-.115-.706-.94-.706-1.792 0-.853.444-1.272.603-1.446.159-.175.347-.219.462-.219.116 0 .232.001.332.006.106.005.249-.04.389.297.144.347.491 1.2.534 1.288.043.088.072.19.014.306-.058.115-.087.189-.173.291-.087.101-.183.226-.261.303-.087.087-.178.181-.077.354.101.173.449.741.963 1.199.662.59 1.221.773 1.394.86.173.087.275.073.376-.044.101-.116.433-.506.549-.68.116-.173.231-.144.389-.087.159.058 1.011.477 1.184.564.173.087.289.13.332.203.043.072.043.419-.098.818z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-[#163E3E] mb-3">تنبيهات واتساب الآلية</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            وفّر الحرج والوقت في الاتصالات. المنصة ترسل فواتير وإشعارات مهذبة للمستأجر على واتساب تلقائيًا قبل موعد الاستحقاق ومتابعة المتأخرات.
                        </p>
                    </div>

                    <!-- Feature 3: تتبع العقود والتجديدات -->
                    <div class="bg-white p-7 rounded-2xl border border-[#E2DDD2] hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-[#163E3E]/10 text-[#163E3E] flex items-center justify-center mb-6">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                                <polyline points="10 9 9 9 8 9"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-[#163E3E] mb-3">أرشفة العقود والتجديد</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            إدارة تواريخ بداية ونهاية العقود، وقيم التأمين، مع تنبيهات مسبقة قبل 30 أو 60 يومًا من انتهاء العقد لتجديده أو إعادة عرض الوحدة.
                        </p>
                    </div>

                    <!-- Feature 4: التحصيل وكشوف الحساب -->
                    <div class="bg-white p-7 rounded-2xl border border-[#E2DDD2] hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-[#FEF7E6] text-[#D97706] flex items-center justify-center mb-6">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="1" x2="12" y2="23"/>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-[#163E3E] mb-3">سجل الدفعات والمستحقات</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            تسجيل المدفوعات الجزئية والكاملة، طباعة إيصالات السداد، وتصدير كشف حساب شامل لكل مستأجر بضغطة زر واحدة.
                        </p>
                    </div>

                    <!-- Feature 5: إدارة المصروفات والصيانة -->
                    <div class="bg-white p-7 rounded-2xl border border-[#E2DDD2] hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center mb-6">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-[#163E3E] mb-3">طلبات الصيانة والمصروفات</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            استقبل بلاغات الصيانة، وسجّل فواتير السباكة والكهرباء والإصلاحات لتخصم تلقائيًا وتظهر صافي عائد كل عقار بدقة.
                        </p>
                    </div>

                    <!-- Feature 6: تقارير مالية وتحليلات إشغال -->
                    <div class="bg-white p-7 rounded-2xl border border-[#E2DDD2] hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-[#163E3E]/10 text-[#163E3E] flex items-center justify-center mb-6">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="20" x2="18" y2="10"/>
                                <line x1="12" y1="20" x2="12" y2="4"/>
                                <line x1="6" y1="20" x2="6" y2="14"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-[#163E3E] mb-3">تقارير مالية وإحصائيات</h3>
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
        <section id="how-it-works" class="py-20 bg-white border-t border-[#E2DDD2]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <span class="inline-block px-3.5 py-1 rounded-full bg-[#163E3E]/10 text-[#163E3E] text-xs font-bold mb-3">
                        بساطة في 3 خطوات
                    </span>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-[#163E3E] mb-4">
                        ابدأ تشغيل عقاراتك خلال دقائق معدودة
                    </h2>
                    <p class="text-base text-[#5B6770]">
                        تصميم سلس وبسيط لا يتطلب أي خبرة تقنية سابقة.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 relative">
                    <!-- Step 1 -->
                    <div class="p-8 rounded-2xl bg-[#F8F4EA] border border-[#E2DDD2] relative flex flex-col items-start">
                        <div class="w-10 h-10 rounded-xl bg-[#163E3E] text-white font-extrabold text-lg flex items-center justify-center mb-6 shadow-sm">
                            1
                        </div>
                        <h3 class="text-xl font-bold text-[#163E3E] mb-3">أضف عقاراتك ووحداتك</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed mb-4">
                            سجّل أسماء العمارات أو الشقق، وحدد الغرف والأسرة وقيمة الإيجار المستهدفة.
                        </p>
                        <div class="mt-auto pt-4 text-xs font-bold text-[#163E3E] flex items-center gap-1.5">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M12 8v8M8 12h8"/>
                            </svg>
                            <span>إضافة سريعة بدون تعقيد</span>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="p-8 rounded-2xl bg-[#F8F4EA] border border-[#E2DDD2] relative flex flex-col items-start">
                        <div class="w-10 h-10 rounded-xl bg-[#F7C33D] text-[#163E3E] font-black text-lg flex items-center justify-center mb-6 shadow-sm">
                            2
                        </div>
                        <h3 class="text-xl font-bold text-[#163E3E] mb-3">سجّل عقود المستأجرين</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed mb-4">
                            أدخل بيانات المستأجر، رقم الواتساب، فترة العقد، وقيمة الإيجار الشهري وتاريخ يوم الاستحقاق.
                        </p>
                        <div class="mt-auto pt-4 text-xs font-bold text-[#163E3E] flex items-center gap-1.5">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 6L9 17l-5-5"/>
                            </svg>
                            <span>أرشفة وحساب فوري</span>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="p-8 rounded-2xl bg-[#F8F4EA] border border-[#E2DDD2] relative flex flex-col items-start">
                        <div class="w-10 h-10 rounded-xl bg-[#163E3E] text-white font-extrabold text-lg flex items-center justify-center mb-6 shadow-sm">
                            3
                        </div>
                        <h3 class="text-xl font-bold text-[#163E3E] mb-3">تابع التحصيل والإشغال</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed mb-4">
                            يتولى <bdi dir="ltr">Nook Mate</bdi> إرسال تنبيهات واتساب في الموعد المحدد، ويحدث كشوفات السداد ونسب الإشغال آليًا.
                        </p>
                        <div class="mt-auto pt-4 text-xs font-bold text-[#163E3E] flex items-center gap-1.5">
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
        <section id="pricing" class="py-20 bg-[#F8F4EA] border-t border-[#E2DDD2]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <span class="inline-block px-3.5 py-1 rounded-full bg-[#163E3E]/10 text-[#163E3E] text-xs font-bold mb-3">
                        خطط واضحة بدون رسوم خفية
                    </span>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-[#163E3E] mb-4">
                        اختر الباقة المناسبة لحجم أعمالك
                    </h2>
                    <p class="text-base text-[#5B6770]">
                        ابدأ مجانًا بدون بطاقة بنكية، ورقِّ خطتك مع نمو عقاراتك ووحداتك.
                    </p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-stretch">
                    <!-- Plan 1: الباقة المجانية -->
                    <div class="bg-white rounded-3xl p-8 border border-[#E2DDD2] flex flex-col justify-between hover:shadow-md transition">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-xl font-bold text-[#163E3E]">الباقة المجانية</h3>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-[#F8F4EA] text-[#163E3E]">للبدء</span>
                            </div>
                            <p class="text-sm text-[#5B6770] mb-6">مثالية لصغار الملاك الراغبين في إدارة أملاكهم دون تكلفة.</p>
                            
                            <div class="flex items-baseline gap-1 mb-8">
                                <span class="text-4xl font-extrabold text-[#163E3E]">0</span>
                                <span class="text-base font-semibold text-[#5B6770]">ج.م / شهريًا</span>
                            </div>

                            <ul class="space-y-3.5 text-sm font-medium text-[#334155] mb-8">
                                <li class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-[#163E3E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span><strong>حتى 5 وحدات تأجيرية</strong> مجانًا للأبد</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-[#163E3E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>إدارة العقود والمستأجرين</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-[#163E3E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
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
                            class="w-full py-3.5 rounded-xl border border-[#E2DDD2] text-center font-bold text-sm text-[#163E3E] hover:bg-[#F8F4EA] transition"
                        >
                            ابدأ مجانًا الآن
                        </Link>
                    </div>

                    <!-- الباقة الأكثر طلبًا -->
                    <div class="bg-white rounded-3xl p-8 border-2 border-[#163E3E] flex flex-col justify-between shadow-xl relative lg:-translate-y-2">
                        <div class="absolute -top-4 right-8 px-4 py-1 rounded-full bg-[#F7C33D] text-[#163E3E] text-xs font-black shadow-sm">
                            الأكثر طلبًا
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-4 mt-1">
                                <h3 class="text-xl font-bold text-[#163E3E]">باقة الملاك</h3>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-[#FEF7E6] text-[#163E3E]">شاملة</span>
                            </div>
                            <p class="text-sm text-[#5B6770] mb-6">للملاك وأصحاب العمارات الساعين لأتمتة كاملة للتحصيل.</p>
                            
                            <div class="flex items-baseline gap-1 mb-8">
                                <span class="text-4xl font-extrabold text-[#163E3E]">249</span>
                                <span class="text-base font-semibold text-[#5B6770]">ج.م / شهريًا</span>
                            </div>

                            <ul class="space-y-3.5 text-sm font-medium text-[#334155] mb-8">
                                <li class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-[#163E3E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span><strong>عدد وحدات غير محدود</strong></span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-[#163E3E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span><strong>تذكيرات واتساب الآلية</strong> للمستأجرين</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-[#163E3E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>تصدير كشوفات الحساب والتقارير</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-[#163E3E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>إدارة فواتير الصيانة والمصروفات</span>
                                </li>
                            </ul>
                        </div>

                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="w-full py-3.5 rounded-xl bg-[#163E3E] text-center font-bold text-sm text-white hover:bg-[#102F2F] shadow-md transition"
                        >
                            ابدأ باستخدام الباقة
                        </Link>
                    </div>

                    <!-- Plan 3: إدارة الأملاك والشركات -->
                    <div class="bg-white rounded-3xl p-8 border border-[#E2DDD2] flex flex-col justify-between hover:shadow-md transition">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-xl font-bold text-[#163E3E]">إدارة الأملاك</h3>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-[#F8F4EA] text-[#163E3E]">للشركات</span>
                            </div>
                            <p class="text-sm text-[#5B6770] mb-6">لشركات إدارة العقارات والسكن المشترك ذي الحجم الكبير.</p>
                            
                            <div class="flex items-baseline gap-1 mb-8">
                                <span class="text-4xl font-extrabold text-[#163E3E]">599</span>
                                <span class="text-base font-semibold text-[#5B6770]">ج.م / شهريًا</span>
                            </div>

                            <ul class="space-y-3.5 text-sm font-medium text-[#334155] mb-8">
                                <li class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-[#163E3E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>كل مميزات باقة الملاك</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-[#163E3E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>فصل الحسابات لكبار الملاك</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-[#163E3E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>إدارة شاملة لجميع عقاراتك وأملاكك</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-[#163E3E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>دعم فني مخصص عبر واتساب والهاتف</span>
                                </li>
                            </ul>
                        </div>

                        <a
                            href="#contact"
                            class="w-full py-3.5 rounded-xl border border-[#E2DDD2] text-center font-bold text-sm text-[#163E3E] hover:bg-[#F8F4EA] transition"
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
        <section id="faq" class="py-20 bg-white border-t border-[#E2DDD2]">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-14">
                    <span class="inline-block px-3.5 py-1 rounded-full bg-[#163E3E]/10 text-[#163E3E] text-xs font-bold mb-3">
                        إجابات سريعة
                    </span>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-[#163E3E] mb-3">
                        الأسئلة الأكثر شيوعًا
                    </h2>
                    <p class="text-base text-[#5B6770]">
                        كل ما تريد معرفته عن منصة Nook Mate قبل البدء.
                    </p>
                </div>

                <div class="space-y-4">
                    <!-- Q1 -->
                    <div class="border border-[#E2DDD2] rounded-2xl overflow-hidden transition">
                        <button
                            @click="toggleFaq(0)"
                            type="button"
                            class="w-full p-5 text-right flex items-center justify-between gap-4 bg-white hover:bg-[#F8F4EA] transition cursor-pointer"
                        >
                            <span class="text-base font-bold text-[#163E3E]">هل يمكنني استخدام <bdi dir="ltr">Nook Mate</bdi> مجانًا؟</span>
                            <span class="w-7 h-7 rounded-full bg-[#F8F4EA] text-[#163E3E] flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': activeFaq === 0 }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 9l6 6 6-6"/>
                                </svg>
                            </span>
                        </button>
                        <div v-show="activeFaq === 0" class="px-5 pb-5 pt-1 text-sm text-[#5B6770] leading-relaxed border-t border-[#E2DDD2]/60 bg-[#F8F4EA]/50">
                            نعم، يمكنك استخدام الباقة المجانية لإدارة حتى 5 وحدات دون الحاجة إلى بطاقة دفع.
                        </div>
                    </div>

                    <!-- Q2 -->
                    <div class="border border-[#E2DDD2] rounded-2xl overflow-hidden transition">
                        <button
                            @click="toggleFaq(1)"
                            type="button"
                            class="w-full p-5 text-right flex items-center justify-between gap-4 bg-white hover:bg-[#F8F4EA] transition cursor-pointer"
                        >
                            <span class="text-base font-bold text-[#163E3E]">كيف تعمل تذكيرات واتساب التلقائية؟</span>
                            <span class="w-7 h-7 rounded-full bg-[#F8F4EA] text-[#163E3E] flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': activeFaq === 1 }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 9l6 6 6-6"/>
                                </svg>
                            </span>
                        </button>
                        <div v-show="activeFaq === 1" class="px-5 pb-5 pt-1 text-sm text-[#5B6770] leading-relaxed border-t border-[#E2DDD2]/60 bg-[#F8F4EA]/50">
                            ترسل المنصة إشعارًا مهذبًا ومخصصًا للمستأجر عبر واتساب قبل موعد استحقاق الإيجار بأيام محددة تختارها، متضمنًا اسم الوحدة والمبلغ المستحق وتاريخ السداد.
                        </div>
                    </div>

                    <!-- Q3 -->
                    <div class="border border-[#E2DDD2] rounded-2xl overflow-hidden transition">
                        <button
                            @click="toggleFaq(2)"
                            type="button"
                            class="w-full p-5 text-right flex items-center justify-between gap-4 bg-white hover:bg-[#F8F4EA] transition cursor-pointer"
                        >
                            <span class="text-base font-bold text-[#163E3E]">هل يمكنني إدارة التأجير بالسرير أو الغرفة المنفصلة؟</span>
                            <span class="w-7 h-7 rounded-full bg-[#F8F4EA] text-[#163E3E] flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': activeFaq === 2 }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 9l6 6 6-6"/>
                                </svg>
                            </span>
                        </button>
                        <div v-show="activeFaq === 2" class="px-5 pb-5 pt-1 text-sm text-[#5B6770] leading-relaxed border-t border-[#E2DDD2]/60 bg-[#F8F4EA]/50">
                            نعم! صُمم نظام الصناديق المتداخلة بهيكلية متدرجة: عقار أو شقة، ثم غرفة، ثم سرير، مما يتيح لك تأجير كل مساحة بدقة متناهية.
                        </div>
                    </div>

                    <!-- Q4 -->
                    <div class="border border-[#E2DDD2] rounded-2xl overflow-hidden transition">
                        <button
                            @click="toggleFaq(3)"
                            type="button"
                            class="w-full p-5 text-right flex items-center justify-between gap-4 bg-white hover:bg-[#F8F4EA] transition cursor-pointer"
                        >
                            <span class="text-base font-bold text-[#163E3E]">هل بياناتي وعقود مستأجريّ آمنة؟</span>
                            <span class="w-7 h-7 rounded-full bg-[#F8F4EA] text-[#163E3E] flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': activeFaq === 3 }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 9l6 6 6-6"/>
                                </svg>
                            </span>
                        </button>
                        <div v-show="activeFaq === 3" class="px-5 pb-5 pt-1 text-sm text-[#5B6770] leading-relaxed border-t border-[#E2DDD2]/60 bg-[#F8F4EA]/50">
                            نعتمد على سيرفرات مشفرة بالكامل ونسخ احتياطي يومي، مع عزل تام لبيانات كل مساحة عمل لضمان سرية وخصوصية سجلاتك المالية وعقودك.
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- 8. الدعوة الختامية والتذييل                -->
        <!-- ========================================== -->
        <section id="contact" class="py-16 bg-[#163E3E] text-white relative overflow-hidden">
            <div class="absolute inset-0 bg-radial-gradient from-[#F7C33D]/20 to-transparent pointer-events-none"></div>
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
                <span class="inline-block px-4 py-1 rounded-full bg-white/10 text-[#F8F4EA] border border-white/20 text-xs font-bold mb-4 font-poppins">
                    اعثر على مساحتك واستأجر ما تحتاج إليه
                </span>
                <h2 class="text-3xl sm:text-5xl font-extrabold mb-6 leading-tight font-montserrat">
                    جاهز لتنظيم عقاراتك وضمان تحصيلك في الموعد؟
                </h2>
                <p class="text-base sm:text-lg text-[#F8F4EA]/80 max-w-2xl mx-auto mb-10">
                    ابدأ تجربتك المجانية اليوم واكتشف كيف يُبسّط <bdi dir="ltr">Nook Mate</bdi> كل تفاصيل إدارة وتأجير العقارات.
                </p>
                <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="w-full sm:w-auto px-8 py-4 rounded-xl bg-[#F7C33D] text-[#163E3E] font-black text-base hover:bg-[#e5b12a] shadow-lg transition"
                    >
                        ابدأ مجانًا حتى 5 وحدات
                    </Link>
                    <a
                        href="mailto:contact@nookmate.com"
                        class="w-full sm:w-auto px-7 py-4 rounded-xl border border-white/20 hover:bg-white/10 text-white font-bold text-base transition"
                    >
                        تحدث مع فريقنا
                    </a>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="bg-[#102F2F] text-[#F8F4EA]/80 py-12 border-t border-white/10 text-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-10">
                    <!-- Brand info -->
                    <div class="md:col-span-2 space-y-4">
                        <div class="flex items-center gap-3.5">
                            <div class="w-12 h-12 rounded-2xl bg-[#F8F4EA] flex items-center justify-center p-1.5 shadow-md ring-2 ring-white/10 shrink-0">
                                <ApplicationLogo size="sm" alt="Nook Mate" />
                            </div>
                            <div class="flex flex-col text-right">
                                <div dir="ltr" class="flex items-center gap-1.5 tracking-tight font-black text-2xl font-montserrat">
                                    <span class="text-white">Nook</span><span class="text-[#F7C33D]">Mate</span>
                                </div>
                                <span class="text-[10px] font-medium text-[#F8F4EA]/70 font-poppins">اعثر على مساحتك واستأجر ما تحتاج إليه</span>
                            </div>
                        </div>
                        <p class="text-xs text-[#F8F4EA]/70 leading-relaxed max-w-sm">
                            منصة متكاملة لإدارة وتأجير العقارات السكنية، الشقق المشتركة، سكن الطلاب، وإدارة الأملاك، مع أتمتة تنبيهات واتساب وحسابات الإشغال.
                        </p>
                    </div>

                    <!-- Links -->
                    <div>
                        <h4 class="text-white font-bold mb-4 text-sm font-poppins">روابط سريعة</h4>
                        <ul class="space-y-2.5 text-xs">
                            <li><a href="#concept" class="hover:text-[#F7C33D] transition">نظام الصناديق المتداخلة</a></li>
                            <li><a href="#features" class="hover:text-[#F7C33D] transition">المميزات</a></li>
                            <li><a href="#how-it-works" class="hover:text-[#F7C33D] transition">كيف يعمل</a></li>
                            <li><a href="#pricing" class="hover:text-[#F7C33D] transition">الأسعار</a></li>
                            <li><a href="#faq" class="hover:text-[#F7C33D] transition">الأسئلة الشائعة</a></li>
                        </ul>
                    </div>

                    <!-- Legal / Support -->
                    <div>
                        <h4 class="text-white font-bold mb-4 text-sm font-poppins">الدعم والقانونية</h4>
                        <ul class="space-y-2.5 text-xs">
                            <li><a href="#" class="hover:text-[#F7C33D] transition">شروط الاستخدام</a></li>
                            <li><a href="#" class="hover:text-[#F7C33D] transition">سياسة الخصوصية</a></li>
                            <li><a href="mailto:support@nookmate.com" class="hover:text-[#F7C33D] transition">الدعم الفني</a></li>
                        </ul>
                    </div>
                </div>

                <div class="pt-8 border-t border-white/10 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs font-poppins">
                    <span>جميع الحقوق محفوظة © 2026 منصة <bdi dir="ltr">Nook Mate</bdi>. اعثر على مساحتك واستأجر ما تحتاج إليه.</span>
                    <span class="text-[#F8F4EA]/60">نظام الصناديق المتداخلة</span>
                </div>
            </div>
        </footer>
    </div>
</template>
