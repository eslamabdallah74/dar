<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useToast } from '@/composables/useToast';
import { useLocale } from '@/composables/useLocale';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import UnitDrawer from '@/Components/Common/UnitDrawer.vue';
import ModalRecordPayment from '@/Components/Common/ModalRecordPayment.vue';
import UnitHierarchyTree, { type UnitItem } from '@/Components/Properties/UnitHierarchyTree.vue';
import ModalUnitForm from '@/Components/Properties/ModalUnitForm.vue';
import ResourceTableHeading from '@/Components/Common/ResourceTableHeading.vue';
import {
    ArrowRight,
    Building2,
    DoorClosed,
    FileText,
    Home,
    Bed,
    Layers,
    List,
    Plus,
    Search,
    Store,
    User,
    Wrench,
    X,
    Maximize2,
    Minimize2,
    AlertTriangle,
    MapPin,
    ShieldCheck,
    Receipt,
} from 'lucide-vue-next';

const { t, isRtl, dir } = useLocale();

const props = withDefaults(
    defineProps<{
        property: {
            id: number;
            name: string;
            code?: string;
            property_type: string;
            property_type_label?: string;
            apartment_gender?: {
                status: 'male' | 'female' | 'mixed' | 'unspecified';
                label: string;
                short_label: string;
                description: string;
                badge_class: string;
            } | null;
            address: string;
            city?: string;
            district?: string;
            street?: string;
            building_number?: string;
            postal_code?: string;
            google_maps_url?: string;
            floors_count?: number;
            floor_number?: string;
            total_area?: number | string;
            commercial_activity?: string;
            year_built?: number;
            finishing_type?: string;
            finishing_type_label?: string;
            amenities?: string[];
            amenities_labels?: Array<{
                id: string;
                label: string;
                icon?: string | null;
            }>;
            electricity_meter_number?: string;
            water_meter_number?: string;
            gas_meter_number?: string;
            guard_name?: string;
            guard_phone?: string;
            deed_number?: string;
            notes?: string;
            cover_image_url?: string;
            created_at: string;
        };
        currency?: string;
        currency_symbol?: string;
        stats: {
            total_units: number;
            billable_units: number;
            occupied: number;
            vacant: number;
            maintenance: number;
            occupancy_rate: number;
            monthly_revenue: number;
            hierarchy_summary?: {
                apartments: number;
                rooms: number;
                beds: number;
                shops: number;
            };
        };
        units: UnitItem[];
        recent_maintenance: Array<{
            id: number;
            title: string;
            status: string;
            reported_at: string;
            renter_name: string;
        }>;
    }>(),
    {
        currency_symbol: 'ج.م',
    }
);

// UI States
const treeRef = ref<InstanceType<typeof UnitHierarchyTree> | null>(null);
const selectedUnit = ref<UnitItem | null>(null);
const drawerOpen = ref(false);
const recordPaymentOpen = ref(false);
const activeUnitForPayment = ref<any>(null);

// Modal Unit Form state
const showUnitModal = ref(false);
const modalMode = ref<'create' | 'edit'>('create');
const unitToEdit = ref<UnitItem | null>(null);
const presetParentId = ref<number | null>(null);
const presetUnitType = ref<'apartment' | 'room' | 'bed' | 'shop' | null>(null);

// Delete Confirmation Modal state
const showDeleteModal = ref(false);
const unitToDelete = ref<UnitItem | null>(null);
const isDeleting = ref(false);

// Search & Filter
const searchQuery = ref('');
const statusFilter = ref<'all' | 'occupied' | 'vacant' | 'maintenance'>('all');
const typeFilter = ref<string>('all');
const activeTab = ref<'tree' | 'table'>('tree');

const getFinishingTypeLabel = (type: string) => {
    const key = `properties.finishing_${type}`;
    const translated = t(key);
    return translated !== key ? translated : type;
};

const getAmenityLabel = (id: string) => {
    const key = `properties.amenity_${id}`;
    const translated = t(key);
    return translated !== key ? translated : id;
};

const finishingTypeLabel = computed(() => {
    if (props.property.finishing_type_label) {
        return props.property.finishing_type_label;
    }
    if (props.property.finishing_type) {
        return getFinishingTypeLabel(props.property.finishing_type);
    }
    return '';
});

const displayAmenities = computed(() => {
    if (props.property.amenities_labels && props.property.amenities_labels.length > 0) {
        return props.property.amenities_labels.map((a) => ({
            id: a.id,
            label: getAmenityLabel(a.id),
        }));
    }
    if (props.property.amenities && props.property.amenities.length > 0) {
        return props.property.amenities.map((item) => {
            const key = String(item);
            return {
                id: key,
                label: getAmenityLabel(key),
            };
        });
    }
    return [];
});

// Drawer actions
const openUnitDetails = (unit: UnitItem) => {
    selectedUnit.value = unit;
    drawerOpen.value = true;
};

const handleSelectSubUnit = (unitId: number) => {
    const found = props.units.find((u) => u.id === unitId);
    if (found) {
        selectedUnit.value = found;
    }
};

const handleRecordPayment = (leaseId: number, renterId: number, amount: number) => {
    activeUnitForPayment.value = { leaseId, renterId, amount };
    drawerOpen.value = false;
    recordPaymentOpen.value = true;
};

// Unit Form triggers
const openGeneralAddModal = () => {
    modalMode.value = 'create';
    unitToEdit.value = null;
    presetParentId.value = null;
    presetUnitType.value = null;
    showUnitModal.value = true;
};

const handleAddChildUnit = (parentUnit: UnitItem, childType: 'room' | 'bed') => {
    drawerOpen.value = false;
    modalMode.value = 'create';
    unitToEdit.value = null;
    presetParentId.value = parentUnit.id;
    presetUnitType.value = childType;
    showUnitModal.value = true;
};

const handleEditUnit = (unit: UnitItem) => {
    drawerOpen.value = false;
    modalMode.value = 'edit';
    unitToEdit.value = unit;
    presetParentId.value = unit.parent_unit_id ?? null;
    presetUnitType.value = null;
    showUnitModal.value = true;
};

const handleDeleteUnit = (unit: UnitItem) => {
    drawerOpen.value = false;
    unitToDelete.value = unit;
    showDeleteModal.value = true;
};

const toast = useToast();

const confirmDeleteUnit = () => {
    if (!unitToDelete.value) return;
    isDeleting.value = true;
    router.delete(route('properties.units.destroy', [props.property.id, unitToDelete.value.id]), {
        onSuccess: () => {
            showDeleteModal.value = false;
            unitToDelete.value = null;
        },
        onError: (errors) => {
            if (errors.unit) {
                toast.error(errors.unit);
            } else {
                toast.fromErrors(errors);
            }
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};

// Tree expand / collapse
const handleExpandAll = () => {
    treeRef.value?.expandAll();
};

const handleCollapseAll = () => {
    treeRef.value?.collapseAll();
};

// Filtered flat list for Table view
const filteredUnits = computed(() => {
    return props.units.filter((u) => {
        const q = searchQuery.value.trim().toLowerCase();
        const matchesSearch =
            q === '' ||
            u.name.toLowerCase().includes(q) ||
            u.code.toLowerCase().includes(q) ||
            (u.active_lease?.renter_name || '').toLowerCase().includes(q);

        const matchesStatus =
            statusFilter.value === 'all' || u.status === statusFilter.value;

        const matchesType =
            typeFilter.value === 'all' || u.unit_type === typeFilter.value;

        return matchesSearch && matchesStatus && matchesType;
    });
});
</script>

<template>
    <AppLayout
        :title="`${property.name} — Nook Mate`"
        active-tab="properties"
        :header-title="property.name"
        :header-subtitle="property.address || t('properties.subtitle')"
        :breadcrumb="`${t('properties.breadcrumb')} / ${property.name}`"
    >
        <template #actions>
            <div class="flex items-center gap-2.5 flex-wrap">
                <Link
                    :href="route('properties.index')"
                    class="h-10 px-4 rounded-xl border border-[#D6E0E7] bg-white hover:bg-[#F8F4EA] text-xs font-semibold text-[#5B6770] transition flex items-center gap-1.5 whitespace-nowrap"
                >
                    <ArrowRight class="w-4 h-4" :class="isRtl ? '' : 'rotate-180'" />
                    <span>{{ t('properties.back_to_properties') }}</span>
                </Link>

                <Link
                    :href="route('leases.create')"
                    class="h-10 px-4 rounded-xl border border-[#163E3E]/30 bg-[#E8F0F0] text-[#163E3E] hover:bg-[#163E3E] hover:text-white text-xs font-bold transition flex items-center gap-1.5 whitespace-nowrap"
                >
                    <FileText class="w-4 h-4" />
                    <span>{{ t('properties.rent_unit') }}</span>
                </Link>

                <button
                    @click="openGeneralAddModal"
                    type="button"
                    class="h-10 px-5 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold transition flex items-center gap-2 shadow-xs cursor-pointer whitespace-nowrap"
                >
                    <Plus class="w-4 h-4" />
                    <span>{{ t('properties.add_unit_to_property') }}</span>
                </button>
            </div>
        </template>

        <!-- 1. شريط المؤشرات المدمج (Architectural Metrics Bar) -->
        <div class="border border-[#E2DDD2] rounded-lg bg-[#FCFAF6] px-5 py-3.5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-6">
                <!-- المؤشرات الرئيسية -->
                <div class="flex items-center flex-wrap gap-x-0 gap-y-2 divide-x divide-x-reverse divide-[#E2DDD2]">
                    <div class="flex items-center gap-2 pe-5">
                        <span class="text-xs text-[#5B6770]">{{ t('properties.total_units_metric') }}</span>
                        <span class="font-mono font-bold text-[#163E3E] text-sm">{{ stats.total_units }}</span>
                        <span class="text-[11px] text-[#5B6770]">({{ t('properties.billable_units_hint', { count: stats.billable_units }) }})</span>
                    </div>
                    <div class="flex items-center gap-2 px-5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                        <span class="text-xs text-[#5B6770]">{{ t('properties.occupied') }}</span>
                        <span class="font-mono font-bold text-emerald-800 text-sm">{{ stats.occupied }}</span>
                    </div>
                    <div class="flex items-center gap-2 px-5">
                        <span class="w-2 h-2 rounded-full bg-[#C5BCAC] shrink-0"></span>
                        <span class="text-xs text-[#5B6770]">{{ t('properties.vacant') }}</span>
                        <span class="font-mono font-bold text-[#163E3E] text-sm">{{ stats.vacant }}</span>
                    </div>
                    <div v-if="stats.maintenance > 0" class="flex items-center gap-2 px-5">
                        <span class="w-2 h-2 rounded-full bg-amber-400 shrink-0"></span>
                        <span class="text-xs text-[#5B6770]">{{ t('properties.maintenance_status') }}</span>
                        <span class="font-mono font-bold text-amber-700 text-sm">{{ stats.maintenance }}</span>
                    </div>
                    <div class="flex items-center gap-2 ps-5">
                        <span class="text-xs text-[#5B6770]">{{ t('properties.monthly_revenue_metric') }}</span>
                        <span class="font-mono font-bold text-[#163E3E] text-sm">{{ stats.monthly_revenue.toLocaleString() }}</span>
                        <span class="text-[11px] text-[#5B6770]">{{ currency_symbol }}</span>
                    </div>
                </div>
                <!-- شريط الإشغال -->
                <div class="flex items-center gap-3 shrink-0">
                    <div class="w-24 bg-[#EFECE2] h-1.5 rounded-full overflow-hidden">
                        <div
                            class="bg-emerald-600 h-full rounded-full transition-all duration-500"
                            :style="{ width: `${stats.occupancy_rate}%` }"
                        ></div>
                    </div>
                    <span class="text-xs font-bold text-[#163E3E] font-mono">{{ stats.occupancy_rate }}%</span>
                    <span class="text-[11px] text-[#5B6770]">{{ t('properties.occupancy') }}</span>
                </div>
            </div>
        </div>

        <!-- 1.5. تفاصيل ومواصفات العقار والمرافق (Property Detailed Specifications Card) -->
        <div class="overflow-hidden rounded-lg border border-[#E2DDD2] bg-[#FCFAF6]">
            <div v-if="property.cover_image_url" class="relative h-48 w-full bg-[#10251D] sm:h-56">
                <img :src="property.cover_image_url" :alt="property.name" class="h-full w-full object-cover" />
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent"></div>
                <div class="absolute bottom-4 right-5 text-white sm:bottom-6 sm:right-8">
                    <div class="flex items-center gap-2">
                        <span class="rounded-lg bg-[#18705A] px-2.5 py-0.5 text-xs font-bold text-white">
                            {{ property.property_type_label || t('properties.property_default_label') }}
                        </span>
                        <span
                            v-if="property.apartment_gender && property.apartment_gender.status !== 'unspecified'"
                            class="rounded-lg px-2.5 py-0.5 text-xs font-bold border flex items-center gap-1 backdrop-blur-xs"
                            :class="[
                                property.apartment_gender.status === 'male' ? 'bg-sky-500/80 text-white border-sky-400' :
                                property.apartment_gender.status === 'female' ? 'bg-rose-500/80 text-white border-rose-400' :
                                'bg-amber-500/80 text-white border-amber-400'
                            ]"
                        >
                            <User class="w-3 h-3" />
                            <span>{{ property.apartment_gender.label }}</span>
                        </span>
                        <span v-if="property.code" class="rounded-lg bg-white/20 px-2 py-0.5 text-xs font-mono font-bold backdrop-blur-xs">
                            {{ property.code }}
                        </span>
                    </div>
                    <h2 class="mt-1.5 text-xl font-black text-white sm:text-2xl">{{ property.name }}</h2>
                    <p class="text-xs text-white/80">{{ property.address }}</p>
                </div>
            </div>

            <div class="p-5 sm:p-6 space-y-5">
                <div v-if="!property.cover_image_url" class="flex items-center justify-between gap-3 flex-wrap border-b border-[#E8EFF4] pb-4">
                    <div>
                        <h2 class="text-xl font-black text-[#163E3E]">{{ property.name }}</h2>
                        <p class="text-xs text-[#5B6770] mt-0.5">{{ property.address }}</p>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="rounded-lg bg-[#18705A] px-2.5 py-1 text-xs font-bold text-white">
                            {{ property.property_type_label || t('properties.property_default_label') }}
                        </span>
                        <span
                            v-if="property.apartment_gender && property.apartment_gender.status !== 'unspecified'"
                            class="rounded-lg px-2.5 py-1 text-xs font-bold border flex items-center gap-1"
                            :class="[
                                property.apartment_gender.status === 'male' ? 'bg-sky-50 text-sky-800 border-sky-200' :
                                property.apartment_gender.status === 'female' ? 'bg-rose-50 text-rose-800 border-rose-200' :
                                'bg-amber-50 text-amber-800 border-amber-200'
                            ]"
                        >
                            <User class="w-3 h-3" />
                            <span>{{ property.apartment_gender.label }}</span>
                        </span>
                        <span v-if="property.code" class="rounded-lg bg-slate-100 px-2 py-1 text-xs font-mono font-bold text-[#5B6770] border border-slate-200">
                            {{ property.code }}
                        </span>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <!-- الموقع -->
                    <div class="rounded-md border border-[#E2DDD2] bg-[#EFECE2]/40 p-4">
                        <span class="flex items-center gap-1.5 text-xs font-extrabold text-[#18705A]">
                            <MapPin class="size-4" />
                            <span>{{ t('properties.specs_location_title') }}</span>
                        </span>
                        <p class="mt-2 text-xs font-bold text-[#163E3E]">
                            {{ property.city || t('properties.city_not_specified') }} {{ property.district ? ` - ${property.district}` : '' }}
                        </p>
                        <p class="mt-1 text-[11px] text-[#5B6770]">
                            {{ property.street || property.address || t('properties.address_not_entered') }}
                        </p>
                        <a
                            v-if="property.google_maps_url"
                            :href="property.google_maps_url"
                            target="_blank"
                            class="mt-2.5 inline-flex items-center gap-1 text-[11px] font-bold text-[#18705A] hover:underline"
                        >
                            <span>{{ t('properties.open_in_google_maps') }}</span>
                        </a>
                    </div>

                    <!-- المواصفات -->
                    <div class="rounded-md border border-[#E2DDD2] bg-[#EFECE2]/40 p-4">
                        <span class="flex items-center gap-1.5 text-xs font-extrabold text-[#18705A]">
                            <Building2 class="size-4" />
                            <span>{{ t('properties.specs_structural_title') }}</span>
                        </span>
                        <div class="mt-2 grid grid-cols-2 gap-2 text-[11px]">
                            <div>
                                <span class="text-[#5B6770]">{{ t('properties.specs_floors') }}</span>
                                <span class="mx-1 font-bold text-[#163E3E]">{{ property.floors_count || 1 }}</span>
                            </div>
                            <div v-if="property.total_area">
                                <span class="text-[#5B6770]">{{ t('properties.specs_area') }}</span>
                                <span class="mx-1 font-bold text-[#163E3E]">{{ property.total_area }} م²</span>
                            </div>
                            <div v-if="property.year_built">
                                <span class="text-[#5B6770]">{{ t('properties.specs_built') }}</span>
                                <span class="mx-1 font-bold text-[#163E3E]">{{ property.year_built }}</span>
                            </div>
                            <div v-if="finishingTypeLabel">
                                <span class="text-[#5B6770]">{{ t('properties.specs_finishing') }}</span>
                                <span class="mx-1 font-bold text-[#163E3E]">{{ finishingTypeLabel }}</span>
                            </div>
                            <div v-if="property.floor_number">
                                <span class="text-[#5B6770]">{{ t('properties.specs_floor_num') }}</span>
                                <span class="mx-1 font-bold text-[#163E3E]">{{ property.floor_number }}</span>
                            </div>
                            <div v-if="property.commercial_activity">
                                <span class="text-[#5B6770]">{{ t('properties.specs_activity') }}</span>
                                <span class="mx-1 font-bold text-[#163E3E]">{{ property.commercial_activity }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- العدادات -->
                    <div class="rounded-md border border-[#E2DDD2] bg-[#EFECE2]/40 p-4">
                        <span class="flex items-center gap-1.5 text-xs font-extrabold text-[#18705A]">
                            <Receipt class="size-4" />
                            <span>{{ t('properties.specs_meters_title') }}</span>
                        </span>
                        <div class="mt-2 space-y-1 text-[11px]">
                            <div v-if="property.electricity_meter_number">
                                <span class="text-[#5B6770]">{{ t('properties.meter_electricity') }}</span>
                                <span class="mx-1 font-mono font-bold text-[#163E3E]">{{ property.electricity_meter_number }}</span>
                            </div>
                            <div v-if="property.water_meter_number">
                                <span class="text-[#5B6770]">{{ t('properties.meter_water') }}</span>
                                <span class="mx-1 font-mono font-bold text-[#163E3E]">{{ property.water_meter_number }}</span>
                            </div>
                            <div v-if="property.gas_meter_number">
                                <span class="text-[#5B6770]">{{ t('properties.meter_gas') }}</span>
                                <span class="mx-1 font-mono font-bold text-[#163E3E]">{{ property.gas_meter_number }}</span>
                            </div>
                            <div v-if="!property.electricity_meter_number && !property.water_meter_number && !property.gas_meter_number" class="text-[#7A8C83]">
                                {{ t('properties.no_meters_recorded') }}
                            </div>
                        </div>
                    </div>

                    <!-- الحارس والصك -->
                    <div class="rounded-md border border-[#E2DDD2] bg-[#EFECE2]/40 p-4">
                        <span class="flex items-center gap-1.5 text-xs font-extrabold text-[#18705A]">
                            <ShieldCheck class="size-4" />
                            <span>{{ t('properties.specs_management_title') }}</span>
                        </span>
                        <div class="mt-2 space-y-1 text-[11px]">
                            <div v-if="property.guard_name">
                                <span class="text-[#5B6770]">{{ t('properties.guard_label') }}</span>
                                <span class="mx-1 font-bold text-[#163E3E]">{{ property.guard_name }}</span>
                            </div>
                            <div v-if="property.guard_phone">
                                <span class="text-[#5B6770]">{{ t('properties.guard_phone_label') }}</span>
                                <a :href="`tel:${property.guard_phone}`" class="mx-1 font-mono text-[#18705A] hover:underline">{{ property.guard_phone }}</a>
                            </div>
                            <div v-if="property.deed_number">
                                <span class="text-[#5B6770]">{{ t('properties.deed_label') }}</span>
                                <span class="mx-1 font-mono font-bold text-[#163E3E]">{{ property.deed_number }}</span>
                            </div>
                            <div v-if="!property.guard_name && !property.deed_number" class="text-[#7A8C83]">
                                {{ t('properties.not_registered') }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- شارات المرافق المتاحة -->
                <div v-if="displayAmenities.length > 0" class="flex flex-wrap items-center gap-2 border-t border-[#E2DDD2] pt-3">
                    <span class="text-xs font-bold text-[#5B6770]">{{ t('properties.amenities_services_title') }}</span>
                    <span
                        v-for="amenity in displayAmenities"
                        :key="amenity.id"
                        class="rounded-md bg-[#EFECE2] px-2.5 py-1 text-[11px] font-bold text-[#18705A] border border-[#E2DDD2]"
                    >
                        {{ amenity.label }}
                    </span>
                </div>
            </div>
        </div>

        <!-- 2. شريط ملخص هيكل الوحدات (Compact Hierarchy Ledger) -->
        <div class="border border-[#E2DDD2] rounded-lg bg-[#FCFAF6] px-5 py-3.5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center flex-wrap gap-x-0 gap-y-2 divide-x divide-x-reverse divide-[#E2DDD2] text-xs">
                    <div class="flex items-center gap-2 pe-5">
                        <Home class="w-3.5 h-3.5 text-[#5B6770]" />
                        <span class="text-[#5B6770]">{{ t('properties.apartments_count') }}</span>
                        <span class="font-mono font-bold text-[#163E3E]">{{ stats.hierarchy_summary?.apartments ?? 0 }}</span>
                        <span class="text-[11px] text-[#8E9CA6]">{{ t('properties.level_1') }}</span>
                    </div>
                    <div class="flex items-center gap-2 px-5">
                        <DoorClosed class="w-3.5 h-3.5 text-[#5B6770]" />
                        <span class="text-[#5B6770]">{{ t('properties.rooms_count') }}</span>
                        <span class="font-mono font-bold text-[#163E3E]">{{ stats.hierarchy_summary?.rooms ?? 0 }}</span>
                        <span class="text-[11px] text-[#8E9CA6]">{{ t('properties.level_2') }}</span>
                    </div>
                    <div class="flex items-center gap-2 px-5">
                        <Bed class="w-3.5 h-3.5 text-[#5B6770]" />
                        <span class="text-[#5B6770]">{{ t('properties.beds_count') }}</span>
                        <span class="font-mono font-bold text-[#163E3E]">{{ stats.hierarchy_summary?.beds ?? 0 }}</span>
                        <span class="text-[11px] text-[#8E9CA6]">{{ t('properties.level_3') }}</span>
                    </div>
                    <div class="flex items-center gap-2 ps-5">
                        <Store class="w-3.5 h-3.5 text-[#5B6770]" />
                        <span class="text-[#5B6770]">{{ t('properties.shops_count') }}</span>
                        <span class="font-mono font-bold text-[#163E3E]">{{ stats.hierarchy_summary?.shops ?? 0 }}</span>
                        <span class="text-[11px] text-[#8E9CA6]">{{ t('properties.level_standalone') }}</span>
                    </div>
                </div>
                <p class="text-[11px] text-[#8E9CA6] shrink-0">
                    {{ t('properties.hierarchy_summary_hint') }}
                </p>
            </div>
        </div>

        <!-- 3. شريط البحث والتصفية وعناصر التحكم التفاعلية -->
        <div class="bg-white rounded-2xl border border-[#D6E0E7] p-4 shadow-2xs space-y-3">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                <!-- البحث الذكي -->
                <div class="relative flex-1 max-w-md">
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="t('properties.search_units_placeholder')"
                        :class="[
                            'w-full h-10 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition placeholder-[#8E9CA6]',
                            isRtl ? 'pr-9 pl-3.5 text-right' : 'pl-9 pr-3.5 text-left'
                        ]"
                    />
                    <Search
                        class="w-4 h-4 text-[#8E9CA6] absolute top-1/2 -translate-y-1/2 pointer-events-none"
                        :class="isRtl ? 'right-3' : 'left-3'"
                    />
                    <button
                        v-if="searchQuery"
                        @click="searchQuery = ''"
                        type="button"
                        class="absolute top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                        :class="isRtl ? 'left-3' : 'right-3'"
                    >
                        <X class="w-3.5 h-3.5" />
                    </button>
                </div>

                <!-- أزرار التوسيع والطي والتبديل لطريقة العرض -->
                <div class="flex items-center gap-2 self-end md:self-auto flex-wrap">
                    <!-- Expand/Collapse all (when in tree view) -->
                    <div v-if="activeTab === 'tree'" class="flex items-center gap-1 border border-[#D6E0E7] rounded-xl p-0.5 bg-[#F8FAFC]">
                        <button
                            @click="handleExpandAll"
                            type="button"
                            class="h-8 px-2.5 rounded-lg text-xs font-semibold text-[#5B6770] hover:text-[#163E3E] hover:bg-white transition flex items-center gap-1 cursor-pointer whitespace-nowrap"
                            :title="t('properties.expand_all')"
                        >
                            <Maximize2 class="w-3.5 h-3.5" />
                            <span>{{ t('properties.expand_all') }}</span>
                        </button>
                        <button
                            @click="handleCollapseAll"
                            type="button"
                            class="h-8 px-2.5 rounded-lg text-xs font-semibold text-[#5B6770] hover:text-[#163E3E] hover:bg-white transition flex items-center gap-1 cursor-pointer whitespace-nowrap"
                            :title="t('properties.collapse_all')"
                        >
                            <Minimize2 class="w-3.5 h-3.5" />
                            <span>{{ t('properties.collapse_all') }}</span>
                        </button>
                    </div>

                    <!-- View Switcher (Tree vs Table) -->
                    <div class="flex items-center gap-1 p-1 rounded-xl bg-[#F8F4EA] border border-[#E8EFF4]">
                        <button
                            @click="activeTab = 'tree'"
                            type="button"
                            class="h-8 px-3 rounded-lg text-xs font-bold transition flex items-center gap-1.5 cursor-pointer whitespace-nowrap"
                            :class="activeTab === 'tree' ? 'bg-white text-[#163E3E] shadow-2xs' : 'text-[#5B6770] hover:text-[#163E3E]'"
                        >
                            <Layers class="w-3.5 h-3.5" />
                            <span>{{ t('properties.tree_view') }}</span>
                        </button>

                        <button
                            @click="activeTab = 'table'"
                            type="button"
                            class="h-8 px-3 rounded-lg text-xs font-bold transition flex items-center gap-1.5 cursor-pointer whitespace-nowrap"
                            :class="activeTab === 'table' ? 'bg-white text-[#163E3E] shadow-2xs' : 'text-[#5B6770] hover:text-[#163E3E]'"
                        >
                            <List class="w-3.5 h-3.5" />
                            <span>{{ t('properties.table_view') }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- صف التصفية (Type & Status Tabs) -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-t border-[#E8EFF4] pt-3 text-xs">
                <!-- تصفية حسب النوع الهرمي -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1">
                    <span class="text-[#5B6770] font-medium shrink-0 ml-1">{{ t('properties.type_filter_label') }}</span>

                    <button
                        @click="typeFilter = 'all'"
                        type="button"
                        class="px-2.5 py-1 rounded-lg font-bold transition shrink-0 cursor-pointer whitespace-nowrap"
                        :class="typeFilter === 'all' ? 'bg-[#163E3E] text-white' : 'bg-[#F8F4EA] text-[#5B6770] hover:bg-slate-200'"
                    >
                        {{ t('properties.filter_all') }}
                    </button>

                    <button
                        @click="typeFilter = 'apartment'"
                        type="button"
                        class="px-2.5 py-1 rounded-lg font-bold transition flex items-center gap-1 shrink-0 cursor-pointer whitespace-nowrap"
                        :class="typeFilter === 'apartment' ? 'bg-blue-600 text-white' : 'bg-blue-50 text-blue-800 hover:bg-blue-100 border border-blue-200'"
                    >
                        <Home class="w-3.5 h-3.5" />
                        <span>{{ t('properties.apartments_count') }} ({{ stats.hierarchy_summary?.apartments ?? 0 }})</span>
                    </button>

                    <button
                        @click="typeFilter = 'room'"
                        type="button"
                        class="px-2.5 py-1 rounded-lg font-bold transition flex items-center gap-1 shrink-0 cursor-pointer whitespace-nowrap"
                        :class="typeFilter === 'room' ? 'bg-teal-600 text-white' : 'bg-teal-50 text-teal-800 hover:bg-teal-100 border border-teal-200'"
                    >
                        <DoorClosed class="w-3.5 h-3.5" />
                        <span>{{ t('properties.rooms_count') }} ({{ stats.hierarchy_summary?.rooms ?? 0 }})</span>
                    </button>

                    <button
                        @click="typeFilter = 'bed'"
                        type="button"
                        class="px-2.5 py-1 rounded-lg font-bold transition flex items-center gap-1 shrink-0 cursor-pointer whitespace-nowrap"
                        :class="typeFilter === 'bed' ? 'bg-amber-600 text-white' : 'bg-amber-50 text-amber-800 hover:bg-amber-100 border border-amber-200'"
                    >
                        <Bed class="w-3.5 h-3.5" />
                        <span>{{ t('properties.beds_count') }} ({{ stats.hierarchy_summary?.beds ?? 0 }})</span>
                    </button>

                    <button
                        @click="typeFilter = 'shop'"
                        type="button"
                        class="px-2.5 py-1 rounded-lg font-bold transition flex items-center gap-1 shrink-0 cursor-pointer whitespace-nowrap"
                        :class="typeFilter === 'shop' ? 'bg-purple-600 text-white' : 'bg-purple-50 text-purple-800 hover:bg-purple-100 border border-purple-200'"
                    >
                        <Store class="w-3.5 h-3.5" />
                        <span>{{ t('properties.shops_count') }} ({{ stats.hierarchy_summary?.shops ?? 0 }})</span>
                    </button>
                </div>

                <!-- تصفية حسب حالة الإشغال -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1">
                    <span class="text-[#5B6770] font-medium shrink-0 ml-1">{{ t('properties.status_filter_label') }}</span>

                    <button
                        @click="statusFilter = 'all'"
                        type="button"
                        class="px-2.5 py-1 rounded-lg font-bold transition shrink-0 cursor-pointer whitespace-nowrap"
                        :class="statusFilter === 'all' ? 'bg-[#163E3E] text-white' : 'bg-[#F8F4EA] text-[#5B6770] hover:bg-slate-200'"
                    >
                        {{ t('properties.filter_all') }}
                    </button>

                    <button
                        @click="statusFilter = 'occupied'"
                        type="button"
                        class="px-2.5 py-1 rounded-lg font-bold transition flex items-center gap-1 shrink-0 cursor-pointer whitespace-nowrap"
                        :class="statusFilter === 'occupied' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100 border border-emerald-200'"
                    >
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500" :class="{ 'bg-white': statusFilter === 'occupied' }"></span>
                        <span>{{ t('properties.occupied') }} ({{ stats.occupied }})</span>
                    </button>

                    <button
                        @click="statusFilter = 'vacant'"
                        type="button"
                        class="px-2.5 py-1 rounded-lg font-bold transition flex items-center gap-1 shrink-0 cursor-pointer whitespace-nowrap"
                        :class="statusFilter === 'vacant' ? 'bg-amber-600 text-white' : 'bg-amber-50 text-amber-800 hover:bg-amber-100 border border-amber-200'"
                    >
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500" :class="{ 'bg-white': statusFilter === 'vacant' }"></span>
                        <span>{{ t('properties.vacant') }} ({{ stats.vacant }})</span>
                    </button>

                    <button
                        v-if="stats.maintenance > 0"
                        @click="statusFilter = 'maintenance'"
                        type="button"
                        class="px-2.5 py-1 rounded-lg font-bold transition flex items-center gap-1 shrink-0 cursor-pointer whitespace-nowrap"
                        :class="statusFilter === 'maintenance' ? 'bg-sky-600 text-white' : 'bg-sky-50 text-sky-800 hover:bg-sky-100 border border-sky-200'"
                    >
                        <span class="w-1.5 h-1.5 rounded-full bg-sky-500" :class="{ 'bg-white': statusFilter === 'maintenance' }"></span>
                        <span>{{ t('properties.maintenance_status') }} ({{ stats.maintenance }})</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 4. عرض المحتوى: الشجرة الهرمية التفاعلية أو الجدول -->
        <div v-if="activeTab === 'tree'">
            <UnitHierarchyTree
                ref="treeRef"
                :units="units"
                :currency-symbol="currency_symbol"
                :search-query="searchQuery"
                :selected-type-filter="typeFilter"
                :selected-status-filter="statusFilter"
                @select-unit="openUnitDetails"
                @edit-unit="handleEditUnit"
                @add-child-unit="handleAddChildUnit"
            />
        </div>

        <!-- جدول تفصيلي تنفيذي (عند اختيار تبويب الجدول) -->
        <div v-else class="bg-white rounded-3xl border border-[#D6E0E7] shadow-2xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm">
                    <thead>
                        <tr class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] text-xs font-bold">
                            <th class="py-3.5 px-6">{{ t('properties.th_unit_code') }}</th>
                            <th class="py-3.5 px-6">{{ t('properties.th_unit_name') }}</th>
                            <th class="py-3.5 px-6">{{ t('properties.th_unit_type') }}</th>
                            <th class="py-3.5 px-6">{{ t('properties.th_parent_path') }}</th>
                            <th class="py-3.5 px-6">
                                <ResourceTableHeading :href="route('renters.index')">{{ t('properties.th_current_renter') }}</ResourceTableHeading>
                            </th>
                            <th class="py-3.5 px-6">{{ t('properties.th_monthly_value') }}</th>
                            <th class="py-3.5 px-6">{{ t('properties.th_status') }}</th>
                            <th class="py-3.5 px-6 text-start">{{ t('properties.th_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E8EFF4]">
                        <tr
                            v-for="unit in filteredUnits"
                            :key="unit.id"
                            class="hover:bg-[#F8FAFC] transition group"
                        >
                            <td class="py-4 px-6 font-mono font-bold text-[#163E3E]">
                                {{ unit.code || '—' }}
                            </td>

                            <td class="py-4 px-6">
                                <span
                                    @click="openUnitDetails(unit)"
                                    class="font-bold text-[#163E3E] hover:text-[#163E3E] transition cursor-pointer block"
                                >
                                    {{ unit.name }}
                                </span>
                            </td>

                            <td class="py-4 px-6 text-xs text-[#163E3E]">
                                <span class="bg-[#F8F4EA] px-2 py-0.5 rounded-md border border-[#D6E0E7] font-bold whitespace-nowrap">
                                    {{ unit.type_label }}
                                </span>
                            </td>

                            <td class="py-4 px-6 text-xs text-[#5B6770]">
                                <span v-if="unit.parent_unit_name" class="font-medium text-[#163E3E]">
                                    {{ t('properties.belongs_to', { parent: unit.parent_unit_name }) }}
                                </span>
                                <span v-else class="text-slate-400">
                                    {{ unit.unit_type === 'shop' ? t('properties.standalone_shop') : t('properties.main_unit') }}
                                </span>
                            </td>

                            <td class="py-4 px-6">
                                <div v-if="unit.active_lease" class="min-w-0">
                                    <span class="font-bold text-[#163E3E] text-xs block truncate">{{ unit.active_lease.renter_name }}</span>
                                    <span class="text-[11px] text-[#5B6770] font-mono block" dir="ltr">{{ unit.active_lease.renter_phone }}</span>
                                </div>
                                <span v-else class="text-xs text-[#8E9CA6] italic">{{ t('properties.vacant_label') }}</span>
                            </td>

                            <td class="py-4 px-6 font-mono text-xs font-bold text-[#163E3E]">
                                <span v-if="unit.active_lease">
                                    {{ unit.active_lease.rent_amount.toLocaleString() }} {{ currency_symbol }}
                                </span>
                                <span v-else class="text-[#8E9CA6]">—</span>
                            </td>

                            <td class="py-4 px-6">
                                <StatusBadge :status="unit.status" :label="unit.status_label" size="sm" />
                            </td>

                            <td class="py-4 px-6 text-start">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        @click="openUnitDetails(unit)"
                                        type="button"
                                        class="h-8 px-3 rounded-lg border border-[#D6E0E7] hover:bg-[#F8F4EA] text-[#163E3E] text-xs font-semibold transition cursor-pointer whitespace-nowrap"
                                    >
                                        {{ t('properties.unit_details_btn') }}
                                    </button>

                                    <button
                                        @click="handleEditUnit(unit)"
                                        type="button"
                                        class="h-8 px-2.5 rounded-lg border border-[#D6E0E7] hover:bg-[#F8F4EA] text-[#5B6770] hover:text-[#163E3E] text-xs transition cursor-pointer whitespace-nowrap"
                                        :title="t('properties.edit_unit_btn')"
                                    >
                                        {{ t('properties.edit_unit_btn') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 5. بلاغات الصيانة المسجلة لهذا العقار -->
        <div v-if="recent_maintenance && recent_maintenance.length > 0" class="rounded-3xl border border-[#D6E0E7] bg-white p-6 shadow-2xs space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Wrench class="w-5 h-5 text-[#163E3E]" />
                    <h3 class="text-base font-bold text-[#163E3E]">{{ t('properties.maintenance_for_property') }}</h3>
                </div>
                <Link :href="route('maintenance.index')" class="text-xs font-bold text-[#163E3E] hover:underline">
                    {{ t('properties.all_maintenance_link') }}
                </Link>
            </div>

            <div class="divide-y divide-[#E8EFF4]">
                <div v-for="m in recent_maintenance" :key="m.id" class="py-3 flex items-center justify-between text-xs">
                    <div>
                        <span class="font-bold text-[#163E3E]">{{ m.title }}</span>
                        <span class="text-[#5B6770] mx-2">{{ t('properties.by_renter', { name: m.renter_name }) }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-[#5B6770] font-mono">{{ m.reported_at }}</span>
                        <StatusBadge :status="m.status" size="sm" />
                    </div>
                </div>
            </div>
        </div>

        <!-- 6. لوحة تفاصيل الوحدة السريعة (UnitDrawer) -->
        <UnitDrawer
            :show="drawerOpen"
            :unit="selectedUnit"
            :property-name="property.name"
            :currency-symbol="currency_symbol"
            @close="drawerOpen = false"
            @select-unit="handleSelectSubUnit"
            @add-sub-unit="(pId, type) => selectedUnit && handleAddChildUnit(selectedUnit, type)"
            @edit-unit="handleEditUnit"
            @delete-unit="handleDeleteUnit"
            @record-payment="handleRecordPayment"
        />

        <!-- 7. نافذة إضافة وتعديل الوحدة (ModalUnitForm) -->
        <ModalUnitForm
            :show="showUnitModal"
            :mode="modalMode"
            :property-id="property.id"
            :property-name="property.name"
            :unit="unitToEdit"
            :preset-parent-id="presetParentId"
            :preset-unit-type="presetUnitType"
            :available-units="units"
            @close="showUnitModal = false"
            @saved="showUnitModal = false"
        />

        <!-- 8. نافذة تأكيد حذف الوحدة -->
        <div v-if="showDeleteModal && unitToDelete" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showDeleteModal = false" class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs"></div>
            <div :dir="dir" class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-6 z-10 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                        <AlertTriangle class="w-5 h-5 stroke-[2]" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-[#163E3E]">{{ t('properties.delete_unit_confirm_title') }}</h3>
                        <p class="text-xs text-[#5B6770]">{{ unitToDelete.name }} ({{ unitToDelete.type_label }})</p>
                    </div>
                </div>

                <p class="text-xs text-[#5B6770] leading-relaxed">
                    {{ t('properties.delete_unit_confirm_desc') }}
                </p>

                <div class="pt-2 flex items-center justify-end gap-2.5">
                    <button
                        @click="showDeleteModal = false"
                        type="button"
                        class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold text-[#5B6770] hover:bg-slate-50 transition cursor-pointer"
                    >
                        {{ t('common.cancel') }}
                    </button>
                    <button
                        @click="confirmDeleteUnit"
                        :disabled="isDeleting"
                        type="button"
                        class="h-10 px-5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                    >
                        <span>{{ isDeleting ? t('properties.deleting') : t('properties.yes_delete_unit') }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 9. نافذة تسجيل دفعة عند طلبها من الدروار -->
        <ModalRecordPayment
            :show="recordPaymentOpen"
            :initial-renter-id="activeUnitForPayment?.renterId"
            :initial-amount="activeUnitForPayment?.amount"
            @close="recordPaymentOpen = false"
        />
    </AppLayout>
</template>
