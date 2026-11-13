<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Head, Link, router } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import LanguageSwitcher from '@/Components/Common/LanguageSwitcher.vue';
import { useLocale } from '@/composables/useLocale';
import {
    AlertOctagon,
    ArrowRight,
    Home,
    RotateCcw,
    ShieldAlert,
    HelpCircle,
} from 'lucide-vue-next';

const props = defineProps<{
    status?: number;
    message?: string;
}>();

const { t } = useI18n();
const { dir, isRtl } = useLocale();
const statusCode = computed(() => props.status || 500);

const details = computed(() => {
    switch (statusCode.value) {
        case 404:
            return {
                title: t('errors.404_title'),
                subtitle: t('errors.404_subtitle'),
                description: props.message || t('errors.404_description'),
                icon: HelpCircle,
                badgeColor: 'bg-amber-100 text-amber-900 border-amber-300',
            };
        case 403:
            return {
                title: t('errors.403_title'),
                subtitle: t('errors.403_subtitle'),
                description: props.message || t('errors.403_description'),
                icon: ShieldAlert,
                badgeColor: 'bg-rose-100 text-rose-900 border-rose-300',
            };
        case 503:
            return {
                title: t('errors.503_title'),
                subtitle: t('errors.503_subtitle'),
                description: props.message || t('errors.503_description'),
                icon: RotateCcw,
                badgeColor: 'bg-sky-100 text-sky-900 border-sky-300',
            };
        case 500:
        default:
            return {
                title: t('errors.500_title'),
                subtitle: statusCode.value === 500 ? t('errors.500_subtitle') : `${statusCode.value} - Server Error`,
                description: props.message || t('errors.500_description'),
                icon: AlertOctagon,
                badgeColor: 'bg-rose-100 text-rose-900 border-rose-300',
            };
    }
});

function goBack() {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        router.visit('/');
    }
}

function reloadPage() {
    window.location.reload();
}
</script>

<template>
    <div class="min-h-screen bg-[#F8F4EA] text-[#163E3E] flex flex-col justify-between selection:bg-[#F7C33D] selection:text-[#163E3E] antialiased" :dir="dir">
        <Head :title="details.title" />

        <!-- Top Header Navigation -->
        <header class="w-full max-w-7xl mx-auto px-6 py-6 flex items-center justify-between">
            <Link href="/" class="flex items-center gap-3 group">
                <div class="w-10 h-10 rounded-xl bg-[#163E3E] p-2 flex items-center justify-center shadow-md transition-transform group-hover:scale-105">
                    <ApplicationLogo size="sm" alt="Nook Mate" />
                </div>
                <div>
                    <span class="font-extrabold text-xl text-[#163E3E] tracking-tight">Nook Mate</span>
                    <span class="text-xs text-[#5B6770] block">Financial & Rental Platform</span>
                </div>
            </Link>

            <div class="flex items-center gap-3">
                <LanguageSwitcher variant="navbar" />

                <button
                    type="button"
                    @click="goBack"
                    class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-bold text-[#163E3E] bg-[#E2DDD2]/60 hover:bg-[#E2DDD2] rounded-xl transition-colors cursor-pointer"
                >
                    <ArrowRight class="w-4 h-4 transition-transform" :class="isRtl ? '' : 'rotate-180'" />
                    <span>{{ $t('errors.go_back') }}</span>
                </button>
            </div>
        </header>

        <!-- Main Error Display Card -->
        <main class="flex-1 flex items-center justify-center px-4 py-12">
            <div class="w-full max-w-xl mx-auto bg-white/90 border border-[#E2DDD2] rounded-3xl p-8 sm:p-12 shadow-xl shadow-[#163E3E]/5 text-center relative overflow-hidden backdrop-blur-xs">
                <!-- Background Accent Glow -->
                <div class="absolute -top-24 -right-24 w-48 h-48 bg-[#F7C33D]/20 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute -bottom-24 -left-24 w-48 h-48 bg-rose-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <!-- Status Badge -->
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border text-xs font-extrabold mb-6 tracking-wider uppercase" :class="details.badgeColor">
                    <component :is="details.icon" class="w-4 h-4" />
                    <span>{{ details.subtitle }}</span>
                </div>

                <!-- Large Status Number -->
                <div class="text-7xl sm:text-8xl font-black text-[#163E3E] tracking-tight mb-4 select-none opacity-95">
                    {{ statusCode }}
                </div>

                <!-- Title -->
                <h1 class="text-2xl sm:text-3xl font-extrabold text-[#163E3E] tracking-tight mb-3">
                    {{ details.title }}
                </h1>

                <!-- Description -->
                <p class="text-sm sm:text-base text-[#5B6770] leading-relaxed max-w-md mx-auto mb-8 font-medium">
                    {{ details.description }}
                </p>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                    <Link
                        href="/"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-2xl bg-[#163E3E] hover:bg-[#1C4E4E] text-white font-bold text-sm shadow-lg shadow-[#163E3E]/20 transition-all hover:-translate-y-0.5 cursor-pointer"
                    >
                        <Home class="w-4 h-4 text-[#F7C33D]" />
                        <span>{{ $t('errors.return_home') }}</span>
                    </Link>

                    <button
                        type="button"
                        @click="reloadPage"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-2xl border border-[#E2DDD2] bg-white hover:bg-[#F8F4EA] text-[#163E3E] font-bold text-sm transition-colors cursor-pointer"
                    >
                        <RotateCcw class="w-4 h-4 text-[#5B6770]" />
                        <span>{{ $t('errors.reload_page') }}</span>
                    </button>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="w-full max-w-7xl mx-auto px-6 py-6 text-center text-xs text-[#5B6770]">
            <p>{{ $t('errors.all_rights_reserved') }} &copy; {{ new Date().getFullYear() }}</p>
        </footer>
    </div>
</template>

