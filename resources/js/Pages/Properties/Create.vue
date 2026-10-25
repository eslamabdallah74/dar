<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    AlertCircle,
    ArrowLeft,
    ArrowRight,
    ArrowUpDown,
    Bed,
    BellRing,
    Boxes,
    Building2,
    Calendar,
    Car,
    Check,
    CheckCircle2,
    ChevronLeft,
    DoorOpen,
    Droplets,
    FileText,
    Flame,
    Home,
    ImagePlus,
    Info,
    MapPin,
    PhoneCall,
    Plus,
    Receipt,
    Shield,
    ShieldCheck,
    Sparkles,
    Store,
    Trash2,
    UploadCloud,
    UserCheck,
    Video,
    Wifi,
    Wind,
    Zap,
} from 'lucide-vue-next';

interface PropertyTypeOption {
    value: string;
    label: string;
    badge: string;
    description: string;
    icon: string;
    suggested_units: string;
}

interface AmenityOption {
    id: string;
    label: string;
    icon: string;
}

interface FinishingTypeOption {
    value: string;
    label: string;
}

interface GeneratedUnit {
    id: string;
    temp_id: string;
    parent_temp_id?: string | null;
    name: string;
    code: string;
    unit_type: 'apartment' | 'shop' | 'room' | 'bed' | 'other';
    is_rentable: boolean;
    floor_name: string;
}

const props = defineProps<{
    property_types: PropertyTypeOption[];
    amenities_list: AmenityOption[];
    finishing_types: FinishingTypeOption[];
}>();

// Steps definition
const steps = [
    { number: 1, title: 'نوع العقار والهوية', subtitle: 'النوع، الاسم، والصورة' },
    { number: 2, title: 'الموقع والعنوان', subtitle: 'المدينة، الحي، والخرائط' },
    { number: 3, title: 'المواصفات والمرافق', subtitle: 'المساحة، العدادات، والخدمات' },
    { number: 4, title: 'توليد وتكوين الوحدات', subtitle: 'الهيكلة والتوزيع للتأجير' },
    { number: 5, title: 'المراجعة والاعتماد', subtitle: 'تأكيد البيانات وإنشاء العقار' },
];

const currentStep = ref(1);

// Form state
const form = useForm({
    name: '',
    code: '',
    property_type: 'building',
    address: '',
    city: 'القاهرة',
    district: '',
    street: '',
    building_number: '',
    postal_code: '',
    google_maps_url: '',
    floors_count: 4,
    floor_number: '',
    total_area: '' as string | number,
    commercial_activity: '',
    year_built: new Date().getFullYear(),
    finishing_type: 'super_lux',
    amenities: ['elevator', 'water_tank', 'intercom'] as string[],
    electricity_meter_number: '',
    water_meter_number: '',
    gas_meter_number: '',
    guard_name: '',
    guard_phone: '',
    deed_number: '',
    notes: '',
    cover_image_file: null as File | null,
    generation_config: {
        enabled: true,
        rental_mode: 'whole',
        floors_count: 4,
        units_per_floor: 2,
        rooms_count: 3,
        beds_per_room: 2,
        include_ground_shops: true,
        shops_count: 2,
    },
    units: [] as GeneratedUnit[],
});

// Image preview
const imagePreviewUrl = ref<string | null>(null);

function handleImageChange(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        const file = target.files[0];
        form.cover_image_file = file;
        imagePreviewUrl.value = URL.createObjectURL(file);
    }
}

function removeImage() {
    form.cover_image_file = null;
    imagePreviewUrl.value = null;
}

// Auto Unit Generator State
const autoGenEnabled = ref(true);

// Building generator parameters
const genFloorsCount = ref(4);
const genUnitsPerFloor = ref(2);
const genIncludeGroundShops = ref(true);
const genShopsCount = ref(2);
const genNamingPattern = ref<'floor_number' | 'sequential'>('floor_number');

// Apartment generator parameters
const aptRentalMode = ref<'whole' | 'rooms' | 'beds'>('whole');
const aptRoomsCount = ref(3);
const aptBedsPerRoom = ref(2);

// Commercial generator parameters
const commUnitsCount = ref(4);

// Villa generator parameters
const villaRentalMode = ref<'whole' | 'rooms'>('whole');
const villaRoomsCount = ref(4);

// Floor names lookup for buildings
const floorNames = [
    'الدور الأول',
    'الدور الثاني',
    'الدور الثالث',
    'الدور الرابع',
    'الدور الخامس',
    'الدور السادس',
    'الدور السابع',
    'الدور الثامن',
    'الدور التاسع',
    'الدور العاشر',
];

// Helper: Available parents in current unit list
const availableApartmentParents = computed(() => {
    return form.units.filter((u) => u.unit_type === 'apartment');
});

const availableRoomParents = computed(() => {
    return form.units.filter((u) => u.unit_type === 'room');
});

/**
 * Generate units for Shop:
 * Strictly 1 independent rentable shop unit by default.
 * Never generates residential hierarchy (floors, apartments, rooms, beds).
 */
function generateUnitsForShop() {
    const code = form.code.trim() || 'SHP-01';
    const name = form.name.trim() || 'محل تجاري';

    form.units = [
        {
            id: 'shop-1',
            temp_id: 'shop-1',
            parent_temp_id: null,
            name: name,
            code: code,
            unit_type: 'shop',
            is_rentable: true,
            floor_name: 'أرضي / مستقل',
        },
    ];
}

/**
 * Generate units for Apartment:
 * 1. whole: 1 rentable apartment unit
 * 2. rooms: apartment is parent (not rentable) + N rentable child rooms
 * 3. beds: apartment parent + N child rooms (not rentable) + M rentable child beds per room
 */
function generateUnitsForApartment() {
    const list: GeneratedUnit[] = [];
    const baseCode = form.code.trim() || 'APT-01';
    const baseName = form.name.trim() || 'شقة سكنية';
    const floorLabel = form.floor_number ? `الدور ${form.floor_number}` : 'الشقة';

    if (aptRentalMode.value === 'whole') {
        list.push({
            id: 'apt-root',
            temp_id: 'apt-root',
            parent_temp_id: null,
            name: baseName,
            code: baseCode,
            unit_type: 'apartment',
            is_rentable: true,
            floor_name: floorLabel,
        });
    } else {
        const parentTempId = 'apt-root';
        list.push({
            id: parentTempId,
            temp_id: parentTempId,
            parent_temp_id: null,
            name: baseName,
            code: baseCode,
            unit_type: 'apartment',
            is_rentable: false,
            floor_name: `${floorLabel} (رئيسية)`,
        });

        const isRoomsOnly = aptRentalMode.value === 'rooms';

        for (let r = 1; r <= aptRoomsCount.value; r++) {
            const roomTempId = `room-${r}`;
            const roomCode = `${baseCode}-R${r}`;
            const roomName = `غرفة ${r}`;

            list.push({
                id: roomTempId,
                temp_id: roomTempId,
                parent_temp_id: parentTempId,
                name: roomName,
                code: roomCode,
                unit_type: 'room',
                is_rentable: isRoomsOnly,
                floor_name: 'غرفة بالشقة',
            });

            if (aptRentalMode.value === 'beds') {
                for (let b = 1; b <= aptBedsPerRoom.value; b++) {
                    const bedTempId = `bed-${r}-${b}`;
                    const bedCode = `${roomCode}-B${b}`;
                    const bedName = `سرير ${b} (غرفة ${r})`;

                    list.push({
                        id: bedTempId,
                        temp_id: bedTempId,
                        parent_temp_id: roomTempId,
                        name: bedName,
                        code: bedCode,
                        unit_type: 'bed',
                        is_rentable: true,
                        floor_name: `سرير بغرفة ${r}`,
                    });
                }
            }
        }
    }

    form.units = list;
}

/**
 * Generate units for Building:
 * Floors with configured apartments per floor, plus optional ground floor shops.
 */
function generateUnitsForBuilding() {
    const list: GeneratedUnit[] = [];

    // Optional Ground Floor Shops
    if (genIncludeGroundShops.value && genShopsCount.value > 0) {
        for (let s = 1; s <= genShopsCount.value; s++) {
            const shopTempId = `shop-${s}`;
            list.push({
                id: shopTempId,
                temp_id: shopTempId,
                parent_temp_id: null,
                name: `محل تجاري ${s}`,
                code: `SHP-0${s}`,
                unit_type: 'shop',
                is_rentable: true,
                floor_name: 'الدور الأرضي',
            });
        }
    }

    let overallUnitIndex = 1;

    for (let f = 1; f <= genFloorsCount.value; f++) {
        const floorLabel = floorNames[f - 1] || `الدور ${f}`;

        for (let u = 1; u <= genUnitsPerFloor.value; u++) {
            let unitCode = '';
            let unitName = '';

            if (genNamingPattern.value === 'floor_number') {
                const codeNum = `${f}0${u}`;
                unitCode = `APT-${codeNum}`;
                unitName = `شقة ${codeNum}`;
            } else {
                unitCode = `APT-${overallUnitIndex}`;
                unitName = `شقة ${overallUnitIndex}`;
            }

            const aptTempId = `apt-${f}-${u}`;
            list.push({
                id: aptTempId,
                temp_id: aptTempId,
                parent_temp_id: null,
                name: unitName,
                code: unitCode,
                unit_type: 'apartment',
                is_rentable: true,
                floor_name: floorLabel,
            });

            overallUnitIndex++;
        }
    }

    form.units = list;
}

/**
 * Generate units for Commercial building:
 * Multiple independent commercial shops/offices.
 */
function generateUnitsForCommercial() {
    const list: GeneratedUnit[] = [];
    for (let c = 1; c <= commUnitsCount.value; c++) {
        const tempId = `comm-${c}`;
        const codeNum = c < 10 ? `0${c}` : `${c}`;
        list.push({
            id: tempId,
            temp_id: tempId,
            parent_temp_id: null,
            name: `وحدة تجارية / مكتب ${c}`,
            code: `COM-${codeNum}`,
            unit_type: 'shop',
            is_rentable: true,
            floor_name: 'تجاري',
        });
    }
    form.units = list;
}

/**
 * Generate units for Villa:
 * Whole villa or divided into rooms.
 */
function generateUnitsForVilla() {
    const baseCode = form.code.trim() || 'VIL-01';
    const baseName = form.name.trim() || 'فيلا';

    if (villaRentalMode.value === 'whole') {
        form.units = [
            {
                id: 'villa-root',
                temp_id: 'villa-root',
                parent_temp_id: null,
                name: baseName,
                code: baseCode,
                unit_type: 'apartment',
                is_rentable: true,
                floor_name: 'الفيلا بالكامل',
            },
        ];
        return;
    }

    const list: GeneratedUnit[] = [];
    const parentTempId = 'villa-root';
    list.push({
        id: parentTempId,
        temp_id: parentTempId,
        parent_temp_id: null,
        name: baseName,
        code: baseCode,
        unit_type: 'apartment',
        is_rentable: false,
        floor_name: 'المبنى الرئيسي للفيلا',
    });

    for (let r = 1; r <= villaRoomsCount.value; r++) {
        const roomTempId = `room-${r}`;
        list.push({
            id: roomTempId,
            temp_id: roomTempId,
            parent_temp_id: parentTempId,
            name: `جناح / غرفة ${r}`,
            code: `${baseCode}-R${r}`,
            unit_type: 'room',
            is_rentable: true,
            floor_name: 'جناح مستقل',
        });
    }

    form.units = list;
}

/**
 * Fallback unit generator for standalone/other.
 */
function generateUnitsForDefault() {
    const baseCode = form.code.trim() || 'U-01';
    const baseName = form.name.trim() || 'وحدة عقارية';
    form.units = [
        {
            id: 'unit-1',
            temp_id: 'unit-1',
            parent_temp_id: null,
            name: baseName,
            code: baseCode,
            unit_type: 'apartment',
            is_rentable: true,
            floor_name: 'رئيسي',
        },
    ];
}

/**
 * Dispatcher for unit generation based on current property_type.
 */
function generateUnits() {
    switch (form.property_type) {
        case 'shop':
            generateUnitsForShop();
            break;
        case 'apartment':
            generateUnitsForApartment();
            break;
        case 'building':
            generateUnitsForBuilding();
            break;
        case 'commercial':
            generateUnitsForCommercial();
            break;
        case 'villa':
            generateUnitsForVilla();
            break;
        default:
            generateUnitsForDefault();
            break;
    }
}

/**
 * Reactively adapt fields and reset stale type-specific data when switching property_type.
 */
watch(
    () => form.property_type,
    (newType) => {
        if (newType === 'shop') {
            form.floors_count = 1;
            form.floor_number = '';
            form.guard_name = '';
            form.guard_phone = '';
            // Retain only commercial-relevant amenities
            form.amenities = form.amenities.filter((a) =>
                ['cctv', 'parking', 'fire_system', 'fiber_internet', 'central_ac', 'generator'].includes(a)
            );
            generateUnitsForShop();
        } else if (newType === 'apartment') {
            form.floors_count = 1;
            form.commercial_activity = '';
            form.guard_name = '';
            form.guard_phone = '';
            generateUnitsForApartment();
        } else if (newType === 'building') {
            form.floor_number = '';
            form.commercial_activity = '';
            if (form.floors_count <= 1) {
                form.floors_count = 4;
            }
            generateUnitsForBuilding();
        } else if (newType === 'commercial') {
            form.floor_number = '';
            generateUnitsForCommercial();
        } else if (newType === 'villa') {
            form.floor_number = '';
            form.commercial_activity = '';
            generateUnitsForVilla();
        } else {
            generateUnitsForDefault();
        }
    }
);

// Initial generation
generateUnits();

function removeUnit(index: number) {
    const removed = form.units[index];
    form.units.splice(index, 1);

    // If removed unit was a parent, clean up orphaned references
    if (removed) {
        form.units.forEach((u) => {
            if (u.parent_temp_id === removed.temp_id) {
                u.parent_temp_id = null;
            }
        });
    }
}

function addManualUnit() {
    const nextIdx = form.units.length + 1;
    let defaultType: 'apartment' | 'shop' | 'room' | 'bed' = 'apartment';
    let defaultParent: string | null = null;
    let defaultRentable = true;

    if (form.property_type === 'shop') {
        defaultType = 'shop';
    } else if (form.property_type === 'apartment') {
        if (aptRentalMode.value === 'rooms') {
            defaultType = 'room';
            defaultParent = availableApartmentParents.value[0]?.temp_id || null;
        } else if (aptRentalMode.value === 'beds') {
            defaultType = 'bed';
            defaultParent = availableRoomParents.value[0]?.temp_id || null;
        }
    }

    const tempId = `custom-${Date.now()}-${nextIdx}`;
    form.units.push({
        id: tempId,
        temp_id: tempId,
        parent_temp_id: defaultParent,
        name: `وحدة جديدة ${nextIdx}`,
        code: `U-${nextIdx}`,
        unit_type: defaultType,
        is_rentable: defaultRentable,
        floor_name: 'مخصص',
    });
}

function handleUnitTypeChange(unit: GeneratedUnit) {
    if (unit.unit_type === 'apartment' || unit.unit_type === 'shop') {
        unit.parent_temp_id = null;
    } else if (unit.unit_type === 'room') {
        unit.parent_temp_id = availableApartmentParents.value[0]?.temp_id || null;
    } else if (unit.unit_type === 'bed') {
        unit.parent_temp_id = availableRoomParents.value[0]?.temp_id || null;
    }
}

function toggleAmenity(id: string) {
    const idx = form.amenities.indexOf(id);
    if (idx > -1) {
        form.amenities.splice(idx, 1);
    } else {
        form.amenities.push(id);
    }
}

// Summary computations
const totalUnitsCount = computed(() => form.units.length);
const totalBillableUnitsCount = computed(() => form.units.filter((u) => u.is_rentable).length);
const totalApartmentsCount = computed(() => form.units.filter((u) => u.unit_type === 'apartment').length);
const totalShopsCount = computed(() => form.units.filter((u) => u.unit_type === 'shop').length);
const totalRoomsCount = computed(() => form.units.filter((u) => u.unit_type === 'room').length);
const totalBedsCount = computed(() => form.units.filter((u) => u.unit_type === 'bed').length);

const selectedTypeDetails = computed(() => {
    return props.property_types.find((t) => t.value === form.property_type) || props.property_types[0];
});

// Step navigation validation
function goToStep(step: number) {
    if (step > currentStep.value) {
        if (currentStep.value === 1) {
            if (!form.name.trim()) {
                form.setError('name', 'اسم العقار مطلوب للاستمرار.');
                return;
            }
            form.clearErrors('name');
        }
    }
    currentStep.value = step;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function submit() {
    // Populate backend generation config
    form.generation_config = {
        enabled: autoGenEnabled.value,
        rental_mode: aptRentalMode.value,
        floors_count: genFloorsCount.value,
        units_per_floor: genUnitsPerFloor.value,
        rooms_count: aptRoomsCount.value,
        beds_per_room: aptBedsPerRoom.value,
        include_ground_shops: genIncludeGroundShops.value,
        shops_count: genShopsCount.value,
    };

    if (!autoGenEnabled.value) {
        form.units = [];
    } else {
        // Ensure every unit has valid temp_id
        form.units.forEach((u) => {
            if (!u.temp_id) {
                u.temp_id = u.id;
            }
        });
    }

    form.post(route('properties.store'), {
        forceFormData: true,
    });
}
</script>

<template>
    <AppLayout title="إضافة عقار جديد">
        <Head title="إضافة عقار جديد" />

        <div class="-mx-4 -my-6 min-h-[calc(100dvh-5rem)] bg-[#F3F7FA] px-4 pb-10 pt-6 sm:-mx-8 sm:-my-8 sm:px-8 sm:pb-12 sm:pt-8" dir="rtl">
            <div class="mx-auto max-w-6xl">
                <!-- Header & Breadcrumbs -->
                <nav class="mb-6 flex items-center gap-2 text-xs font-semibold text-[#65766F]">
                    <Link :href="route('properties.index')" class="transition hover:text-[#18705A]">
                        العقارات
                    </Link>
                    <ChevronLeft class="size-3.5 text-[#A5B5AD]" />
                    <span class="text-[#18372E]">إضافة عقار جديد</span>
                </nav>

                <div class="mb-7 flex flex-col justify-between gap-5 border-b border-[#D6E0E7] pb-6 sm:mb-8 sm:flex-row sm:items-center">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <span class="grid size-10 place-items-center rounded-2xl bg-[#DCEDE3] text-[#18705A]">
                                <Building2 v-if="form.property_type === 'building'" class="size-5" />
                                <Home v-else-if="form.property_type === 'apartment'" class="size-5" />
                                <Store v-else-if="form.property_type === 'shop' || form.property_type === 'commercial'" class="size-5" />
                                <Boxes v-else class="size-5" />
                            </span>
                            <div>
                                <h1 class="text-2xl font-black tracking-tight text-[#0F2747] sm:text-3xl">إضافة عقار جديد</h1>
                                <p class="mt-1 max-w-2xl text-sm leading-6 text-[#5B6770]">
                                    اختر نوع العقار لتخصيص الحقول المناسبة له وتوليد الوحدات المتوافقة مع طبيعته الاستثمارية.
                                </p>
                            </div>
                        </div>
                    </div>
                    <Link
                        :href="route('properties.index')"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-[#D6E0E7] bg-white px-4 py-2.5 text-xs font-bold text-[#0F2747] shadow-2xs transition hover:border-[#0E6E7E] hover:text-[#0E6E7E]"
                    >
                        <ArrowRight class="size-4" />
                        العودة لقائمة العقارات
                    </Link>
                </div>

                <!-- Wizard Progress Stepper -->
                <div class="mb-7 overflow-hidden rounded-3xl border border-[#D6E0E7] bg-white p-4 shadow-2xs sm:mb-8 sm:p-6">
                    <div class="grid grid-cols-5 gap-1 sm:gap-3">
                        <button
                            v-for="step in steps"
                            :key="step.number"
                            type="button"
                            @click="goToStep(step.number)"
                            :aria-current="currentStep === step.number ? 'step' : undefined"
                            :aria-label="`الخطوة ${step.number}: ${step.title}`"
                            class="group relative flex flex-col items-center rounded-xl p-1.5 text-center transition sm:items-start sm:rounded-2xl sm:p-3 sm:text-right"
                            :class="[
                                currentStep === step.number
                                    ? 'bg-[#EFF7F2] ring-1 ring-[#18705A]'
                                    : currentStep > step.number
                                    ? 'bg-white hover:bg-[#F8FBF9]'
                                    : 'opacity-60 hover:opacity-100',
                            ]"
                        >
                            <div class="flex flex-col items-center gap-1.5 sm:flex-row sm:gap-2.5">
                                <span
                                    class="grid size-8 place-items-center rounded-full text-xs font-black transition"
                                    :class="[
                                        currentStep === step.number
                                            ? 'bg-[#18705A] text-white shadow-sm'
                                            : currentStep > step.number
                                            ? 'bg-[#DCEDE3] text-[#18705A]'
                                            : 'bg-[#EAEFEA] text-[#65766F]',
                                    ]"
                                >
                                    <Check v-if="currentStep > step.number" class="size-4" :stroke-width="3" />
                                    <span v-else>{{ step.number }}</span>
                                </span>
                                <span
                                    class="hidden text-xs font-extrabold sm:block"
                                    :class="currentStep === step.number ? 'text-[#18705A]' : 'text-[#18372E]'"
                                >
                                    {{ step.title }}
                                </span>
                            </div>
                            <span class="mt-2 hidden line-clamp-1 text-[11px] text-[#7A8C83] md:block">
                                {{ step.subtitle }}
                            </span>
                        </button>
                    </div>

                    <div class="mt-4 border-t border-[#E8EFF4] pt-4 sm:hidden">
                        <p class="text-[11px] font-semibold text-[#5B6770]">الخطوة {{ currentStep }} من {{ steps.length }}</p>
                        <p class="mt-1 text-sm font-extrabold text-[#18372E]">{{ steps[currentStep - 1].title }}</p>
                    </div>

                    <!-- Progress Bar Line -->
                    <div
                        class="mt-4 h-1.5 w-full overflow-hidden rounded-full bg-[#E8EFF4]"
                        role="progressbar"
                        :aria-valuenow="currentStep"
                        :aria-valuemin="1"
                        :aria-valuemax="steps.length"
                        :aria-label="`الخطوة ${currentStep} من ${steps.length}`"
                    >
                        <div
                            class="h-full bg-gradient-to-r from-[#18705A] to-[#25B492] transition-all duration-300"
                            :style="{ width: `${((currentStep - 1) / (steps.length - 1)) * 100}%` }"
                        ></div>
                    </div>
                </div>

                <!-- Form Container -->
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- ======================================================== -->
                    <!-- STEP 1: النوع والهوية الأساسية                           -->
                    <!-- ======================================================== -->
                    <div
                        v-show="currentStep === 1"
                        class="space-y-6 rounded-3xl border border-[#E3EAE6] bg-white p-6 shadow-sm sm:p-8"
                    >
                        <div class="border-b border-[#EAEFEA] pb-5">
                            <span class="text-xs font-bold text-[#18705A]">الخطوة الأولى</span>
                            <h2 class="mt-1 text-xl font-black text-[#18372E]">نوع وهوية العقار</h2>
                            <p class="mt-1 text-sm text-[#65766F]">
                                حدد التصنيف الدقيق للعقار لتخصيص الحقول المناسبة له وتوليد شجرة الوحدات والتأجير المناسبة.
                            </p>
                        </div>

                        <!-- Property Types Selection Cards -->
                        <div class="space-y-3">
                            <label class="block text-sm font-bold text-[#18372E]">
                                نوع العقار <span class="text-rose-500">*</span>
                            </label>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <button
                                    v-for="type in property_types"
                                    :key="type.value"
                                    type="button"
                                    @click="form.property_type = type.value"
                                    class="relative flex flex-col justify-between rounded-2xl border p-4 text-right transition"
                                    :class="[
                                        form.property_type === type.value
                                            ? 'border-[#18705A] bg-[#F2F8F4] ring-2 ring-[#18705A]'
                                            : 'border-[#DDE6E0] bg-white hover:border-[#18705A]/40 hover:bg-[#FAFCFA]',
                                    ]"
                                >
                                    <div>
                                        <div class="flex items-center justify-between">
                                            <span
                                                class="grid size-11 place-items-center rounded-xl"
                                                :class="form.property_type === type.value ? 'bg-[#DCEDE3] text-[#18705A]' : 'bg-[#EDF2EE] text-[#556960]'"
                                            >
                                                <Building2 v-if="type.icon === 'building'" class="size-6" />
                                                <Store v-else-if="type.icon === 'store'" class="size-6" />
                                                <Home v-else-if="type.icon === 'home'" class="size-6" />
                                                <DoorOpen v-else-if="type.icon === 'door-open'" class="size-6" />
                                                <Boxes v-else class="size-6" />
                                            </span>
                                            <span class="rounded-full bg-[#E5EEE8] px-2.5 py-0.5 text-[10px] font-bold text-[#18705A]">
                                                {{ type.badge }}
                                            </span>
                                        </div>
                                        <h3 class="mt-3.5 text-base font-black text-[#18372E]">{{ type.label }}</h3>
                                        <p class="mt-1 text-xs leading-5 text-[#65766F]">{{ type.description }}</p>
                                    </div>

                                    <div v-if="form.property_type === type.value" class="mt-3 flex items-center gap-1.5 text-xs font-bold text-[#18705A]">
                                        <CheckCircle2 class="size-4" />
                                        <span>محدد حالياً</span>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Basic Names & Code -->
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <div>
                                <label for="prop-name" class="mb-2 block text-sm font-bold text-[#18372E]">
                                    اسم العقار <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    id="prop-name"
                                    v-model="form.name"
                                    type="text"
                                    required
                                    :placeholder="
                                        form.property_type === 'shop'
                                            ? 'مثال: محل البركة التجاري - المعادي'
                                            : form.property_type === 'apartment'
                                            ? 'مثال: شقة رقم 12 - برج الياسمين'
                                            : 'مثال: عمارة النور والأمل - المعادي'
                                    "
                                    class="h-12 w-full rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] px-4 text-sm text-[#18372E] outline-none transition placeholder:text-[#99A8A0] focus:border-[#18705A] focus:bg-white focus:ring-2 focus:ring-[#18705A]/15"
                                />
                                <p v-if="form.errors.name" class="mt-1.5 text-xs font-bold text-rose-500">
                                    {{ form.errors.name }}
                                </p>
                            </div>

                            <div>
                                <label for="prop-code" class="mb-2 block text-sm font-bold text-[#18372E]">
                                    كود / مرجع العقار <span class="text-xs font-normal text-[#7A8C83]">(اختياري)</span>
                                </label>
                                <input
                                    id="prop-code"
                                    v-model="form.code"
                                    type="text"
                                    :placeholder="
                                        form.property_type === 'shop'
                                            ? 'مثال: SHP-101'
                                            : form.property_type === 'apartment'
                                            ? 'مثال: APT-12'
                                            : 'مثال: BLD-102'
                                    "
                                    class="h-12 w-full rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] px-4 text-sm text-[#18372E] outline-none transition placeholder:text-[#99A8A0] focus:border-[#18705A] focus:bg-white focus:ring-2 focus:ring-[#18705A]/15"
                                />
                                <p v-if="form.errors.code" class="mt-1.5 text-xs font-bold text-rose-500">
                                    {{ form.errors.code }}
                                </p>
                            </div>
                        </div>

                        <!-- Cover Image Upload -->
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-[#18372E]">
                                صورة واجهة العقار <span class="text-xs font-normal text-[#7A8C83]">(اختياري)</span>
                            </label>

                            <div
                                v-if="imagePreviewUrl"
                                class="relative h-56 w-full overflow-hidden rounded-3xl border border-[#D8E4DC] bg-[#10251D]"
                            >
                                <img :src="imagePreviewUrl" alt="معاينة العقار" class="h-full w-full object-cover" />
                                <button
                                    type="button"
                                    @click="removeImage"
                                    class="absolute left-4 top-4 flex items-center gap-1.5 rounded-xl bg-black/70 px-3 py-1.5 text-xs font-bold text-white backdrop-blur-md transition hover:bg-rose-600"
                                >
                                    <Trash2 class="size-4" />
                                    حذف الصورة
                                </button>
                            </div>

                            <label
                                v-else
                                class="flex cursor-pointer flex-col items-center justify-center rounded-3xl border-2 border-dashed border-[#D8E4DC] bg-[#FAFDFB] p-8 text-center transition hover:border-[#18705A] hover:bg-[#F2F8F4]"
                            >
                                <span class="grid size-14 place-items-center rounded-2xl bg-[#DCEDE3] text-[#18705A]">
                                    <UploadCloud class="size-7" />
                                </span>
                                <span class="mt-3 text-sm font-bold text-[#18372E]">
                                    انقر لرفع صورة العقار الرئيسية أو اسحبها هنا
                                </span>
                                <span class="mt-1 text-xs text-[#7A8C83]">PNG, JPG, WEBP حتى 5 ميجابايت</span>
                                <input
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    @change="handleImageChange"
                                />
                            </label>
                            <p v-if="form.errors.cover_image_file" class="text-xs font-bold text-rose-500">
                                {{ form.errors.cover_image_file }}
                            </p>
                        </div>
                    </div>

                    <!-- ======================================================== -->
                    <!-- STEP 2: الموقع الجغرافي والعنوان                         -->
                    <!-- ======================================================== -->
                    <div
                        v-show="currentStep === 2"
                        class="space-y-6 rounded-3xl border border-[#E3EAE6] bg-white p-6 shadow-sm sm:p-8"
                    >
                        <div class="border-b border-[#EAEFEA] pb-5">
                            <span class="text-xs font-bold text-[#18705A]">الخطوة الثانية</span>
                            <h2 class="mt-1 text-xl font-black text-[#18372E]">الموقع الجغرافي والعنوان</h2>
                            <p class="mt-1 text-sm text-[#65766F]">
                                حدد عنوان العقار وموقعه بدقة لتسهيل وصول المستأجرين والفنيين.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <div>
                                <label for="prop-city" class="mb-2 block text-sm font-bold text-[#18372E]">المدينة / المحافظة</label>
                                <input
                                    id="prop-city"
                                    v-model="form.city"
                                    type="text"
                                    placeholder="مثال: القاهرة، الجيزة، الإسكندرية..."
                                    class="h-12 w-full rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] px-4 text-sm text-[#18372E] outline-none transition placeholder:text-[#99A8A0] focus:border-[#18705A] focus:bg-white focus:ring-2 focus:ring-[#18705A]/15"
                                />
                            </div>

                            <div>
                                <label for="prop-district" class="mb-2 block text-sm font-bold text-[#18372E]">الحي / المنطقة</label>
                                <input
                                    id="prop-district"
                                    v-model="form.district"
                                    type="text"
                                    placeholder="مثال: المعادي، الدقي، التجمع الخامس..."
                                    class="h-12 w-full rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] px-4 text-sm text-[#18372E] outline-none transition placeholder:text-[#99A8A0] focus:border-[#18705A] focus:bg-white focus:ring-2 focus:ring-[#18705A]/15"
                                />
                            </div>

                            <div>
                                <label for="prop-street" class="mb-2 block text-sm font-bold text-[#18372E]">اسم الشارع</label>
                                <input
                                    id="prop-street"
                                    v-model="form.street"
                                    type="text"
                                    placeholder="مثال: شارع النصر، متفرع من اللاسلكي"
                                    class="h-12 w-full rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] px-4 text-sm text-[#18372E] outline-none transition placeholder:text-[#99A8A0] focus:border-[#18705A] focus:bg-white focus:ring-2 focus:ring-[#18705A]/15"
                                />
                            </div>

                            <!-- Contextual Building / Location field -->
                            <div>
                                <label for="prop-bld-num" class="mb-2 block text-sm font-bold text-[#18372E]">
                                    <template v-if="form.property_type === 'shop'">رقم المحل أو المبنى التجاري</template>
                                    <template v-else-if="form.property_type === 'apartment'">رقم العقار أو اسم البرج التابع له</template>
                                    <template v-else>رقم المبنى / القطعة</template>
                                </label>
                                <input
                                    id="prop-bld-num"
                                    v-model="form.building_number"
                                    type="text"
                                    :placeholder="
                                        form.property_type === 'shop'
                                            ? 'مثال: محل رقم 4، مول الصفا'
                                            : form.property_type === 'apartment'
                                            ? 'مثال: برج الياسمين 2، عمارة 15'
                                            : 'مثال: عمارة رقم 15، قطعة 4'
                                    "
                                    class="h-12 w-full rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] px-4 text-sm text-[#18372E] outline-none transition placeholder:text-[#99A8A0] focus:border-[#18705A] focus:bg-white focus:ring-2 focus:ring-[#18705A]/15"
                                />
                            </div>

                            <!-- Apartment Specific: Floor Number -->
                            <div v-if="form.property_type === 'apartment'">
                                <label for="prop-floor-num" class="mb-2 block text-sm font-bold text-[#18372E]">
                                    رقم الدور / الطابق <span class="text-xs font-normal text-[#7A8C83]">(خاص بالشقة)</span>
                                </label>
                                <input
                                    id="prop-floor-num"
                                    v-model="form.floor_number"
                                    type="text"
                                    placeholder="مثال: الدور الثالث، الدور الأرضي..."
                                    class="h-12 w-full rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] px-4 text-sm text-[#18372E] outline-none transition placeholder:text-[#99A8A0] focus:border-[#18705A] focus:bg-white focus:ring-2 focus:ring-[#18705A]/15"
                                />
                            </div>
                        </div>

                        <div>
                            <label for="prop-maps-url" class="mb-2 block text-sm font-bold text-[#18372E]">
                                رابط موقع العقار على خرائط جوجل (Google Maps)
                            </label>
                            <div class="relative">
                                <span class="pointer-events-none absolute right-4 top-3.5 text-[#18705A]">
                                    <MapPin class="size-5" />
                                </span>
                                <input
                                    id="prop-maps-url"
                                    v-model="form.google_maps_url"
                                    type="url"
                                    placeholder="https://maps.google.com/?q=..."
                                    class="h-12 w-full rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] pr-12 pl-4 text-sm text-[#18372E] outline-none transition placeholder:text-[#99A8A0] focus:border-[#18705A] focus:bg-white focus:ring-2 focus:ring-[#18705A]/15"
                                />
                            </div>
                            <p v-if="form.errors.google_maps_url" class="mt-1.5 text-xs font-bold text-rose-500">
                                {{ form.errors.google_maps_url }}
                            </p>
                        </div>

                        <div>
                            <label for="prop-address-full" class="mb-2 block text-sm font-bold text-[#18372E]">
                                العنوان الكامل المسجل / العلامة المميزة
                            </label>
                            <textarea
                                id="prop-address-full"
                                v-model="form.address"
                                rows="2"
                                placeholder="مثال: بجوار مسجد الفاروق، أمام بنك مصر..."
                                class="w-full rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] p-4 text-sm text-[#18372E] outline-none transition placeholder:text-[#99A8A0] focus:border-[#18705A] focus:bg-white focus:ring-2 focus:ring-[#18705A]/15"
                            ></textarea>
                        </div>
                    </div>

                    <!-- ======================================================== -->
                    <!-- STEP 3: المواصفات، المرافق، والعدادات                    -->
                    <!-- ======================================================== -->
                    <div
                        v-show="currentStep === 3"
                        class="space-y-8 rounded-3xl border border-[#E3EAE6] bg-white p-6 shadow-sm sm:p-8"
                    >
                        <div class="border-b border-[#EAEFEA] pb-5">
                            <span class="text-xs font-bold text-[#18705A]">الخطوة الثالثة</span>
                            <h2 class="mt-1 text-xl font-black text-[#18372E]">المواصفات الإنشائية والمرافق والخدمات</h2>
                            <p class="mt-1 text-sm text-[#65766F]">
                                حقول مخصصة تناسب نوع العقار المختار:
                                <span class="font-bold text-[#18705A]">{{ selectedTypeDetails.label }}</span>
                            </p>
                        </div>

                        <!-- CASE A: محل تجاري (Shop) Specific Specs -->
                        <div v-if="form.property_type === 'shop'" class="space-y-6">
                            <div class="rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] p-5">
                                <h3 class="flex items-center gap-2 text-sm font-extrabold text-[#18705A]">
                                    <Store class="size-4" />
                                    <span>مواصفات المحل التجاري</span>
                                </h3>

                                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">المساحة بالمتر المربع (م²)</label>
                                        <input
                                            v-model="form.total_area"
                                            type="number"
                                            placeholder="مثال: 65"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-white px-3 text-sm font-bold text-[#18372E] focus:border-[#18705A]"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">النشاط التجاري الحالي أو المرخص</label>
                                        <input
                                            v-model="form.commercial_activity"
                                            type="text"
                                            placeholder="مثال: مطعم / كافيه، صيدلية، سوبر ماركت، ملابس..."
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-white px-3 text-sm text-[#18372E] focus:border-[#18705A]"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">مستوى التشطيب التجاري</label>
                                        <select
                                            v-model="form.finishing_type"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-white px-3 text-xs font-bold text-[#18372E] focus:border-[#18705A]"
                                        >
                                            <option v-for="ft in finishing_types" :key="ft.value" :value="ft.value">
                                                {{ ft.label }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Commercial Utility Meters -->
                            <div class="space-y-4">
                                <h3 class="flex items-center gap-2 text-sm font-extrabold text-[#18705A]">
                                    <Receipt class="size-4" />
                                    <span>عدادات واشتراكات المحل التجاري</span>
                                </h3>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">رقم عداد الكهرباء التجاري</label>
                                        <input
                                            v-model="form.electricity_meter_number"
                                            type="text"
                                            placeholder="مثال: ELEC-99120"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-xs text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">رقم عداد المياه</label>
                                        <input
                                            v-model="form.water_meter_number"
                                            type="text"
                                            placeholder="مثال: WTR-33211"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-xs text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">رقم اشتراك الغاز (إن وجد)</label>
                                        <input
                                            v-model="form.gas_meter_number"
                                            type="text"
                                            placeholder="مثال: GAS-11204"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-xs text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CASE B: شقة سكنية (Apartment) Specific Specs -->
                        <div v-else-if="form.property_type === 'apartment'" class="space-y-6">
                            <div class="rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] p-5">
                                <h3 class="flex items-center gap-2 text-sm font-extrabold text-[#18705A]">
                                    <Home class="size-4" />
                                    <span>مواصفات الشقة</span>
                                </h3>

                                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">مساحة الشقة (م²)</label>
                                        <input
                                            v-model="form.total_area"
                                            type="number"
                                            placeholder="مثال: 140"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-white px-3 text-sm font-bold text-[#18372E] focus:border-[#18705A]"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">سنة البناء / التجديد</label>
                                        <input
                                            v-model.number="form.year_built"
                                            type="number"
                                            min="1900"
                                            max="2099"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-white px-3 text-sm text-[#18372E] focus:border-[#18705A]"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">مستوى تشطيب الشقة</label>
                                        <select
                                            v-model="form.finishing_type"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-white px-3 text-xs font-bold text-[#18372E] focus:border-[#18705A]"
                                        >
                                            <option v-for="ft in finishing_types" :key="ft.value" :value="ft.value">
                                                {{ ft.label }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Apartment Utility Meters -->
                            <div class="space-y-4">
                                <h3 class="flex items-center gap-2 text-sm font-extrabold text-[#18705A]">
                                    <Receipt class="size-4" />
                                    <span>عدادات واشتراكات الشقة</span>
                                </h3>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">رقم عداد الكهرباء الخاص بالشقة</label>
                                        <input
                                            v-model="form.electricity_meter_number"
                                            type="text"
                                            placeholder="مثال: 0102938475"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-xs text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">رقم عداد المياه</label>
                                        <input
                                            v-model="form.water_meter_number"
                                            type="text"
                                            placeholder="مثال: WTR-99210"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-xs text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">رقم اشتراك الغاز الطبيعي</label>
                                        <input
                                            v-model="form.gas_meter_number"
                                            type="text"
                                            placeholder="مثال: GAS-33491"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-xs text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CASE C: عمارة أو مبنى متعدد الوحدات (Building / Commercial / Villa) -->
                        <div v-else class="space-y-6">
                            <!-- Structural Specs -->
                            <div class="space-y-4">
                                <h3 class="flex items-center gap-2 text-sm font-extrabold text-[#18705A]">
                                    <Building2 class="size-4" />
                                    <span>المواصفات الإنشائية للمبنى</span>
                                </h3>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">عدد الطوابق</label>
                                        <input
                                            v-model.number="form.floors_count"
                                            type="number"
                                            min="1"
                                            max="200"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-sm font-bold text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">المساحة الإجمالية (م²)</label>
                                        <input
                                            v-model="form.total_area"
                                            type="number"
                                            placeholder="مثال: 550"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-sm text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">سنة البناء</label>
                                        <input
                                            v-model.number="form.year_built"
                                            type="number"
                                            min="1900"
                                            max="2099"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-sm text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">مستوى التشطيب</label>
                                        <select
                                            v-model="form.finishing_type"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-xs font-bold text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        >
                                            <option v-for="ft in finishing_types" :key="ft.value" :value="ft.value">
                                                {{ ft.label }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Building Utility Meters -->
                            <div class="space-y-4">
                                <h3 class="flex items-center gap-2 text-sm font-extrabold text-[#18705A]">
                                    <Receipt class="size-4" />
                                    <span>عدادات واشتراكات المبنى الرئيسية</span>
                                </h3>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">رقم عداد الكهرباء الرئيسي</label>
                                        <input
                                            v-model="form.electricity_meter_number"
                                            type="text"
                                            placeholder="مثال: 0102938475"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-xs text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">رقم عداد المياه الرئيسي</label>
                                        <input
                                            v-model="form.water_meter_number"
                                            type="text"
                                            placeholder="مثال: WTR-99210"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-xs text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">رقم اشتراك الغاز الطبيعي</label>
                                        <input
                                            v-model="form.gas_meter_number"
                                            type="text"
                                            placeholder="مثال: GAS-33491"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-xs text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Building Guard Details (Only for Building / Commercial / Villa) -->
                            <div class="space-y-4">
                                <h3 class="flex items-center gap-2 text-sm font-extrabold text-[#18705A]">
                                    <UserCheck class="size-4" />
                                    <span>بيانات الحارس والإشراف على المبنى</span>
                                </h3>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">اسم حارس / مشرف العقار</label>
                                        <input
                                            v-model="form.guard_name"
                                            type="text"
                                            placeholder="مثال: عم محمد"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-xs text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">رقم هاتف الحارس</label>
                                        <input
                                            v-model="form.guard_phone"
                                            type="tel"
                                            placeholder="010XXXXXXXX"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-xs text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Amenities Checklist (Filtered based on property type) -->
                        <div v-if="form.property_type !== 'shop'" class="space-y-4">
                            <h3 class="flex items-center gap-2 text-sm font-extrabold text-[#18705A]">
                                <Sparkles class="size-4" />
                                <span>المرافق والخدمات المتوفرة</span>
                            </h3>

                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <button
                                    v-for="amenity in amenities_list"
                                    :key="amenity.id"
                                    type="button"
                                    @click="toggleAmenity(amenity.id)"
                                    class="flex items-center gap-2.5 rounded-2xl border p-3 text-right transition"
                                    :class="[
                                        form.amenities.includes(amenity.id)
                                            ? 'border-[#18705A] bg-[#EFF7F2] text-[#18705A] ring-1 ring-[#18705A]'
                                            : 'border-[#DDE6E0] bg-white text-[#4A6055] hover:border-[#18705A]/40',
                                    ]"
                                >
                                    <span
                                        class="grid size-8 shrink-0 place-items-center rounded-xl"
                                        :class="form.amenities.includes(amenity.id) ? 'bg-[#DCEDE3] text-[#18705A]' : 'bg-[#F2F6F3] text-[#71857C]'"
                                    >
                                        <ArrowUpDown v-if="amenity.icon === 'ArrowUpDown'" class="size-4" />
                                        <Car v-else-if="amenity.icon === 'Car'" class="size-4" />
                                        <ShieldCheck v-else-if="amenity.icon === 'ShieldCheck'" class="size-4" />
                                        <Video v-else-if="amenity.icon === 'Video'" class="size-4" />
                                        <Droplets v-else-if="amenity.icon === 'Droplets'" class="size-4" />
                                        <Flame v-else-if="amenity.icon === 'Flame'" class="size-4" />
                                        <Wind v-else-if="amenity.icon === 'Wind'" class="size-4" />
                                        <Wifi v-else-if="amenity.icon === 'Wifi'" class="size-4" />
                                        <PhoneCall v-else-if="amenity.icon === 'PhoneCall'" class="size-4" />
                                        <BellRing v-else-if="amenity.icon === 'BellRing'" class="size-4" />
                                        <Zap v-else-if="amenity.icon === 'Zap'" class="size-4" />
                                        <Trash2 v-else class="size-4" />
                                    </span>
                                    <span class="text-xs font-bold leading-tight">{{ amenity.label }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Deed and Internal Notes (Universal) -->
                        <div class="space-y-4 border-t border-[#EAEFEA] pt-6">
                            <h3 class="flex items-center gap-2 text-sm font-extrabold text-[#18705A]">
                                <FileText class="size-4" />
                                <span>سند الملكية والملاحظات الداخلية</span>
                            </h3>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="mb-2 block text-xs font-bold text-[#2A4137]">رقم صك الملكية أو العقد</label>
                                    <input
                                        v-model="form.deed_number"
                                        type="text"
                                        placeholder="مثال: DEED-2024-998"
                                        class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-xs text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                    />
                                </div>

                                <div>
                                    <label class="mb-2 block text-xs font-bold text-[#2A4137]">ملاحظات داخلية حول العقار</label>
                                    <input
                                        v-model="form.notes"
                                        type="text"
                                        placeholder="أي تفاصيل تشغيلية أو ملاحظات خاصة بإدارة العقار..."
                                        class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-xs text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ======================================================== -->
                    <!-- STEP 4: التوليد والتكوين التلقائي للوحدات                 -->
                    <!-- ======================================================== -->
                    <div
                        v-show="currentStep === 4"
                        class="space-y-6 rounded-3xl border border-[#E3EAE6] bg-white p-6 shadow-sm sm:p-8"
                    >
                        <div class="border-b border-[#EAEFEA] pb-5">
                            <span class="text-xs font-bold text-[#18705A]">الخطوة الرابعة</span>
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="mt-1 text-xl font-black text-[#18372E]">توليد وتكوين الوحدات تلقائياً</h2>
                                    <p class="mt-1 text-sm text-[#65766F]">
                                        هيكل الوحدات يتبع طبيعة
                                        <span class="font-bold text-[#18705A]">{{ selectedTypeDetails.label }}</span>
                                        مباشرة دون تكرار أو علاقات غير صالحة.
                                    </p>
                                </div>
                                <label class="flex cursor-pointer items-center gap-2 rounded-2xl bg-[#EFF7F2] px-4 py-2 text-xs font-bold text-[#18705A]">
                                    <input
                                        type="checkbox"
                                        v-model="autoGenEnabled"
                                        class="size-4 rounded text-[#18705A] focus:ring-[#18705A]"
                                    />
                                    <span>تفعيل التوليد السريع للوحدات</span>
                                </label>
                            </div>
                        </div>

                        <div v-if="autoGenEnabled" class="space-y-6">
                            <!-- ---------------------------------------------------- -->
                            <!-- WORKFLOW A: محل تجاري (Shop) Generator Config         -->
                            <!-- ---------------------------------------------------- -->
                            <div v-if="form.property_type === 'shop'" class="rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] p-5">
                                <div class="flex items-start gap-3">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-[#DCEDE3] text-[#18705A]">
                                        <Store class="size-5" />
                                    </span>
                                    <div>
                                        <h4 class="text-sm font-black text-[#18372E]">توليد المحل التجاري المستقل</h4>
                                        <p class="mt-1 text-xs leading-5 text-[#65766F]">
                                            المحل التجاري هو وحدة تأجيرية واحدة مستقلة بذاتها. لا يتم توليد أدوار سكنية أو شقق أو غرف أو أسرة لمحل تجاري.
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-[#E3EAE6] pt-4">
                                    <button
                                        type="button"
                                        @click="generateUnitsForShop"
                                        class="flex h-10 items-center justify-center gap-1.5 rounded-xl bg-[#18705A] px-4 text-xs font-bold text-white shadow-sm transition hover:bg-[#105742]"
                                    >
                                        <Sparkles class="size-4" />
                                        <span>تحديث بيانات المحل</span>
                                    </button>
                                </div>
                            </div>

                            <!-- ---------------------------------------------------- -->
                            <!-- WORKFLOW B: شقة (Apartment) Generator Config         -->
                            <!-- ---------------------------------------------------- -->
                            <div v-else-if="form.property_type === 'apartment'" class="space-y-4 rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] p-5">
                                <div>
                                    <h4 class="text-sm font-black text-[#18372E]">طريقة تأجير الشقة وهيكلة الوحدات</h4>
                                    <p class="mt-1 text-xs text-[#65766F]">
                                        حدد كيف ستقوم بتأجير هذه الشقة ليتم بناء شجرة الوحدات وقابليتها للفوترة بدقة:
                                    </p>
                                </div>

                                <!-- 3-Way Mode Selection -->
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    <button
                                        type="button"
                                        @click="aptRentalMode = 'whole'; generateUnitsForApartment()"
                                        class="flex flex-col justify-between rounded-xl border p-3.5 text-right transition"
                                        :class="[
                                            aptRentalMode === 'whole'
                                                ? 'border-[#18705A] bg-[#EFF7F2] ring-1 ring-[#18705A]'
                                                : 'border-[#DDE6E0] bg-white hover:border-[#18705A]/40',
                                        ]"
                                    >
                                        <div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs font-black text-[#18372E]">1. تأجير الشقة بالكامل</span>
                                                <Home class="size-4 text-[#18705A]" />
                                            </div>
                                            <p class="mt-1.5 text-[11px] leading-4 text-[#65766F]">
                                                تأجير الشقة كعقد واحد متكامل لمستأجر واحد (النمط التقليدي).
                                            </p>
                                        </div>
                                        <span class="mt-3 text-[10px] font-bold text-[#18705A]">وحدة واحدة قابلة للتأجير</span>
                                    </button>

                                    <button
                                        type="button"
                                        @click="aptRentalMode = 'rooms'; generateUnitsForApartment()"
                                        class="flex flex-col justify-between rounded-xl border p-3.5 text-right transition"
                                        :class="[
                                            aptRentalMode === 'rooms'
                                                ? 'border-[#18705A] bg-[#EFF7F2] ring-1 ring-[#18705A]'
                                                : 'border-[#DDE6E0] bg-white hover:border-[#18705A]/40',
                                        ]"
                                    >
                                        <div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs font-black text-[#18372E]">2. تأجير الغرف المستقلة</span>
                                                <DoorOpen class="size-4 text-[#18705A]" />
                                            </div>
                                            <p class="mt-1.5 text-[11px] leading-4 text-[#65766F]">
                                                تأجير كل غرفة داخل الشقة بعقد منفصل (سكن مشترك / مغتربين).
                                            </p>
                                        </div>
                                        <span class="mt-3 text-[10px] font-bold text-[#18705A]">الغرف فقط قابلة للتأجير</span>
                                    </button>

                                    <button
                                        type="button"
                                        @click="aptRentalMode = 'beds'; generateUnitsForApartment()"
                                        class="flex flex-col justify-between rounded-xl border p-3.5 text-right transition"
                                        :class="[
                                            aptRentalMode === 'beds'
                                                ? 'border-[#18705A] bg-[#EFF7F2] ring-1 ring-[#18705A]'
                                                : 'border-[#DDE6E0] bg-white hover:border-[#18705A]/40',
                                        ]"
                                    >
                                        <div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs font-black text-[#18372E]">3. تأجير الأسرّة الفردية</span>
                                                <Bed class="size-4 text-[#18705A]" />
                                            </div>
                                            <p class="mt-1.5 text-[11px] leading-4 text-[#65766F]">
                                                تأجير كل سرير داخل الغرفة بعقد منفصل (سكن طلاب / كوادر).
                                            </p>
                                        </div>
                                        <span class="mt-3 text-[10px] font-bold text-[#18705A]">الأسرّة فقط قابلة للتأجير</span>
                                    </button>
                                </div>

                                <!-- Dynamic Room and Bed inputs -->
                                <div v-if="aptRentalMode === 'rooms' || aptRentalMode === 'beds'" class="mt-4 border-t border-[#E3EAE6] pt-4">
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                        <div>
                                            <label class="mb-1.5 block text-xs font-bold text-[#18372E]">عدد الغرف بالشقة</label>
                                            <input
                                                v-model.number="aptRoomsCount"
                                                type="number"
                                                min="1"
                                                max="20"
                                                class="h-10 w-full rounded-xl border border-[#D8E4DC] bg-white px-3 text-xs font-bold text-[#18372E]"
                                            />
                                        </div>

                                        <div v-if="aptRentalMode === 'beds'">
                                            <label class="mb-1.5 block text-xs font-bold text-[#18372E]">عدد الأسرّة في كل غرفة</label>
                                            <input
                                                v-model.number="aptBedsPerRoom"
                                                type="number"
                                                min="1"
                                                max="10"
                                                class="h-10 w-full rounded-xl border border-[#D8E4DC] bg-white px-3 text-xs font-bold text-[#18372E]"
                                            />
                                        </div>

                                        <div class="flex flex-col justify-end">
                                            <button
                                                type="button"
                                                @click="generateUnitsForApartment"
                                                class="flex h-10 items-center justify-center gap-1.5 rounded-xl bg-[#18705A] px-4 text-xs font-bold text-white shadow-sm transition hover:bg-[#105742]"
                                            >
                                                <Sparkles class="size-4" />
                                                <span>تطبيق وإعادة التوليد</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ---------------------------------------------------- -->
                            <!-- WORKFLOW C: عمارة (Building) Generator Config         -->
                            <!-- ---------------------------------------------------- -->
                            <div v-else-if="form.property_type === 'building'" class="rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] p-5">
                                <h4 class="mb-3 text-sm font-black text-[#18372E]">إعدادات أدوار وشقق ومحلات العمارة</h4>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                                    <div>
                                        <label class="mb-1.5 block text-xs font-bold text-[#18372E]">عدد الأدوار السكنية</label>
                                        <input
                                            v-model.number="genFloorsCount"
                                            type="number"
                                            min="1"
                                            max="50"
                                            class="h-10 w-full rounded-xl border border-[#D8E4DC] bg-white px-3 text-xs font-bold text-[#18372E]"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-1.5 block text-xs font-bold text-[#18372E]">شقق في كل دور</label>
                                        <input
                                            v-model.number="genUnitsPerFloor"
                                            type="number"
                                            min="1"
                                            max="20"
                                            class="h-10 w-full rounded-xl border border-[#D8E4DC] bg-white px-3 text-xs font-bold text-[#18372E]"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-1.5 block text-xs font-bold text-[#18372E]">نمط الترقيم</label>
                                        <select
                                            v-model="genNamingPattern"
                                            class="h-10 w-full rounded-xl border border-[#D8E4DC] bg-white px-3 text-xs font-bold text-[#18372E]"
                                        >
                                            <option value="floor_number">رقم الدور (101, 102, 201...)</option>
                                            <option value="sequential">تسلسلي (شقة 1, شقة 2...)</option>
                                        </select>
                                    </div>

                                    <div class="flex flex-col justify-end">
                                        <button
                                            type="button"
                                            @click="generateUnitsForBuilding"
                                            class="flex h-10 items-center justify-center gap-1.5 rounded-xl bg-[#18705A] px-4 text-xs font-bold text-white shadow-sm transition hover:bg-[#105742]"
                                        >
                                            <Sparkles class="size-4" />
                                            <span>إعادة توليد الوحدات</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Ground Floor Shops Config -->
                                <div class="mt-4 flex flex-wrap items-center gap-6 border-t border-[#E3EAE6] pt-4">
                                    <label class="flex cursor-pointer items-center gap-2 text-xs font-bold text-[#18372E]">
                                        <input
                                            type="checkbox"
                                            v-model="genIncludeGroundShops"
                                            class="size-4 rounded text-[#18705A] focus:ring-[#18705A]"
                                        />
                                        <span>تضمين محلات تجارية في الدور الأرضي للعمارة</span>
                                    </label>

                                    <div v-if="genIncludeGroundShops" class="flex items-center gap-2">
                                        <span class="text-xs text-[#65766F]">عدد المحلات الأرضية:</span>
                                        <input
                                            v-model.number="genShopsCount"
                                            type="number"
                                            min="1"
                                            max="15"
                                            class="h-8 w-16 rounded-lg border border-[#D8E4DC] bg-white text-center text-xs font-bold text-[#18372E]"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- ---------------------------------------------------- -->
                            <!-- WORKFLOW D: مركز تجاري / فيلا (Commercial / Villa)   -->
                            <!-- ---------------------------------------------------- -->
                            <div v-else-if="form.property_type === 'commercial'" class="rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] p-5">
                                <h4 class="mb-3 text-sm font-black text-[#18372E]">إعدادات المركز / المجمع التجاري</h4>
                                <div class="flex items-center gap-4">
                                    <div>
                                        <label class="mb-1.5 block text-xs font-bold text-[#18372E]">إجمالي عدد الوحدات والمكاتب التجارية</label>
                                        <input
                                            v-model.number="commUnitsCount"
                                            type="number"
                                            min="1"
                                            max="50"
                                            class="h-10 w-32 rounded-xl border border-[#D8E4DC] bg-white px-3 text-xs font-bold text-[#18372E]"
                                        />
                                    </div>
                                    <div class="mt-5">
                                        <button
                                            type="button"
                                            @click="generateUnitsForCommercial"
                                            class="flex h-10 items-center justify-center gap-1.5 rounded-xl bg-[#18705A] px-4 text-xs font-bold text-white shadow-sm transition hover:bg-[#105742]"
                                        >
                                            <Sparkles class="size-4" />
                                            <span>إعادة توليد الوحدات</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div v-else-if="form.property_type === 'villa'" class="rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] p-5">
                                <h4 class="mb-3 text-sm font-black text-[#18372E]">إعدادات الفيلا</h4>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <label class="mb-1.5 block text-xs font-bold text-[#18372E]">نمط التأجير</label>
                                        <select
                                            v-model="villaRentalMode"
                                            class="h-10 w-full rounded-xl border border-[#D8E4DC] bg-white px-3 text-xs font-bold text-[#18372E]"
                                        >
                                            <option value="whole">الفيلا بالكامل (عقد واحد)</option>
                                            <option value="rooms">مقسمة إلى أجنحة / غرف مستقلة</option>
                                        </select>
                                    </div>

                                    <div v-if="villaRentalMode === 'rooms'">
                                        <label class="mb-1.5 block text-xs font-bold text-[#18372E]">عدد الأجنحة / الغرف</label>
                                        <input
                                            v-model.number="villaRoomsCount"
                                            type="number"
                                            min="1"
                                            max="20"
                                            class="h-10 w-full rounded-xl border border-[#D8E4DC] bg-white px-3 text-xs font-bold text-[#18372E]"
                                        />
                                    </div>

                                    <div class="flex flex-col justify-end">
                                        <button
                                            type="button"
                                            @click="generateUnitsForVilla"
                                            class="flex h-10 items-center justify-center gap-1.5 rounded-xl bg-[#18705A] px-4 text-xs font-bold text-white shadow-sm transition hover:bg-[#105742]"
                                        >
                                            <Sparkles class="size-4" />
                                            <span>إعادة توليد الوحدات</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- ---------------------------------------------------- -->
                            <!-- Generated Units Hierarchy / Preview Table             -->
                            <!-- ---------------------------------------------------- -->
                            <div class="space-y-3">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <h3 class="text-sm font-extrabold text-[#18372E]">
                                            معاينة وتعديل الوحدات الناتجة
                                        </h3>
                                        <span class="rounded-full bg-[#E5EEE8] px-2.5 py-0.5 text-xs font-black text-[#18705A]">
                                            {{ form.units.length }} وحدة إجمالية
                                        </span>
                                        <span class="rounded-full bg-[#EFF7F2] px-2.5 py-0.5 text-xs font-bold text-[#0E6E7E] border border-[#DCEDE3]">
                                            {{ totalBillableUnitsCount }} قابلة للتأجير المباشر
                                        </span>
                                    </div>

                                    <button
                                        type="button"
                                        @click="addManualUnit"
                                        class="flex items-center gap-1 text-xs font-bold text-[#18705A] transition hover:underline"
                                    >
                                        <Plus class="size-3.5" />
                                        <span>إضافة وحدة إضافية يدوياً</span>
                                    </button>
                                </div>

                                <div class="max-h-96 overflow-y-auto rounded-2xl border border-[#E3EAE6] bg-[#FAFDFB]">
                                    <table class="w-full text-right text-xs">
                                        <thead class="sticky top-0 bg-[#EFF5F1] text-[#334D41]">
                                            <tr>
                                                <th class="p-3 font-bold">اسم الوحدة</th>
                                                <th class="p-3 font-bold">الكود</th>
                                                <th class="p-3 font-bold">النوع</th>
                                                <th class="p-3 font-bold">الوحدة الأب (إن وجدت)</th>
                                                <th class="p-3 font-bold text-center">قابلة للتأجير</th>
                                                <th class="p-3 font-bold text-center">إجراء</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-[#EAEFEA]">
                                            <tr
                                                v-for="(unit, index) in form.units"
                                                :key="unit.id"
                                                class="transition hover:bg-white"
                                                :class="[
                                                    unit.unit_type === 'room' ? 'bg-[#FAFDFB]' : '',
                                                    unit.unit_type === 'bed' ? 'bg-[#F4F9F6]' : '',
                                                ]"
                                            >
                                                <!-- Unit Name with hierarchy indentation -->
                                                <td class="p-2.5">
                                                    <div class="flex items-center gap-1.5" :style="{ paddingRight: unit.unit_type === 'room' ? '1.25rem' : unit.unit_type === 'bed' ? '2.5rem' : '0' }">
                                                        <span v-if="unit.unit_type === 'room'" class="text-[#18705A]">↳</span>
                                                        <span v-else-if="unit.unit_type === 'bed'" class="text-[#0E6E7E]">↳↳</span>
                                                        <input
                                                            v-model="unit.name"
                                                            type="text"
                                                            class="h-8 w-full rounded-lg border border-[#D8E4DC] bg-white px-2.5 text-xs font-bold text-[#18372E]"
                                                        />
                                                    </div>
                                                </td>

                                                <!-- Unit Code -->
                                                <td class="p-2.5">
                                                    <input
                                                        v-model="unit.code"
                                                        type="text"
                                                        class="h-8 w-28 rounded-lg border border-[#D8E4DC] bg-white px-2.5 text-xs text-[#18372E]"
                                                    />
                                                </td>

                                                <!-- Unit Type Selector -->
                                                <td class="p-2.5">
                                                    <select
                                                        v-model="unit.unit_type"
                                                        @change="handleUnitTypeChange(unit)"
                                                        class="h-8 rounded-lg border border-[#D8E4DC] bg-white px-2 text-xs font-bold text-[#18372E]"
                                                    >
                                                        <template v-if="form.property_type === 'shop'">
                                                            <option value="shop">محل تجاري</option>
                                                        </template>
                                                        <template v-else-if="form.property_type === 'apartment'">
                                                            <option value="apartment">شقة رئيسية</option>
                                                            <option value="room">غرفة داخل الشقة</option>
                                                            <option value="bed">سرير فردي</option>
                                                        </template>
                                                        <template v-else>
                                                            <option value="apartment">شقة سكنية</option>
                                                            <option value="shop">محل تجاري</option>
                                                            <option value="room">غرفة مستقلة</option>
                                                            <option value="bed">سرير</option>
                                                        </template>
                                                    </select>
                                                </td>

                                                <!-- Parent Unit Selector -->
                                                <td class="p-2.5">
                                                    <div v-if="unit.unit_type === 'room'">
                                                        <select
                                                            v-model="unit.parent_temp_id"
                                                            class="h-8 w-36 rounded-lg border border-[#D8E4DC] bg-white px-2 text-[11px] text-[#18372E]"
                                                        >
                                                            <option :value="null">-- اختر الشقة الأب --</option>
                                                            <option
                                                                v-for="apt in availableApartmentParents"
                                                                :key="apt.temp_id"
                                                                :value="apt.temp_id"
                                                            >
                                                                {{ apt.name }} ({{ apt.code }})
                                                            </option>
                                                        </select>
                                                    </div>
                                                    <div v-else-if="unit.unit_type === 'bed'">
                                                        <select
                                                            v-model="unit.parent_temp_id"
                                                            class="h-8 w-36 rounded-lg border border-[#D8E4DC] bg-white px-2 text-[11px] text-[#18372E]"
                                                        >
                                                            <option :value="null">-- اختر الغرفة الأب --</option>
                                                            <option
                                                                v-for="rm in availableRoomParents"
                                                                :key="rm.temp_id"
                                                                :value="rm.temp_id"
                                                            >
                                                                {{ rm.name }} ({{ rm.code }})
                                                            </option>
                                                        </select>
                                                    </div>
                                                    <span v-else class="text-[11px] text-[#8EA096]">
                                                        وحدة رئيسية مستقلة
                                                    </span>
                                                </td>

                                                <!-- Rentable Status -->
                                                <td class="p-2.5 text-center">
                                                    <span
                                                        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold"
                                                        :class="unit.is_rentable ? 'bg-[#DCEDE3] text-[#18705A]' : 'bg-[#EAEFEA] text-[#65766F]'"
                                                    >
                                                        <CheckCircle2 v-if="unit.is_rentable" class="size-3" />
                                                        <span>{{ unit.is_rentable ? 'قابلة للتأجير' : 'وحدة أب غير قابلة للتأجير' }}</span>
                                                    </span>
                                                </td>

                                                <!-- Action: Remove -->
                                                <td class="p-2.5 text-center">
                                                    <button
                                                        type="button"
                                                        @click="removeUnit(index)"
                                                        class="grid size-7 place-items-center rounded-lg text-rose-500 transition hover:bg-rose-50 hover:text-rose-700"
                                                    >
                                                        <Trash2 class="size-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div v-else class="rounded-2xl border border-dashed border-[#D8E4DC] bg-[#FAFDFB] p-8 text-center">
                            <span class="grid size-12 place-items-center rounded-2xl bg-[#EAEFEA] text-[#71857C] mx-auto">
                                <DoorOpen class="size-6" />
                            </span>
                            <h3 class="mt-3 text-sm font-bold text-[#18372E]">تم تخطي التوليد التلقائي للوحدات</h3>
                            <p class="mt-1 text-xs text-[#71857C]">
                                يمكنك إنشاء العقار فارغاً ثم إضافة الوحدات والشقق وتقسيمها يدوياً لاحقاً في أي وقت.
                            </p>
                        </div>
                    </div>

                    <!-- ======================================================== -->
                    <!-- STEP 5: المراجعة النهائية والاعتماد                       -->
                    <!-- ======================================================== -->
                    <div
                        v-show="currentStep === 5"
                        class="space-y-6 rounded-3xl border border-[#E3EAE6] bg-white p-6 shadow-sm sm:p-8"
                    >
                        <div class="border-b border-[#EAEFEA] pb-5">
                            <span class="text-xs font-bold text-[#18705A]">الخطوة الخامسة والأخيرة</span>
                            <h2 class="mt-1 text-xl font-black text-[#18372E]">المراجعة الشاملة وتأكيد إنشاء العقار</h2>
                            <p class="mt-1 text-sm text-[#65766F]">
                                تحقق من ملخص بيانات العقار والوحدات قبل الحفظ والبدء في إدارة التأجير.
                            </p>
                        </div>

                        <!-- Summary Cards Grid -->
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                            <!-- Card 1: Property Identity -->
                            <div class="rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] p-5">
                                <span class="text-xs font-extrabold text-[#18705A]">الهوية والنوع</span>
                                <h3 class="mt-2 text-lg font-black text-[#18372E]">{{ form.name || 'بدون اسم' }}</h3>
                                <p class="mt-1 text-xs text-[#65766F]">كود العقار: {{ form.code || 'غير محدد' }}</p>

                                <div class="mt-4 flex items-center gap-2 rounded-xl bg-white p-2.5 border border-[#E3EAE6]">
                                    <span class="grid size-8 place-items-center rounded-lg bg-[#DCEDE3] text-[#18705A]">
                                        <Store v-if="form.property_type === 'shop' || form.property_type === 'commercial'" class="size-4" />
                                        <Home v-else-if="form.property_type === 'apartment'" class="size-4" />
                                        <Building2 v-else class="size-4" />
                                    </span>
                                    <div>
                                        <span class="block text-xs font-bold text-[#18372E]">{{ selectedTypeDetails.label }}</span>
                                        <span class="text-[10px] text-[#71857C]">{{ selectedTypeDetails.badge }}</span>
                                    </div>
                                </div>

                                <div v-if="form.property_type === 'shop' && form.commercial_activity" class="mt-3 text-xs text-[#18705A] font-semibold">
                                    النشاط: {{ form.commercial_activity }}
                                </div>
                            </div>

                            <!-- Card 2: Location -->
                            <div class="rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] p-5">
                                <span class="text-xs font-extrabold text-[#18705A]">العنوان والموقع</span>
                                <h3 class="mt-2 text-sm font-bold text-[#18372E]">
                                    {{ form.city }} {{ form.district ? ` - ${form.district}` : '' }}
                                </h3>
                                <p class="mt-1 text-xs text-[#65766F]">
                                    {{ form.street || 'الشارع غير محدد' }} {{ form.building_number ? ` (${form.building_number})` : '' }}
                                </p>
                                <p v-if="form.property_type === 'apartment' && form.floor_number" class="mt-1 text-xs font-bold text-[#18705A]">
                                    {{ form.floor_number }}
                                </p>

                                <div v-if="form.google_maps_url" class="mt-4">
                                    <a
                                        :href="form.google_maps_url"
                                        target="_blank"
                                        class="inline-flex items-center gap-1.5 text-xs font-bold text-[#18705A] hover:underline"
                                    >
                                        <MapPin class="size-3.5" />
                                        <span>عرض الموقع على Google Maps</span>
                                    </a>
                                </div>
                            </div>

                            <!-- Card 3: Units Summary -->
                            <div class="rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] p-5">
                                <span class="text-xs font-extrabold text-[#18705A]">إجمالي الوحدات للتأجير</span>
                                <div class="mt-2 flex items-baseline gap-2">
                                    <span class="text-3xl font-black text-[#18705A]">{{ totalBillableUnitsCount }}</span>
                                    <span class="text-xs font-bold text-[#65766F]">وحدة قابلة للفوترة</span>
                                </div>

                                <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                                    <div v-if="totalApartmentsCount > 0" class="rounded-xl bg-white p-2 border border-[#E3EAE6]">
                                        <span class="text-[#71857C]">شقق:</span>
                                        <span class="font-black text-[#18372E] mr-1">{{ totalApartmentsCount }}</span>
                                    </div>
                                    <div v-if="totalShopsCount > 0" class="rounded-xl bg-white p-2 border border-[#E3EAE6]">
                                        <span class="text-[#71857C]">محلات:</span>
                                        <span class="font-black text-[#18372E] mr-1">{{ totalShopsCount }}</span>
                                    </div>
                                    <div v-if="totalRoomsCount > 0" class="rounded-xl bg-white p-2 border border-[#E3EAE6]">
                                        <span class="text-[#71857C]">غرف:</span>
                                        <span class="font-black text-[#18372E] mr-1">{{ totalRoomsCount }}</span>
                                    </div>
                                    <div v-if="totalBedsCount > 0" class="rounded-xl bg-white p-2 border border-[#E3EAE6]">
                                        <span class="text-[#71857C]">أسرّة:</span>
                                        <span class="font-black text-[#18372E] mr-1">{{ totalBedsCount }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Amenities Chips -->
                        <div v-if="form.amenities.length > 0" class="space-y-2">
                            <span class="text-xs font-extrabold text-[#18372E]">المرافق والخدمات المشمولة:</span>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="amenityId in form.amenities"
                                    :key="amenityId"
                                    class="inline-flex items-center gap-1.5 rounded-xl bg-[#EFF7F2] px-3 py-1.5 text-xs font-bold text-[#18705A] border border-[#DCEDE3]"
                                >
                                    <Check class="size-3.5" />
                                    <span>{{ amenities_list.find((a) => a.id === amenityId)?.label || amenityId }}</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Action Controls -->
                    <div class="flex flex-col-reverse items-center justify-between gap-4 border-t border-[#E3EAE6] pt-6 sm:flex-row">
                        <div>
                            <button
                                v-if="currentStep > 1"
                                type="button"
                                @click="goToStep(currentStep - 1)"
                                class="inline-flex h-12 items-center gap-2 rounded-2xl border border-[#D8E4DC] bg-white px-6 text-sm font-bold text-[#18372E] transition hover:bg-[#F3F7F5]"
                            >
                                <ArrowRight class="size-4" />
                                <span>الخطوة السابقة</span>
                            </button>
                        </div>

                        <div class="flex w-full items-center justify-end gap-3 sm:w-auto">
                            <Link
                                :href="route('properties.index')"
                                class="inline-flex h-12 items-center justify-center rounded-2xl px-5 text-sm font-bold text-[#71857C] transition hover:text-[#18372E]"
                            >
                                إلغاء
                            </Link>

                            <button
                                v-if="currentStep < steps.length"
                                type="button"
                                @click="goToStep(currentStep + 1)"
                                class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-[#18705A] px-8 text-sm font-bold text-white shadow-md shadow-[#18705A]/20 transition hover:bg-[#105742] sm:w-auto"
                            >
                                <span>المتابعة للخطوة التالية</span>
                                <ArrowLeft class="size-4" />
                            </button>

                            <button
                                v-else
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-[#18705A] px-10 text-sm font-black text-white shadow-lg shadow-[#18705A]/30 transition hover:bg-[#105742] disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                            >
                                <Check class="size-5" :stroke-width="2.5" />
                                <span>{{ form.processing ? 'جاري حفظ وتجهيز العقار...' : 'تأكيد وحفظ العقار والوحدات' }}</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
