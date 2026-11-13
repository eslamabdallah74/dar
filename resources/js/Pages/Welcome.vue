<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import LanguageSwitcher from '@/Components/Common/LanguageSwitcher.vue';
import { useLocale } from '@/composables/useLocale';

defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
    laravelVersion?: string;
    phpVersion?: string;
}>();

const { dir, isRtl } = useLocale();

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
        <title>{{ isRtl ? 'Nook Mate — إدارة ممتلكاتك، بكل سهولة' : 'Nook Mate — Smart Rental & Property Management' }}</title>
        <meta name="description" :content="isRtl ? 'Nook Mate — إدارة ممتلكاتك، بكل سهولة. منصة سحابية لإدارة وتأجير العقارات والشقق والغرف والأسرة بنظام الصناديق المتداخلة.' : 'Nook Mate — Smart property and rental management platform for apartments, rooms, and student housing.'" />
        <meta property="og:title" :content="isRtl ? 'Nook Mate — إدارة ممتلكاتك، بكل سهولة' : 'Nook Mate — Smart Rental & Property Management'" />
        <meta property="og:description" :content="isRtl ? 'أدر عقاراتك، وحداتك، غرفك وأسرتك مع تذكيرات واتساب التلقائية وسندات القبض الفورية.' : 'Manage properties, units, and leases with automated WhatsApp reminders and instant receipts.'" />
        <meta property="og:image" content="/images/logo.png" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" :content="isRtl ? 'Nook Mate — إدارة ممتلكاتك، بكل سهولة' : 'Nook Mate — Smart Rental & Property Management'" />
        <meta name="twitter:description" :content="isRtl ? 'منصة سحابية متكاملة لإدارة وتأجير العقارات، عقود الإيجار، وسندات القبض مع تذكيرات واتساب.' : 'Comprehensive cloud platform for property management, leases, and receipts with WhatsApp automation.'" />
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
                        "alternateName": isRtl ? "نوك ميت لإدارة العقارات" : "Nook Mate Property Management",
                        "url": "https://nookmate.com",
                        "logo": {
                            "@type": "ImageObject",
                            "url": "/images/logo.png"
                        },
                        "description": isRtl ? "منصة سحابية متكاملة لإدارة وتأجير العقارات السكنية والشقق المشتركة وسكن الطلاب بنظام الصناديق المتداخلة" : "Integrated cloud platform for managing and leasing residential properties, shared apartments, and student housing"
                    },
                    {
                        "@type": "SoftwareApplication",
                        "@id": "https://nookmate.com/#software",
                        "name": "Nook Mate",
                        "applicationCategory": "BusinessApplication",
                        "operatingSystem": "All modern web browsers",
                        "offers": [
                            {
                                "@type": "Offer",
                                "name": isRtl ? "باقة التجربة (Trial)" : "Trial Plan",
                                "price": "0",
                                "priceCurrency": "EGP",
                                "description": isRtl ? "تجربة مجانية لوحدة أو عقار رئيسي واحد مع عقد تأجير نشط وتقسيمات داخلية غير محدودة" : "Free trial for 1 property with 1 active lease and unlimited internal divisions"
                            },
                            {
                                "@type": "Offer",
                                "name": isRtl ? "باقة البداية (Starter)" : "Starter Plan",
                                "price": "99",
                                "priceCurrency": "EGP",
                                "description": isRtl ? "إدارة حتى 3 وحدات وعقارات رئيسية مع عقود تأجير نشطة وتقسيمات داخلية وتذكيرات واتساب" : "Manage up to 3 parent properties with active leases and automated WhatsApp reminders"
                            },
                            {
                                "@type": "Offer",
                                "name": isRtl ? "باقة النمو (Growth)" : "Growth Plan",
                                "price": "199",
                                "priceCurrency": "EGP",
                                "description": isRtl ? "إدارة من 4 إلى 10 وحدات وعقارات رئيسية مع تذكيرات واتساب وإدارة صيانة متقدمة" : "Manage 4 to 10 parent properties with WhatsApp automation and maintenance tracking"
                            },
                            {
                                "@type": "Offer",
                                "name": isRtl ? "باقة المحترفين (Pro)" : "Pro Plan",
                                "price": "399",
                                "priceCurrency": "EGP",
                                "description": isRtl ? "إدارة من 11 إلى 25 وحدة وعقار رئيسي مع تقارير مالية ومحاسبية متقدمة" : "Manage 11 to 25 parent properties with advanced financial and accounting reports"
                            },
                            {
                                "@type": "Offer",
                                "name": isRtl ? "باقة الأعمال (Business)" : "Business Plan",
                                "price": "699",
                                "priceCurrency": "EGP",
                                "description": isRtl ? "إدارة من 26 إلى 50 وحدة وعقار رئيسي مع دعم فني VIP وتقارير ضريبية مفصلة" : "Manage 26 to 50 parent properties with 24/7 VIP technical support and tax reporting"
                            },
                            {
                                "@type": "Offer",
                                "name": isRtl ? "باقة الشركات وكبار الملاك (Enterprise)" : "Enterprise Plan",
                                "price": "0",
                                "priceCurrency": "EGP",
                                "description": isRtl ? "إدارة أكثر من 50 وحدة وعقار رئيسي مع سعة غير محدودة وتسعير مخصص ومدير حساب مخصص" : "Manage 50+ properties with unlimited capacity, dedicated account manager and SLA"
                            }
                        ]
                    },
                    {
                        "@type": "FAQPage",
                        "@id": "https://nookmate.com/#faq",
                        "mainEntity": [
                            {
                                "@type": "Question",
                                "name": isRtl ? "هل يمكنني استخدام Nook Mate مجانًا؟" : "Can I use Nook Mate for free?",
                                "acceptedAnswer": {
                                    "@type": "Answer",
                                    "text": isRtl ? "نعم، يمكنك استخدام الباقة المجانية لإدارة عقار رئيسي واحد مع عقد تأجير نشط واحد، مع إمكانية إضافة عدد غير محدود من الغرف والأسِرّة الداخلية دون الحاجة إلى بطاقة دفع." : "Yes, you can use the free Trial plan to manage one parent property with one active lease and unlimited rooms and beds without a credit card."
                                }
                            },
                            {
                                "@type": "Question",
                                "name": isRtl ? "كيف تعمل تذكيرات واتساب التلقائية؟" : "How do automated WhatsApp reminders work?",
                                "acceptedAnswer": {
                                    "@type": "Answer",
                                    "text": isRtl ? "ترسل المنصة إشعارًا مهذبًا ومخصصًا للمستأجر عبر واتساب قبل موعد استحقاق الإيجار بأيام محددة تختارها، متضمنًا اسم الوحدة والمبلغ المستحق وتاريخ السداد." : "The platform automatically sends polite, customized WhatsApp notifications to tenants before rent is due, detailing the unit name, balance, and due date."
                                }
                            },
                            {
                                "@type": "Question",
                                "name": isRtl ? "هل يمكنني إدارة التأجير بالسرير أو الغرفة المنفصلة؟" : "Can I manage bed or single room rentals?",
                                "acceptedAnswer": {
                                    "@type": "Answer",
                                    "text": isRtl ? "نعم! صُمم نظام الصناديق المتداخلة بهيكلية متدرجة: عقار أو شقة، ثم غرفة، ثم سرير، مما يتيح لك تأجير كل مساحة بدقة متناهية." : "Yes! The Nested Units system is hierarchically structured from property to room down to bed, letting you lease any subdivision precisely."
                                }
                            },
                            {
                                "@type": "Question",
                                "name": isRtl ? "هل بياناتي وعقود مستأجريّ آمنة؟" : "Is my data and my renters' contracts secure?",
                                "acceptedAnswer": {
                                    "@type": "Answer",
                                    "text": isRtl ? "نعتمد على سيرفرات مشفرة بالكامل ونسخ احتياطي يومي، مع عزل تام لبيانات كل مساحة عمل لضمان سرية وخصوصية سجلاتك المالية وعقودك." : "We use fully encrypted servers with daily automated backups and workspace data isolation to protect your financial and contract records."
                                }
                            }
                        ]
                    }
                ]
            }) }}
        </component>
    </Head>

    <div class="min-h-screen bg-[#F8F4EA] text-[#334155] font-sans selection:bg-[#F7C33D] selection:text-[#163E3E]" :dir="dir">
        <!-- ========================================== -->
        <!-- 1. NAVIGATION BAR (Nav)                    -->
        <!-- ========================================== -->
        <header class="sticky top-0 z-50 bg-[#F8F4EA]/95 backdrop-blur-md border-b border-[#E2DDD2] transition-all">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16 sm:h-[70px]">
                    <!-- Brand Logo & Desktop Nav Links -->
                    <div class="flex items-center gap-6 xl:gap-8 min-w-0">
                        <Link href="/" class="flex items-center gap-3 group shrink-0">
                            <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-white flex items-center justify-center p-1.5 shadow-xs border border-[#E2DDD2] group-hover:scale-105 group-hover:shadow-sm transition-all shrink-0">
                                <ApplicationLogo size="sm" alt="Nook Mate" />
                            </div>
                            <div class="flex flex-col text-start">
                                <div dir="ltr" class="flex items-center tracking-tight font-black text-xl sm:text-2xl font-montserrat leading-none">
                                    <span class="text-[#163E3E]">Nook</span><span class="text-[#F7C33D]">Mate</span>
                                </div>
                                <span class="hidden xl:block text-[10px] font-medium text-[#5B6770] font-poppins leading-none mt-1">{{ $t('nav.tagline') }}</span>
                            </div>
                        </Link>

                        <!-- Desktop Navigation Links (Pill links, whitespace-nowrap, hidden below lg) -->
                        <nav class="hidden lg:flex items-center gap-1 xl:gap-1.5 text-xs xl:text-sm font-semibold text-[#5B6770]">
                            <a href="#concept" class="px-2.5 xl:px-3 py-1.5 rounded-lg whitespace-nowrap hover:text-[#163E3E] hover:bg-[#163E3E]/5 transition-all">{{ $t('landing.nav.concept') }}</a>
                            <a href="#features" class="px-2.5 xl:px-3 py-1.5 rounded-lg whitespace-nowrap hover:text-[#163E3E] hover:bg-[#163E3E]/5 transition-all">{{ $t('landing.nav.features') }}</a>
                            <a href="#how-it-works" class="px-2.5 xl:px-3 py-1.5 rounded-lg whitespace-nowrap hover:text-[#163E3E] hover:bg-[#163E3E]/5 transition-all">{{ $t('landing.nav.how_it_works') }}</a>
                            <a href="#pricing" class="px-2.5 xl:px-3 py-1.5 rounded-lg whitespace-nowrap hover:text-[#163E3E] hover:bg-[#163E3E]/5 transition-all">{{ $t('landing.nav.pricing') }}</a>
                            <a href="#faq" class="px-2.5 xl:px-3 py-1.5 rounded-lg whitespace-nowrap hover:text-[#163E3E] hover:bg-[#163E3E]/5 transition-all">{{ $t('landing.nav.faq') }}</a>
                            <a href="#contact" class="px-2.5 xl:px-3 py-1.5 rounded-lg whitespace-nowrap hover:text-[#163E3E] hover:bg-[#163E3E]/5 transition-all">{{ $t('landing.nav.contact') }}</a>
                        </nav>
                    </div>

                    <!-- Desktop Actions: Language Switcher & Auth Buttons -->
                    <div class="hidden lg:flex items-center gap-3 shrink-0">
                        <LanguageSwitcher variant="navbar" />

                        <template v-if="$page.props.auth?.user">
                            <Link
                                :href="route('dashboard')"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#163E3E] text-white font-bold text-xs xl:text-sm shadow-xs hover:bg-[#102F2F] transition whitespace-nowrap"
                            >
                                <span>{{ $t('nav.dashboard') }}</span>
                                <svg aria-hidden="true" class="w-4 h-4" :class="isRtl ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M5 12h14M12 5l7 7-7 7"/>
                                </svg>
                            </Link>
                        </template>
                        <template v-else>
                            <Link
                                v-if="canLogin"
                                :href="route('login')"
                                class="px-3.5 py-2 rounded-xl text-xs xl:text-sm font-bold text-[#163E3E] hover:bg-[#163E3E]/10 transition whitespace-nowrap"
                            >
                                {{ $t('auth.login_btn') }}
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="px-4 py-2 rounded-xl text-xs xl:text-sm font-bold bg-[#163E3E] text-white hover:bg-[#102F2F] shadow-xs hover:shadow transition whitespace-nowrap"
                            >
                                {{ $t('auth.register_btn') }}
                            </Link>
                        </template>
                    </div>

                    <!-- Mobile / Tablet Right Controls (Language Switcher + Hamburger) -->
                    <div class="flex items-center gap-2 lg:hidden">
                        <LanguageSwitcher variant="navbar" />
                        <button
                            @click="mobileMenuOpen = !mobileMenuOpen"
                            type="button"
                            class="p-2 rounded-xl text-[#5B6770] hover:text-[#163E3E] hover:bg-white/70 border border-[#E2DDD2]/60 focus:outline-none transition"
                            aria-label="Toggle navigation menu"
                        >
                            <svg aria-hidden="true" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path v-if="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Mobile & Tablet Menu Dropdown -->
                <div v-show="mobileMenuOpen" class="lg:hidden border-t border-[#E2DDD2] py-4 space-y-1.5">
                    <a @click="mobileMenuOpen = false" href="#concept" class="block px-3.5 py-2.5 text-sm font-semibold text-[#5B6770] hover:text-[#163E3E] hover:bg-white/70 rounded-xl transition">{{ $t('landing.nav.concept') }}</a>
                    <a @click="mobileMenuOpen = false" href="#features" class="block px-3.5 py-2.5 text-sm font-semibold text-[#5B6770] hover:text-[#163E3E] hover:bg-white/70 rounded-xl transition">{{ $t('landing.nav.features') }}</a>
                    <a @click="mobileMenuOpen = false" href="#how-it-works" class="block px-3.5 py-2.5 text-sm font-semibold text-[#5B6770] hover:text-[#163E3E] hover:bg-white/70 rounded-xl transition">{{ $t('landing.nav.how_it_works') }}</a>
                    <a @click="mobileMenuOpen = false" href="#pricing" class="block px-3.5 py-2.5 text-sm font-semibold text-[#5B6770] hover:text-[#163E3E] hover:bg-white/70 rounded-xl transition">{{ $t('landing.nav.pricing') }}</a>
                    <a @click="mobileMenuOpen = false" href="#faq" class="block px-3.5 py-2.5 text-sm font-semibold text-[#5B6770] hover:text-[#163E3E] hover:bg-white/70 rounded-xl transition">{{ $t('landing.nav.faq') }}</a>
                    <a @click="mobileMenuOpen = false" href="#contact" class="block px-3.5 py-2.5 text-sm font-semibold text-[#5B6770] hover:text-[#163E3E] hover:bg-white/70 rounded-xl transition">{{ $t('landing.nav.contact') }}</a>

                    <div class="pt-3 border-t border-[#E2DDD2] flex flex-col gap-2">
                        <template v-if="$page.props.auth?.user">
                            <Link
                                :href="route('dashboard')"
                                class="w-full text-center py-2.5 text-sm font-bold text-white bg-[#163E3E] rounded-xl shadow-xs hover:bg-[#102F2F] transition"
                            >
                                {{ $t('nav.dashboard') }}
                            </Link>
                        </template>
                        <template v-else>
                            <Link v-if="canLogin" :href="route('login')" class="w-full text-center py-2.5 text-sm font-bold text-[#163E3E] bg-white border border-[#E2DDD2] rounded-xl hover:bg-[#F8F4EA] transition">{{ $t('auth.login_btn') }}</Link>
                            <Link v-if="canRegister" :href="route('register')" class="w-full text-center py-2.5 text-sm font-bold text-white bg-[#163E3E] rounded-xl shadow-xs hover:bg-[#102F2F] transition">{{ $t('auth.register_btn') }}</Link>
                        </template>
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
                    src="/images/banner.webp"
                    :alt="$t('landing.hero.banner_alt')"
                    fetchpriority="high"
                    class="block aspect-[16/9] w-full object-cover object-[76%_center] lg:aspect-[1983/793] lg:object-fill"
                />

                <div class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 lg:absolute lg:inset-0 lg:flex lg:items-center lg:px-8 lg:py-0" dir="ltr">
                    <div class="max-w-xl text-start" :dir="dir">
                        <h1 class="mb-4 text-3xl font-extrabold leading-[1.2] tracking-tight text-[#163E3E] sm:text-5xl lg:text-6xl font-montserrat">
                            {{ $t('landing.hero.title_p1') }}
                            <span class="block text-[#F7C33D] mt-1">{{ $t('landing.hero.title_p2') }}</span>
                        </h1>

                        <p class="mb-7 max-w-2xl text-base font-normal leading-relaxed text-[#334155] sm:text-lg">
                            {{ $t('landing.hero.subtitle') }}
                        </p>

                        <div class="mb-8 flex w-full flex-col items-stretch gap-3 sm:w-auto sm:flex-row sm:items-center">
                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="inline-flex items-center justify-center gap-3 rounded-xl bg-[#163E3E] px-8 py-4 text-base font-bold text-white shadow-lg shadow-[#163E3E]/20 transition hover:bg-[#102F2F] hover:shadow-md active:scale-[0.99] whitespace-nowrap"
                            >
                                <span>{{ $t('landing.hero.start_free_trial') }}</span>
                                <svg aria-hidden="true" class="h-5 w-5" :class="isRtl ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M5 12h14M12 5l7 7-7 7"/>
                                </svg>
                            </Link>
                            <a
                                href="#concept"
                                class="inline-flex items-center justify-center gap-2.5 rounded-xl border border-[#E2DDD2] bg-white px-7 py-4 text-base font-bold text-[#163E3E] shadow-xs transition hover:border-[#C5BCAC] hover:bg-[#F8F4EA] whitespace-nowrap"
                            >
                                <svg aria-hidden="true" class="h-5 w-5 text-[#F7C33D]" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                                <span>{{ $t('landing.hero.see_how_it_works') }}</span>
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
                        {{ $t('landing.concept.badge') }}
                    </span>
                    <h2 class="text-2xl sm:text-4xl font-extrabold text-[#163E3E] mb-3">
                        {{ $t('landing.concept.title') }}
                    </h2>
                    <p class="text-base text-[#5B6770]">
                        {{ $t('landing.concept.subtitle') }}
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Full Apartments -->
                    <div class="p-6 rounded-2xl bg-[#163E3E]/5 border border-[#163E3E]/20 hover:border-[#163E3E] transition hover:shadow-md group">
                        <div class="w-12 h-12 rounded-xl bg-[#163E3E] text-white flex items-center justify-center mb-5 shadow-xs group-hover:scale-105 transition-transform">
                            <svg aria-hidden="true" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 21h18M9 8h1M9 12h1M9 16h1M14 8h1M14 12h1M14 16h1M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-[#163E3E] mb-1">{{ $t('landing.concept.full_apartments_title') }}</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            {{ $t('landing.concept.full_apartments_desc') }}
                        </p>
                    </div>

                    <!-- Private Rooms -->
                    <div class="p-6 rounded-2xl bg-[#F8F4EA] border border-[#E2DDD2] hover:border-[#163E3E] transition hover:shadow-md group">
                        <div class="w-12 h-12 rounded-xl bg-white text-[#163E3E] border border-[#E2DDD2] flex items-center justify-center mb-5 shadow-xs group-hover:scale-105 transition-transform">
                            <svg aria-hidden="true" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-[#163E3E] mb-1">{{ $t('landing.concept.private_rooms_title') }}</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            {{ $t('landing.concept.private_rooms_desc') }}
                        </p>
                    </div>

                    <!-- Bed Rentals -->
                    <div class="p-6 rounded-2xl bg-[#FEF7E6] border border-[#F7C33D]/30 hover:border-[#F7C33D] transition hover:shadow-md group">
                        <div class="w-12 h-12 rounded-xl bg-[#F7C33D] text-[#163E3E] flex items-center justify-center mb-5 shadow-xs group-hover:scale-105 transition-transform">
                            <svg aria-hidden="true" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 17h20M6 8v9"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-[#163E3E] mb-1">{{ $t('landing.concept.bed_rentals_title') }}</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            {{ $t('landing.concept.bed_rentals_desc') }}
                        </p>
                    </div>

                    <!-- Automation -->
                    <div class="p-6 rounded-2xl bg-[#163E3E]/5 border border-[#163E3E]/20 hover:border-[#163E3E] transition hover:shadow-md group">
                        <div class="w-12 h-12 rounded-xl bg-[#163E3E] text-white flex items-center justify-center mb-5 shadow-xs group-hover:scale-105 transition-transform">
                            <svg aria-hidden="true" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M12 2a14.5 14.5 0 0 0 0 20M2 12h20"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-[#163E3E] mb-1">{{ $t('landing.concept.automation_title') }}</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            {{ $t('landing.concept.automation_desc') }}
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
                        {{ $t('landing.features.badge') }}
                    </span>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-[#163E3E] mb-4">
                        {{ $t('landing.features.title') }}
                    </h2>
                    <p class="text-base text-[#5B6770]">
                        {{ $t('landing.features.subtitle') }}
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- Feature 1: هيكلة وحدات مرنة -->
                    <div class="bg-white p-7 rounded-2xl border border-[#E2DDD2] hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-[#163E3E]/10 text-[#163E3E] flex items-center justify-center mb-6">
                            <svg aria-hidden="true" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="18" height="18" rx="2"/>
                                <path d="M3 9h18M9 21V9"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-[#163E3E] mb-3">{{ $t('landing.features.structure_title') }}</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            {{ $t('landing.features.structure_desc') }}
                        </p>
                    </div>

                    <!-- Feature 2: تذكيرات واتساب الذكية -->
                    <div class="bg-white p-7 rounded-2xl border border-[#E2DDD2] hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-6">
                            <svg aria-hidden="true" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.664-.698c.969.587 1.771.888 2.796.888 3.182 0 5.767-2.587 5.767-5.766.001-3.181-2.585-5.775-5.767-5.775zm3.366 8.163c-.141.399-.824.773-1.144.821-.309.047-.706.071-2.072-.497-1.745-.726-2.871-2.493-2.958-2.609-.088-.115-.706-.94-.706-1.792 0-.853.444-1.272.603-1.446.159-.175.347-.219.462-.219.116 0 .232.001.332.006.106.005.249-.04.389.297.144.347.491 1.2.534 1.288.043.088.072.19.014.306-.058.115-.087.189-.173.291-.087.101-.183.226-.261.303-.087.087-.178.181-.077.354.101.173.449.741.963 1.199.662.59 1.221.773 1.394.86.173.087.275.073.376-.044.101-.116.433-.506.549-.68.116-.173.231-.144.389-.087.159.058 1.011.477 1.184.564.173.087.289.13.332.203.043.072.043.419-.098.818z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-[#163E3E] mb-3">{{ $t('landing.features.whatsapp_title') }}</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            {{ $t('landing.features.whatsapp_desc') }}
                        </p>
                    </div>

                    <!-- Feature 3: تتبع العقود والتجديدات -->
                    <div class="bg-white p-7 rounded-2xl border border-[#E2DDD2] hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-[#163E3E]/10 text-[#163E3E] flex items-center justify-center mb-6">
                            <svg aria-hidden="true" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                                <polyline points="10 9 9 9 8 9"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-[#163E3E] mb-3">{{ $t('landing.features.contracts_title') }}</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            {{ $t('landing.features.contracts_desc') }}
                        </p>
                    </div>

                    <!-- Feature 4: سجل الدفعات والمستحقات -->
                    <div class="bg-white p-7 rounded-2xl border border-[#E2DDD2] hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-[#FEF7E6] text-[#D97706] flex items-center justify-center mb-6">
                            <svg aria-hidden="true" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="1" x2="12" y2="23"/>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-[#163E3E] mb-3">{{ $t('landing.features.ledger_title') }}</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            {{ $t('landing.features.ledger_desc') }}
                        </p>
                    </div>

                    <!-- Feature 5: طلبات الصيانة والمصروفات -->
                    <div class="bg-white p-7 rounded-2xl border border-[#E2DDD2] hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center mb-6">
                            <svg aria-hidden="true" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-[#163E3E] mb-3">{{ $t('landing.features.maintenance_title') }}</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            {{ $t('landing.features.maintenance_desc') }}
                        </p>
                    </div>

                    <!-- Feature 6: تقارير مالية وإحصائيات -->
                    <div class="bg-white p-7 rounded-2xl border border-[#E2DDD2] hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-[#163E3E]/10 text-[#163E3E] flex items-center justify-center mb-6">
                            <svg aria-hidden="true" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="20" x2="18" y2="10"/>
                                <line x1="12" y1="20" x2="12" y2="4"/>
                                <line x1="6" y1="20" x2="6" y2="14"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-[#163E3E] mb-3">{{ $t('landing.features.reports_title') }}</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed">
                            {{ $t('landing.features.reports_desc') }}
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
                        {{ $t('landing.how_it_works.badge') }}
                    </span>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-[#163E3E] mb-4">
                        {{ $t('landing.how_it_works.title') }}
                    </h2>
                    <p class="text-base text-[#5B6770]">
                        {{ $t('landing.how_it_works.subtitle') }}
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 relative">
                    <!-- Step 1 -->
                    <div class="p-8 rounded-2xl bg-[#F8F4EA] border border-[#E2DDD2] relative flex flex-col items-start">
                        <div class="w-10 h-10 rounded-xl bg-[#163E3E] text-white font-extrabold text-lg flex items-center justify-center mb-6 shadow-xs">
                            1
                        </div>
                        <h3 class="text-xl font-bold text-[#163E3E] mb-3">{{ $t('landing.how_it_works.step1_title') }}</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed mb-4">
                            {{ $t('landing.how_it_works.step1_desc') }}
                        </p>
                        <div class="mt-auto pt-4 text-xs font-bold text-[#163E3E] flex items-center gap-1.5">
                            <svg aria-hidden="true" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M12 8v8M8 12h8"/>
                            </svg>
                            <span>{{ $t('landing.how_it_works.step1_badge') }}</span>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="p-8 rounded-2xl bg-[#F8F4EA] border border-[#E2DDD2] relative flex flex-col items-start">
                        <div class="w-10 h-10 rounded-xl bg-[#F7C33D] text-[#163E3E] font-black text-lg flex items-center justify-center mb-6 shadow-xs">
                            2
                        </div>
                        <h3 class="text-xl font-bold text-[#163E3E] mb-3">{{ $t('landing.how_it_works.step2_title') }}</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed mb-4">
                            {{ $t('landing.how_it_works.step2_desc') }}
                        </p>
                        <div class="mt-auto pt-4 text-xs font-bold text-[#163E3E] flex items-center gap-1.5">
                            <svg aria-hidden="true" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 6L9 17l-5-5"/>
                            </svg>
                            <span>{{ $t('landing.how_it_works.step2_badge') }}</span>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="p-8 rounded-2xl bg-[#F8F4EA] border border-[#E2DDD2] relative flex flex-col items-start">
                        <div class="w-10 h-10 rounded-xl bg-[#163E3E] text-white font-extrabold text-lg flex items-center justify-center mb-6 shadow-xs">
                            3
                        </div>
                        <h3 class="text-xl font-bold text-[#163E3E] mb-3">{{ $t('landing.how_it_works.step3_title') }}</h3>
                        <p class="text-sm text-[#5B6770] leading-relaxed mb-4">
                            {{ $t('landing.how_it_works.step3_desc') }}
                        </p>
                        <div class="mt-auto pt-4 text-xs font-bold text-[#163E3E] flex items-center gap-1.5">
                            <svg aria-hidden="true" class="w-4 h-4 text-[#25D366]" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.664-.698c.969.587 1.771.888 2.796.888 3.182 0 5.767-2.587 5.767-5.766.001-3.181-2.585-5.775-5.767-5.775zm3.366 8.163c-.141.399-.824.773-1.144.821-.309.047-.706.071-2.072-.497-1.745-.726-2.871-2.493-2.958-2.609-.088-.115-.706-.94-.706-1.792 0-.853.444-1.272.603-1.446.159-.175.347-.219.462-.219.116 0 .232.001.332.006.106.005.249-.04.389.297.144.347.491 1.2.534 1.288.043.088.072.19.014.306-.058.115-.087.189-.173.291-.087.101-.183.226-.261.303-.087.087-.178.181-.077.354.101.173.449.741.963 1.199.662.59 1.221.773 1.394.86.173.087.275.073.376-.044.101-.116.433-.506.549-.68.116-.173.231-.144.389-.087.159.058 1.011.477 1.184.564.173.087.289.13.332.203.043.072.043.419-.098.818z"/>
                            </svg>
                            <span>{{ $t('landing.how_it_works.step3_badge') }}</span>
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
                        {{ $t('landing.pricing.badge') }}
                    </span>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-[#163E3E] mb-4">
                        {{ $t('landing.pricing.title') }}
                    </h2>
                    <p class="text-base text-[#5B6770]">
                        {{ $t('landing.pricing.subtitle') }}
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 items-stretch">
                    <!-- Plan 1: باقة التجربة -->
                    <div class="bg-white rounded-3xl p-8 border border-[#E2DDD2] flex flex-col justify-between hover:shadow-md transition">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-xl font-bold text-[#163E3E]">{{ $t('landing.pricing.trial_name') }}</h3>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-[#F8F4EA] text-[#163E3E]">{{ $t('landing.pricing.trial_tag') }}</span>
                            </div>
                            <p class="text-sm text-[#5B6770] mb-4">{{ $t('landing.pricing.trial_desc') }}</p>
                            
                            <div class="p-3 mb-6 rounded-2xl bg-[#F8F4EA]/70 border border-[#E2DDD2]/60 text-xs text-[#163E3E] font-bold">
                                {{ $t('landing.pricing.trial_cap') }}
                            </div>

                            <div class="flex items-baseline gap-1 mb-8">
                                <span class="text-4xl font-extrabold text-[#163E3E]">0</span>
                                <span class="text-base font-semibold text-[#5B6770]">{{ $t('landing.pricing.free_trial_unit') }}</span>
                            </div>

                            <ul class="space-y-3.5 text-sm font-medium text-[#334155] mb-8">
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.trial_f1') }}</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.trial_f2') }}</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.trial_f3') }}</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.trial_f4') }}</span>
                                </li>
                            </ul>
                        </div>

                        <Link
                            v-if="$page.props.auth?.user"
                            :href="route('subscription.index')"
                            class="w-full py-3.5 rounded-xl border border-[#E2DDD2] text-center font-bold text-sm text-[#163E3E] hover:bg-[#F8F4EA] transition"
                        >
                            {{ $t('landing.pricing.choose_plan') }}
                        </Link>
                        <Link
                            v-else-if="canRegister"
                            :href="route('register', { plan: 'trial' })"
                            class="w-full py-3.5 rounded-xl border border-[#E2DDD2] text-center font-bold text-sm text-[#163E3E] hover:bg-[#F8F4EA] transition"
                        >
                            {{ $t('landing.pricing.start_trial') }}
                        </Link>
                    </div>

                    <!-- Plan 2: باقة البداية -->
                    <div class="bg-white rounded-3xl p-8 border border-[#E2DDD2] flex flex-col justify-between hover:shadow-md transition">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-xl font-bold text-[#163E3E]">{{ $t('landing.pricing.starter_name') }}</h3>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-[#F8F4EA] text-[#163E3E]">{{ $t('landing.pricing.starter_tag') }}</span>
                            </div>
                            <p class="text-sm text-[#5B6770] mb-4">{{ $t('landing.pricing.starter_desc') }}</p>
                            
                            <div class="p-3 mb-6 rounded-2xl bg-[#F8F4EA]/70 border border-[#E2DDD2]/60 text-xs text-[#163E3E] font-bold">
                                {{ $t('landing.pricing.starter_cap') }}
                            </div>

                            <div class="flex items-baseline gap-1 mb-8">
                                <span class="text-4xl font-extrabold text-[#163E3E]">99</span>
                                <span class="text-base font-semibold text-[#5B6770]">{{ $t('landing.pricing.per_month') }}</span>
                            </div>

                            <ul class="space-y-3.5 text-sm font-medium text-[#334155] mb-8">
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.starter_f1') }}</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.starter_f2') }}</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.starter_f3') }}</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.starter_f4') }}</span>
                                </li>
                            </ul>
                        </div>

                        <Link
                            v-if="$page.props.auth?.user"
                            :href="route('subscription.index')"
                            class="w-full py-3.5 rounded-xl border border-[#E2DDD2] text-center font-bold text-sm text-[#163E3E] hover:bg-[#F8F4EA] transition"
                        >
                            {{ $t('landing.pricing.upgrade_plan') }}
                        </Link>
                        <Link
                            v-else-if="canRegister"
                            :href="route('register', { plan: 'starter' })"
                            class="w-full py-3.5 rounded-xl border border-[#E2DDD2] text-center font-bold text-sm text-[#163E3E] hover:bg-[#F8F4EA] transition"
                        >
                            {{ $t('landing.pricing.start_now') }}
                        </Link>
                    </div>

                    <!-- Plan 3: باقة النمو — الأكثر طلباً -->
                    <div class="bg-white rounded-3xl p-8 border-2 border-[#163E3E] flex flex-col justify-between shadow-xl relative lg:-translate-y-2">
                        <div class="absolute -top-4 right-8 px-4 py-1 rounded-full bg-[#F7C33D] text-[#163E3E] text-xs font-black shadow-xs">
                            {{ $t('landing.pricing.most_popular') }}
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-4 mt-1">
                                <h3 class="text-xl font-bold text-[#163E3E]">{{ $t('landing.pricing.growth_name') }}</h3>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-[#FEF7E6] text-[#163E3E]">{{ $t('landing.pricing.growth_tag') }}</span>
                            </div>
                            <p class="text-sm text-[#5B6770] mb-4">{{ $t('landing.pricing.growth_desc') }}</p>
                            
                            <div class="p-3 mb-6 rounded-2xl bg-[#E8F0F0] border border-[#163E3E]/20 text-xs text-[#163E3E] font-bold">
                                {{ $t('landing.pricing.growth_cap') }}
                            </div>

                            <div class="flex items-baseline gap-1 mb-8">
                                <span class="text-4xl font-extrabold text-[#163E3E]">199</span>
                                <span class="text-base font-semibold text-[#5B6770]">{{ $t('landing.pricing.per_month') }}</span>
                            </div>

                            <ul class="space-y-3.5 text-sm font-medium text-[#334155] mb-8">
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.growth_f1') }}</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.growth_f2') }}</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.growth_f3') }}</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.growth_f4') }}</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.growth_f5') }}</span>
                                </li>
                            </ul>
                        </div>

                        <Link
                            v-if="$page.props.auth?.user"
                            :href="route('subscription.index')"
                            class="w-full py-3.5 rounded-xl bg-[#163E3E] text-center font-bold text-sm text-white hover:bg-[#102F2F] shadow-md transition"
                        >
                            {{ $t('landing.pricing.upgrade_plan') }}
                        </Link>
                        <Link
                            v-else-if="canRegister"
                            :href="route('register', { plan: 'growth' })"
                            class="w-full py-3.5 rounded-xl bg-[#163E3E] text-center font-bold text-sm text-white hover:bg-[#102F2F] shadow-md transition"
                        >
                            {{ $t('landing.pricing.start_now') }}
                        </Link>
                    </div>

                    <!-- Plan 4: باقة المحترفين -->
                    <div class="bg-white rounded-3xl p-8 border border-[#E2DDD2] flex flex-col justify-between hover:shadow-md transition">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-xl font-bold text-[#163E3E]">{{ $t('landing.pricing.pro_name') }}</h3>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-[#F8F4EA] text-[#163E3E]">{{ $t('landing.pricing.pro_tag') }}</span>
                            </div>
                            <p class="text-sm text-[#5B6770] mb-4">{{ $t('landing.pricing.pro_desc') }}</p>
                            
                            <div class="p-3 mb-6 rounded-2xl bg-[#F8F4EA]/70 border border-[#E2DDD2]/60 text-xs text-[#163E3E] font-bold">
                                {{ $t('landing.pricing.pro_cap') }}
                            </div>

                            <div class="flex items-baseline gap-1 mb-8">
                                <span class="text-4xl font-extrabold text-[#163E3E]">399</span>
                                <span class="text-base font-semibold text-[#5B6770]">{{ $t('landing.pricing.per_month') }}</span>
                            </div>

                            <ul class="space-y-3.5 text-sm font-medium text-[#334155] mb-8">
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.pro_f1') }}</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.pro_f2') }}</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.pro_f3') }}</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.pro_f4') }}</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.pro_f5') }}</span>
                                </li>
                            </ul>
                        </div>

                        <Link
                            v-if="$page.props.auth?.user"
                            :href="route('subscription.index')"
                            class="w-full py-3.5 rounded-xl border border-[#E2DDD2] text-center font-bold text-sm text-[#163E3E] hover:bg-[#F8F4EA] transition"
                        >
                            {{ $t('landing.pricing.upgrade_plan') }}
                        </Link>
                        <Link
                            v-else-if="canRegister"
                            :href="route('register', { plan: 'pro' })"
                            class="w-full py-3.5 rounded-xl border border-[#E2DDD2] text-center font-bold text-sm text-[#163E3E] hover:bg-[#F8F4EA] transition"
                        >
                            {{ $t('landing.pricing.start_now') }}
                        </Link>
                    </div>

                    <!-- Plan 5: باقة الأعمال -->
                    <div class="bg-white rounded-3xl p-8 border border-[#E2DDD2] flex flex-col justify-between hover:shadow-md transition">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-xl font-bold text-[#163E3E]">{{ $t('landing.pricing.business_name') }}</h3>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-[#F8F4EA] text-[#163E3E]">{{ $t('landing.pricing.business_tag') }}</span>
                            </div>
                            <p class="text-sm text-[#5B6770] mb-4">{{ $t('landing.pricing.business_desc') }}</p>
                            
                            <div class="p-3 mb-6 rounded-2xl bg-[#F8F4EA]/70 border border-[#E2DDD2]/60 text-xs text-[#163E3E] font-bold">
                                {{ $t('landing.pricing.business_cap') }}
                            </div>

                            <div class="flex items-baseline gap-1 mb-8">
                                <span class="text-4xl font-extrabold text-[#163E3E]">699</span>
                                <span class="text-base font-semibold text-[#5B6770]">{{ $t('landing.pricing.per_month') }}</span>
                            </div>

                            <ul class="space-y-3.5 text-sm font-medium text-[#334155] mb-8">
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.business_f1') }}</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.business_f2') }}</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.business_f3') }}</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.business_f4') }}</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.business_f5') }}</span>
                                </li>
                            </ul>
                        </div>

                        <Link
                            v-if="$page.props.auth?.user"
                            :href="route('subscription.index')"
                            class="w-full py-3.5 rounded-xl border border-[#E2DDD2] text-center font-bold text-sm text-[#163E3E] hover:bg-[#F8F4EA] transition"
                        >
                            {{ $t('landing.pricing.upgrade_plan') }}
                        </Link>
                        <Link
                            v-else-if="canRegister"
                            :href="route('register', { plan: 'business' })"
                            class="w-full py-3.5 rounded-xl border border-[#E2DDD2] text-center font-bold text-sm text-[#163E3E] hover:bg-[#F8F4EA] transition"
                        >
                            {{ $t('landing.pricing.start_now') }}
                        </Link>
                    </div>

                    <!-- Plan 6: أكثر من 50 وحدة (تسعير مخصص / Enterprise) -->
                    <div class="bg-white rounded-3xl p-8 border border-[#E2DDD2] flex flex-col justify-between hover:shadow-md transition">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-xl font-bold text-[#163E3E]">{{ $t('landing.pricing.enterprise_name') }}</h3>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-[#F8F4EA] text-[#163E3E]">{{ $t('landing.pricing.enterprise_tag') }}</span>
                            </div>
                            <p class="text-sm text-[#5B6770] mb-4">{{ $t('landing.pricing.enterprise_desc') }}</p>
                            
                            <div class="p-3 mb-6 rounded-2xl bg-[#F8F4EA]/70 border border-[#E2DDD2]/60 text-xs text-[#163E3E] font-bold">
                                {{ $t('landing.pricing.enterprise_cap') }}
                            </div>

                            <div class="flex items-baseline gap-1 mb-8">
                                <span class="text-3xl font-extrabold text-[#163E3E]">{{ $t('landing.pricing.custom_pricing') }}</span>
                                <span class="text-xs font-semibold text-[#5B6770]">{{ $t('landing.pricing.custom_pricing_sub') }}</span>
                            </div>

                            <ul class="space-y-3.5 text-sm font-medium text-[#334155] mb-8">
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.enterprise_f1') }}</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.enterprise_f2') }}</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.enterprise_f3') }}</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.enterprise_f4') }}</span>
                                </li>
                                <li class="flex items-center gap-3">
                                    <svg aria-hidden="true" class="w-4 h-4 text-[#163E3E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span>{{ $t('landing.pricing.enterprise_f5') }}</span>
                                </li>
                            </ul>
                        </div>

                        <Link
                            v-if="$page.props.auth?.user"
                            :href="route('subscription.index')"
                            class="w-full py-3.5 rounded-xl border border-[#E2DDD2] text-center font-bold text-sm text-[#163E3E] hover:bg-[#F8F4EA] transition"
                        >
                            {{ $t('landing.pricing.request_custom') }}
                        </Link>
                        <Link
                            v-else-if="canRegister"
                            :href="route('register', { plan: 'enterprise' })"
                            class="w-full py-3.5 rounded-xl border border-[#E2DDD2] text-center font-bold text-sm text-[#163E3E] hover:bg-[#F8F4EA] transition"
                        >
                            {{ $t('landing.pricing.start_custom') }}
                        </Link>
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
                        {{ $t('landing.faq.badge') }}
                    </span>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-[#163E3E] mb-3">
                        {{ $t('landing.faq.title') }}
                    </h2>
                    <p class="text-base text-[#5B6770]">
                        {{ $t('landing.faq.subtitle') }}
                    </p>
                </div>

                <div class="space-y-4">
                    <!-- Q1 -->
                    <div class="border border-[#E2DDD2] rounded-2xl overflow-hidden transition">
                        <button
                            @click="toggleFaq(0)"
                            type="button"
                            class="w-full p-5 text-start flex items-center justify-between gap-4 bg-white hover:bg-[#F8F4EA] transition cursor-pointer"
                        >
                            <span class="text-base font-bold text-[#163E3E]">{{ $t('landing.faq.q1') }}</span>
                            <span class="w-7 h-7 rounded-full bg-[#F8F4EA] text-[#163E3E] flex items-center justify-center shrink-0">
                                <svg aria-hidden="true" class="w-4 h-4 transition-transform" :class="{ 'rotate-180': activeFaq === 0 }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 9l6 6 6-6"/>
                                </svg>
                            </span>
                        </button>
                        <div v-show="activeFaq === 0" class="px-5 pb-5 pt-1 text-sm text-[#5B6770] leading-relaxed border-t border-[#E2DDD2]/60 bg-[#F8F4EA]/50">
                            {{ $t('landing.faq.a1') }}
                        </div>
                    </div>

                    <!-- Q2 -->
                    <div class="border border-[#E2DDD2] rounded-2xl overflow-hidden transition">
                        <button
                            @click="toggleFaq(1)"
                            type="button"
                            class="w-full p-5 text-start flex items-center justify-between gap-4 bg-white hover:bg-[#F8F4EA] transition cursor-pointer"
                        >
                            <span class="text-base font-bold text-[#163E3E]">{{ $t('landing.faq.q2') }}</span>
                            <span class="w-7 h-7 rounded-full bg-[#F8F4EA] text-[#163E3E] flex items-center justify-center shrink-0">
                                <svg aria-hidden="true" class="w-4 h-4 transition-transform" :class="{ 'rotate-180': activeFaq === 1 }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 9l6 6 6-6"/>
                                </svg>
                            </span>
                        </button>
                        <div v-show="activeFaq === 1" class="px-5 pb-5 pt-1 text-sm text-[#5B6770] leading-relaxed border-t border-[#E2DDD2]/60 bg-[#F8F4EA]/50">
                            {{ $t('landing.faq.a2') }}
                        </div>
                    </div>

                    <!-- Q3 -->
                    <div class="border border-[#E2DDD2] rounded-2xl overflow-hidden transition">
                        <button
                            @click="toggleFaq(2)"
                            type="button"
                            class="w-full p-5 text-start flex items-center justify-between gap-4 bg-white hover:bg-[#F8F4EA] transition cursor-pointer"
                        >
                            <span class="text-base font-bold text-[#163E3E]">{{ $t('landing.faq.q3') }}</span>
                            <span class="w-7 h-7 rounded-full bg-[#F8F4EA] text-[#163E3E] flex items-center justify-center shrink-0">
                                <svg aria-hidden="true" class="w-4 h-4 transition-transform" :class="{ 'rotate-180': activeFaq === 2 }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 9l6 6 6-6"/>
                                </svg>
                            </span>
                        </button>
                        <div v-show="activeFaq === 2" class="px-5 pb-5 pt-1 text-sm text-[#5B6770] leading-relaxed border-t border-[#E2DDD2]/60 bg-[#F8F4EA]/50">
                            {{ $t('landing.faq.a3') }}
                        </div>
                    </div>

                    <!-- Q4 -->
                    <div class="border border-[#E2DDD2] rounded-2xl overflow-hidden transition">
                        <button
                            @click="toggleFaq(3)"
                            type="button"
                            class="w-full p-5 text-start flex items-center justify-between gap-4 bg-white hover:bg-[#F8F4EA] transition cursor-pointer"
                        >
                            <span class="text-base font-bold text-[#163E3E]">{{ $t('landing.faq.q4') }}</span>
                            <span class="w-7 h-7 rounded-full bg-[#F8F4EA] text-[#163E3E] flex items-center justify-center shrink-0">
                                <svg aria-hidden="true" class="w-4 h-4 transition-transform" :class="{ 'rotate-180': activeFaq === 3 }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 9l6 6 6-6"/>
                                </svg>
                            </span>
                        </button>
                        <div v-show="activeFaq === 3" class="px-5 pb-5 pt-1 text-sm text-[#5B6770] leading-relaxed border-t border-[#E2DDD2]/60 bg-[#F8F4EA]/50">
                            {{ $t('landing.faq.a4') }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- 8. CTA SECTION (الدعوة الختامية)           -->
        <!-- ========================================== -->
        <section id="contact" class="py-16 bg-[#163E3E] text-white relative overflow-hidden">
            <div class="absolute inset-0 bg-radial-gradient from-[#F7C33D]/20 to-transparent pointer-events-none"></div>
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
                <span class="inline-block px-4 py-1 rounded-full bg-white/10 text-[#F8F4EA] border border-white/20 text-xs font-bold mb-4 font-poppins">
                    {{ $t('landing.cta.badge') }}
                </span>
                <h2 class="text-3xl sm:text-5xl font-extrabold mb-6 leading-tight font-montserrat">
                    {{ $t('landing.cta.title') }}
                </h2>
                <p class="text-base sm:text-lg text-[#F8F4EA]/80 max-w-2xl mx-auto mb-10">
                    {{ $t('landing.cta.subtitle') }}
                </p>
                <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="w-full sm:w-auto px-8 py-4 rounded-xl bg-[#F7C33D] text-[#163E3E] font-black text-base hover:bg-[#e5b12a] shadow-lg transition whitespace-nowrap"
                    >
                        {{ $t('landing.cta.start_btn') }}
                    </Link>
                    <a
                        href="mailto:contact@nookmate.com"
                        class="w-full sm:w-auto px-7 py-4 rounded-xl border border-white/20 hover:bg-white/10 text-white font-bold text-base transition whitespace-nowrap"
                    >
                        {{ $t('landing.cta.talk_btn') }}
                    </a>
                </div>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- 9. FOOTER (التذييل)                        -->
        <!-- ========================================== -->
        <footer class="bg-[#102F2F] text-[#F8F4EA]/80 py-12 border-t border-white/10 text-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-10">
                    <!-- Brand info -->
                    <div class="md:col-span-2 space-y-4">
                        <div class="flex items-center gap-3.5">
                            <div class="w-10 h-10 rounded-xl bg-[#F8F4EA] flex items-center justify-center p-1.5 shadow-xs ring-2 ring-white/10 shrink-0">
                                <ApplicationLogo size="sm" alt="Nook Mate" />
                            </div>
                            <div class="flex flex-col text-start">
                                <div dir="ltr" class="flex items-center tracking-tight font-black text-xl font-montserrat leading-none">
                                    <span class="text-white">Nook</span><span class="text-[#F7C33D]">Mate</span>
                                </div>
                                <span class="text-[10px] font-medium text-[#F8F4EA]/70 font-poppins mt-1">{{ $t('nav.tagline') }}</span>
                            </div>
                        </div>
                        <p class="text-xs text-[#F8F4EA]/70 leading-relaxed max-w-sm">
                            {{ $t('landing.footer.brand_desc') }}
                        </p>
                    </div>

                    <!-- Links -->
                    <div>
                        <h4 class="text-white font-bold mb-4 text-sm font-poppins">{{ $t('landing.footer.quick_links') }}</h4>
                        <ul class="space-y-2.5 text-xs">
                            <li><a href="#concept" class="hover:text-[#F7C33D] transition">{{ $t('landing.nav.concept') }}</a></li>
                            <li><a href="#features" class="hover:text-[#F7C33D] transition">{{ $t('landing.nav.features') }}</a></li>
                            <li><a href="#how-it-works" class="hover:text-[#F7C33D] transition">{{ $t('landing.nav.how_it_works') }}</a></li>
                            <li><a href="#pricing" class="hover:text-[#F7C33D] transition">{{ $t('landing.nav.pricing') }}</a></li>
                            <li><a href="#faq" class="hover:text-[#F7C33D] transition">{{ $t('landing.nav.faq') }}</a></li>
                        </ul>
                    </div>

                    <!-- Legal / Support -->
                    <div>
                        <h4 class="text-white font-bold mb-4 text-sm font-poppins">{{ $t('landing.footer.legal') }}</h4>
                        <ul class="space-y-2.5 text-xs">
                            <li><a href="#" class="hover:text-[#F7C33D] transition">{{ $t('landing.footer.terms') }}</a></li>
                            <li><a href="#" class="hover:text-[#F7C33D] transition">{{ $t('landing.footer.privacy') }}</a></li>
                            <li><a href="mailto:support@nookmate.com" class="hover:text-[#F7C33D] transition">{{ $t('landing.footer.support') }}</a></li>
                        </ul>
                    </div>
                </div>

                <div class="pt-8 border-t border-white/10 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs font-poppins">
                    <span>{{ $t('landing.footer.rights') }}</span>
                    <span class="text-[#F8F4EA]/60">{{ $t('landing.concept.badge') }}</span>
                </div>
            </div>
        </footer>
    </div>
</template>
