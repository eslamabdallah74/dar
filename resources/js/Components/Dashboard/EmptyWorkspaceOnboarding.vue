<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Building2, Home, Users, FileText, ArrowLeft, Plus, Sparkles } from 'lucide-vue-next';
import { useLocale } from '@/composables/useLocale';

const { t, isRtl } = useLocale();

defineProps<{
    userName?: string;
}>();

const steps = computed(() => [
    {
        num: 1,
        title: t('dashboard.step1_title'),
        desc: t('dashboard.step1_desc'),
        icon: Building2,
        actionLabel: t('dashboard.step1_btn'),
        routeName: 'properties.create',
    },
    {
        num: 2,
        title: t('dashboard.step2_title'),
        desc: t('dashboard.step2_desc'),
        icon: Home,
        actionLabel: t('dashboard.step2_btn'),
        routeName: 'properties.index',
    },
    {
        num: 3,
        title: t('dashboard.step3_title'),
        desc: t('dashboard.step3_desc'),
        icon: Users,
        actionLabel: t('dashboard.step3_btn'),
        routeName: 'renters.create',
    },
    {
        num: 4,
        title: t('dashboard.step4_title'),
        desc: t('dashboard.step4_desc'),
        icon: FileText,
        actionLabel: t('dashboard.step4_btn'),
        routeName: 'leases.create',
    },
]);
</script>

<template>
    <div class="rounded-3xl border border-[#D6E0E7] bg-white p-6 sm:p-10 shadow-sm space-y-8">
        <!-- الترويسة الترحيبية -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6 pb-6 border-b border-[#E8EFF4]">
            <div class="space-y-2">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#E8F0F0] text-[#163E3E] text-xs font-bold">
                    <Sparkles class="h-3.5 w-3.5" />
                    <span>{{ t('dashboard.onboarding_badge') }}</span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-black text-[#163E3E] tracking-tight">
                    {{ t('dashboard.onboarding_title') }}
                </h2>
                <p class="text-sm text-[#5B6770] font-medium max-w-2xl leading-relaxed">
                    {{ t('dashboard.onboarding_desc') }}
                </p>
            </div>

            <Link
                :href="route('properties.create')"
                class="h-12 px-6 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-sm font-bold transition shadow-xs flex items-center justify-center gap-2 shrink-0 self-start md:self-auto"
            >
                <Plus class="h-5 w-5" />
                <span>{{ t('dashboard.onboarding_cta') }}</span>
            </Link>
        </div>

        <!-- الخطوات الأربعة المرتبة -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
            <div
                v-for="step in steps"
                :key="step.num"
                class="rounded-2xl border border-[#E8EFF4] bg-[#FBFDFF] p-5 flex flex-col justify-between hover:border-[#163E3E]/40 hover:shadow-xs transition group"
            >
                <div>
                    <!-- الترقيم والأيقونة -->
                    <div class="flex items-center justify-between">
                        <span class="w-8 h-8 rounded-xl bg-[#E8F0F0] text-[#163E3E] font-black text-sm font-mono flex items-center justify-center">
                            {{ step.num }}
                        </span>
                        <div class="w-9 h-9 rounded-xl bg-[#F0F4F8] text-[#5B6770] group-hover:text-[#163E3E] group-hover:bg-[#E8F0F0] flex items-center justify-center transition-colors">
                            <component :is="step.icon" class="h-4 w-4" />
                        </div>
                    </div>

                    <!-- العنوان والوصف -->
                    <h3 class="text-sm font-bold text-[#163E3E] mt-3 group-hover:text-[#163E3E] transition">
                        {{ step.title }}
                    </h3>
                    <p class="text-xs text-[#5B6770] font-medium mt-1.5 leading-relaxed">
                        {{ step.desc }}
                    </p>
                </div>

                <!-- زر الإجراء للخطوة -->
                <div class="mt-5 pt-3 border-t border-[#E8EFF4]">
                    <Link
                        :href="route(step.routeName)"
                        class="w-full py-2 px-3 rounded-lg border border-[#D6E0E7] hover:border-[#163E3E] hover:bg-[#E8F0F0]/40 text-xs font-bold text-[#163E3E] group-hover:text-[#163E3E] transition flex items-center justify-center gap-1.5"
                    >
                        <span>{{ step.actionLabel }}</span>
                        <ArrowLeft class="h-3 w-3" :class="isRtl ? '' : 'rotate-180'" />
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
