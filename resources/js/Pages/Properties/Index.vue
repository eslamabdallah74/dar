<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import {
    Building2,
    Check,
    Coins,
    Home,
    MapPin,
    Plus,
    Search,
    TrendingUp,
    Users,
    X,
} from 'lucide-vue-next';

interface PropertyItem {
    id: number;
    name: string;
    property_type: string;
    type_label: string;
    address: string;
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
const showAddModal = ref(false);

const form = useForm({
    name: '',
    property_type: 'building',
    address: '',
});

const filteredProperties = computed(() => {
    return props.properties.filter(p => {
        const matchesSearch = p.name.includes(searchQuery.value) || (p.address && p.address.includes(searchQuery.value));
        const matchesType = selectedType.value === 'all' || p.property_type === selectedType.value;
        return matchesSearch && matchesType;
    });
});

const submitProperty = () => {
    form.post(route('properties.store'), {
        onSuccess: () => {
            form.reset();
            showAddModal.value = false;
        },
    });
};

onMounted(() => {
    if (typeof window !== 'undefined' && new URLSearchParams(window.location.search).has('new')) {
        showAddModal.value = true;
    }
});
</script>

<template>
    <AppLayout
        title="العقارات والوحدات — دار"
        active-tab="properties"
        header-title="العقارات والوحدات"
        header-subtitle="إدارة العقارات السكنية ومتابعة نسب الإشغال والوحدات الشاغرة"
        breadcrumb="العقارات"
    >
        <template #actions>
            <button
                @click="showAddModal = true"
                type="button"
                class="h-11 px-5 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-sm font-semibold transition shadow-xs flex items-center gap-2 cursor-pointer"
            >
                <Plus class="h-4 w-4 stroke-[2.5]" />
                <span>إضافة عقار جديد</span>
            </button>
        </template>

        <!-- صف المؤشرات الرئيسية المتطابق (KPI Cards) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">إجمالي العقارات</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#0F2747] font-mono">{{ stats.total_properties }}</span>
                    <span class="text-xs text-[#0E6E7E] font-medium">مباني ومجمعات</span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">إجمالي الوحدات التأجيرية</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#0F2747] font-mono">{{ stats.total_units }}</span>
                    <span class="text-xs text-[#5B6770] font-medium">شقق وغرف</span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">نسبة الإشغال الإجمالية</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#0F2747] font-mono">{{ stats.overall_occupancy }}%</span>
                    <span class="text-xs text-emerald-700 font-semibold bg-emerald-50 px-2 py-0.5 rounded-md">
                        مستوى ممتاز
                    </span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">الإيراد الشهري المتوقع</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#0F2747] font-mono">{{ stats.total_monthly_revenue.toLocaleString() }} <span class="text-xs font-sans font-normal text-[#5B6770]">ج.م</span></span>
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
                    class="w-full h-10 pr-10 pl-4 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] placeholder:text-[#5B6770] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-right"
                />
                <Search class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-[#5B6770]" />
            </div>

            <!-- أزرار التصفية حسب النوع -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
                <button
                    v-for="filter in [
                        { id: 'all', label: 'الكل' },
                        { id: 'building', label: 'عمارات' },
                        { id: 'compound', label: 'مجمعات' },
                        { id: 'villa', label: 'فلل' }
                    ]"
                    :key="filter.id"
                    @click="selectedType = filter.id"
                    type="button"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition cursor-pointer shrink-0"
                    :class="selectedType === filter.id 
                        ? 'bg-[#0E6E7E] text-white shadow-2xs' 
                        : 'bg-[#F8FAFC] text-[#5B6770] hover:text-[#0F2747] border border-[#D6E0E7]'"
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
                class="rounded-3xl border border-[#D6E0E7] bg-white p-6 shadow-2xs hover:border-[#0E6E7E]/40 hover:shadow-md transition-all flex flex-col justify-between group"
            >
                <div class="space-y-4">
                    <!-- الرأس: الاسم + النوع -->
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-2xl bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center shrink-0 group-hover:bg-[#0E6E7E] group-hover:text-white transition">
                                <Building2 class="w-6 h-6 stroke-[1.75]" />
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-[#0F2747] group-hover:text-[#0E6E7E] transition">
                                    {{ prop.name }}
                                </h3>
                                <p class="text-xs text-[#5B6770] flex items-center gap-1 mt-0.5">
                                    <MapPin class="w-3.5 h-3.5 text-[#5B6770]" />
                                    <span>{{ prop.address }}</span>
                                </p>
                            </div>
                        </div>

                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-[#F1F5F9] text-[#0F2747] border border-black/5">
                            {{ prop.type_label }}
                        </span>
                    </div>

                    <!-- شريط نسبة الإشغال -->
                    <div class="space-y-1.5 pt-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-semibold text-[#0F2747]">معدل الإشغال</span>
                            <span class="font-bold text-[#0E6E7E] font-mono">{{ prop.occupancy_rate }}%</span>
                        </div>
                        <div class="w-full h-2 bg-[#F1F5F9] rounded-full overflow-hidden">
                            <div
                                class="h-full bg-[#0E6E7E] rounded-full transition-all duration-500"
                                :style="{ width: `${prop.occupancy_rate}%` }"
                            ></div>
                        </div>
                    </div>

                    <!-- تفاصيل الوحدات والمالية -->
                    <div class="grid grid-cols-3 gap-3 p-3.5 rounded-2xl bg-[#F8FAFC] border border-[#D6E0E7] text-center">
                        <div>
                            <span class="text-[11px] text-[#5B6770] block">الوحدات المؤجرة</span>
                            <span class="text-sm font-bold text-[#0F2747] font-mono mt-0.5 block">
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
                            <span class="text-sm font-bold text-[#0E6E7E] font-mono mt-0.5 block">
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
                        class="h-9 px-4 rounded-xl bg-[#0E6E7E]/10 hover:bg-[#0E6E7E] text-[#0E6E7E] hover:text-white text-xs font-bold transition flex items-center gap-1.5"
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
            @action="showAddModal = true"
        />

        <!-- نافذة إضافة عقار جديد (Add Property Modal) -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showAddModal = false" class="fixed inset-0 bg-[#0F2747]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-6 z-10 animate-in fade-in zoom-in-95" dir="rtl">
                <div class="flex items-center justify-between pb-4 border-b border-[#D6E0E7] mb-5">
                    <h3 class="text-base font-bold text-[#0F2747]">إضافة عقار جديد</h3>
                    <button @click="showAddModal = false" type="button" class="p-1.5 text-[#5B6770] hover:text-[#0F2747]">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitProperty" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">اسم العقار / المبنى *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            maxlength="255"
                            placeholder="مثال: عمارة النور، مجمع الصحافة..."
                            required
                            class="w-full h-11 px-3.5 rounded-xl border bg-[#F8FAFC] text-sm text-[#0F2747] transition"
                            :class="form.errors.name ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500 focus:ring-1 focus:ring-rose-500' : 'border-[#D6E0E7] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E]'"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600 font-medium">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">نوع العقار *</label>
                        <select
                            v-model="form.property_type"
                            class="w-full h-11 px-3.5 rounded-xl border bg-[#F8FAFC] text-sm text-[#0F2747] transition"
                            :class="form.errors.property_type ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500 focus:ring-1 focus:ring-rose-500' : 'border-[#D6E0E7] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E]'"
                        >
                            <option value="building">عمارة سكنية</option>
                            <option value="compound">مجمع سكني</option>
                            <option value="villa">فيلا</option>
                            <option value="other">عقار تجاري / آخر</option>
                        </select>
                        <p v-if="form.errors.property_type" class="mt-1 text-xs text-rose-600 font-medium">{{ form.errors.property_type }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">العنوان والموقع <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري)</span></label>
                        <input
                            v-model="form.address"
                            type="text"
                            maxlength="500"
                            placeholder="مثال: المعادي الجديدة، شارع النصر، القاهرة"
                            class="w-full h-11 px-3.5 rounded-xl border bg-[#F8FAFC] text-sm text-[#0F2747] transition"
                            :class="form.errors.address ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500 focus:ring-1 focus:ring-rose-500' : 'border-[#D6E0E7] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E]'"
                        />
                        <p v-if="form.errors.address" class="mt-1 text-xs text-rose-600 font-medium">{{ form.errors.address }}</p>
                    </div>

                    <div class="pt-4 flex items-center justify-end gap-3 border-t border-[#D6E0E7]">
                        <button
                            @click="showAddModal = false"
                            type="button"
                            class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold text-[#5B6770]"
                        >
                            إلغاء
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="h-10 px-5 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-bold transition flex items-center gap-2"
                        >
                            <Check class="w-4 h-4" />
                            <span>{{ form.processing ? 'جاري الإضافة...' : 'حفظ العقار' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

