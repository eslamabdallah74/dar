<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import UnitDrawer from '@/Components/Common/UnitDrawer.vue';
import ModalRecordPayment from '@/Components/Common/ModalRecordPayment.vue';
import {
    ArrowRight,
    Building2,
    Check,
    CreditCard,
    DollarSign,
    FileText,
    Home,
    Layers,
    MapPin,
    Plus,
    User,
    Wrench,
    X,
} from 'lucide-vue-next';

interface UnitItem {
    id: number;
    parent_unit_id?: number | null;
    parent_unit_name?: string | null;
    code: string;
    name: string;
    unit_type: string;
    type_label: string;
    is_rentable: boolean;
    status: string;
    status_label: string;
    active_lease: {
        id: number;
        renter_id: number;
        renter_name: string;
        renter_phone: string;
        rent_amount: number;
        starts_on: string;
        ends_on: string;
        billing_interval: string;
    } | null;
    has_maintenance: boolean;
}

const props = defineProps<{
    property: {
        id: number;
        name: string;
        property_type: string;
        address: string;
        created_at: string;
    };
    stats: {
        total_units: number;
        occupied: number;
        vacant: number;
        maintenance: number;
        occupancy_rate: number;
        monthly_revenue: number;
    };
    units: UnitItem[];
    recent_maintenance: Array<{
        id: number;
        title: string;
        status: string;
        reported_at: string;
        renter_name: string;
    }>;
}>();

const selectedUnit = ref<UnitItem | null>(null);
const drawerOpen = ref(false);
const showAddUnitModal = ref(false);
const recordPaymentOpen = ref(false);
const activeUnitForPayment = ref<any>(null);

const addUnitForm = useForm<{
    name: string;
    code: string;
    unit_type: string;
    parent_unit_id: number | null;
    is_rentable: boolean;
    unit_limit?: string;
}>({
    name: '',
    code: '',
    unit_type: 'apartment',
    parent_unit_id: null,
    is_rentable: true,
});

const openUnitDetails = (unit: UnitItem) => {
    selectedUnit.value = unit;
    drawerOpen.value = true;
};

const handleRecordPayment = (leaseId: number, renterId: number, amount: number) => {
    activeUnitForPayment.value = { leaseId, renterId, amount };
    drawerOpen.value = false;
    recordPaymentOpen.value = true;
};

const submitUnit = () => {
    addUnitForm.post(route('properties.units.store', props.property.id), {
        onSuccess: () => {
            addUnitForm.reset();
            showAddUnitModal.value = false;
        },
    });
};
</script>

<template>
    <AppLayout
        :title="`${property.name} — دار`"
        active-tab="properties"
        :header-title="property.name"
        :header-subtitle="property.address || 'القاهرة، جمهورية مصر العربية'"
        :breadcrumb="`العقارات / ${property.name}`"
    >
        <template #actions>
            <Link
                :href="route('properties.index')"
                class="h-10 px-4 rounded-xl border border-[#D6E0E7] bg-white hover:bg-[#F3F7FA] text-xs font-semibold text-[#5B6770] transition flex items-center gap-1.5"
            >
                <ArrowRight class="w-4 h-4" />
                <span>العودة لقائمة العقارات</span>
            </Link>

            <button
                @click="showAddUnitModal = true"
                type="button"
                class="h-10 px-5 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-bold transition flex items-center gap-2 shadow-xs cursor-pointer"
            >
                <Plus class="w-4 h-4" />
                <span>إضافة وحدة بالعمارة</span>
            </button>
        </template>

        <!-- كروت مؤشرات العمارة (4 KPIs) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">إجمالي الوحدات</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#0F2747] font-mono">{{ stats.total_units }}</span>
                    <span class="text-xs text-[#5B6770]">شقق سكنية</span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">نسبة الإشغال</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#0F2747] font-mono">{{ stats.occupancy_rate }}%</span>
                    <span class="text-xs font-bold text-[#0E6E7E] bg-[#E3F1F4] px-2 py-0.5 rounded-md">
                        {{ stats.occupied }} / {{ stats.total_units }} وحدة
                    </span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">الوحدات الشاغرة</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#0F2747] font-mono">{{ stats.vacant }}</span>
                    <span class="text-xs text-amber-700 bg-amber-50 px-2 py-0.5 rounded-md font-medium">
                        متاحة للتأجير
                    </span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">الإيراد الشهري المحقق</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#0F2747] font-mono">{{ stats.monthly_revenue.toLocaleString() }} <span class="text-xs font-sans font-normal text-[#5B6770]">ج.م</span></span>
                    <span class="text-xs text-[#5B6770]">شهرياً</span>
                </div>
            </div>
        </div>

        <!-- شجرة الأدوار والوحدات (Units Grid & Hierarchy) -->
        <div class="space-y-4">
            <div class="flex items-center justify-between pb-2 border-b border-[#D6E0E7]">
                <div class="flex items-center gap-2">
                    <Layers class="w-5 h-5 text-[#0E6E7E]" />
                    <h2 class="text-lg font-bold text-[#0F2747]">شجرة الوحدات والأدوار</h2>
                </div>
                <span class="text-xs text-[#5B6770]">انقر على أي وحدة لفتح لوحة التفاصيل السريعة</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <div
                    v-for="unit in units"
                    :key="unit.id"
                    @click="openUnitDetails(unit)"
                    class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs hover:border-[#0E6E7E] hover:shadow-md transition-all cursor-pointer flex flex-col justify-between group text-right"
                >
                    <div class="space-y-3">
                        <!-- الرأس: الكود + الحالة -->
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-mono font-bold text-[#5B6770] bg-[#F8FAFC] px-2 py-0.5 rounded-md border border-[#D6E0E7]">
                                {{ unit.code || 'APT-' + unit.id }}
                            </span>
                            <StatusBadge :status="unit.status" :label="unit.status_label" size="sm" />
                        </div>

                        <!-- اسم الشقة -->
                        <div>
                            <h4 class="text-sm font-bold text-[#0F2747] group-hover:text-[#0E6E7E] transition">
                                {{ unit.name }}
                            </h4>
                            <div class="flex items-center gap-1.5 flex-wrap mt-0.5">
                                <span class="text-xs text-[#5B6770]">{{ unit.type_label }}</span>
                                <span v-if="unit.parent_unit_name" class="text-[10px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded font-medium">
                                    تابع لـ: {{ unit.parent_unit_name }}
                                </span>
                            </div>
                        </div>

                        <!-- تفاصيل الإشغال -->
                        <div v-if="unit.active_lease" class="pt-2 border-t border-slate-100 space-y-1">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-[#5B6770]">المستأجر:</span>
                                <span class="font-bold text-[#0F2747]">{{ unit.active_lease.renter_name }}</span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-[#5B6770]">الإيجار:</span>
                                <span class="font-bold text-[#0E6E7E] font-mono">{{ unit.active_lease.rent_amount.toLocaleString() }} ج.م</span>
                            </div>
                        </div>

                        <div v-else class="pt-2 border-t border-slate-100">
                            <span class="text-xs text-amber-700 bg-amber-50 px-2 py-1 rounded-md block text-center font-medium">
                                جاهزة للتأجير الآن
                            </span>
                        </div>
                    </div>

                    <!-- أسفل الكارت -->
                    <div class="pt-3 mt-3 border-t border-[#D6E0E7] flex items-center justify-between text-[11px] text-[#0E6E7E] font-semibold">
                        <span>عرض التفاصيل</span>
                        <span class="group-hover:translate-x-[-3px] transition-transform">←</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- البلاغات الحديثة للعقار -->
        <div v-if="recent_maintenance && recent_maintenance.length > 0" class="rounded-3xl border border-[#D6E0E7] bg-white p-6 shadow-2xs space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Wrench class="w-5 h-5 text-[#0E6E7E]" />
                    <h3 class="text-base font-bold text-[#0F2747]">بلاغات الصيانة المسجلة لهذا العقار</h3>
                </div>
                <Link :href="route('maintenance.index')" class="text-xs font-bold text-[#0E6E7E] hover:underline">
                    كل طلبات الصيانة ↗
                </Link>
            </div>

            <div class="divide-y divide-slate-100">
                <div v-for="m in recent_maintenance" :key="m.id" class="py-3 flex items-center justify-between text-xs">
                    <div>
                        <span class="font-bold text-[#0F2747]">{{ m.title }}</span>
                        <span class="text-[#5B6770] mr-2">بواسطة {{ m.renter_name }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-[#5B6770] font-mono">{{ m.reported_at }}</span>
                        <StatusBadge :status="m.status" size="sm" />
                    </div>
                </div>
            </div>
        </div>

        <!-- اللوحة الجانبية السريعة للوحدة (UnitDrawer) -->
        <UnitDrawer
            :show="drawerOpen"
            :unit="selectedUnit"
            @close="drawerOpen = false"
            @record-payment="handleRecordPayment"
        />

        <!-- نافذة إضافة وحدة جديدة للعمارة -->
        <div v-if="showAddUnitModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showAddUnitModal = false" class="fixed inset-0 bg-[#0F2747]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-6 z-10 animate-in fade-in zoom-in-95" dir="rtl">
                <div class="flex items-center justify-between pb-4 border-b border-[#D6E0E7] mb-5">
                    <h3 class="text-base font-bold text-[#0F2747]">إضافة وحدة جديدة في {{ property.name }}</h3>
                    <button @click="showAddUnitModal = false" type="button" class="p-1.5 text-[#5B6770] hover:text-[#0F2747]">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitUnit" class="space-y-4">
                    <div v-if="addUnitForm.errors.unit_limit" class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs font-semibold leading-relaxed">
                        ⚠️ {{ addUnitForm.errors.unit_limit }}
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">اسم الوحدة *</label>
                        <input
                            v-model="addUnitForm.name"
                            type="text"
                            maxlength="255"
                            placeholder="مثال: شقة 303 (الدور الثالث)"
                            required
                            class="w-full h-11 px-3.5 rounded-xl border bg-[#F8FAFC] text-sm text-[#0F2747] transition"
                            :class="addUnitForm.errors.name ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500 focus:ring-1 focus:ring-rose-500' : 'border-[#D6E0E7] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E]'"
                        />
                        <p v-if="addUnitForm.errors.name" class="mt-1 text-xs text-rose-600 font-medium">{{ addUnitForm.errors.name }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-[#0F2747] mb-1.5">كود الوحدة <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري)</span></label>
                            <input
                                v-model="addUnitForm.code"
                                type="text"
                                maxlength="50"
                                placeholder="APT-303"
                                class="w-full h-11 px-3.5 rounded-xl border bg-[#F8FAFC] text-sm text-[#0F2747] font-mono transition"
                                :class="addUnitForm.errors.code ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500 focus:ring-1 focus:ring-rose-500' : 'border-[#D6E0E7] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E]'"
                            />
                            <p v-if="addUnitForm.errors.code" class="mt-1 text-xs text-rose-600 font-medium">{{ addUnitForm.errors.code }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-[#0F2747] mb-1.5">النوع *</label>
                            <select
                                v-model="addUnitForm.unit_type"
                                class="w-full h-11 px-3.5 rounded-xl border bg-[#F8FAFC] text-sm text-[#0F2747] transition"
                                :class="addUnitForm.errors.unit_type ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500 focus:ring-1 focus:ring-rose-500' : 'border-[#D6E0E7] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E]'"
                            >
                                <option value="apartment">شقة</option>
                                <option value="room">غرفة</option>
                                <option value="bed">سرير</option>
                                <option value="other">مرفق آخر</option>
                            </select>
                            <p v-if="addUnitForm.errors.unit_type" class="mt-1 text-xs text-rose-600 font-medium">{{ addUnitForm.errors.unit_type }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">الوحدة الرئيسية (الأب) <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري للتقسيم الهرمي)</span></label>
                        <select
                            v-model="addUnitForm.parent_unit_id"
                            class="w-full h-11 px-3.5 rounded-xl border bg-[#F8FAFC] text-sm text-[#0F2747] transition"
                            :class="addUnitForm.errors.parent_unit_id ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500 focus:ring-1 focus:ring-rose-500' : 'border-[#D6E0E7] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E]'"
                        >
                            <option :value="null">بدون وحدة أب (وحدة رئيسية بالعقار)</option>
                            <option v-for="u in units" :key="u.id" :value="u.id">
                                {{ u.code ? u.code + ' • ' : '' }}{{ u.name }} ({{ u.type_label }})
                            </option>
                        </select>
                        <p v-if="addUnitForm.errors.parent_unit_id" class="mt-1 text-xs text-rose-600 font-medium">{{ addUnitForm.errors.parent_unit_id }}</p>
                    </div>

                    <div class="flex items-center gap-2.5 p-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC]">
                        <input
                            id="is_rentable_checkbox"
                            v-model="addUnitForm.is_rentable"
                            type="checkbox"
                            class="w-4 h-4 rounded text-[#0E6E7E] focus:ring-[#0E6E7E] border-[#D6E0E7]"
                        />
                        <label for="is_rentable_checkbox" class="text-xs font-bold text-[#0F2747] cursor-pointer">
                            الوحدة قابلة للتأجير بشكل مستقل
                        </label>
                    </div>
                    <p v-if="addUnitForm.errors.is_rentable" class="mt-1 text-xs text-rose-600 font-medium">{{ addUnitForm.errors.is_rentable }}</p>

                    <div class="pt-4 flex items-center justify-end gap-3 border-t border-[#D6E0E7]">
                        <button
                            @click="showAddUnitModal = false"
                            type="button"
                            class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold text-[#5B6770]"
                        >
                            إلغاء
                        </button>
                        <button
                            type="submit"
                            :disabled="addUnitForm.processing"
                            class="h-10 px-5 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-bold transition flex items-center gap-2"
                        >
                            <Check class="w-4 h-4" />
                            <span>{{ addUnitForm.processing ? 'جاري الحفظ...' : 'حفظ الوحدة' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- نافذة تسجيل دفعة عند طلبها من الدروار -->
        <ModalRecordPayment
            :show="recordPaymentOpen"
            :initial-renter-id="activeUnitForPayment?.renterId"
            :initial-amount="activeUnitForPayment?.amount"
            @close="recordPaymentOpen = false"
        />
    </AppLayout>
</template>

