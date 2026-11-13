<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import {
    Building2,
    MapPin,
    Plus,
    Search,
    User,
    Users,
    ArrowLeft,
} from 'lucide-vue-next';
import { useLocale } from '@/composables/useLocale';

const { t, isRtl } = useLocale();

interface PropertyItem {
    id: number;
    name: string;
    property_type: string;
    type_label: string;
    address: string;
    apartment_gender?: {
        status: 'male' | 'female' | 'mixed' | 'unspecified';
        label: string;
        short_label: string;
        description: string;
        badge_class: string;
    } | null;
    total_units: number;
    occupied_units: number;
    vacant_units: number;
    occupancy_rate: number;
    monthly_revenue: number;
}

const props = defineProps<{
    properties: PropertyItem[];
    currency_symbol?: string;
    stats: {
        total_properties: number;
        total_units: number;
        overall_occupancy: number;
        total_monthly_revenue: number;
    };
}>();

const searchQuery = ref('');
const selectedType = ref('all');

const filterOptions = computed(() => [
    { id: 'all', label: t('properties.filter_all') },
    { id: 'building', label: t('properties.filter_buildings') },
    { id: 'apartment', label: t('properties.filter_apartments') },
]);

function getPropertyTypeLabel(type: string, fallback: string): string {
    const key = `properties.type_${type}`;
    const translated = t(key);
    return translated !== key ? translated : fallback;
}

const filteredProperties = computed(() => {
    return props.properties.filter(p => {
        const matchesSearch = p.name.includes(searchQuery.value) || Boolean(p.address?.includes(searchQuery.value));
        const matchesType = selectedType.value === 'all' || p.property_type === selectedType.value;
        return matchesSearch && matchesType;
    });
});
</script>

<template>
    <AppLayout
        :title="`${t('properties.title')} — Nook Mate`"
        active-tab="properties"
        :header-title="t('properties.title')"
        :header-subtitle="t('properties.subtitle')"
        :breadcrumb="t('properties.breadcrumb')"
    >
        <template #actions>
            <Link
                :href="route('properties.create')"
                class="h-11 px-5 rounded-xl bg-[#18705A] hover:bg-[#105742] text-white text-sm font-semibold transition shadow-xs flex items-center gap-2 cursor-pointer whitespace-nowrap"
            >
                <Plus class="h-4 w-4 stroke-[2.5]" />
                <span>{{ t('properties.add_property') }}</span>
            </Link>
        </template>

        <!-- صف المؤشرات الرئيسية المتطابق (KPI Cards) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">{{ t('properties.total_properties') }}</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#163E3E] font-mono">{{ stats.total_properties }}</span>
                    <span class="text-xs text-[#163E3E] font-medium">{{ t('properties.properties_subtitle') }}</span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">{{ t('properties.total_units') }}</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#163E3E] font-mono">{{ stats.total_units }}</span>
                    <span class="text-xs text-[#5B6770] font-medium">{{ t('properties.units_subtitle') }}</span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">{{ t('properties.overall_occupancy') }}</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#163E3E] font-mono">{{ stats.overall_occupancy }}%</span>
                    <span class="text-xs text-emerald-700 font-semibold bg-emerald-50 px-2 py-0.5 rounded-md">
                        {{ t('properties.optimal_rate') }}
                    </span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">{{ t('properties.expected_monthly_revenue') }}</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#163E3E] font-mono">{{ stats.total_monthly_revenue.toLocaleString() }} <span class="text-xs font-sans font-normal text-[#5B6770]">{{ currency_symbol || 'ج.م' }}</span></span>
                    <span class="text-xs text-[#5B6770] font-medium">{{ t('properties.monthly') }}</span>
                </div>
            </div>
        </div>

        <!-- شريط التصفية والبحث (Filter & Search Toolbar) -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-white p-4 rounded-2xl border border-[#D6E0E7] shadow-2xs">
            <!-- حقل البحث -->
            <div class="relative flex-1 max-w-md">
                <input
                    v-model="searchQuery"
                    type="text"
                    :placeholder="t('properties.search_placeholder')"
                    :aria-label="t('properties.search_placeholder')"
                    class="w-full h-10 px-4 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] placeholder:text-[#5B6770] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                    :class="isRtl ? 'pr-10 pl-4 text-start' : 'pl-10 pr-4 text-start'"
                />
                <Search
                    class="pointer-events-none absolute top-1/2 -translate-y-1/2 h-4 w-4 text-[#5B6770]"
                    :class="isRtl ? 'right-3.5' : 'left-3.5'"
                />
            </div>

            <!-- أزرار التصفية حسب النوع -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
                <button
                    v-for="filter in filterOptions"
                    :key="filter.id"
                    @click="selectedType = filter.id"
                    type="button"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition cursor-pointer shrink-0 whitespace-nowrap"
                    :class="selectedType === filter.id 
                        ? 'bg-[#163E3E] text-white shadow-2xs' 
                        : 'bg-[#F8FAFC] text-[#5B6770] hover:text-[#163E3E] border border-[#D6E0E7]'"
                >
                    {{ filter.label }}
                </button>
            </div>
        </div>

        <!-- قائمة كروت العقارات (Properties Grid) -->
        <div v-if="filteredProperties.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div
                v-for="prop in filteredProperties"
                :key="prop.id"
                class="rounded-3xl border border-[#D6E0E7] bg-white p-6 shadow-2xs hover:border-[#163E3E]/40 hover:shadow-md transition-all flex flex-col justify-between group"
            >
                <div class="space-y-4">
                    <!-- الرأس: الاسم + النوع -->
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-2xl bg-[#E8F0F0] text-[#163E3E] flex items-center justify-center shrink-0 group-hover:bg-[#163E3E] group-hover:text-white transition">
                                <Building2 class="w-6 h-6 stroke-[1.75]" />
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-[#163E3E] group-hover:text-[#163E3E] transition">
                                    {{ prop.name }}
                                </h3>
                                <p class="text-xs text-[#5B6770] flex items-center gap-1 mt-0.5">
                                    <MapPin class="w-3.5 h-3.5 text-[#5B6770]" />
                                    <span>{{ prop.address }}</span>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 flex-wrap shrink-0">
                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-[#F1F5F9] text-[#163E3E] border border-black/5 whitespace-nowrap">
                                {{ getPropertyTypeLabel(prop.property_type, prop.type_label) }}
                            </span>
                            <span
                                v-if="prop.apartment_gender && prop.apartment_gender.status !== 'unspecified'"
                                class="px-2.5 py-1 rounded-full text-xs font-bold border flex items-center gap-1 whitespace-nowrap"
                                :class="[
                                    prop.apartment_gender.status === 'male' ? 'bg-sky-50 text-sky-800 border-sky-200' :
                                    prop.apartment_gender.status === 'female' ? 'bg-rose-50 text-rose-800 border-rose-200' :
                                    'bg-amber-50 text-amber-800 border-amber-200'
                                ]"
                                :title="prop.apartment_gender.description"
                            >
                                <User v-if="prop.apartment_gender.status === 'male' || prop.apartment_gender.status === 'female'" class="w-3 h-3" />
                                <Users v-else class="w-3 h-3" />
                                <span>{{ prop.apartment_gender.label }}</span>
                            </span>
                        </div>
                    </div>

                    <!-- شريط نسبة الإشغال -->
                    <div class="space-y-1.5 pt-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-semibold text-[#163E3E]">{{ t('properties.occupancy_rate_label') }}</span>
                            <span class="font-bold text-[#163E3E] font-mono">{{ prop.occupancy_rate }}%</span>
                        </div>
                        <div class="w-full h-2 bg-[#F1F5F9] rounded-full overflow-hidden">
                            <div
                                class="h-full bg-[#163E3E] rounded-full transition-all duration-500"
                                :style="{ width: `${prop.occupancy_rate}%` }"
                            ></div>
                        </div>
                    </div>

                    <!-- تفاصيل الوحدات والمالية -->
                    <div class="grid grid-cols-3 gap-3 p-3.5 rounded-2xl bg-[#F8FAFC] border border-[#D6E0E7] text-center">
                        <div>
                            <span class="text-[11px] text-[#5B6770] block whitespace-nowrap">{{ t('properties.occupied_units_label') }}</span>
                            <span class="text-sm font-bold text-[#163E3E] font-mono mt-0.5 block">
                                {{ prop.occupied_units }}
                            </span>
                        </div>
                        <div class="border-x border-[#D6E0E7]">
                            <span class="text-[11px] text-[#5B6770] block whitespace-nowrap">{{ t('properties.vacant_units_label') }}</span>
                            <span class="text-sm font-bold text-amber-700 font-mono mt-0.5 block">
                                {{ prop.vacant_units }}
                            </span>
                        </div>
                        <div>
                            <span class="text-[11px] text-[#5B6770] block whitespace-nowrap">{{ t('properties.monthly_revenue_label') }}</span>
                            <span class="text-sm font-bold text-[#163E3E] font-mono mt-0.5 block">
                                {{ prop.monthly_revenue.toLocaleString() }} <span class="text-[10px] font-sans">{{ currency_symbol || 'ج.م' }}</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- زر الانتقال للتفاصيل والوحدات -->
                <div class="pt-5 mt-4 border-t border-[#D6E0E7] flex items-center justify-between">
                    <span class="text-xs text-[#5B6770] font-mono">
                        {{ t('properties.units_registered_badge', { count: prop.total_units }) }}
                    </span>
                    <Link
                        :href="route('properties.show', prop.id)"
                        class="h-9 px-4 rounded-xl bg-[#163E3E]/10 hover:bg-[#163E3E] text-[#163E3E] hover:text-white text-xs font-bold transition flex items-center gap-1.5 whitespace-nowrap"
                    >
                        <span>{{ t('properties.manage_units_action') }}</span>
                        <ArrowLeft class="w-3.5 h-3.5" :class="isRtl ? '' : 'rotate-180'" />
                    </Link>
                </div>
            </div>
        </div>

        <!-- حالة الفراغ عند عدم وجود نتائج -->
        <EmptyState
            v-else
            type="key"
            :title="t('properties.no_properties_found')"
            :description="t('properties.no_properties_desc')"
            :action-label="t('properties.add_property')"
            @action="$inertia.visit(route('properties.create'))"
        />
    </AppLayout>
</template>
