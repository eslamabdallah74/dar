<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import {
    Home,
    DoorClosed,
    Bed,
    Store,
    ChevronDown,
    ChevronLeft,
    Plus,
    User,
    DollarSign,
    Layers,
    Edit3,
    ArrowUpRight,
    Lock,
    Sparkles,
    Eye,
} from 'lucide-vue-next';

export interface ActiveLease {
    id: number;
    contract_number?: string;
    renter_id: number;
    renter_name: string;
    renter_phone: string;
    renter_national_id?: string;
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
    <div class="space-y-4" dir="rtl">
        <!-- If no units match search or filters -->
        <div
            v-if="visibleRootUnits.length === 0"
            class="rounded-3xl border border-dashed border-[#D6E0E7] bg-white p-12 text-center space-y-3"
        >
            <div class="w-12 h-12 rounded-2xl bg-slate-100 text-[#5B6770] mx-auto flex items-center justify-center">
                <Layers class="w-6 h-6 stroke-[1.5]" />
            </div>
            <h4 class="text-base font-bold text-[#0F2747]">لا توجد وحدات مطابقة للبحث أو التصفية</h4>
            <p class="text-xs text-[#5B6770] max-w-sm mx-auto">
                جرّب تعديل كلمات البحث أو مسح تصفيات النوع والحالة لعرض جميع مستويات الهيكل العقاري.
            </p>
        </div>

        <!-- Tree Nodes List -->
        <div v-else class="space-y-4">
            <div
                v-for="rootUnit in visibleRootUnits"
                :key="rootUnit.id"
                class="rounded-3xl border transition-all duration-200 overflow-hidden shadow-2xs"
                :class="[
                    rootUnit.unit_type === 'shop'
                        ? 'border-purple-200 bg-gradient-to-b from-purple-50/20 to-white'
                        : 'border-[#D6E0E7] bg-white hover:border-[#0E6E7E]/40'
                ]"
            >
                <!-- ================= LEVEL 1: ROOT NODE (Apartment or Shop) ================= -->
                <div class="p-4 sm:p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100/80">
                    <div class="flex items-start sm:items-center gap-3 min-w-0">
                        <!-- Expand/Collapse Button (for Apartments with children) -->
                        <button
                            v-if="rootUnit.unit_type === 'apartment'"
                            @click="toggleNode(rootUnit.id)"
                            type="button"
                            class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-[#E3F1F4] text-[#5B6770] hover:text-[#0E6E7E] flex items-center justify-center transition shrink-0 cursor-pointer"
                            :title="isNodeExpanded(rootUnit.id) ? 'طي تفاصيل الشقة' : 'عرض غرف الشقة'"
                        >
                            <ChevronDown
                                class="w-4 h-4 transition-transform duration-200"
                                :class="{ 'rotate-90': !isNodeExpanded(rootUnit.id) }"
                            />
                        </button>

                        <!-- Unit Icon -->
                        <div
                            class="w-11 h-11 rounded-2xl flex items-center justify-center shrink-0 shadow-2xs"
                            :class="[
                                rootUnit.unit_type === 'shop'
                                    ? 'bg-purple-100 text-purple-700'
                                    : 'bg-[#E3F1F4] text-[#0E6E7E]'
                            ]"
                        >
                            <component
                                :is="rootUnit.unit_type === 'shop' ? Store : Home"
                                class="w-5 h-5 stroke-[2]"
                            />
                        </div>

                        <!-- Name, Code, Badges -->
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3
                                    @click="emit('selectUnit', rootUnit)"
                                    class="text-base font-bold text-[#0F2747] hover:text-[#0E6E7E] transition cursor-pointer truncate"
                                >
                                    {{ rootUnit.name }}
                                </h3>

                                <span
                                    v-if="rootUnit.code"
                                    class="text-xs font-mono font-bold text-[#5B6770] bg-slate-100 px-2.5 py-0.5 rounded-lg border border-slate-200"
                                >
                                    {{ rootUnit.code }}
                                </span>

                                <span
                                    class="text-xs font-bold px-2.5 py-0.5 rounded-lg border"
                                    :class="[
                                        rootUnit.unit_type === 'shop'
                                            ? 'bg-purple-50 text-purple-800 border-purple-200'
                                            : 'bg-emerald-50 text-emerald-800 border-emerald-200'
                                    ]"
                                >
                                    {{ rootUnit.type_label }}
                                </span>

                                <StatusBadge :status="rootUnit.status" :label="rootUnit.status_label" size="sm" />
                            </div>

                            <!-- Meta / Active Lease Info -->
                            <div class="flex items-center gap-3 text-xs text-[#5B6770] mt-1.5 flex-wrap">
                                <span v-if="rootUnit.floor_name" class="font-medium">
                                    {{ rootUnit.floor_name }}
                                </span>

                                <span v-if="rootUnit.unit_type === 'apartment'" class="font-semibold text-[#0E6E7E]">
                                    {{ getChildren(rootUnit.id).length }} غرف مسجلة
                                </span>

                                <span v-if="rootUnit.unit_type === 'shop'" class="text-purple-700 font-medium">
                                    محل تجاري مستقل
                                </span>

                                <span v-if="rootUnit.active_lease" class="flex items-center gap-1.5 text-emerald-800 font-bold bg-emerald-50 px-2 py-0.5 rounded-md">
                                    <User class="w-3.5 h-3.5" />
                                    <span>المستأجر: {{ rootUnit.active_lease.renter_name }}</span>
                                    <span class="font-mono mr-1">({{ rootUnit.active_lease.rent_amount.toLocaleString() }} {{ currencySymbol }}/شهرياً)</span>
                                </span>

                                <span v-else-if="rootUnit.descendant_has_active_lease" class="text-amber-800 bg-amber-50 px-2 py-0.5 rounded-md font-medium text-[11px]">
                                    ⚠️ يوجد عقود نشطة على غرف داخلية (الشقة ككل غير قابلة للتأجير المنفصل)
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Right / Action Controls on Root Node -->
                    <div class="flex items-center gap-2 self-end sm:self-center shrink-0">
                        <!-- Add Room Button (Only for Apartments) -->
                        <div v-if="rootUnit.unit_type === 'apartment'">
                            <button
                                v-if="rootUnit.can_subdivide"
                                @click="emit('addChildUnit', rootUnit, 'room')"
                                type="button"
                                class="h-9 px-3.5 rounded-xl bg-[#E3F1F4] hover:bg-[#0E6E7E] text-[#0E6E7E] hover:text-white text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-2xs"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>إضافة أوضة</span>
                            </button>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 h-9 px-3 rounded-xl bg-slate-100 text-[#8E9CA6] text-[11px] font-medium"
                                title="لا يمكن تقسيم هذه الوحدة حالياً لوجود عقد إيجار نشط يتعارض مع التقسيم"
                            >
                                <Lock class="w-3.5 h-3.5" />
                                <span>التقسيم محجوز بعقد</span>
                            </span>
                        </div>

                        <!-- Rent action if vacant & eligible -->
                        <Link
                            v-if="rootUnit.can_rent"
                            :href="route('leases.create', { unit_id: rootUnit.id })"
                            class="h-9 px-3.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold transition flex items-center gap-1 shadow-2xs"
                        >
                            <ArrowUpRight class="w-3.5 h-3.5" />
                            <span>تأجير</span>
                        </Link>

                        <!-- Details Button -->
                        <button
                            @click="emit('selectUnit', rootUnit)"
                            type="button"
                            class="h-9 px-3.5 rounded-xl border border-[#D6E0E7] bg-white hover:bg-[#F3F7FA] text-[#0F2747] text-xs font-semibold transition flex items-center gap-1.5 cursor-pointer"
                        >
                            <Eye class="w-3.5 h-3.5 text-[#5B6770]" />
                            <span>التفاصيل</span>
                        </button>
                    </div>
                </div>

                <!-- ================= LEVEL 2: ROOMS UNDER APARTMENT ================= -->
                <div v-if="rootUnit.unit_type === 'apartment' && isNodeExpanded(rootUnit.id)" class="bg-[#F8FAFC]/60 p-3 sm:p-5 space-y-3 border-t border-slate-100">
                    <!-- If Apartment has NO rooms -->
                    <div
                        v-if="getChildren(rootUnit.id).length === 0"
                        class="p-4 sm:p-5 rounded-2xl border border-dashed border-[#D6E0E7] bg-white text-center space-y-2"
                    >
                        <p class="text-xs text-[#5B6770]">
                            🏠 هذه الشقة غير مقسمة حالياً — يمكن تأجيرها كشقة كاملة أو تقسيمها إلى غرف مستقلة.
                        </p>
                        <div class="pt-1">
                            <button
                                v-if="rootUnit.can_subdivide"
                                @click="emit('addChildUnit', rootUnit, 'room')"
                                type="button"
                                class="inline-flex items-center gap-1.5 px-4 h-9 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-bold transition cursor-pointer shadow-xs"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>تقسيم الشقة (إضافة أوضة)</span>
                            </button>
                            <span v-else class="text-xs text-amber-800 font-medium">
                                🔒 الشقة مؤجرة بعقد نشط، ولا يمكن تقسيمها أثناء سريان العقد.
                            </span>
                        </div>
                    </div>

                    <!-- List of Rooms -->
                    <div v-else class="space-y-3">
                        <div
                            v-for="room in getChildren(rootUnit.id)"
                            :key="room.id"
                            v-show="isRoomVisible(room)"
                            class="rounded-2xl border border-[#D6E0E7] bg-white overflow-hidden shadow-2xs mr-2 sm:mr-5 relative"
                        >
                            <!-- Connector visual notch (RTL Right side) -->
                            <div class="absolute top-5 -right-3 sm:-right-4 w-3 sm:w-4 h-0.5 bg-slate-300"></div>

                            <!-- Room Node Row -->
                            <div class="p-3.5 sm:p-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
                                <div class="flex items-start sm:items-center gap-2.5 min-w-0">
                                    <!-- Expand/Collapse Beds -->
                                    <button
                                        @click="toggleNode(room.id)"
                                        type="button"
                                        class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-teal-50 text-[#5B6770] hover:text-[#0E6E7E] flex items-center justify-center transition shrink-0 cursor-pointer"
                                        :title="isNodeExpanded(room.id) ? 'طي تفاصيل الأوضة' : 'عرض أسِرَّة الأوضة'"
                                    >
                                        <ChevronDown
                                            class="w-3.5 h-3.5 transition-transform duration-200"
                                            :class="{ 'rotate-90': !isNodeExpanded(room.id) }"
                                        />
                                    </button>

                                    <!-- Room Icon -->
                                    <div class="w-9 h-9 rounded-xl bg-teal-50 text-teal-800 flex items-center justify-center shrink-0">
                                        <DoorClosed class="w-4 h-4 stroke-[2]" />
                                    </div>

                                    <!-- Room Details -->
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h4
                                                @click="emit('selectUnit', room)"
                                                class="text-sm font-bold text-[#0F2747] hover:text-[#0E6E7E] transition cursor-pointer truncate"
                                            >
                                                {{ room.name }}
                                            </h4>

                                            <span
                                                v-if="room.code"
                                                class="text-[11px] font-mono text-[#5B6770] bg-slate-100 px-2 py-0.5 rounded border border-slate-200"
                                            >
                                                {{ room.code }}
                                            </span>

                                            <span class="text-[11px] font-bold text-teal-900 bg-teal-100/70 px-2 py-0.5 rounded border border-teal-200">
                                                {{ room.type_label }}
                                            </span>

                                            <StatusBadge :status="room.status" :label="room.status_label" size="sm" />
                                        </div>

                                        <!-- Meta / Active Lease Info -->
                                        <div class="flex items-center gap-2.5 text-xs text-[#5B6770] mt-1 flex-wrap">
                                            <span class="text-teal-700 font-medium">
                                                {{ getChildren(room.id).length }} أسرّة مسجلة
                                            </span>

                                            <span v-if="room.active_lease" class="flex items-center gap-1 text-emerald-800 font-bold bg-emerald-50 px-2 py-0.5 rounded text-[11px]">
                                                <User class="w-3 h-3" />
                                                <span>{{ room.active_lease.renter_name }}</span>
                                                <span class="font-mono">({{ room.active_lease.rent_amount.toLocaleString() }} {{ currencySymbol }})</span>
                                            </span>

                                            <span v-else-if="room.descendant_has_active_lease" class="text-amber-800 bg-amber-50 px-2 py-0.5 rounded text-[11px]">
                                                ⚠️ أسرّة داخلية مؤجرة (لا يمكن تأجير الغرفة بالكامل)
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Room Actions -->
                                <div class="flex items-center gap-2 self-end sm:self-center shrink-0">
                                    <!-- Add Bed Button -->
                                    <button
                                        v-if="room.can_subdivide"
                                        @click="emit('addChildUnit', room, 'bed')"
                                        type="button"
                                        class="h-8 px-3 rounded-xl bg-teal-50 hover:bg-teal-700 text-teal-800 hover:text-white text-xs font-bold transition flex items-center gap-1 cursor-pointer"
                                    >
                                        <Plus class="w-3 h-3" />
                                        <span>إضافة سرير</span>
                                    </button>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 h-8 px-2.5 rounded-xl bg-slate-100 text-[#8E9CA6] text-[10px] font-medium"
                                        title="لا يمكن إضافة أسرّة لوجود عقد إيجار سارٍ على الغرفة أو الشقة"
                                    >
                                        <Lock class="w-3 h-3" />
                                        <span>محجوز بعقد</span>
                                    </span>

                                    <!-- Rent Room if vacant & eligible -->
                                    <Link
                                        v-if="room.can_rent"
                                        :href="route('leases.create', { unit_id: room.id })"
                                        class="h-8 px-3 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold transition flex items-center gap-1"
                                    >
                                        <ArrowUpRight class="w-3 h-3" />
                                        <span>تأجير</span>
                                    </Link>

                                    <!-- Room Details -->
                                    <button
                                        @click="emit('selectUnit', room)"
                                        type="button"
                                        class="h-8 px-2.5 rounded-xl border border-[#D6E0E7] bg-white hover:bg-slate-50 text-[#0F2747] text-xs font-semibold transition"
                                    >
                                        التفاصيل
                                    </button>
                                </div>
                            </div>

                            <!-- ================= LEVEL 3: BEDS UNDER ROOM ================= -->
                            <div v-if="isNodeExpanded(room.id)" class="bg-slate-50/70 p-3 sm:p-4 border-t border-slate-100 space-y-2">
                                <!-- If Room has NO beds -->
                                <div
                                    v-if="getChildren(room.id).length === 0"
                                    class="p-3.5 rounded-xl border border-dashed border-slate-200 bg-white text-center space-y-1.5"
                                >
                                    <p class="text-[11px] text-[#5B6770]">
                                        🚪 هذه الغرفة لا تحتوي على أسرّة بعد — يمكن تأجيرها كغرفة خاصة أو تقسيمها إلى أسرّة فردية.
                                    </p>
                                    <div class="pt-1">
                                        <button
                                            v-if="room.can_subdivide"
                                            @click="emit('addChildUnit', room, 'bed')"
                                            type="button"
                                            class="inline-flex items-center gap-1 px-3 h-7 rounded-lg bg-teal-700 hover:bg-teal-800 text-white text-[11px] font-bold transition cursor-pointer"
                                        >
                                            <Plus class="w-3 h-3" />
                                            <span>إضافة سرير</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- List of Beds -->
                                <div v-else class="space-y-2">
                                    <div
                                        v-for="bed in getChildren(room.id)"
                                        :key="bed.id"
                                        v-show="isBedVisible(bed)"
                                        class="p-3 rounded-xl border border-slate-200 bg-white flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 shadow-2xs hover:border-amber-300 transition mr-2 sm:mr-5 relative"
                                    >
                                        <!-- Connector notch for Bed -->
                                        <div class="absolute top-4 -right-3 sm:-right-4 w-3 sm:w-4 h-0.5 bg-slate-300"></div>

                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-800 flex items-center justify-center shrink-0">
                                                <Bed class="w-4 h-4 stroke-[2]" />
                                            </div>

                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span
                                                        @click="emit('selectUnit', bed)"
                                                        class="text-xs font-bold text-[#0F2747] hover:text-[#0E6E7E] transition cursor-pointer truncate"
                                                    >
                                                        {{ bed.name }}
                                                    </span>

                                                    <span v-if="bed.code" class="text-[10px] font-mono text-[#5B6770] bg-slate-100 px-1.5 py-0.5 rounded">
                                                        {{ bed.code }}
                                                    </span>

                                                    <span class="text-[10px] font-bold text-amber-900 bg-amber-100/80 px-2 py-0.5 rounded">
                                                        {{ bed.type_label }}
                                                    </span>

                                                    <StatusBadge :status="bed.status" :label="bed.status_label" size="sm" />
                                                </div>

                                                <!-- Active Lease on Bed -->
                                                <div v-if="bed.active_lease" class="text-[11px] text-emerald-800 font-bold mt-0.5 flex items-center gap-1">
                                                    <User class="w-3 h-3" />
                                                    <span>مستأجر: {{ bed.active_lease.renter_name }}</span>
                                                    <span class="font-mono">({{ bed.active_lease.rent_amount.toLocaleString() }} {{ currencySymbol }})</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Bed Actions -->
                                        <div class="flex items-center gap-2 self-end sm:self-center shrink-0">
                                            <Link
                                                v-if="bed.can_rent"
                                                :href="route('leases.create', { unit_id: bed.id })"
                                                class="h-7 px-2.5 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-[11px] font-bold transition flex items-center gap-1"
                                            >
                                                <ArrowUpRight class="w-3 h-3" />
                                                <span>تأجير السرير</span>
                                            </Link>

                                            <button
                                                @click="emit('selectUnit', bed)"
                                                type="button"
                                                class="h-7 px-2.5 rounded-lg border border-[#D6E0E7] bg-white hover:bg-slate-50 text-[#0F2747] text-[11px] font-semibold transition cursor-pointer"
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
</template>
