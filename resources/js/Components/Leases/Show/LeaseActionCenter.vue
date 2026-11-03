<script setup lang="ts">
import { computed } from 'vue';
import type { ActionCenterItem } from './types';
import {
    AlertCircle,
    Bell,
    CheckCircle2,
    Clock,
    Coins,
    ShieldAlert,
} from 'lucide-vue-next';

const props = defineProps<{
    items: ActionCenterItem[];
}>();

const emit = defineEmits<{
    (e: 'action', actionKey: string, meta?: any): void;
}>();

const totalItems = computed(() => props.items.length);

const getToneClasses = (tone?: string) => {
    switch (tone) {
        case 'rose':
            return {
                border: 'border-rose-200 bg-rose-50/70',
                badge: 'bg-rose-100 text-rose-800 border-rose-300',
                text: 'text-rose-950',
                icon: AlertCircle,
                iconColor: 'text-rose-600',
            };
        case 'amber':
            return {
                border: 'border-amber-200 bg-amber-50/60',
                badge: 'bg-amber-100 text-amber-800 border-amber-300',
                text: 'text-amber-950',
                icon: Clock,
                iconColor: 'text-amber-600',
            };
        case 'teal':
            return {
                border: 'border-[#163E3E]/20 bg-[#E8F0F0]/50',
                badge: 'bg-[#163E3E]/10 text-[#163E3E] border-[#163E3E]/30',
                text: 'text-[#163E3E]',
                icon: ShieldAlert,
                iconColor: 'text-[#163E3E]',
            };
        default:
            return {
                border: 'border-[#D6E0E7] bg-white',
                badge: 'bg-slate-100 text-slate-700 border-slate-300',
                text: 'text-[#163E3E]',
                icon: Coins,
                iconColor: 'text-[#163E3E]',
            };
    }
};

const getButtonClasses = (tone?: string) => {
    switch (tone) {
        case 'danger':
            return 'bg-rose-600 text-white hover:bg-rose-700 focus-visible:ring-rose-400';
        case 'secondary':
            return 'bg-white text-[#163E3E] border border-[#D6E0E7] hover:bg-[#F8F4EA] focus-visible:ring-[#163E3E]/20';
        default:
            return 'bg-[#163E3E] text-white hover:bg-[#102F2F] focus-visible:ring-[#163E3E]/30';
    }
};
</script>

<template>
    <section aria-labelledby="action-center-title" class="space-y-3">
        <!-- All Clear State -->
        <div
            v-if="totalItems === 0"
            class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50/60 px-5 py-4 text-emerald-950 shadow-xs"
        >
            <CheckCircle2 class="h-5 w-5 shrink-0 text-emerald-600" />
            <div class="flex-1 text-xs sm:text-sm font-semibold">
                <span class="font-bold">كل شيء على ما يرام ✓</span>
                <span class="ms-1.5 text-emerald-900/80 font-normal">لا توجد متأخرات أو إجراءات معلقة تتطلب انتباهك على هذا العقد الآن.</span>
            </div>
        </div>

        <!-- Attention Required Items -->
        <div v-else class="rounded-3xl border border-[#D6E0E7] bg-white p-5 sm:p-6 shadow-xs space-y-3.5">
            <header class="flex items-center justify-between pb-1">
                <div class="flex items-center gap-2">
                    <div class="flex h-7 w-7 items-center justify-center rounded-xl bg-amber-100 text-amber-800">
                        <Bell class="h-3.5 w-3.5" />
                    </div>
                    <h2 id="action-center-title" class="text-sm sm:text-base font-bold text-[#163E3E]">
                        محتاج انتباهك
                        <span class="font-mono text-xs font-semibold text-[#5B6770]">({{ totalItems }})</span>
                    </h2>
                </div>
                <span class="text-[11px] font-medium text-[#5B6770]">مرتبة حسب الأولوية الإجرائية</span>
            </header>

            <div class="space-y-2.5">
                <article
                    v-for="item in items"
                    :key="item.id"
                    class="flex flex-col gap-3 rounded-2xl border p-4 sm:flex-row sm:items-center sm:justify-between transition-colors"
                    :class="getToneClasses(item.badgeTone).border"
                >
                    <div class="flex items-start gap-3">
                        <component
                            :is="getToneClasses(item.badgeTone).icon"
                            class="mt-0.5 h-5 w-5 shrink-0"
                            :class="getToneClasses(item.badgeTone).iconColor"
                        />
                        <div class="space-y-0.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-xs sm:text-sm font-bold" :class="getToneClasses(item.badgeTone).text">
                                    {{ item.title }}
                                </h3>
                                <span
                                    v-if="item.badge"
                                    class="rounded-md border px-2 py-0.5 text-[10px] font-bold"
                                    :class="getToneClasses(item.badgeTone).badge"
                                >
                                    {{ item.badge }}
                                </span>
                            </div>
                            <p class="text-xs text-[#5B6770] leading-relaxed">
                                {{ item.description }}
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-9 shrink-0 cursor-pointer items-center justify-center rounded-xl px-4 text-xs font-bold shadow-xs transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-1 active:translate-y-px"
                        :class="getButtonClasses(item.buttonTone)"
                        @click="emit('action', item.action, item.meta)"
                    >
                        <span>{{ item.buttonLabel }}</span>
                    </button>
                </article>
            </div>
        </div>
    </section>
</template>

