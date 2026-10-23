<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Building2, ArrowLeft, Home, MapPin } from 'lucide-vue-next';

interface PropertyItem {
    id: number;
    name: string;
    property_type: string;
    property_type_label: string;
    address: string;
    units_count: number;
    occupied_count: number;
    vacant_count: number;
    occupancy_rate: number;
}

defineProps<{
    properties?: PropertyItem[];
}>();
</script>

<template>
    <div class="rounded-2xl border border-[#D6E0E7] bg-white p-6 shadow-2xs space-y-4">
        <!-- الرأس وزر عرض الكل -->
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-[#0F2747]">أحدث العقارات المسجلة</h3>
                <p class="text-xs text-[#5B6770] font-medium mt-0.5">
                    متابعة فورية لحالة الإشغال في كل عقار
                </p>
            </div>

            <Link
                :href="route('properties.index')"
                class="text-xs font-bold text-[#0E6E7E] hover:text-[#0B5A68] transition flex items-center gap-1"
            >
                <span>عرض كل العقارات</span>
                <ArrowLeft class="h-3.5 w-3.5" />
            </Link>
        </div>

        <!-- شبكة العقارات (4 كروت متناسقة) -->
        <div v-if="properties && properties.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <Link
                v-for="prop in properties"
                :key="prop.id"
                :href="route('properties.show', prop.id)"
                class="p-4 rounded-xl border border-[#E8EFF4] hover:border-[#0E6E7E]/40 hover:shadow-xs transition bg-[#FBFDFF] group flex flex-col justify-between"
            >
                <div>
                    <!-- النوع والشارة -->
                    <div class="flex items-center justify-between gap-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-[#E8F3F5] text-[#0E6E7E]">
                            {{ prop.property_type_label }}
                        </span>
                        <span class="text-xs font-bold font-mono text-[#0F2747]">
                            {{ prop.occupancy_rate }}% إشغال
                        </span>
                    </div>

                    <!-- اسم العقار -->
                    <h4 class="text-sm font-bold text-[#0F2747] group-hover:text-[#0E6E7E] transition mt-2 truncate">
                        {{ prop.name }}
                    </h4>

                    <!-- العنوان -->
                    <p class="text-[11px] text-[#5B6770] font-medium mt-1 flex items-center gap-1 truncate">
                        <MapPin class="h-3 w-3 shrink-0 text-[#8E9CA6]" />
                        <span class="truncate">{{ prop.address }}</span>
                    </p>
                </div>

                <!-- شريط الإشغال والوحدات -->
                <div class="mt-4 pt-3 border-t border-[#E8EFF4] space-y-1.5">
                    <div class="flex items-center justify-between text-xs text-[#5B6770]">
                        <span class="font-medium">{{ prop.units_count }} وحدة</span>
                        <span class="font-medium text-emerald-700">{{ prop.occupied_count }} مشغولة</span>
                    </div>
                    <div class="w-full h-1.5 bg-[#E8EFF4] rounded-full overflow-hidden">
                        <div
                            class="h-full bg-emerald-600 rounded-full transition-all duration-500"
                            :style="{ width: `${prop.occupancy_rate}%` }"
                        ></div>
                    </div>
                </div>
            </Link>
        </div>

        <!-- حالة فارغة -->
        <div
            v-else
            class="py-8 px-4 rounded-xl border border-dashed border-[#D6E0E7] bg-[#F8FAFC] text-center"
        >
            <p class="text-xs text-[#5B6770] font-medium">لا توجد عقارات مسجلة بعد.</p>
        </div>
    </div>
</template>

