<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
</script>

<template>
    <div class="relative min-h-screen flex flex-col justify-center items-center bg-[#09090b] px-4 py-12 text-zinc-100 font-sans selection:bg-emerald-500 selection:text-black text-right" dir="rtl">
        <!-- خلفية هادية متناسقة -->
        <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden opacity-30">
            <div class="absolute top-0 right-1/2 translate-x-1/2 w-[800px] h-[400px] bg-gradient-to-b from-emerald-500/20 via-zinc-900/10 to-transparent blur-3xl"></div>
            <div class="absolute inset-0 bg-[linear-gradient(to_right,#27272a_1px,transparent_1px),linear-gradient(to_bottom,#27272a_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)]"></div>
        </div>

        <div class="mb-6 flex w-full max-w-md items-center">
            <Link
                href="/"
                class="inline-flex items-center gap-1.5 text-xs font-semibold text-zinc-400 hover:text-white transition"
            >
                <ArrowRight class="h-3.5 w-3.5" />
                الرجوع للرئيسية
            </Link>
        </div>

        <!-- اللوجو والهوية -->
        <div class="mb-6 flex flex-col items-center text-center">
            <Link href="/" class="flex items-center gap-4 group">
                <div class="h-16 w-16 sm:h-20 sm:w-20 rounded-full bg-white flex items-center justify-center p-2.5 sm:p-3 shadow-xl shadow-black/20 ring-4 ring-white/10 group-hover:scale-105 group-hover:ring-white/25 transition-all shrink-0">
                    <img
                        src="/images/logo.png"
                        alt="شعار دار — لإدارة الإيجار"
                        class="w-full h-full object-contain"
                    />
                </div>
                <div class="text-right">
                    <div class="text-2xl sm:text-3xl font-bold tracking-tight text-white flex items-center gap-1.5 font-sans">
                        دار
                    </div>
                    <p class="text-xs sm:text-sm text-zinc-400 font-medium">لإدارة الإيجار والأملاك</p>
                </div>
            </Link>
        </div>

        <!-- كارت الفورم -->
        <div class="w-full max-w-md overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900/80 p-6 sm:p-8 shadow-2xl shadow-black/80 backdrop-blur-xl">
            <slot />
        </div>

        <!-- الفوتر -->
        <div class="mt-8 text-center text-xs text-zinc-500 font-medium">
            دار • لإدارة الإيجار والأملاك
        </div>
    </div>
</template>
