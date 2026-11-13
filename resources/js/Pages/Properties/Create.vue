<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch, onUnmounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useLocale } from '@/composables/useLocale';
import {
    ArrowLeft,
    ArrowRight,
    ArrowUpDown,
    Bed,
    BellRing,
    Boxes,
    Building2,
    Car,
    Check,
    CheckCircle2,
    ChevronLeft,
    DoorOpen,
    Droplets,
    FileText,
    Flame,
    Home,
    Info,
    MapPin,
    PhoneCall,
    Plus,
    Receipt,
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

const { t, isRtl } = useLocale();

function localizedText(arabic: string, english: string): string {
    return isRtl.value ? arabic : english;
}

// Steps definition
const steps = computed(() => [
    { number: 1, title: t('property_wizard.step_identity'), subtitle: t('property_wizard.step_identity_desc') },
    { number: 2, title: t('property_wizard.step_location'), subtitle: t('property_wizard.step_location_desc') },
    { number: 3, title: t('property_wizard.step_specs'), subtitle: t('property_wizard.step_specs_desc') },
    { number: 4, title: t('property_wizard.step_units'), subtitle: t('property_wizard.step_units_desc') },
    { number: 5, title: t('property_wizard.step_review'), subtitle: t('property_wizard.step_review_desc') },
]);

const currentStep = ref(1);

// Form state
const form = useForm({
    name: '',
    code: '',
    property_type: 'building',
    address: '',
    city: isRtl.value ? 'القاهرة' : 'Cairo',
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
    amenities: [] as string[],
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
    if (target.files?.[0]) {
        if (imagePreviewUrl.value) {
            URL.revokeObjectURL(imagePreviewUrl.value);
            imagePreviewUrl.value = null;
        }
        const file = target.files[0];
        form.cover_image_file = file;
        imagePreviewUrl.value = URL.createObjectURL(file);
    }
}

function removeImage() {
    if (imagePreviewUrl.value) {
        URL.revokeObjectURL(imagePreviewUrl.value);
    }
    form.cover_image_file = null;
    imagePreviewUrl.value = null;
}

onUnmounted(() => {
    if (imagePreviewUrl.value) {
        URL.revokeObjectURL(imagePreviewUrl.value);
    }
});

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
const floorNames = computed(() => [
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
].map((floorName, index) => isRtl.value ? floorName : `${t('property_wizard.floor')} ${index + 1}`));

const propertyTypeTranslations: Record<string, { label: string; badge: string; description: string }> = {
    building: {
        label: 'properties.type_building',
        badge: 'property_wizard.building_badge',
        description: 'property_wizard.building_description',
    },
    apartment: {
        label: 'properties.type_apartment',
        badge: 'property_wizard.apartment_badge',
        description: 'property_wizard.apartment_description',
    },
    shop: {
        label: 'property_wizard.shop',
        badge: 'property_wizard.shop_badge',
        description: 'property_wizard.shop_description',
    },
    villa: {
        label: 'properties.type_villa',
        badge: 'property_wizard.villa_badge',
        description: 'property_wizard.villa_description',
    },
    other: {
        label: 'property_wizard.other',
        badge: 'property_wizard.other_badge',
        description: 'property_wizard.other_description',
    },
};

const localizedPropertyTypes = computed(() => availablePropertyTypes.value.map((type) => {
    const translations = propertyTypeTranslations[type.value];
    if (isRtl.value || !translations) {
        return type;
    }

    return {
        ...type,
        label: t(translations.label),
        badge: t(translations.badge),
        description: t(translations.description),
    };
}));

const localizedAmenities = computed(() => props.amenities_list.map((amenity) => ({
    ...amenity,
    label: isRtl.value ? amenity.label : t(`properties.amenity_${amenity.id}`),
})));

const localizedFinishingTypes = computed(() => props.finishing_types.map((finishingType) => ({
    ...finishingType,
    label: isRtl.value ? finishingType.label : t(`properties.finishing_${finishingType.value}`),
})));

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
    const name = form.name.trim() || t('property_wizard.shop');

    form.units = [
        {
            id: 'shop-1',
            temp_id: 'shop-1',
            parent_temp_id: null,
            name: name,
            code: code,
            unit_type: 'shop',
            is_rentable: true,
            floor_name: localizedText('أرضي / مستقل', 'Ground floor / standalone'),
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
    const baseName = form.name.trim() || t('properties.type_apartment');
    const floorLabel = form.floor_number
        ? `${t('property_wizard.floor')} ${form.floor_number}`
        : t('properties.type_apartment');

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
            is_rentable: true,
            floor_name: isRtl.value ? `${floorLabel} (رئيسية)` : `${floorLabel} (Main)`,
        });

        for (let r = 1; r <= aptRoomsCount.value; r++) {
            const roomTempId = `room-${r}`;
            const roomCode = `${baseCode}-R${r}`;
            const roomName = `${localizedText('غرفة', 'Room')} ${r}`;

            list.push({
                id: roomTempId,
                temp_id: roomTempId,
                parent_temp_id: parentTempId,
                name: roomName,
                code: roomCode,
                unit_type: 'room',
                is_rentable: true,
                floor_name: isRtl.value ? 'غرفة بالشقة' : 'Apartment room',
            });

            if (aptRentalMode.value === 'beds') {
                for (let b = 1; b <= aptBedsPerRoom.value; b++) {
                    const bedTempId = `bed-${r}-${b}`;
                    const bedCode = `${roomCode}-B${b}`;
                    const bedName = isRtl.value
                        ? `سرير ${b} (غرفة ${r})`
                        : `Bed ${b} (Room ${r})`;

                    list.push({
                        id: bedTempId,
                        temp_id: bedTempId,
                        parent_temp_id: roomTempId,
                        name: bedName,
                        code: bedCode,
                        unit_type: 'bed',
                        is_rentable: true,
                        floor_name: isRtl.value ? `سرير بغرفة ${r}` : `Bed in Room ${r}`,
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
                name: `${t('property_wizard.shop')} ${s}`,
                code: `SHP-0${s}`,
                unit_type: 'shop',
                is_rentable: true,
                floor_name: isRtl.value ? 'الدور الأرضي' : 'Ground Floor',
            });
        }
    }

    let overallUnitIndex = 1;

    for (let f = 1; f <= genFloorsCount.value; f++) {
        const floorLabel = floorNames.value[f - 1] || `${t('property_wizard.floor')} ${f}`;

        for (let u = 1; u <= genUnitsPerFloor.value; u++) {
            let unitCode = '';
            let unitName = '';

            if (genNamingPattern.value === 'floor_number') {
                const codeNum = `${f}0${u}`;
                unitCode = `APT-${codeNum}`;
                unitName = `${t('properties.type_apartment')} ${codeNum}`;
            } else {
                unitCode = `APT-${overallUnitIndex}`;
                unitName = `${t('properties.type_apartment')} ${overallUnitIndex}`;
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
            name: `${localizedText('وحدة تجارية / مكتب', 'Commercial Unit / Office')} ${c}`,
            code: `COM-${codeNum}`,
            unit_type: 'shop',
            is_rentable: true,
            floor_name: isRtl.value ? 'تجاري' : 'Commercial',
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
    const baseName = form.name.trim() || t('properties.type_villa');

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
                floor_name: isRtl.value ? 'الفيلا بالكامل' : 'Entire Villa',
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
        is_rentable: true,
        floor_name: isRtl.value ? 'المبنى الرئيسي للفيلا' : 'Main Villa Building',
    });

    for (let r = 1; r <= villaRoomsCount.value; r++) {
        const roomTempId = `room-${r}`;
        list.push({
            id: roomTempId,
            temp_id: roomTempId,
            parent_temp_id: parentTempId,
            name: isRtl.value ? `جناح / غرفة ${r}` : `Suite / Room ${r}`,
            code: `${baseCode}-R${r}`,
            unit_type: 'room',
            is_rentable: true,
            floor_name: isRtl.value ? 'جناح مستقل' : 'Private Suite',
        });
    }

    form.units = list;
}

/**
 * Fallback unit generator for standalone/other.
 */
function generateUnitsForDefault() {
    const baseCode = form.code.trim() || 'U-01';
    const baseName = form.name.trim() || t('property_wizard.other');
    form.units = [
        {
            id: 'unit-1',
            temp_id: 'unit-1',
            parent_temp_id: null,
            name: baseName,
            code: baseCode,
            unit_type: 'apartment',
            is_rentable: true,
            floor_name: isRtl.value ? 'رئيسي' : 'Main',
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
        name: `${t('property_wizard.new_unit')} ${nextIdx}`,
        code: `U-${nextIdx}`,
        unit_type: defaultType,
        is_rentable: defaultRentable,
        floor_name: isRtl.value ? 'مخصص' : 'Custom',
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
const totalBillableUnitsCount = computed(() => form.units.filter((u) => u.is_rentable).length);
const totalApartmentsCount = computed(() => form.units.filter((u) => u.unit_type === 'apartment').length);
const totalShopsCount = computed(() => form.units.filter((u) => u.unit_type === 'shop').length);
const totalRoomsCount = computed(() => form.units.filter((u) => u.unit_type === 'room').length);
const totalBedsCount = computed(() => form.units.filter((u) => u.unit_type === 'bed').length);

const availablePropertyTypes = computed(() => {
    return props.property_types.filter(
        (t) => t.value !== 'standalone' && t.value !== 'commercial'
    );
});

const selectedTypeDetails = computed(() => {
    return localizedPropertyTypes.value.find((type) => type.value === form.property_type) || localizedPropertyTypes.value[0] || props.property_types[0];
});

// Step navigation validation
function goToStep(step: number) {
    if (step > currentStep.value) {
        if (currentStep.value === 1) {
            if (!form.name.trim() || form.name.trim().length < 3) {
                form.setError('name', t('property_wizard.property_name_min_error'));
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
    <AppLayout :title="$t('properties.add_property')">
        <Head :title="$t('properties.add_property')" />

        <div class="-mx-4 -my-6 min-h-[calc(100dvh-5rem)] bg-[#F8F4EA] px-4 pb-10 pt-6 sm:-mx-8 sm:-my-8 sm:px-8 sm:pb-12 sm:pt-8" >
            <div class="mx-auto max-w-6xl">
                <!-- Header & Breadcrumbs -->
                <nav class="mb-6 flex items-center gap-2 text-xs font-semibold text-[#65766F]">
                    <Link :href="route('properties.index')" class="transition hover:text-[#18705A]">
                        {{ $t('properties.breadcrumb') }}
                    </Link>
                    <ChevronLeft class="size-3.5 text-[#A5B5AD]" />
                    <span class="text-[#18372E]">{{ $t('properties.add_property') }}</span>
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
                                <h1 class="text-2xl font-black tracking-tight text-[#163E3E] sm:text-3xl">{{ $t('properties.add_property') }}</h1>
                                <p class="mt-1 max-w-2xl text-sm leading-6 text-[#5B6770]">
                                    {{ $t('property_wizard.property_type_help') }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <Link
                        :href="route('properties.index')"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-[#D6E0E7] bg-white px-4 py-2.5 text-xs font-bold text-[#163E3E] shadow-2xs transition hover:border-[#163E3E] hover:text-[#163E3E]"
                    >
                        <ArrowRight class="size-4" />
                        {{ $t('properties.back_to_properties') }}
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
                            :aria-label="$t('property_wizard.step_counter', { current: step.number, total: steps.length }) + ': ' + step.title"
                            class="group relative flex flex-col items-center rounded-xl p-1.5 text-center transition sm:items-start sm:rounded-2xl sm:p-3 sm:text-start"
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
                        <p class="text-[11px] font-semibold text-[#5B6770]">
                            {{ $t('property_wizard.step_counter', { current: currentStep, total: steps.length }) }}
                        </p>
                        <p class="mt-1 text-sm font-extrabold text-[#18372E]">{{ steps[currentStep - 1].title }}</p>
                    </div>

                    <!-- Progress Bar Line -->
                    <div
                        class="mt-4 h-1.5 w-full overflow-hidden rounded-full bg-[#E8EFF4]"
                        role="progressbar"
                        :aria-valuenow="currentStep"
                        :aria-valuemin="1"
                        :aria-valuemax="steps.length"
                        :aria-label="$t('property_wizard.step_counter', { current: currentStep, total: steps.length })"
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
                            <span class="text-xs font-bold text-[#18705A]">{{ $t('property_wizard.step_first') }}</span>
                            <h2 class="mt-1 text-xl font-black text-[#18372E]">{{ $t('property_wizard.property_identity') }}</h2>
                            <p class="mt-1 text-sm text-[#65766F]">
                                {{ $t('property_wizard.property_type_help') }}
                            </p>
                        </div>

                        <!-- Property Types Selection Cards -->
                        <div class="space-y-3">
                            <label class="block text-sm font-bold text-[#18372E]">
                                {{ $t('property_wizard.property_type') }} <span class="text-rose-500">*</span>
                            </label>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <button
                                    v-for="type in localizedPropertyTypes"
                                    :key="type.value"
                                    type="button"
                                    @click="form.property_type = type.value"
                                    class="relative flex flex-col justify-between rounded-2xl border p-4 text-start transition"
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
                                        <span>{{ $t('property_wizard.current_selection') }}</span>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Basic Names & Code -->
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <div>
                                <label for="prop-name" class="mb-2 block text-sm font-bold text-[#18372E]">
                                    {{ $t('property_wizard.property_name') }} <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    id="prop-name"
                                    v-model="form.name"
                                    type="text"
                                    required
                                    minlength="3"
                                    :placeholder="
                                        form.property_type === 'shop'
                                            ? $t('property_wizard.property_name_placeholder_shop')
                                            : form.property_type === 'apartment'
                                            ? $t('property_wizard.property_name_placeholder_apartment')
                                            : $t('property_wizard.property_name_placeholder_building')
                                    "
                                    class="h-12 w-full rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] px-4 text-sm text-[#18372E] outline-none transition placeholder:text-[#99A8A0] focus:border-[#18705A] focus:bg-white focus:ring-2 focus:ring-[#18705A]/15"
                                />
                                <p v-if="form.errors.name" class="mt-1.5 text-xs font-bold text-rose-500">
                                    {{ form.errors.name }}
                                </p>
                            </div>

                            <div>
                                <label for="prop-code" class="mb-2 block text-sm font-bold text-[#18372E]">
                                    {{ $t('property_wizard.property_code') }}
                                    <span class="text-xs font-normal text-[#7A8C83]">({{ $t('property_wizard.optional') }})</span>
                                </label>
                                <input
                                    id="prop-code"
                                    v-model="form.code"
                                    type="text"
                                    :placeholder="
                                        form.property_type === 'shop'
                                            ? $t('property_wizard.property_code_placeholder_shop')
                                            : form.property_type === 'apartment'
                                            ? $t('property_wizard.property_code_placeholder_apartment')
                                            : $t('property_wizard.property_code_placeholder_building')
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
                                {{ $t('property_wizard.upload_cover_image') }}
                                <span class="text-xs font-normal text-[#7A8C83]">({{ $t('property_wizard.optional') }})</span>
                            </label>

                            <div
                                v-if="imagePreviewUrl"
                                class="relative h-56 w-full overflow-hidden rounded-3xl border border-[#D8E4DC] bg-[#10251D]"
                            >
                                <img :src="imagePreviewUrl" :alt="$t('property_wizard.preview_property')" class="h-full w-full object-cover" />
                                <button
                                    type="button"
                                    @click="removeImage"
                                    class="absolute left-4 top-4 flex items-center gap-1.5 rounded-xl bg-black/70 px-3 py-1.5 text-xs font-bold text-white backdrop-blur-md transition hover:bg-rose-600"
                                >
                                    <Trash2 class="size-4" />
                                    {{ $t('property_wizard.remove_image') }}
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
                                    {{ $t('property_wizard.upload_image_prompt') }}
                                </span>
                                <span class="mt-1 text-xs text-[#7A8C83]">{{ $t('property_wizard.upload_image_formats') }}</span>
                                <input
                                    type="file"
                                    :aria-label="$t('property_wizard.upload_cover_image')"
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
                            <span class="text-xs font-bold text-[#18705A]">{{ localizedText('الخطوة الثانية', 'Step 2') }}</span>
                            <h2 class="mt-1 text-xl font-black text-[#18372E]">{{ localizedText('الموقع الجغرافي والعنوان', 'Location and address') }}</h2>
                            <p class="mt-1 text-sm text-[#65766F]">
                                {{ localizedText('حدد عنوان العقار وموقعه بدقة لتسهيل وصول المستأجرين والفنيين.', 'Enter the property address and precise location to help renters and technicians find it.') }}
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <div>
                                <label for="prop-city" class="mb-2 block text-sm font-bold text-[#18372E]">{{ localizedText('المدينة / المحافظة', 'City / Governorate') }}</label>
                                <input
                                    id="prop-city"
                                    v-model="form.city"
                                    type="text"
                                    :placeholder="localizedText('مثال: القاهرة، الجيزة، الإسكندرية...', 'e.g. Cairo, Giza, Alexandria...')"
                                    class="h-12 w-full rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] px-4 text-sm text-[#18372E] outline-none transition placeholder:text-[#99A8A0] focus:border-[#18705A] focus:bg-white focus:ring-2 focus:ring-[#18705A]/15"
                                />
                            </div>

                            <div>
                                <label for="prop-district" class="mb-2 block text-sm font-bold text-[#18372E]">{{ localizedText('الحي / المنطقة', 'District / Area') }}</label>
                                <input
                                    id="prop-district"
                                    v-model="form.district"
                                    type="text"
                                    :placeholder="localizedText('مثال: المعادي، الدقي، التجمع الخامس...', 'e.g. Maadi, Dokki, Fifth Settlement...')"
                                    class="h-12 w-full rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] px-4 text-sm text-[#18372E] outline-none transition placeholder:text-[#99A8A0] focus:border-[#18705A] focus:bg-white focus:ring-2 focus:ring-[#18705A]/15"
                                />
                            </div>

                            <div>
                                <label for="prop-street" class="mb-2 block text-sm font-bold text-[#18372E]">{{ localizedText('اسم الشارع', 'Street name') }}</label>
                                <input
                                    id="prop-street"
                                    v-model="form.street"
                                    type="text"
                                    :placeholder="localizedText('مثال: شارع النصر، متفرع من اللاسلكي', 'e.g. El Nasr Street, off El Laselky Street')"
                                    class="h-12 w-full rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] px-4 text-sm text-[#18372E] outline-none transition placeholder:text-[#99A8A0] focus:border-[#18705A] focus:bg-white focus:ring-2 focus:ring-[#18705A]/15"
                                />
                            </div>

                            <!-- Contextual Building / Location field -->
                            <div>
                                <label for="prop-bld-num" class="mb-2 block text-sm font-bold text-[#18372E]">
                                    <template v-if="form.property_type === 'shop'">{{ localizedText('رقم المحل أو المبنى التجاري', 'Shop or commercial building number') }}</template>
                                    <template v-else-if="form.property_type === 'apartment'">{{ localizedText('رقم العقار أو اسم البرج التابع له', 'Property number or building name') }}</template>
                                    <template v-else>{{ localizedText('رقم المبنى / القطعة', 'Building / Lot number') }}</template>
                                </label>
                                <input
                                    id="prop-bld-num"
                                    v-model="form.building_number"
                                    type="text"
                                    :placeholder="
                                        form.property_type === 'shop'
                                            ? localizedText('مثال: محل رقم 4، مول الصفا', 'e.g. Shop 4, Al Safa Mall')
                                            : form.property_type === 'apartment'
                                            ? localizedText('مثال: برج الياسمين 2، عمارة 15', 'e.g. Yasmin Tower 2, Building 15')
                                            : localizedText('مثال: عمارة رقم 15، قطعة 4', 'e.g. Building 15, Lot 4')
                                    "
                                    class="h-12 w-full rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] px-4 text-sm text-[#18372E] outline-none transition placeholder:text-[#99A8A0] focus:border-[#18705A] focus:bg-white focus:ring-2 focus:ring-[#18705A]/15"
                                />
                            </div>

                            <!-- Apartment Specific: Floor Number -->
                            <div v-if="form.property_type === 'apartment'">
                                <label for="prop-floor-num" class="mb-2 block text-sm font-bold text-[#18372E]">
                                    {{ localizedText('رقم الدور / الطابق', 'Floor number') }} <span class="text-xs font-normal text-[#7A8C83]">({{ localizedText('خاص بالشقة', 'for this apartment') }})</span>
                                </label>
                                <input
                                    id="prop-floor-num"
                                    v-model="form.floor_number"
                                    type="text"
                                    :placeholder="localizedText('مثال: الدور الثالث، الدور الأرضي...', 'e.g. Third floor, ground floor...')"
                                    class="h-12 w-full rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] px-4 text-sm text-[#18372E] outline-none transition placeholder:text-[#99A8A0] focus:border-[#18705A] focus:bg-white focus:ring-2 focus:ring-[#18705A]/15"
                                />
                            </div>
                        </div>

                        <div>
                            <label for="prop-maps-url" class="mb-2 block text-sm font-bold text-[#18372E]">
                                {{ localizedText('رابط موقع العقار على خرائط جوجل (Google Maps)', 'Property location on Google Maps') }}
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
                                {{ localizedText('العنوان الكامل المسجل / العلامة المميزة', 'Full registered address / landmark') }}
                            </label>
                            <textarea
                                id="prop-address-full"
                                v-model="form.address"
                                rows="2"
                                :placeholder="localizedText('مثال: بجوار مسجد الفاروق، أمام بنك مصر...', 'e.g. Near Al Farouq Mosque, opposite Banque Misr...')"
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
                            <span class="text-xs font-bold text-[#18705A]">{{ localizedText('الخطوة الثالثة', 'Step 3') }}</span>
                            <h2 class="mt-1 text-xl font-black text-[#18372E]">{{ localizedText('المواصفات الإنشائية والمرافق والخدمات', 'Specifications, amenities, and utilities') }}</h2>
                            <p class="mt-1 text-sm text-[#65766F]">
                                {{ localizedText('حقول مخصصة تناسب نوع العقار المختار:', 'Fields tailored to the selected property type:') }}
                                <span class="font-bold text-[#18705A]">{{ selectedTypeDetails.label }}</span>
                            </p>
                        </div>

                        <!-- CASE A: محل تجاري (Shop) Specific Specs -->
                        <div v-if="form.property_type === 'shop'" class="space-y-6">
                            <div class="rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] p-5">
                                <h3 class="flex items-center gap-2 text-sm font-extrabold text-[#18705A]">
                                    <Store class="size-4" />
                                    <span>{{ localizedText('مواصفات المحل التجاري', 'Commercial unit specifications') }}</span>
                                </h3>

                                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">{{ localizedText('المساحة بالمتر المربع (م²)', 'Area (m²)') }}</label>
                                        <input
                                            v-model="form.total_area"
                                            type="number"
                                            :placeholder="localizedText('مثال: 65', 'e.g. 65')"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-white px-3 text-sm font-bold text-[#18372E] focus:border-[#18705A]"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">{{ localizedText('النشاط التجاري الحالي أو المرخص', 'Current or licensed commercial activity') }}</label>
                                        <input
                                            v-model="form.commercial_activity"
                                            type="text"
                                            :placeholder="localizedText('مثال: مطعم / كافيه، صيدلية، سوبر ماركت، ملابس...', 'e.g. Restaurant / cafe, pharmacy, supermarket, clothing...')"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-white px-3 text-sm text-[#18372E] focus:border-[#18705A]"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">{{ localizedText('مستوى التشطيب التجاري', 'Commercial finishing level') }}</label>
                                        <select
                                            v-model="form.finishing_type"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-white px-3 text-xs font-bold text-[#18372E] focus:border-[#18705A]"
                                        >
                                            <option                                             v-for="ft in localizedFinishingTypes" :key="ft.value" :value="ft.value">
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
                                    <span>{{ localizedText('عدادات واشتراكات المحل التجاري', 'Commercial unit meters and utilities') }}</span>
                                </h3>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">{{ localizedText('رقم عداد الكهرباء التجاري', 'Commercial electricity meter number') }}</label>
                                        <input
                                            v-model="form.electricity_meter_number"
                                            type="text"
                                            :placeholder="localizedText('مثال: ELEC-99120', 'e.g. ELEC-99120')"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-xs text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">{{ localizedText('رقم عداد المياه', 'Water meter number') }}</label>
                                        <input
                                            v-model="form.water_meter_number"
                                            type="text"
                                            :placeholder="localizedText('مثال: WTR-33211', 'e.g. WTR-33211')"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-xs text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">{{ localizedText('رقم اشتراك الغاز (إن وجد)', 'Gas subscription number (if available)') }}</label>
                                        <input
                                            v-model="form.gas_meter_number"
                                            type="text"
                                            :placeholder="localizedText('مثال: GAS-11204', 'e.g. GAS-11204')"
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
                                    <span>{{ localizedText('مواصفات الشقة', 'Apartment specifications') }}</span>
                                </h3>

                                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">{{ localizedText('مساحة الشقة (م²)', 'Apartment area (m²)') }}</label>
                                        <input
                                            v-model="form.total_area"
                                            type="number"
                                            :placeholder="localizedText('مثال: 140', 'e.g. 140')"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-white px-3 text-sm font-bold text-[#18372E] focus:border-[#18705A]"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">{{ localizedText('سنة البناء / التجديد', 'Year built / renovated') }}</label>
                                        <input
                                            v-model.number="form.year_built"
                                            type="number"
                                            min="1900"
                                            max="2099"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-white px-3 text-sm text-[#18372E] focus:border-[#18705A]"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">{{ localizedText('مستوى تشطيب الشقة', 'Apartment finishing level') }}</label>
                                        <select
                                            v-model="form.finishing_type"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-white px-3 text-xs font-bold text-[#18372E] focus:border-[#18705A]"
                                        >
                                            <option                                             v-for="ft in localizedFinishingTypes" :key="ft.value" :value="ft.value">
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
                                    <span>{{ localizedText('عدادات واشتراكات الشقة', 'Apartment meters and utilities') }}</span>
                                </h3>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">{{ localizedText('رقم عداد الكهرباء الخاص بالشقة', 'Apartment electricity meter number') }}</label>
                                        <input
                                            v-model="form.electricity_meter_number"
                                            type="text"
                                            :placeholder="localizedText('مثال: 0102938475', 'e.g. 0102938475')"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-xs text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">{{ localizedText('رقم عداد المياه', 'Water meter number') }}</label>
                                        <input
                                            v-model="form.water_meter_number"
                                            type="text"
                                            :placeholder="localizedText('مثال: WTR-99210', 'e.g. WTR-99210')"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-xs text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">{{ localizedText('رقم اشتراك الغاز الطبيعي', 'Natural gas subscription number') }}</label>
                                        <input
                                            v-model="form.gas_meter_number"
                                            type="text"
                                            :placeholder="localizedText('مثال: GAS-33491', 'e.g. GAS-33491')"
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
                                    <span>{{ localizedText('المواصفات الإنشائية للمبنى', 'Building specifications') }}</span>
                                </h3>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">{{ localizedText('عدد الطوابق', 'Number of floors') }}</label>
                                        <input
                                            v-model.number="form.floors_count"
                                            type="number"
                                            min="1"
                                            max="200"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-sm font-bold text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">{{ localizedText('المساحة الإجمالية (م²)', 'Total area (m²)') }}</label>
                                        <input
                                            v-model="form.total_area"
                                            type="number"
                                            :placeholder="localizedText('مثال: 550', 'e.g. 550')"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-sm text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">{{ localizedText('سنة البناء', 'Year built') }}</label>
                                        <input
                                            v-model.number="form.year_built"
                                            type="number"
                                            min="1900"
                                            max="2099"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-sm text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">{{ localizedText('مستوى التشطيب', 'Finishing level') }}</label>
                                        <select
                                            v-model="form.finishing_type"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-xs font-bold text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        >
                                            <option                                             v-for="ft in localizedFinishingTypes" :key="ft.value" :value="ft.value">
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
                                    <span>{{ localizedText('عدادات واشتراكات المبنى الرئيسية', 'Main building meters and utilities') }}</span>
                                </h3>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">{{ localizedText('رقم عداد الكهرباء الرئيسي', 'Main electricity meter number') }}</label>
                                        <input
                                            v-model="form.electricity_meter_number"
                                            type="text"
                                            :placeholder="localizedText('مثال: 0102938475', 'e.g. 0102938475')"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-xs text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">{{ localizedText('رقم عداد المياه الرئيسي', 'Main water meter number') }}</label>
                                        <input
                                            v-model="form.water_meter_number"
                                            type="text"
                                            :placeholder="localizedText('مثال: WTR-99210', 'e.g. WTR-99210')"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-xs text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">{{ localizedText('رقم اشتراك الغاز الطبيعي', 'Natural gas subscription number') }}</label>
                                        <input
                                            v-model="form.gas_meter_number"
                                            type="text"
                                            :placeholder="localizedText('مثال: GAS-33491', 'e.g. GAS-33491')"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-xs text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Building Guard Details (Only for Building / Commercial / Villa) -->
                            <div class="space-y-4">
                                <h3 class="flex items-center gap-2 text-sm font-extrabold text-[#18705A]">
                                    <UserCheck class="size-4" />
                                    <span>{{ localizedText('بيانات الحارس والإشراف على المبنى', 'Property guard and management details') }}</span>
                                </h3>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">{{ localizedText('اسم حارس / مشرف العقار', 'Guard / property manager name') }}</label>
                                        <input
                                            v-model="form.guard_name"
                                            type="text"
                                            :placeholder="localizedText('مثال: عم محمد', 'e.g. John Smith')"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-xs text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        />
                                        <p v-if="form.errors.guard_name" class="mt-1 text-xs font-bold text-rose-500">
                                            {{ form.errors.guard_name }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-bold text-[#2A4137]">{{ localizedText('رقم هاتف الحارس', 'Guard phone number') }}</label>
                                        <input
                                            v-model="form.guard_phone"
                                            type="tel"
                                            placeholder="010XXXXXXXX"
                                            class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-xs text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                        />
                                        <p v-if="form.errors.guard_phone" class="mt-1 text-xs font-bold text-rose-500">
                                            {{ form.errors.guard_phone }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Amenities Checklist (Filtered based on property type) -->
                        <div v-if="form.property_type !== 'shop'" class="space-y-4">
                            <div>
                                <h3 class="flex items-center gap-2 text-sm font-extrabold text-[#18705A]">
                                    <Sparkles class="size-4" />
                                    <span>{{ localizedText('المرافق والخدمات المتوفرة', 'Available amenities and services') }}</span>
                                </h3>
                                <p class="mt-1 text-xs text-[#556960]">
                                    {{ localizedText('اختر المرافق والخدمات المتوفرة في العقار', 'Select the amenities and services available at this property') }}
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <button
                                    v-for="amenity in localizedAmenities"
                                    :key="amenity.id"
                                    type="button"
                                    @click="toggleAmenity(amenity.id)"
                                    class="flex items-center gap-2.5 rounded-2xl border p-3 text-start transition"
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
                                <span>{{ localizedText('سند الملكية والملاحظات الداخلية', 'Ownership documents and internal notes') }}</span>
                            </h3>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="mb-2 block text-xs font-bold text-[#2A4137]">{{ localizedText('رقم صك الملكية أو العقد', 'Title deed or contract number') }}</label>
                                    <input
                                        v-model="form.deed_number"
                                        type="text"
                                        :placeholder="localizedText('مثال: DEED-2024-998', 'e.g. DEED-2024-998')"
                                        class="h-11 w-full rounded-xl border border-[#D8E4DC] bg-[#FAFDFB] px-3 text-xs text-[#18372E] focus:border-[#18705A] focus:bg-white"
                                    />
                                </div>

                                <div>
                                    <label class="mb-2 block text-xs font-bold text-[#2A4137]">{{ localizedText('ملاحظات داخلية حول العقار', 'Internal property notes') }}</label>
                                    <input
                                        v-model="form.notes"
                                        type="text"
                                        :placeholder="localizedText('أي تفاصيل تشغيلية أو ملاحظات خاصة بإدارة العقار...', 'Operational details or notes for managing this property...')"
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
                            <span class="text-xs font-bold text-[#18705A]">{{ localizedText('الخطوة الرابعة', 'Step 4') }}</span>
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="mt-1 text-xl font-black text-[#18372E]">{{ localizedText('توليد وتكوين الوحدات تلقائياً', 'Generate and organize units') }}</h2>
                                    <p class="mt-1 text-sm text-[#65766F]">
                                        {{ localizedText('هيكل الوحدات يتبع طبيعة', 'The unit structure is tailored to the') }}
                                        <span class="font-bold text-[#18705A]">{{ selectedTypeDetails.label }}</span>
                                        {{ localizedText('مباشرة دون تكرار أو علاقات غير صالحة.', 'without duplicates or invalid relationships.') }}
                                    </p>
                                </div>
                                <label class="flex cursor-pointer items-center gap-2 rounded-2xl bg-[#EFF7F2] px-4 py-2 text-xs font-bold text-[#18705A]">
                                    <input
                                        type="checkbox"
                                        v-model="autoGenEnabled"
                                        class="size-4 rounded text-[#18705A] focus:ring-[#18705A]"
                                    />
                                    <span>{{ localizedText('تفعيل التوليد السريع للوحدات', 'Enable quick unit generation') }}</span>
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
                                        <h4 class="text-sm font-black text-[#18372E]">{{ localizedText('توليد المحل التجاري المستقل', 'Generate a standalone commercial unit') }}</h4>
                                        <p class="mt-1 text-xs leading-5 text-[#65766F]">
                                            {{ localizedText('المحل التجاري هو وحدة تأجيرية واحدة مستقلة بذاتها. لا يتم توليد أدوار سكنية أو شقق أو غرف أو أسرة لمحل تجاري.', 'A commercial shop is one standalone rentable unit. Residential floors, apartments, rooms, and beds are not generated for it.') }}
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
                                        <span>{{ localizedText('تحديث بيانات المحل', 'Refresh shop details') }}</span>
                                    </button>
                                </div>
                            </div>

                            <!-- ---------------------------------------------------- -->
                            <!-- WORKFLOW B: شقة (Apartment) Generator Config         -->
                            <!-- ---------------------------------------------------- -->
                            <div v-else-if="form.property_type === 'apartment'" class="space-y-4 rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] p-5">
                                <div>
                                    <h4 class="text-sm font-black text-[#18372E]">{{ localizedText('طريقة تأجير الشقة وهيكلة الوحدات', 'Apartment rental and unit structure') }}</h4>
                                    <p class="mt-1 text-xs text-[#65766F]">
                                        {{ localizedText('حدد كيف ستقوم بتأجير هذه الشقة ليتم بناء شجرة الوحدات وقابليتها للفوترة بدقة:', 'Choose how to rent this apartment to build the unit hierarchy and billing structure:') }}
                                    </p>
                                </div>

                                <!-- 3-Way Mode Selection -->
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    <button
                                        type="button"
                                        @click="aptRentalMode = 'whole'; generateUnitsForApartment()"
                                        class="flex flex-col justify-between rounded-xl border p-3.5 text-start transition"
                                        :class="[
                                            aptRentalMode === 'whole'
                                                ? 'border-[#18705A] bg-[#EFF7F2] ring-1 ring-[#18705A]'
                                                : 'border-[#DDE6E0] bg-white hover:border-[#18705A]/40',
                                        ]"
                                    >
                                        <div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs font-black text-[#18372E]">{{ localizedText('1. تأجير الشقة بالكامل', '1. Rent the entire apartment') }}</span>
                                                <Home class="size-4 text-[#18705A]" />
                                            </div>
                                            <p class="mt-1.5 text-[11px] leading-4 text-[#65766F]">
                                                {{ localizedText('تأجير الشقة كعقد واحد متكامل لمستأجر واحد (النمط التقليدي).', 'Rent the apartment to one renter under a single lease (traditional setup).') }}
                                            </p>
                                        </div>
                                        <span class="mt-3 text-[10px] font-bold text-[#18705A]">{{ localizedText('وحدة واحدة قابلة للتأجير', 'One rentable unit') }}</span>
                                    </button>

                                    <button
                                        type="button"
                                        @click="aptRentalMode = 'rooms'; generateUnitsForApartment()"
                                        class="flex flex-col justify-between rounded-xl border p-3.5 text-start transition"
                                        :class="[
                                            aptRentalMode === 'rooms'
                                                ? 'border-[#18705A] bg-[#EFF7F2] ring-1 ring-[#18705A]'
                                                : 'border-[#DDE6E0] bg-white hover:border-[#18705A]/40',
                                        ]"
                                    >
                                        <div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs font-black text-[#18372E]">{{ localizedText('2. تأجير الغرف المستقلة', '2. Rent individual rooms') }}</span>
                                                <DoorOpen class="size-4 text-[#18705A]" />
                                            </div>
                                            <p class="mt-1.5 text-[11px] leading-4 text-[#65766F]">
                                                {{ localizedText('تأجير كل غرفة داخل الشقة بعقد منفصل (سكن مشترك / مغتربين).', 'Rent each room under a separate lease (shared housing / residents).') }}
                                            </p>
                                        </div>
                                        <span class="mt-3 text-[10px] font-bold text-[#18705A]">{{ localizedText('الغرف فقط قابلة للتأجير', 'Only rooms are rentable') }}</span>
                                    </button>

                                    <button
                                        type="button"
                                        @click="aptRentalMode = 'beds'; generateUnitsForApartment()"
                                        class="flex flex-col justify-between rounded-xl border p-3.5 text-start transition"
                                        :class="[
                                            aptRentalMode === 'beds'
                                                ? 'border-[#18705A] bg-[#EFF7F2] ring-1 ring-[#18705A]'
                                                : 'border-[#DDE6E0] bg-white hover:border-[#18705A]/40',
                                        ]"
                                    >
                                        <div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs font-black text-[#18372E]">{{ localizedText('3. تأجير الأسرّة / تأجير مرن', '3. Rent beds / flexible rental') }}</span>
                                                <Bed class="size-4 text-[#18705A]" />
                                            </div>
                                            <p class="mt-1.5 text-[11px] leading-4 text-[#65766F]">
                                                {{ localizedText('تأجير بالسرير أو بالغرفة بالكامل بمرونة (سكن طلاب / كوادر / شباب وبنات).', 'Rent by the bed or by the entire room (student or shared housing).') }}
                                            </p>
                                        </div>
                                        <span class="mt-3 text-[10px] font-bold text-[#18705A]">{{ localizedText('مرونة كاملة: بالسرير أو بالغرفة', 'Flexible: rent by bed or room') }}</span>
                                    </button>
                                </div>

                                <!-- Dynamic Room and Bed inputs -->
                                <div v-if="aptRentalMode === 'rooms' || aptRentalMode === 'beds'" class="mt-4 border-t border-[#E3EAE6] pt-4">
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                        <div>
                                            <label class="mb-1.5 block text-xs font-bold text-[#18372E]">{{ localizedText('عدد الغرف بالشقة', 'Number of rooms in the apartment') }}</label>
                                            <input
                                                v-model.number="aptRoomsCount"
                                                type="number"
                                                min="1"
                                                max="20"
                                                class="h-10 w-full rounded-xl border border-[#D8E4DC] bg-white px-3 text-xs font-bold text-[#18372E]"
                                            />
                                        </div>

                                        <div v-if="aptRentalMode === 'beds'">
                                            <label class="mb-1.5 block text-xs font-bold text-[#18372E]">{{ localizedText('عدد الأسرّة في كل غرفة', 'Beds per room') }}</label>
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
                                                <span>{{ localizedText('تطبيق وإعادة التوليد', 'Apply and regenerate') }}</span>
                                            </button>
                                        </div>
                                    </div>

                                    <div v-if="aptRentalMode === 'beds'" class="mt-3 p-3 rounded-xl bg-[#EFF7F2] border border-[#D8E4DC] text-[11px] text-[#18705A] flex items-center gap-2">
                                        <Info class="size-4 shrink-0" />
                                        <span>{{ localizedText('مرونة كاملة: يمكنك تأجير بعض الغرف كأسرّة فردية وغرف أخرى كغرفة مستقلة بالكامل (مثلاً غرفتان بالأسرّة وغرفة كغرفة كاملة)، أو حذف الأسرّة من أي غرفة في خطوة المراجعة التالية.', 'Flexible setup: rent some rooms by the bed and others as a whole room (for example, two rooms by bed and one as a whole room), or remove beds from any room in the review step.') }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- ---------------------------------------------------- -->
                            <!-- WORKFLOW C: عمارة (Building) Generator Config         -->
                            <!-- ---------------------------------------------------- -->
                            <div v-else-if="form.property_type === 'building'" class="rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] p-5">
                                <h4 class="mb-3 text-sm font-black text-[#18372E]">{{ localizedText('إعدادات أدوار وشقق ومحلات العمارة', 'Building floors, apartments, and shops') }}</h4>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                                    <div>
                                        <label class="mb-1.5 block text-xs font-bold text-[#18372E]">{{ localizedText('عدد الأدوار السكنية', 'Number of residential floors') }}</label>
                                        <input
                                            v-model.number="genFloorsCount"
                                            type="number"
                                            min="1"
                                            max="50"
                                            class="h-10 w-full rounded-xl border border-[#D8E4DC] bg-white px-3 text-xs font-bold text-[#18372E]"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-1.5 block text-xs font-bold text-[#18372E]">{{ localizedText('شقق في كل دور', 'Apartments per floor') }}</label>
                                        <input
                                            v-model.number="genUnitsPerFloor"
                                            type="number"
                                            min="1"
                                            max="20"
                                            class="h-10 w-full rounded-xl border border-[#D8E4DC] bg-white px-3 text-xs font-bold text-[#18372E]"
                                        />
                                    </div>

                                    <div>
                                        <label class="mb-1.5 block text-xs font-bold text-[#18372E]">{{ localizedText('نمط الترقيم', 'Numbering pattern') }}</label>
                                        <select
                                            v-model="genNamingPattern"
                                            class="h-10 w-full rounded-xl border border-[#D8E4DC] bg-white px-3 text-xs font-bold text-[#18372E]"
                                        >
                                            <option value="floor_number">{{ localizedText('رقم الدور (101, 102, 201...)', 'Floor-based (101, 102, 201...)') }}</option>
                                            <option value="sequential">{{ localizedText('تسلسلي (شقة 1, شقة 2...)', 'Sequential (Apartment 1, Apartment 2...)') }}</option>
                                        </select>
                                    </div>

                                    <div class="flex flex-col justify-end">
                                        <button
                                            type="button"
                                            @click="generateUnitsForBuilding"
                                            class="flex h-10 items-center justify-center gap-1.5 rounded-xl bg-[#18705A] px-4 text-xs font-bold text-white shadow-sm transition hover:bg-[#105742]"
                                        >
                                            <Sparkles class="size-4" />
                                            <span>{{ localizedText('إعادة توليد الوحدات', 'Regenerate units') }}</span>
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
                                        <span>{{ localizedText('تضمين محلات تجارية في الدور الأرضي للعمارة', 'Include shops on the building ground floor') }}</span>
                                    </label>

                                    <div v-if="genIncludeGroundShops" class="flex items-center gap-2">
                                        <span class="text-xs text-[#65766F]">{{ localizedText('عدد المحلات الأرضية:', 'Ground-floor shops:') }}</span>
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
                                <h4 class="mb-3 text-sm font-black text-[#18372E]">{{ localizedText('إعدادات المركز / المجمع التجاري', 'Commercial center / complex settings') }}</h4>
                                <div class="flex items-center gap-4">
                                    <div>
                                        <label class="mb-1.5 block text-xs font-bold text-[#18372E]">{{ localizedText('إجمالي عدد الوحدات والمكاتب التجارية', 'Total commercial units and offices') }}</label>
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
                                            <span>{{ localizedText('إعادة توليد الوحدات', 'Regenerate units') }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div v-else-if="form.property_type === 'villa'" class="rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] p-5">
                                <h4 class="mb-3 text-sm font-black text-[#18372E]">{{ localizedText('إعدادات الفيلا', 'Villa settings') }}</h4>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <label class="mb-1.5 block text-xs font-bold text-[#18372E]">{{ localizedText('نمط التأجير', 'Rental mode') }}</label>
                                        <select
                                            v-model="villaRentalMode"
                                            class="h-10 w-full rounded-xl border border-[#D8E4DC] bg-white px-3 text-xs font-bold text-[#18372E]"
                                        >
                                            <option value="whole">{{ localizedText('الفيلا بالكامل (عقد واحد)', 'Entire villa (one lease)') }}</option>
                                            <option value="rooms">{{ localizedText('مقسمة إلى أجنحة / غرف مستقلة', 'Divided into separate suites / rooms') }}</option>
                                        </select>
                                    </div>

                                    <div v-if="villaRentalMode === 'rooms'">
                                        <label class="mb-1.5 block text-xs font-bold text-[#18372E]">{{ localizedText('عدد الأجنحة / الغرف', 'Number of suites / rooms') }}</label>
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
                                            <span>{{ localizedText('إعادة توليد الوحدات', 'Regenerate units') }}</span>
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
                                            {{ localizedText('معاينة وتعديل الوحدات الناتجة', 'Preview and edit generated units') }}
                                        </h3>
                                        <span class="rounded-full bg-[#E5EEE8] px-2.5 py-0.5 text-xs font-black text-[#18705A]">
                                            {{ form.units.length }} {{ localizedText('وحدة إجمالية', 'total units') }}
                                        </span>
                                        <span class="rounded-full bg-[#EFF7F2] px-2.5 py-0.5 text-xs font-bold text-[#163E3E] border border-[#DCEDE3]">
                                            {{ totalBillableUnitsCount }} {{ localizedText('قابلة للتأجير المباشر', 'directly rentable') }}
                                        </span>
                                    </div>

                                    <button
                                        type="button"
                                        @click="addManualUnit"
                                        class="flex items-center gap-1 text-xs font-bold text-[#18705A] transition hover:underline"
                                    >
                                        <Plus class="size-3.5" />
                                        <span>{{ localizedText('إضافة وحدة إضافية يدوياً', 'Add a unit manually') }}</span>
                                    </button>
                                </div>

                                <div class="max-h-96 overflow-y-auto rounded-2xl border border-[#E3EAE6] bg-[#FAFDFB]">
                                    <table class="w-full text-start text-xs">
                                        <thead class="sticky top-0 bg-[#EFF5F1] text-[#334D41]">
                                            <tr>
                                                <th class="p-3 font-bold">{{ localizedText('اسم الوحدة', 'Unit name') }}</th>
                                                <th class="p-3 font-bold">{{ localizedText('الكود', 'Code') }}</th>
                                                <th class="p-3 font-bold">{{ localizedText('النوع', 'Type') }}</th>
                                                <th class="p-3 font-bold">{{ localizedText('الوحدة الأب (إن وجدت)', 'Parent unit (if applicable)') }}</th>
                                                <th class="p-3 font-bold text-center">{{ localizedText('قابلة للتأجير', 'Rentable') }}</th>
                                                <th class="p-3 font-bold text-center">{{ localizedText('إجراء', 'Action') }}</th>
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
                                                        <span v-else-if="unit.unit_type === 'bed'" class="text-[#163E3E]">↳↳</span>
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
                                                            <option value="shop">{{ localizedText('محل تجاري', 'Commercial shop') }}</option>
                                                        </template>
                                                        <template v-else-if="form.property_type === 'apartment'">
                                                            <option value="apartment">{{ localizedText('شقة رئيسية', 'Main apartment') }}</option>
                                                            <option value="room">{{ localizedText('غرفة داخل الشقة', 'Room in apartment') }}</option>
                                                            <option value="bed">{{ localizedText('سرير فردي', 'Individual bed') }}</option>
                                                        </template>
                                                        <template v-else>
                                                            <option value="apartment">{{ localizedText('شقة سكنية', 'Residential apartment') }}</option>
                                                            <option value="shop">{{ localizedText('محل تجاري', 'Commercial shop') }}</option>
                                                            <option value="room">{{ localizedText('غرفة مستقلة', 'Private room') }}</option>
                                                            <option value="bed">{{ localizedText('سرير', 'Bed') }}</option>
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
                                                            <option :value="null">{{ localizedText('-- اختر الشقة الأب --', '-- Select parent apartment --') }}</option>
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
                                                            <option :value="null">{{ localizedText('-- اختر الغرفة الأب --', '-- Select parent room --') }}</option>
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
                                                        {{ localizedText('وحدة رئيسية مستقلة', 'Standalone top-level unit') }}
                                                    </span>
                                                </td>

                                                <!-- Rentable Status -->
                                                <td class="p-2.5 text-center">
                                                    <span
                                                        v-if="unit.unit_type === 'apartment' && form.units.some(u => u.parent_temp_id === unit.temp_id)"
                                                        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold bg-[#EAEFEA] text-[#65766F]"
                                                        :title="localizedText('شقة رئيسية مقسمة لغرف - يتم تأجير الغرف أو الأسرّة بداخلها', 'Main apartment divided into rooms; rent the rooms or beds inside it')"
                                                    >
                                                        {{ localizedText('شقة أب (غير مؤجرة مباشرة)', 'Parent apartment (not rented directly)') }}
                                                    </span>
                                                    <button
                                                        v-else
                                                        type="button"
                                                        @click="unit.is_rentable = !unit.is_rentable"
                                                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[10px] font-bold cursor-pointer transition"
                                                        :class="unit.is_rentable ? 'bg-[#DCEDE3] text-[#18705A] hover:bg-[#c9e4d3]' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'"
                                                    >
                                                        <CheckCircle2 v-if="unit.is_rentable" class="size-3" />
                                                        <span>{{ unit.is_rentable ? (unit.unit_type === 'room' && form.units.some(u => u.parent_temp_id === unit.temp_id) ? localizedText('قابلة للتأجير (غرفة/أسرّة)', 'Rentable (room/beds)') : localizedText('قابلة للتأجير', 'Rentable')) : localizedText('معطلة', 'Disabled') }}</span>
                                                    </button>
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
                            <h3 class="mt-3 text-sm font-bold text-[#18372E]">{{ localizedText('تم تخطي التوليد التلقائي للوحدات', 'Automatic unit generation was skipped') }}</h3>
                            <p class="mt-1 text-xs text-[#71857C]">
                                {{ localizedText('يمكنك إنشاء العقار فارغاً ثم إضافة الوحدات والشقق وتقسيمها يدوياً لاحقاً في أي وقت.', 'You can create the property without units and add or subdivide units manually later.') }}
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
                            <span class="text-xs font-bold text-[#18705A]">{{ localizedText('الخطوة الخامسة والأخيرة', 'Step 5 — Final step') }}</span>
                            <h2 class="mt-1 text-xl font-black text-[#18372E]">{{ localizedText('المراجعة الشاملة وتأكيد إنشاء العقار', 'Review and confirm property creation') }}</h2>
                            <p class="mt-1 text-sm text-[#65766F]">
                                {{ localizedText('تحقق من ملخص بيانات العقار والوحدات قبل الحفظ والبدء في إدارة التأجير.', 'Review the property and unit summary before saving and managing rentals.') }}
                            </p>
                        </div>

                        <!-- Summary Cards Grid -->
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                            <!-- Card 1: Property Identity -->
                            <div class="rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] p-5">
                                <span class="text-xs font-extrabold text-[#18705A]">{{ localizedText('الهوية والنوع', 'Identity and type') }}</span>
                                <h3 class="mt-2 text-lg font-black text-[#18372E]">{{ form.name || localizedText('بدون اسم', 'Unnamed property') }}</h3>
                                <p class="mt-1 text-xs text-[#65766F]">{{ localizedText('كود العقار:', 'Property code:') }} {{ form.code || localizedText('غير محدد', 'Not specified') }}</p>

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
                                    {{ localizedText('النشاط:', 'Activity:') }} {{ form.commercial_activity }}
                                </div>
                            </div>

                            <!-- Card 2: Location -->
                            <div class="rounded-2xl border border-[#D8E4DC] bg-[#FAFDFB] p-5">
                                <span class="text-xs font-extrabold text-[#18705A]">{{ localizedText('العنوان والموقع', 'Address and location') }}</span>
                                <h3 class="mt-2 text-sm font-bold text-[#18372E]">
                                    {{ form.city }} {{ form.district ? ` - ${form.district}` : '' }}
                                </h3>
                                <p class="mt-1 text-xs text-[#65766F]">
                                    {{ form.street || localizedText('الشارع غير محدد', 'Street not specified') }} {{ form.building_number ? ` (${form.building_number})` : '' }}
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
                                    <span>{{ localizedAmenities.find((amenity) => amenity.id === amenityId)?.label || amenityId }}</span>
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
