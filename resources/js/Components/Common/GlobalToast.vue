<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast, type ToastItem } from '@/composables/useToast';
import {
    AlertOctagon,
    AlertTriangle,
    CheckCircle2,
    Info,
    X,
} from 'lucide-vue-next';

const toastManager = useToast();
const { toasts, remove } = toastManager;
const hoveredToastId = ref<string | null>(null);

let intervalId: number | null = null;

// Watch for initial or dynamically updated flash and errors from Inertia props
try {
    const page = usePage();

    watch(
        () => (page.props as any)?.flash,
        (flash: any) => {
            if (flash?.error) {
                toastManager.error(flash.error);
            }
            if (flash?.success) {
                toastManager.success(flash.success);
            }
            if (flash?.warning) {
                toastManager.warning(flash.warning);
            }
            if (flash?.info) {
                toastManager.info(flash.info);
            }
        },
        { deep: true, immediate: true }
    );

    watch(
        () => (page.props as any)?.errors,
        (errors: any) => {
            if (errors && Object.keys(errors).length > 0) {
                toastManager.fromErrors(errors);
            }
        },
        { deep: true }
    );
} catch {
    // Graceful fallback if outside Inertia context (e.g. unit tests)
}

onMounted(() => {
    // Tick every 100ms to decrement remaining time for active toasts
    intervalId = window.setInterval(() => {
        toasts.forEach((toast) => {
            if (hoveredToastId.value !== toast.id && toast.remaining !== undefined) {
                toast.remaining -= 100;
                if (toast.remaining <= 0) {
                    remove(toast.id);
                }
            }
        });
    }, 100);
});

onUnmounted(() => {
    if (intervalId !== null) {
        clearInterval(intervalId);
    }
});

function getProgressWidth(toast: ToastItem): number {
    if (!toast.duration || toast.remaining === undefined) {
        return 0;
    }
    return Math.max(0, Math.min(100, (toast.remaining / toast.duration) * 100));
}
</script>

<template>
    <Teleport to="body">
        <div
            class="fixed top-6 left-1/2 -translate-x-1/2 z-[99999] flex flex-col gap-3 w-full max-w-md sm:max-w-lg px-4 pointer-events-none select-none"
            dir="rtl"
            aria-live="assertive"
        >
            <TransitionGroup
                enter-active-class="transform ease-out duration-300 transition"
                enter-from-class="-translate-y-4 opacity-0 scale-95"
                enter-to-class="translate-y-0 opacity-100 scale-100"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="-translate-y-2 opacity-0 scale-95"
            >
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="pointer-events-auto relative overflow-hidden rounded-2xl border-2 p-4 sm:p-5 transition-all duration-200 shadow-2xl"
                    :class="{
                        'bg-[#FEF2F2] border-rose-400 text-rose-950 border-r-8 border-r-rose-600': toast.type === 'error',
                        'bg-[#ECFDF5] border-emerald-400 text-emerald-950 border-r-8 border-r-emerald-600': toast.type === 'success',
                        'bg-[#FFFBEB] border-amber-400 text-amber-950 border-r-8 border-r-amber-500': toast.type === 'warning',
                        'bg-[#F0F9FF] border-sky-400 text-sky-950 border-r-8 border-r-sky-500': toast.type === 'info',
                    }"
                    :style="{
                        backgroundColor: toast.type === 'error' ? '#FEF2F2' : (toast.type === 'success' ? '#ECFDF5' : (toast.type === 'warning' ? '#FFFBEB' : '#F0F9FF')),
                        boxShadow: '0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1)'
                    }"
                    @mouseenter="hoveredToastId = toast.id"
                    @mouseleave="hoveredToastId = null"
                    role="alert"
                >
                    <div class="flex items-start gap-3.5">
                        <!-- Icon -->
                        <div
                            class="shrink-0 p-2.5 rounded-xl border mt-0.5"
                            :class="{
                                'bg-rose-100 text-rose-700 border-rose-300': toast.type === 'error',
                                'bg-emerald-100 text-emerald-700 border-emerald-300': toast.type === 'success',
                                'bg-amber-100 text-amber-700 border-amber-300': toast.type === 'warning',
                                'bg-sky-100 text-sky-700 border-sky-300': toast.type === 'info',
                            }"
                            :style="{
                                backgroundColor: toast.type === 'error' ? '#FFE4E6' : (toast.type === 'success' ? '#D1FAE5' : (toast.type === 'warning' ? '#FEF3C7' : '#E0F2FE'))
                            }"
                        >
                            <AlertOctagon v-if="toast.type === 'error'" class="w-5 h-5 text-rose-600" />
                            <CheckCircle2 v-else-if="toast.type === 'success'" class="w-5 h-5 text-emerald-600" />
                            <AlertTriangle v-else-if="toast.type === 'warning'" class="w-5 h-5 text-amber-600" />
                            <Info v-else class="w-5 h-5 text-sky-600" />
                        </div>

                        <!-- Content -->
                        <div class="flex-1 min-w-0 pr-0.5">
                            <h4
                                v-if="toast.title"
                                class="text-sm font-bold tracking-tight mb-1"
                                :class="{
                                    'text-rose-950': toast.type === 'error',
                                    'text-emerald-950': toast.type === 'success',
                                    'text-amber-950': toast.type === 'warning',
                                    'text-sky-950': toast.type === 'info',
                                }"
                            >
                                {{ toast.title }}
                            </h4>

                            <p
                                class="text-xs sm:text-sm font-semibold leading-relaxed select-text"
                                :class="{
                                    'text-rose-900': toast.type === 'error',
                                    'text-emerald-900': toast.type === 'success',
                                    'text-amber-900': toast.type === 'warning',
                                    'text-sky-900': toast.type === 'info',
                                }"
                            >
                                {{ toast.message }}
                            </p>

                            <!-- Multi-line validation messages list if present -->
                            <div
                                v-if="toast.messages && toast.messages.length > 0"
                                class="mt-3 p-3 rounded-xl border space-y-1.5 shadow-xs"
                                :class="{
                                    'bg-white border-rose-300 text-rose-950': toast.type === 'error',
                                    'bg-white border-amber-300 text-amber-950': toast.type === 'warning',
                                    'bg-white border-emerald-300 text-emerald-950': toast.type === 'success',
                                    'bg-white border-sky-300 text-sky-950': toast.type === 'info',
                                }"
                                style="background-color: #FFFFFF;"
                            >
                                <div
                                    v-for="(msg, idx) in toast.messages"
                                    :key="idx"
                                    class="flex items-start gap-2 text-xs font-bold leading-relaxed"
                                >
                                    <span class="text-rose-600 font-bold shrink-0 mt-0.5">•</span>
                                    <span>{{ msg }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Close button -->
                        <button
                            type="button"
                            @click="remove(toast.id)"
                            class="shrink-0 p-1.5 rounded-lg transition-colors cursor-pointer"
                            :class="{
                                'text-rose-600 hover:text-rose-950 hover:bg-rose-200': toast.type === 'error',
                                'text-emerald-600 hover:text-emerald-950 hover:bg-emerald-200': toast.type === 'success',
                                'text-amber-600 hover:text-amber-950 hover:bg-amber-200': toast.type === 'warning',
                                'text-sky-600 hover:text-sky-950 hover:bg-sky-200': toast.type === 'info',
                            }"
                            aria-label="إغلاق التنبيه"
                        >
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- Countdown Progress Bar -->
                    <div
                        v-if="toast.duration"
                        class="absolute bottom-0 right-0 left-0 h-1.5"
                        :class="{
                            'bg-rose-200': toast.type === 'error',
                            'bg-emerald-200': toast.type === 'success',
                            'bg-amber-200': toast.type === 'warning',
                            'bg-sky-200': toast.type === 'info',
                        }"
                    >
                        <div
                            class="h-full transition-all ease-linear"
                            :style="{ width: `${getProgressWidth(toast)}%` }"
                            :class="{
                                'bg-rose-600': toast.type === 'error',
                                'bg-emerald-600': toast.type === 'success',
                                'bg-amber-500': toast.type === 'warning',
                                'bg-sky-500': toast.type === 'info',
                            }"
                        ></div>
                    </div>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
