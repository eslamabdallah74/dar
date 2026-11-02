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
} from 'lucide-vue-next';

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
    stats: {
        total_properties: number;
        total_units: number;
        overall_occupancy: number;
        total_monthly_revenue: number;
    };
}>();

const searchQuery = ref('');
const selectedType = ref('all');

const filteredProperties = computed(() => {
    return props.properties.filter(p => {
        const matchesSearch = p.name.includes(searchQuery.value) || (p.address && p.address.includes(searchQuery.value));
        const matchesType = selectedType.value === 'all' || p.property_type === selectedType.value;
        return matchesSearch && matchesType;
    });
});

</script>

<template>
    <AppLayout
        title="العقارات والوحدات — Nook Mate"
        active-tab="properties"
        header-title="العقارات والوحدات"
        header-subtitle="إدارة العقارات السكنية ومتابعة نسب الإشغال والوحدات الشاغرة"
        breadcrumb="العقارات"
    >
        <template #actions>
            <Link
                :href="route('properties.create')"
                class="h-11 px-5 rounded-xl bg-[#18705A] hover:bg-[#105742] text-white text-sm font-semibold transition shadow-xs flex items-center gap-2 cursor-pointer"
            >
                <Plus class="h-4 w-4 stroke-[2.5]" />
                <span>إضافة عقار جديد</span>
            </Link>
        </template>

        <!-- صف المؤشرات الرئيسية المتطابق (KPI Cards) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">إجمالي العقارات</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#163E3E] font-mono">{{ stats.total_properties }}</span>
                    <span class="text-xs text-[#163E3E] font-medium">عمارات وشقق</span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">إجمالي الوحدات التأجيرية</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#163E3E] font-mono">{{ stats.total_units }}</span>
                    <span class="text-xs text-[#5B6770] font-medium">شقق وغرف</span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">نسبة الإشغال الإجمالية</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#163E3E] font-mono">{{ stats.overall_occupancy }}%</span>
                    <span class="text-xs text-emerald-700 font-semibold bg-emerald-50 px-2 py-0.5 rounded-md">
                        مستوى ممتاز
                    </span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">الإيراد الشهري المتوقع</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#163E3E] font-mono">{{ stats.total_monthly_revenue.toLocaleString() }} <span class="text-xs font-sans font-normal text-[#5B6770]">ج.م</span></span>
                    <span class="text-xs text-[#5B6770] font-medium">شهرياً</span>
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
                    placeholder="ابحث باسم العقار أو الحي..."
                    class="w-full h-10 pr-10 pl-4 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] placeholder:text-[#5B6770] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right"
                />
                <Search class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-[#5B6770]" />
            </div>

            <!-- أزرار التصفية حسب النوع -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
                <button
                    v-for="filter in [
                        { id: 'all', label: 'الكل' },
                        { id: 'building', label: 'عمارات' },
                        { id: 'apartment', label: 'شقق' }
                    ]"
                    :key="filter.id"
                    @click="selectedType = filter.id"
                    type="button"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition cursor-pointer shrink-0"
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
                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-[#F1F5F9] text-[#163E3E] border border-black/5">
                                {{ prop.type_label }}
                            </span>
                            <span
                                v-if="prop.apartment_gender && prop.apartment_gender.status !== 'unspecified'"
                                class="px-2.5 py-1 rounded-full text-xs font-bold border flex items-center gap-1"
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
                            <span class="font-semibold text-[#163E3E]">معدل الإشغال</span>
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
                            <span class="text-[11px] text-[#5B6770] block">الوحدات المؤجرة</span>
                            <span class="text-sm font-bold text-[#163E3E] font-mono mt-0.5 block">
                                {{ prop.occupied_units }}
                            </span>
                        </div>
                        <div class="border-x border-[#D6E0E7]">
                            <span class="text-[11px] text-[#5B6770] block">الوحدات الشاغرة</span>
                            <span class="text-sm font-bold text-amber-700 font-mono mt-0.5 block">
                                {{ prop.vacant_units }}
                            </span>
                        </div>
                        <div>
                            <span class="text-[11px] text-[#5B6770] block">الإيراد الشهري</span>
                            <span class="text-sm font-bold text-[#163E3E] font-mono mt-0.5 block">
                                {{ prop.monthly_revenue.toLocaleString() }} <span class="text-[10px] font-sans">ج.م</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- زر الانتقال للتفاصيل والوحدات -->
                <div class="pt-5 mt-4 border-t border-[#D6E0E7] flex items-center justify-between">
                    <span class="text-xs text-[#5B6770] font-mono">
                        {{ prop.total_units }} وحدات مسجلة
                    </span>
                    <Link
                        :href="route('properties.show', prop.id)"
                        class="h-9 px-4 rounded-xl bg-[#163E3E]/10 hover:bg-[#163E3E] text-[#163E3E] hover:text-white text-xs font-bold transition flex items-center gap-1.5"
                    >
                        <span>إدارة الوحدات وشجرة الأدوار</span>
                        <span>←</span>
                    </Link>
                </div>
            </div>
        </div>

        <!-- حالة الفراغ عند عدم وجود نتائج -->
        <EmptyState
            v-else
            type="key"
            title="لم يتم العثور على أي عقارات"
            description="لم نجد أي عقار يطابق بحثك الحالي. يمكنك إضافة عقار جديد أو تعديل معايير البحث."
            action-label="إضافة عقار جديد الآن"
            @action="$inertia.visit(route('properties.create'))"
        />
    </AppLayout>
</template>
