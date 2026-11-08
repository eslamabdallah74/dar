<script setup lang="ts">
import { computed } from 'vue';
import type { LeaseData, SecurityDepositData } from './types';
import { Check } from 'lucide-vue-next';

const props = defineProps<{
    lease: LeaseData;
    securityDeposit: SecurityDepositData;
    remainingDays: number;
}>();

interface Step {
    id: number;
    title: string;
    description: string;
}

const steps: Step[] = [
    { id: 1, title: 'بدأ العقد', description: 'توقيع وتفعيل العقد' },
    { id: 2, title: 'سارٍ', description: 'سداد دوري وانتظام' },
    { id: 3, title: 'قرب الانتهاء', description: 'بحث التجديد أو الإخلاء' },
    { id: 4, title: 'منتهي', description: 'انتهاء المدة أو فسخ' },
    { id: 5, title: 'فحص وتسوية', description: 'معاينة وخصومات التأمين' },
    { id: 6, title: 'مغلق', description: 'صرف المستحق وإغلاق' },
];

const currentStepId = computed(() => {
    const isLeaseActive = props.lease.status === 'active';
    const isTerminatedOrExpired = props.lease.status === 'terminated' || props.lease.status === 'expired';
    const depositStatus = props.securityDeposit.status;
    const hasDeposit = props.securityDeposit.agreed_amount > 0 || props.securityDeposit.total_received > 0;

    if (isTerminatedOrExpired) {
        if (!hasDeposit || depositStatus === 'settled' || depositStatus === 'not_required') {
            return 6; // مغلق
        }
        return 5; // فحص وتسوية التأمين
    }

    if (isLeaseActive) {
        if (props.lease.is_past_end_date || props.remainingDays < 0) {
            return 4; // منتهي
        }
        if (props.lease.is_expiring_soon || props.remainingDays <= 30) {
            return 3; // قرب الانتهاء
        }
        return 2; // سارٍ
    }

    if (props.lease.status === 'draft') {
        return 1; // بدأ العقد
    }

    if (props.lease.status === 'cancelled') {
        return 4;
    }

    return 2;
});
</script>

<template>
    <nav aria-label="مراحل دورة حياة العقد" class="overflow-x-auto rounded-3xl border border-[#D6E0E7] bg-white p-4 sm:p-5 shadow-xs">
        <ol class="flex min-w-[620px] items-center justify-between gap-2">
            <li
                v-for="(step, index) in steps"
                :key="step.id"
                class="relative flex flex-1 items-center"
            >
                <div class="flex flex-col items-center gap-1.5 w-full text-center">
                    <!-- Step circle indicator -->
                    <div class="relative z-10 flex items-center justify-center">
                        <!-- Completed step (index < currentStepId - 1) -->
                        <div
                            v-if="step.id < currentStepId"
                            class="flex h-7 w-7 items-center justify-center rounded-full bg-[#163E3E] text-white shadow-xs"
                            title="مكتمل"
                        >
                            <Check class="h-4 w-4" />
                        </div>

                        <!-- Active current step (step.id === currentStepId) -->
                        <div
                            v-else-if="step.id === currentStepId"
                            class="flex h-7 w-7 items-center justify-center rounded-full border-2 border-[#163E3E] bg-[#E8F0F0] text-[#163E3E] ring-4 ring-[#163E3E]/10 font-bold"
                            aria-current="step"
                        >
                            <span class="h-2.5 w-2.5 rounded-full bg-[#163E3E]"></span>
                        </div>

                        <!-- Upcoming future step (step.id > currentStepId) -->
                        <div
                            v-else
                            class="flex h-7 w-7 items-center justify-center rounded-full border border-[#D6E0E7] bg-[#FBFAF6] text-[#8A9AA7]"
                        >
                            <span class="text-xs font-mono font-semibold">{{ step.id }}</span>
                        </div>
                    </div>

                    <!-- Step label -->
                    <div class="min-h-[36px]">
                        <p
                            class="text-xs font-bold leading-tight"
                            :class="step.id === currentStepId ? 'text-[#163E3E]' : step.id < currentStepId ? 'text-[#163E3E]' : 'text-[#8A9AA7]'"
                        >
                            {{ step.title }}
                        </p>
                        <p class="hidden sm:block text-[10px] text-[#5B6770] mt-0.5">
                            {{ step.description }}
                        </p>
                    </div>
                </div>

                <!-- Connecting bar between steps -->
                <div
                    v-if="index < steps.length - 1"
                    class="absolute top-3.5 -start-1/2 w-full h-0.5 -translate-y-1/2 z-0"
                    aria-hidden="true"
                >
                    <div
                        class="h-full transition-colors duration-300"
                        :class="step.id < currentStepId ? 'bg-[#163E3E]' : 'bg-[#E6ECF0]'"
                    ></div>
                </div>
            </li>
        </ol>
    </nav>
</template>

