<script setup lang="ts">
import { computed } from 'vue';
import { Building2, DoorOpen, Home, KeyRound, Plus } from 'lucide-vue-next';

interface PropertySummary {
    id: number;
    name: string;
    type: string;
    total_units: number;
    occupied_units: number;
    vacant_units: number;
}

const props = defineProps<{
    totalUnits: number;
    occupiedUnits: number;
    vacantUnits: number;
    occupancyRate: number;
    properties: PropertySummary[];
}>();

const emit = defineEmits<{
    (e: 'newUnit'): void;
}>();
</script>

<template>
    <div class="rounded-2xl border border-[#D6E0E7] bg-white p-6 shadow-xs flex flex-col h-full">
        <!-- الرأس -->
        <div class="flex items-center justify-between pb-4 border-b border-[#D6E0E7]">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center">
                    <Building2 class="h-4 w-4" />
                </div>
                <div>
                    <h2 class="text-base font-extrabold text-[#0F2747]">حالة العقارات والوحدات</h2>
                    <p class="text-[11px] font-semibold text-[#5B6770]">توزيع الوحدات المؤجرة والشاغرة حسب المباني</p>
                </div>
            </div>

            <button
                @click="emit('newUnit')"
                type="button"
                class="inline-flex items-center gap-1 text-xs font-bold text-[#0E6E7E] hover:text-[#0B5A68] transition"
            >
                <Plus class="h-3.5 w-3.5" />
                <span>إضافة وحدة</span>
            </button>
        </div>

        <!-- ملخص أرقام الإشغال السريع -->
        <div class="grid grid-cols-3 gap-2.5 my-4">
            <div class="rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] p-3 text-center">
                <span class="text-[10px] font-bold text-[#5B6770] block">إجمالي الوحدات</span>
                <span class="text-lg font-black text-[#0F2747] font-mono mt-0.5 block">{{ totalUnits }}</span>
            </div>
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-3 text-center">
                <span class="text-[10px] font-bold text-emerald-800 block">وحدات مؤجرة</span>
                <span class="text-lg font-black text-emerald-700 font-mono mt-0.5 block">{{ occupiedUnits }}</span>
            </div>
            <div class="rounded-xl border border-amber-100 bg-amber-50/60 p-3 text-center">
                <span class="text-[10px] font-bold text-amber-800 block">وحدات شاغرة</span>
                <span class="text-lg font-black text-amber-700 font-mono mt-0.5 block">{{ vacantUnits }}</span>
            </div>
        </div>

        <!-- قائمة العقارات والمباني التابعة للمحفظة -->
        <div v-if="properties.length > 0" class="divide-y divide-[#D6E0E7] overflow-y-auto max-h-[220px]">
            <div
                v-for="prop in properties"
                :key="prop.id"
                class="py-3 first:pt-0 last:pb-0 flex items-center justify-between"
            >
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-[#E8EFF4] text-[#0F2747] flex items-center justify-center text-xs font-bold shrink-0">
                        <Home class="h-4 w-4" />
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-[#0F2747]">{{ prop.name }}</h4>
                        <span class="text-[10px] font-semibold text-[#5B6770]">
                            {{ prop.type === 'building' ? 'عمارة سكنية' : prop.type === 'villa' ? 'فيلا' : 'عقار' }}
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-2 text-left font-mono text-xs">
                    <span class="text-emerald-700 font-bold">{{ prop.occupied_units }} مؤجر</span>
                    <span class="text-[#D6E0E7]">/</span>
                    <span class="text-amber-700 font-bold">{{ prop.vacant_units }} شاغر</span>
                </div>
            </div>
        </div>

        <div v-else class="my-auto py-6 text-center text-xs text-[#5B6770] font-semibold">
            لم تسجل أي عقارات بعد في هذه المحفظة.
        </div>
    </div>
</template>
