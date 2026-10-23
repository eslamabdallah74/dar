<script setup lang="ts">
import { Clock, FileCheck2, RefreshCw } from 'lucide-vue-next';

interface ExpiringLease {
    id: number;
    renter_name: string;
    renter_phone: string;
    unit_name: string;
    property_name: string;
    rent_amount: number;
    ends_on: string;
    days_remaining: number;
}

const props = defineProps<{
    leases: ExpiringLease[];
    currency?: string;
}>();

const emit = defineEmits<{
    (e: 'renewLease', lease: ExpiringLease): void;
}>();

function formatCurrency(val: number): string {
    return new Intl.NumberFormat('en-US').format(Math.round(val || 0));
}
</script>

<template>
    <div class="rounded-2xl border border-[#D6E0E7] bg-white p-6 shadow-xs flex flex-col h-full">
        <!-- الرأس -->
        <div class="flex items-center justify-between pb-4 border-b border-[#D6E0E7]">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center border border-amber-100">
                    <Clock class="h-4 w-4" />
                </div>
                <div>
                    <h2 class="text-base font-extrabold text-[#0F2747]">عقود تنتهي قريباً</h2>
                    <p class="text-[11px] font-semibold text-[#5B6770]">تنبيهات العقود الواجب تجديدها أو إخلائها</p>
                </div>
            </div>

            <span v-if="leases.length > 0" class="px-2 py-0.5 rounded-full bg-amber-50 border border-amber-200 text-amber-700 text-xs font-bold font-mono">
                {{ leases.length }} عقود
            </span>
        </div>

        <!-- قائمة العقود -->
        <div v-if="leases.length > 0" class="mt-4 divide-y divide-[#D6E0E7] overflow-y-auto max-h-[240px]">
            <div
                v-for="lease in leases"
                :key="lease.id"
                class="py-3 first:pt-0 last:pb-0 flex items-center justify-between gap-3"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <h4 class="text-xs font-bold text-[#0F2747]">{{ lease.renter_name }}</h4>
                        <span class="px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 text-[10px] font-bold border border-amber-200">
                            باقي {{ lease.days_remaining }} يوم
                        </span>
                    </div>
                    <p class="text-[11px] font-semibold text-[#5B6770] mt-0.5">
                        <span>{{ lease.unit_name }}</span>
                        <span class="mx-1">•</span>
                        <span>{{ lease.property_name }}</span>
                    </p>
                </div>

                <div class="flex items-center gap-2.5 shrink-0">
                    <div class="text-left font-mono">
                        <span class="text-xs font-bold text-[#0F2747]">{{ formatCurrency(lease.rent_amount) }}</span>
                        <span class="text-[10px] text-[#5B6770] mr-1">{{ currency || 'ج.م' }}</span>
                    </div>

                    <button
                        @click="emit('renewLease', lease)"
                        type="button"
                        class="p-1.5 rounded-lg border border-[#D6E0E7] hover:border-[#0E6E7E] hover:text-[#0E6E7E] text-[#0F2747] transition shadow-xs"
                        title="تجديد العقد"
                    >
                        <RefreshCw class="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>
        </div>

        <div v-else class="my-auto py-8 text-center text-xs text-[#5B6770] font-semibold">
            لا توجد أي عقود تنتهي خلال الـ 30 يوماً القادمة.
        </div>
    </div>
</template>
