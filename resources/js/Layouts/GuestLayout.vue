<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';

withDefaults(defineProps<{
    imageBackground?: boolean;
}>(), {
    imageBackground: false,
});
</script>

<template>
    <div
        class="relative flex min-h-svh flex-col items-center justify-center px-4 text-right font-sans text-zinc-100 selection:bg-emerald-500 selection:text-black"
        :class="imageBackground ? 'py-3 sm:py-6' : 'min-h-screen bg-[#09090b] py-12'"
        dir="rtl"
    >
        <div v-if="imageBackground" class="pointer-events-none fixed inset-0 z-0">
            <img
                src="/images/login-register-bg.png"
                alt=""
                aria-hidden="true"
                class="h-full w-full object-cover object-[68%_center]"
            />
            <div class="absolute inset-0 bg-slate-950/20"></div>
        </div>

        <div v-else class="pointer-events-none absolute inset-0 -z-10 overflow-hidden opacity-30">
            <div class="absolute top-0 right-1/2 translate-x-1/2 w-[800px] h-[400px] bg-gradient-to-b from-emerald-500/20 via-zinc-900/10 to-transparent blur-3xl"></div>
            <div class="absolute inset-0 bg-[linear-gradient(to_right,#27272a_1px,transparent_1px),linear-gradient(to_bottom,#27272a_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)]"></div>
        </div>

        <div class="relative z-10 flex w-full flex-col items-center">
            <div class="flex w-full max-w-md items-center" :class="imageBackground ? 'mb-2 sm:mb-4' : 'mb-6'">
                <Link
                    href="/"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold transition hover:text-white"
                    :class="imageBackground ? 'text-zinc-200' : 'text-zinc-400'"
                >
                    <ArrowRight class="h-3.5 w-3.5" />
                    الرجوع للرئيسية
                </Link>
            </div>

            <div class="flex flex-col items-center text-center" :class="imageBackground ? 'mb-2 sm:mb-4' : 'mb-6'">
                <Link href="/" class="group flex items-center" :class="imageBackground ? 'gap-3 sm:gap-4' : 'gap-4'">
                    <div
                        class="flex shrink-0 items-center justify-center rounded-full bg-white p-2.5 shadow-xl shadow-black/20 ring-4 ring-white/10 transition-all group-hover:scale-105 group-hover:ring-white/25"
                        :class="imageBackground ? 'h-12 w-12 sm:h-16 sm:w-16' : 'h-16 w-16 sm:h-20 sm:w-20'"
                    >
                        <img
                            src="/images/logo.png"
                            alt="شعار دار — لإدارة الإيجار"
                            class="h-full w-full object-contain"
                        />
                    </div>
                    <div class="text-right">
                        <div class="flex items-center gap-1.5 font-sans text-2xl font-bold tracking-tight text-white sm:text-3xl">
                            دار
                        </div>
                        <p class="text-xs font-medium text-zinc-200 sm:text-sm">لإدارة الإيجار والأملاك</p>
                    </div>
                </Link>
            </div>

            <div
                class="w-full max-w-md overflow-hidden rounded-2xl shadow-2xl backdrop-blur-xl"
                :class="imageBackground
                    ? 'border border-white/20 bg-slate-950/80 p-4 shadow-black/40 sm:p-7'
                    : 'border border-zinc-800 bg-zinc-900/80 p-6 shadow-black/80 sm:p-8'"
            >
                <slot />
            </div>

            <div
                class="text-center text-xs font-medium"
                :class="imageBackground ? 'mt-3 text-zinc-200 sm:mt-4' : 'mt-8 text-zinc-500'"
            >
                دار • لإدارة الإيجار والأملاك
            </div>
        </div>
    </div>
</template>
