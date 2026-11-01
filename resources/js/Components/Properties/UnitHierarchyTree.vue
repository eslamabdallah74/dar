<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import {
    ChevronDown,
    Plus,
    User,
    Users,
    ArrowUpRight,
    Lock,
    Eye,
    Layers,
} from 'lucide-vue-next';

export interface ActiveLease {
    id: number;
    contract_number?: string;
    renter_id: number;
    renter_name: string;
    renter_phone: string;
    renter_national_id?: string;
    renter_gender?: string | null;
    renter_gender_label?: string | null;
    rent_amount: number;
    security_deposit_amount?: number | null;
    security_deposit_status?: string | null;
    starts_on: string;
    ends_on: string;
    days_remaining?: number | null;
    billing_interval?: string;
}

export interface UnitItem {
    id: number;
    property_id?: number;
    parent_unit_id?: number | null;
    parent_unit_name?: string | null;
    floor_name?: string;
    code: string;
    name: string;
    unit_type: 'apartment' | 'room' | 'bed' | 'shop' | 'other';
    type_label: string;
    breadcrumb?: string;
    is_rentable: boolean;
    can_rent?: boolean;
    can_subdivide?: boolean;
    allowed_child_type?: 'room' | 'bed' | null;
    allowed_child_label?: string | null;
    status: 'occupied' | 'vacant' | 'maintenance' | 'unavailable';
    status_label: string;
    apartment_gender?: {
        status: 'male' | 'female' | 'mixed' | 'unspecified';
        label: string;
        short_label: string;
        description: string;
        badge_class: string;
        male_count: number;
        female_count: number;
        unspecified_count: number;
        total_active_renters: number;
    } | null;
    ancestor_has_active_lease?: boolean;
    descendant_has_active_lease?: boolean;
    active_lease: ActiveLease | null;
    child_units?: any[];
    has_maintenance?: boolean;
    maintenance_requests?: Array<{
        id: number;
        title: string;
        status: string;
        reported_at: string;
    }>;
}

const props = defineProps<{
    units: UnitItem[];
    currencySymbol?: string;
    searchQuery?: string;
    selectedTypeFilter?: string;
    selectedStatusFilter?: string;
}>();

const emit = defineEmits<{
    (e: 'selectUnit', unit: UnitItem): void;
    (e: 'editUnit', unit: UnitItem): void;
    (e: 'addChildUnit', parentUnit: UnitItem, childType: 'room' | 'bed'): void;
}>();

// Expanded branches state: stores unit IDs that are currently expanded
const expandedNodes = ref<Set<number>>(new Set());

// Initialize all apartments and rooms as expanded by default
const initializeExpanded = () => {
    const newSet = new Set<number>();
    props.units.forEach(u => {
        if (u.unit_type === 'apartment' || u.unit_type === 'room') {
            newSet.add(u.id);
        }
    });
    expandedNodes.value = newSet;
};

// Initialize once mounted/updated
watch(() => props.units, () => {
    if (expandedNodes.value.size === 0) {
        initializeExpanded();
    }
}, { immediate: true });

const toggleNode = (nodeId: number) => {
    if (expandedNodes.value.has(nodeId)) {
        expandedNodes.value.delete(nodeId);
    } else {
        expandedNodes.value.add(nodeId);
    }
};

const isNodeExpanded = (nodeId: number): boolean => {
    return expandedNodes.value.has(nodeId);
};

// Expand all / Collapse all methods exposed to parent
const expandAll = () => {
    const newSet = new Set<number>();
    props.units.forEach(u => {
        newSet.add(u.id);
    });
    expandedNodes.value = newSet;
};

const collapseAll = () => {
    expandedNodes.value.clear();
};

defineExpose({
    expandAll,
    collapseAll,
});

// Group units by parent_unit_id
const unitsByParent = computed(() => {
    const map = new Map<number | null, UnitItem[]>();
    props.units.forEach(u => {
        const pId = u.parent_unit_id ?? null;
        if (!map.has(pId)) {
            map.set(pId, []);
        }
        map.get(pId)!.push(u);
    });
    return map;
});

// Root units (Apartments, Shops, and any top-level unit)
const rootUnits = computed(() => {
    return unitsByParent.value.get(null) || [];
});

const getChildren = (parentId: number): UnitItem[] => {
    return unitsByParent.value.get(parentId) || [];
};

// Search & filter predicate
const query = computed(() => (props.searchQuery || '').trim().toLowerCase());
const typeFilter = computed(() => props.selectedTypeFilter || 'all');
const statusFilter = computed(() => props.selectedStatusFilter || 'all');

const matchesSearch = (unit: UnitItem): boolean => {
    if (!query.value) return true;
    const nameMatch = unit.name.toLowerCase().includes(query.value);
    const codeMatch = unit.code ? unit.code.toLowerCase().includes(query.value) : false;
    const renterMatch = unit.active_lease?.renter_name.toLowerCase().includes(query.value) || false;
    return nameMatch || codeMatch || renterMatch;
};

const matchesStatus = (unit: UnitItem): boolean => {
    if (statusFilter.value === 'all') return true;
    if (statusFilter.value === 'occupied') return unit.status === 'occupied';
    if (statusFilter.value === 'vacant') return unit.status === 'vacant';
    if (statusFilter.value === 'maintenance') return unit.status === 'maintenance';
    return true;
};

const matchesType = (unit: UnitItem): boolean => {
    if (typeFilter.value === 'all') return true;
    return unit.unit_type === typeFilter.value;
};

// Check if a unit or any of its descendants matches
const unitMatchesAllFilters = (unit: UnitItem): boolean => {
    return matchesSearch(unit) && matchesStatus(unit) && matchesType(unit);
};

// Check if a node should be visible in the tree (either it matches, or any of its descendants match)
const isBedVisible = (bed: UnitItem): boolean => {
    return unitMatchesAllFilters(bed);
};

const isRoomVisible = (room: UnitItem): boolean => {
    // If the room itself matches all filters
    if (unitMatchesAllFilters(room)) return true;

    // Or if any of its child beds matches
    const beds = getChildren(room.id);
    return beds.some(b => isBedVisible(b));
};

const isApartmentVisible = (apt: UnitItem): boolean => {
    // If apartment itself matches all filters
    if (unitMatchesAllFilters(apt)) return true;

    // Or if any of its child rooms (or their beds) match
    const rooms = getChildren(apt.id);
    return rooms.some(r => isRoomVisible(r));
};

const isRootVisible = (root: UnitItem): boolean => {
    if (root.unit_type === 'shop') {
        return unitMatchesAllFilters(root);
    }
    return isApartmentVisible(root);
};

// Filtered root units
const visibleRootUnits = computed(() => {
    return rootUnits.value.filter(u => isRootVisible(u));
});

// Auto-expand branches when searching so user sees the matching nested bed/room immediately
watch(query, (q) => {
    if (q) {
        expandAll();
    }
});
</script>

<template>
    <div class="space-y-0" dir="rtl">
        <!-- Empty State -->
        <div
            v-if="visibleRootUnits.length === 0"
            class="border border-dashed border-[#C5BCAC] bg-[#FCFAF6] rounded-lg p-10 text-center space-y-3"
        >
            <div class="w-10 h-10 rounded-md bg-[#EFECE2] text-[#5B6770] mx-auto flex items-center justify-center">
                <Layers class="w-5 h-5 stroke-[1.5]" />
            </div>
            <h4 class="text-sm font-semibold text-[#163E3E]">لا توجد وحدات مطابقة للبحث أو التصفية</h4>
            <p class="text-xs text-[#5B6770] max-w-xs mx-auto leading-relaxed">
                جرّب تعديل كلمات البحث أو مسح تصفيات النوع والحالة لعرض جميع مستويات الهيكل العقاري.
            </p>
        </div>

        <!-- ====================================================
             ARCHITECTURAL UNIT REGISTER
             Each root unit (apartment/shop) is a structured
             building section with clear hierarchical connectors
        ===================================================== -->
        <div v-else class="border border-[#E2DDD2] rounded-lg overflow-hidden bg-[#FCFAF6]">

            <!-- Root Unit Rows -->
            <div
                v-for="(rootUnit, rootIndex) in visibleRootUnits"
                :key="rootUnit.id"
            >
                <!-- ====== LEVEL 1: APARTMENT / SHOP ROW ====== -->
                <div
                    class="group border-b border-[#E2DDD2] transition-colors"
                    :class="[
                        rootIndex === visibleRootUnits.length - 1 && !isNodeExpanded(rootUnit.id) ? 'border-b-0' : '',
                        rootUnit.status === 'occupied' ? 'bg-white' :
                        rootUnit.status === 'maintenance' ? 'bg-[#FDFAF4]' :
                        'bg-white'
                    ]"
                >
                    <!-- Status accent line on right edge (RTL left = visual right) -->
                    <div class="flex items-stretch">
                        <!-- Status stripe -->
                        <div
                            class="w-1 shrink-0 self-stretch"
                            :class="[
                                rootUnit.status === 'occupied' ? 'bg-emerald-500' :
                                rootUnit.status === 'maintenance' ? 'bg-amber-400' :
                                rootUnit.unit_type === 'shop' ? 'bg-[#163E3E]/30' :
                                'bg-[#E2DDD2]'
                            ]"
                        ></div>

                        <div class="flex-1 px-4 py-3.5 sm:px-5 sm:py-4 flex flex-col md:flex-row md:items-center justify-between gap-3 min-w-0">
                            <!-- Left: Expand control + Identity -->
                            <div class="flex items-center gap-3 min-w-0">
                                <!-- Expand/Collapse toggle for apartments -->
                                <button
                                    v-if="rootUnit.unit_type === 'apartment'"
                                    @click="toggleNode(rootUnit.id)"
                                    type="button"
                                    class="w-6 h-6 rounded flex items-center justify-center text-[#5B6770] hover:text-[#163E3E] hover:bg-[#EFECE2] transition shrink-0 cursor-pointer"
                                    :title="isNodeExpanded(rootUnit.id) ? 'طي الغرف' : 'عرض الغرف'"
                                >
                                    <ChevronDown
                                        class="w-4 h-4 transition-transform duration-200"
                                        :class="{ '-rotate-90': !isNodeExpanded(rootUnit.id) }"
                                    />
                                </button>
                                <!-- Spacer for shops (no expand button) -->
                                <div v-else class="w-6 shrink-0"></div>

                                <!-- Unit Identity Block -->
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-baseline gap-2.5 flex-wrap">
                                        <!-- Unit name — clickable to open drawer -->
                                        <button
                                            @click="emit('selectUnit', rootUnit)"
                                            type="button"
                                            class="text-sm font-bold text-[#163E3E] hover:text-[#102F2F] transition text-right cursor-pointer truncate"
                                        >
                                            {{ rootUnit.name }}
                                        </button>

                                        <!-- Unit code (mono) -->
                                        <span
                                            v-if="rootUnit.code"
                                            class="text-[11px] font-mono text-[#5B6770] bg-[#EFECE2] px-1.5 py-px rounded border border-[#C5BCAC] leading-none"
                                        >
                                            {{ rootUnit.code }}
                                        </span>

                                        <!-- Unit type label -->
                                        <span
                                            class="text-[11px] font-semibold px-1.5 py-px rounded border leading-none shrink-0"
                                            :class="[
                                                rootUnit.unit_type === 'shop'
                                                    ? 'bg-[#163E3E]/5 text-[#163E3E] border-[#163E3E]/20'
                                                    : 'bg-[#EFECE2] text-[#334155] border-[#C5BCAC]'
                                            ]"
                                        >
                                            {{ rootUnit.type_label }}
                                        </span>

                                        <!-- Status badge -->
                                        <StatusBadge :status="rootUnit.status" :label="rootUnit.status_label" size="sm" />

                                        <!-- Gender badge for apartments -->
                                        <span
                                            v-if="rootUnit.unit_type === 'apartment' && rootUnit.apartment_gender && rootUnit.apartment_gender.status !== 'unspecified'"
                                            class="inline-flex items-center gap-1 text-[11px] font-semibold px-1.5 py-px rounded border leading-none shrink-0"
                                            :class="[
                                                rootUnit.apartment_gender.status === 'male'
                                                    ? 'bg-sky-50 text-sky-800 border-sky-200'
                                                    : rootUnit.apartment_gender.status === 'female'
                                                    ? 'bg-rose-50 text-rose-800 border-rose-200'
                                                    : rootUnit.apartment_gender.status === 'mixed'
                                                    ? 'bg-amber-50 text-amber-800 border-amber-200'
                                                    : 'bg-slate-50 text-slate-600 border-slate-200'
                                            ]"
                                            :title="rootUnit.apartment_gender.description"
                                        >
                                            <User v-if="rootUnit.apartment_gender.status === 'male' || rootUnit.apartment_gender.status === 'female'" class="w-3 h-3" />
                                            <Users v-else-if="rootUnit.apartment_gender.status === 'mixed'" class="w-3 h-3" />
                                            <span>{{ rootUnit.apartment_gender.label }}</span>
                                        </span>
                                    </div>

                                    <!-- Sub-line: floor / tenant / children count -->
                                    <div class="flex items-center gap-3 mt-1 flex-wrap">
                                        <span v-if="rootUnit.floor_name" class="text-[11px] text-[#5B6770]">
                                            {{ rootUnit.floor_name }}
                                        </span>

                                        <!-- Rooms count for apartments -->
                                        <span
                                            v-if="rootUnit.unit_type === 'apartment'"
                                            class="text-[11px] text-[#5B6770]"
                                        >
                                            {{ getChildren(rootUnit.id).length }} {{ getChildren(rootUnit.id).length === 1 ? 'غرفة' : 'غرف' }}
                                        </span>

                                        <!-- Active tenant -->
                                        <span
                                            v-if="rootUnit.active_lease"
                                            class="text-[11px] text-emerald-800 font-medium flex items-center gap-1"
                                        >
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                                            {{ rootUnit.active_lease.renter_name }}
                                            <span class="font-mono text-[#5B6770]">· {{ rootUnit.active_lease.rent_amount.toLocaleString() }} {{ currencySymbol }}</span>
                                        </span>

                                        <!-- Descendant lease warning -->
                                        <span
                                            v-else-if="rootUnit.descendant_has_active_lease"
                                            class="text-[11px] text-amber-700 flex items-center gap-1"
                                        >
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0"></span>
                                            <span>عقود نشطة على غرف داخلية</span>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Action buttons -->
                            <div class="flex items-center gap-1.5 self-end md:self-center shrink-0">
                                <!-- Add room button (apartments only) -->
                                <button
                                    v-if="rootUnit.unit_type === 'apartment' && rootUnit.can_subdivide"
                                    @click="emit('addChildUnit', rootUnit, 'room')"
                                    type="button"
                                    class="h-8 px-3 rounded-md border border-[#C5BCAC] bg-[#FCFAF6] hover:bg-[#163E3E] hover:text-white hover:border-[#163E3E] text-[#163E3E] text-[11px] font-semibold transition flex items-center gap-1 cursor-pointer"
                                >
                                    <Plus class="w-3 h-3" />
                                    <span>إضافة غرفة</span>
                                </button>
                                <span
                                    v-else-if="rootUnit.unit_type === 'apartment' && !rootUnit.can_subdivide"
                                    class="inline-flex items-center gap-1 h-8 px-2.5 rounded-md bg-[#EFECE2] text-[#8E9CA6] text-[11px] font-medium border border-[#E2DDD2]"
                                    title="لا يمكن تقسيم هذه الوحدة حالياً لوجود عقد إيجار نشط"
                                >
                                    <Lock class="w-3 h-3" />
                                    <span>مؤجرة كوحدة</span>
                                </span>

                                <!-- Rent button -->
                                <Link
                                    v-if="rootUnit.can_rent"
                                    :href="route('leases.create', { unit_id: rootUnit.id })"
                                    class="h-8 px-3 rounded-md bg-[#163E3E] hover:bg-[#102F2F] text-white text-[11px] font-semibold transition flex items-center gap-1"
                                >
                                    <ArrowUpRight class="w-3 h-3" />
                                    <span>تأجير</span>
                                </Link>

                                <!-- Details button -->
                                <button
                                    @click="emit('selectUnit', rootUnit)"
                                    type="button"
                                    class="h-8 px-3 rounded-md border border-[#C5BCAC] bg-[#FCFAF6] hover:bg-[#EFECE2] text-[#163E3E] text-[11px] font-semibold transition flex items-center gap-1.5 cursor-pointer"
                                >
                                    <Eye class="w-3 h-3 text-[#5B6770]" />
                                    <span>التفاصيل</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ====== LEVEL 2: ROOMS UNDER APARTMENT ====== -->
                <div
                    v-if="rootUnit.unit_type === 'apartment' && isNodeExpanded(rootUnit.id)"
                    class="border-b border-[#E2DDD2]"
                    :class="rootIndex === visibleRootUnits.length - 1 ? 'border-b-0' : ''"
                >
                    <!-- Empty apartment (no rooms) -->
                    <div
                        v-if="getChildren(rootUnit.id).length === 0"
                        class="bg-[#F8F4EA]/50 px-5 py-4 flex items-center gap-3"
                    >
                        <!-- Tree connector -->
                        <div class="w-1 shrink-0 self-stretch"></div>
                        <div class="mr-4 sm:mr-8 flex items-center justify-between flex-1 gap-3 flex-wrap">
                            <p class="text-[11px] text-[#5B6770] leading-relaxed">
                                هذه الشقة غير مقسمة — يمكن تأجيرها كوحدة متكاملة أو تقسيمها إلى غرف مستقلة.
                            </p>
                            <button
                                v-if="rootUnit.can_subdivide"
                                @click="emit('addChildUnit', rootUnit, 'room')"
                                type="button"
                                class="h-7 px-3 rounded-md bg-[#163E3E] hover:bg-[#102F2F] text-white text-[11px] font-semibold transition flex items-center gap-1 cursor-pointer shrink-0"
                            >
                                <Plus class="w-3 h-3" />
                                <span>تقسيم الشقة</span>
                            </button>
                            <span v-else class="text-[11px] text-amber-700 flex items-center gap-1 shrink-0">
                                <Lock class="w-3 h-3" />
                                <span>مؤجرة — لا يمكن التقسيم</span>
                            </span>
                        </div>
                    </div>

                    <!-- Rooms list -->
                    <div v-else>
                        <div
                            v-for="(room, roomIndex) in getChildren(rootUnit.id)"
                            :key="room.id"
                            v-show="isRoomVisible(room)"
                        >
                            <!-- Room row -->
                            <div
                                class="group border-b border-[#EFECE2] transition-colors"
                                :class="[
                                    roomIndex === getChildren(rootUnit.id).length - 1 && !isNodeExpanded(room.id) ? 'border-b-0' : '',
                                ]"
                            >
                                <div class="flex items-stretch">
                                    <!-- Level indent connector (RTL: visual right = logical start) -->
                                    <div class="w-1 shrink-0 bg-transparent"></div>

                                    <!-- RTL tree line -->
                                    <div class="w-6 sm:w-10 shrink-0 flex flex-col items-center py-4">
                                        <div class="w-px flex-1 bg-[#C5BCAC]"></div>
                                        <div class="h-px w-3 sm:w-5 bg-[#C5BCAC]"></div>
                                    </div>

                                    <div class="flex-1 bg-[#FCFAF6] px-3 py-3 sm:px-4 sm:py-3.5 flex flex-col md:flex-row md:items-center justify-between gap-3 min-w-0">
                                        <!-- Room identity -->
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <!-- Expand toggle for rooms with beds -->
                                            <button
                                                @click="toggleNode(room.id)"
                                                type="button"
                                                class="w-5 h-5 rounded flex items-center justify-center text-[#5B6770] hover:text-[#163E3E] hover:bg-[#EFECE2] transition shrink-0 cursor-pointer"
                                                :title="isNodeExpanded(room.id) ? 'طي الأسرّة' : 'عرض الأسرّة'"
                                            >
                                                <ChevronDown
                                                    class="w-3.5 h-3.5 transition-transform duration-200"
                                                    :class="{ '-rotate-90': !isNodeExpanded(room.id) }"
                                                />
                                            </button>

                                            <div class="min-w-0">
                                                <div class="flex items-baseline gap-2 flex-wrap">
                                                    <button
                                                        @click="emit('selectUnit', room)"
                                                        type="button"
                                                        class="text-xs font-semibold text-[#163E3E] hover:text-[#102F2F] transition text-right cursor-pointer"
                                                    >
                                                        {{ room.name }}
                                                    </button>

                                                    <span
                                                        v-if="room.code"
                                                        class="text-[10px] font-mono text-[#5B6770] bg-[#EFECE2] px-1.5 py-px rounded border border-[#C5BCAC] leading-none"
                                                    >
                                                        {{ room.code }}
                                                    </span>

                                                    <span class="text-[10px] font-medium px-1.5 py-px rounded border border-[#C5BCAC] bg-[#EFECE2] text-[#334155] leading-none">
                                                        {{ room.type_label }}
                                                    </span>

                                                    <StatusBadge :status="room.status" :label="room.status_label" size="sm" />
                                                </div>

                                                <!-- Room sub-line -->
                                                <div class="flex items-center gap-2.5 mt-0.5 flex-wrap">
                                                    <span class="text-[11px] text-[#5B6770]">
                                                        {{ getChildren(room.id).length }} {{ getChildren(room.id).length === 1 ? 'سرير' : 'أسرّة' }}
                                                    </span>

                                                    <span
                                                        v-if="room.active_lease"
                                                        class="text-[11px] text-emerald-800 font-medium flex items-center gap-1"
                                                    >
                                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                                                        {{ room.active_lease.renter_name }}
                                                        <span class="font-mono text-[#5B6770]">· {{ room.active_lease.rent_amount.toLocaleString() }} {{ currencySymbol }}</span>
                                                    </span>

                                                    <span
                                                        v-else-if="room.descendant_has_active_lease"
                                                        class="text-[11px] text-amber-700 flex items-center gap-1"
                                                    >
                                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0"></span>
                                                        <span>أسرّة داخلية مؤجرة</span>
                                                    </span>

                                                    <span
                                                        v-else-if="getChildren(room.id).length > 0"
                                                        class="text-[11px] text-[#5B6770]"
                                                    >
                                                        متاحة للتأجير كغرفة خاصة أو بالسرير
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Room Actions -->
                                        <div class="flex items-center gap-1.5 self-end md:self-center shrink-0">
                                            <button
                                                v-if="room.can_subdivide"
                                                @click="emit('addChildUnit', room, 'bed')"
                                                type="button"
                                                class="h-7 px-2.5 rounded border border-[#C5BCAC] bg-[#FCFAF6] hover:bg-[#163E3E] hover:text-white hover:border-[#163E3E] text-[#163E3E] text-[11px] font-semibold transition flex items-center gap-1 cursor-pointer"
                                            >
                                                <Plus class="w-3 h-3" />
                                                <span>إضافة سرير</span>
                                            </button>
                                            <span
                                                v-else
                                                class="inline-flex items-center gap-1 h-7 px-2 rounded bg-[#EFECE2] text-[#8E9CA6] text-[10px] font-medium border border-[#E2DDD2]"
                                                title="لا يمكن إضافة أسرّة لوجود عقد إيجار سارٍ"
                                            >
                                                <Lock class="w-3 h-3" />
                                            </span>

                                            <Link
                                                v-if="room.can_rent"
                                                :href="route('leases.create', { unit_id: room.id })"
                                                class="h-7 px-2.5 rounded bg-[#163E3E] hover:bg-[#102F2F] text-white text-[11px] font-semibold transition flex items-center gap-1"
                                            >
                                                <ArrowUpRight class="w-3 h-3" />
                                                <span>تأجير</span>
                                            </Link>

                                            <button
                                                @click="emit('selectUnit', room)"
                                                type="button"
                                                class="h-7 px-2.5 rounded border border-[#C5BCAC] bg-[#FCFAF6] hover:bg-[#EFECE2] text-[#163E3E] text-[11px] font-semibold transition cursor-pointer"
                                            >
                                                التفاصيل
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ====== LEVEL 3: BEDS UNDER ROOM ====== -->
                            <div
                                v-if="isNodeExpanded(room.id)"
                                class="border-b border-[#EFECE2]"
                            >
                                <!-- Empty room (no beds) -->
                                <div
                                    v-if="getChildren(room.id).length === 0"
                                    class="flex items-center"
                                >
                                    <div class="w-1 shrink-0"></div>
                                    <div class="w-6 sm:w-10 shrink-0"></div>
                                    <div class="w-6 sm:w-10 shrink-0 flex flex-col items-center py-3.5">
                                        <div class="w-px flex-1 bg-[#EFECE2]"></div>
                                        <div class="h-px w-3 sm:w-5 bg-[#EFECE2]"></div>
                                    </div>
                                    <div class="flex-1 bg-[#F8F4EA]/40 px-3 py-3.5 flex items-center justify-between gap-3 flex-wrap">
                                        <p class="text-[11px] text-[#5B6770]">
                                            الغرفة لا تحتوي على أسرّة — يمكن تأجيرها كغرفة خاصة أو تقسيمها إلى أسرّة.
                                        </p>
                                        <button
                                            v-if="room.can_subdivide"
                                            @click="emit('addChildUnit', room, 'bed')"
                                            type="button"
                                            class="h-6 px-2.5 rounded bg-[#163E3E] hover:bg-[#102F2F] text-white text-[10px] font-semibold transition flex items-center gap-1 cursor-pointer shrink-0"
                                        >
                                            <Plus class="w-2.5 h-2.5" />
                                            <span>إضافة سرير</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Beds list -->
                                <div v-else>
                                    <div
                                        v-for="bed in getChildren(room.id)"
                                        :key="bed.id"
                                        v-show="isBedVisible(bed)"
                                        class="flex items-stretch border-b border-[#EFECE2] last:border-b-0 transition-colors hover:bg-[#F8F4EA]/30 group"
                                    >
                                        <!-- Indent spacer -->
                                        <div class="w-1 shrink-0"></div>
                                        <!-- Level 2 connector line -->
                                        <div class="w-6 sm:w-10 shrink-0"></div>
                                        <!-- Level 3 connector -->
                                        <div class="w-6 sm:w-10 shrink-0 flex flex-col items-center py-3.5">
                                            <div class="w-px flex-1 bg-[#EFECE2]"></div>
                                            <div class="h-px w-3 sm:w-5 bg-[#EFECE2]"></div>
                                        </div>

                                        <!-- Bed content -->
                                        <div class="flex-1 px-3 py-3 sm:px-4 flex flex-col sm:flex-row sm:items-center justify-between gap-2 min-w-0">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <div class="min-w-0">
                                                    <div class="flex items-baseline gap-2 flex-wrap">
                                                        <button
                                                            @click="emit('selectUnit', bed)"
                                                            type="button"
                                                            class="text-[11px] font-semibold text-[#163E3E] hover:text-[#102F2F] transition cursor-pointer"
                                                        >
                                                            {{ bed.name }}
                                                        </button>

                                                        <span v-if="bed.code" class="text-[10px] font-mono text-[#5B6770] bg-[#EFECE2] px-1.5 py-px rounded border border-[#C5BCAC] leading-none">
                                                            {{ bed.code }}
                                                        </span>

                                                        <span class="text-[10px] font-medium text-[#334155] bg-[#EFECE2] px-1.5 py-px rounded border border-[#C5BCAC] leading-none">
                                                            {{ bed.type_label }}
                                                        </span>

                                                        <StatusBadge :status="bed.status" :label="bed.status_label" size="sm" />
                                                    </div>

                                                    <!-- Bed tenant info -->
                                                    <div v-if="bed.active_lease" class="text-[11px] text-emerald-800 font-medium mt-0.5 flex items-center gap-1">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                                                        <span>{{ bed.active_lease.renter_name }}</span>
                                                        <span
                                                            v-if="bed.active_lease.renter_gender_label"
                                                            class="text-[10px] px-1.5 rounded font-semibold"
                                                            :class="bed.active_lease.renter_gender === 'female' ? 'bg-rose-100 text-rose-700' : 'bg-sky-100 text-sky-700'"
                                                        >
                                                            {{ bed.active_lease.renter_gender_label }}
                                                        </span>
                                                        <span class="font-mono text-[#5B6770]">· {{ bed.active_lease.rent_amount.toLocaleString() }} {{ currencySymbol }}</span>
                                                    </div>
                                                    <div v-else-if="bed.ancestor_has_active_lease" class="text-[10px] text-[#5B6770] mt-0.5 flex items-center gap-1">
                                                        <Lock class="w-3 h-3 text-[#8E9CA6]" />
                                                        <span>محجوز ضمن عقد الغرفة</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Bed Actions -->
                                            <div class="flex items-center gap-1.5 self-end sm:self-center shrink-0">
                                                <Link
                                                    v-if="bed.can_rent"
                                                    :href="route('leases.create', { unit_id: bed.id })"
                                                    class="h-7 px-2.5 rounded bg-[#163E3E] hover:bg-[#102F2F] text-white text-[10px] font-semibold transition flex items-center gap-1"
                                                >
                                                    <ArrowUpRight class="w-3 h-3" />
                                                    <span>تأجير</span>
                                                </Link>
                                                <span
                                                    v-else-if="bed.ancestor_has_active_lease"
                                                    class="inline-flex items-center gap-1 h-7 px-2 rounded bg-[#EFECE2] text-[#8E9CA6] text-[10px] font-medium border border-[#E2DDD2]"
                                                    title="السرير محجوز لأن الغرفة مؤجرة كغرفة خاصة بالكامل"
                                                >
                                                    <Lock class="w-3 h-3" />
                                                </span>

                                                <button
                                                    @click="emit('selectUnit', bed)"
                                                    type="button"
                                                    class="h-7 px-2.5 rounded border border-[#C5BCAC] bg-[#FCFAF6] hover:bg-[#EFECE2] text-[#163E3E] text-[10px] font-semibold transition cursor-pointer"
                                                >
                                                    التفاصيل
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
