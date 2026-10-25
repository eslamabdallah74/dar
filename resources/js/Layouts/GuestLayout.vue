<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import GlobalToast from '@/Components/Common/GlobalToast.vue';

withDefaults(defineProps<{
    imageBackground?: boolean;
}>(), {
    imageBackground: false,
});
</script>

<template>
    <div
        class="relative flex min-h-svh flex-col items-center justify-center px-4 text-right font-sans text-zinc-100 selection:bg-[#F7C33D] selection:text-[#163E3E]"
        :class="imageBackground ? 'py-3 sm:py-6' : 'min-h-screen bg-[#112626] py-12'"
        dir="rtl"
    >
        <div v-if="imageBackground" class="pointer-events-none fixed inset-0 z-0">
            <img
                src="/images/login-register-bg.png"
                alt=""
                aria-hidden="true"
                class="h-full w-full object-cover object-[68%_center]"
            />
            <div class="absolute inset-0 bg-[#163E3E]/60 backdrop-blur-2xs"></div>
        </div>

        <div v-else class="pointer-events-none absolute inset-0 -z-10 overflow-hidden opacity-30">
            <div class="absolute top-0 right-1/2 translate-x-1/2 w-[800px] h-[400px] bg-gradient-to-b from-[#F7C33D]/20 via-[#163E3E]/30 to-transparent blur-3xl"></div>
            <div class="absolute inset-0 bg-[linear-gradient(to_right,#163E3E_1px,transparent_1px),linear-gradient(to_bottom,#163E3E_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)]"></div>
        </div>

        <div class="relative z-10 flex w-full flex-col items-center">
            <div class="flex items-center justify-center gap-3" :class="imageBackground ? 'mb-2 sm:mb-4' : 'mb-6'">
                <Link
                    href="/"
                    aria-label="الرجوع للرئيسية"
                    title="الرجوع للرئيسية"
                    class="group inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-white/20 bg-white/10 text-[#F8F4EA] shadow-lg shadow-black/20 backdrop-blur-md transition duration-200 hover:-translate-y-0.5 hover:border-[#F7C33D]/70 hover:bg-[#F7C33D] hover:text-[#163E3E] hover:shadow-[#F7C33D]/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#F7C33D] focus-visible:ring-offset-2 focus-visible:ring-offset-[#163E3E] sm:h-9 sm:w-9"
                >
                    <ArrowRight class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" />
                </Link>
                <Link href="/" class="group flex items-center" :class="imageBackground ? 'gap-3 sm:gap-4' : 'gap-4'">
                    <div
                        class="flex shrink-0 items-center justify-center rounded-2xl bg-[#F8F4EA] p-2.5 shadow-xl shadow-black/20 ring-4 ring-white/10 transition-all group-hover:scale-105 group-hover:ring-[#F7C33D]/40"
                        :class="imageBackground ? 'h-12 w-12 sm:h-16 sm:w-16' : 'h-16 w-16 sm:h-20 sm:w-20'"
                    >
                        <ApplicationLogo size="lg" alt="Nook Mate" />
                    </div>
                    <div class="text-right">
                        <div dir="ltr" class="flex items-center gap-1 font-montserrat text-2xl font-black tracking-tight text-white sm:text-3xl">
                            <span>Nook</span><span class="text-[#F7C33D]">Mate</span>
                        </div>
                        <p class="text-xs font-medium text-[#F8F4EA]/80 sm:text-sm font-poppins">اعثر على مساحتك واستأجر ما تحتاج إليه</p>
                    </div>
                </Link>
            </div>

            <div
                class="w-full max-w-md overflow-hidden rounded-2xl shadow-2xl backdrop-blur-xl"
                :class="imageBackground
                    ? 'border border-white/20 bg-slate-950/80 p-4 shadow-black/40 sm:p-7'
                    : 'border border-[#163E3E] bg-[#163E3E]/90 p-6 shadow-black/80 sm:p-8'"
            >
                <slot />
            </div>

            <div
                class="text-center text-xs font-medium font-poppins"
                :class="imageBackground ? 'mt-3 text-[#F8F4EA]/80 sm:mt-4' : 'mt-8 text-[#F8F4EA]/60'"
            >
                Nook Mate • اعثر على مساحتك واستأجر ما تحتاج إليه
            </div>
        </div>

        <!-- Global Toast Center -->
        <GlobalToast />
    </div>
</template>
