<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import {
    Home,
    DoorClosed,
    Bed,
    Store,
    X,
    Check,
    AlertCircle,
    Info,
} from 'lucide-vue-next';

export interface UnitItem {
    id: number;
    property_id?: number;
    parent_unit_id?: number | null;
    parent_unit_name?: string | null;
    code?: string;
    name: string;
    unit_type: 'apartment' | 'room' | 'bed' | 'shop' | 'other';
    type_label: string;
    breadcrumb?: string;
    is_rentable: boolean;
    status: string;
    status_label: string;
    child_units?: any[];
}

const props = defineProps<{
    show: boolean;
    mode: 'create' | 'edit';
    propertyId: number;
    propertyName: string;
    unit?: UnitItem | null;
    presetParentId?: number | null;
    presetUnitType?: 'apartment' | 'room' | 'bed' | 'shop' | null;
    availableUnits: UnitItem[];
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'saved'): void;
}>();

const form = useForm<{
    name: string;
    code: string;
    unit_type: 'apartment' | 'room' | 'bed' | 'shop';
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

// Watch props to populate form
watch(
    () => props.show,
    (isOpen) => {
        if (!isOpen) return;

        form.clearErrors();

        if (props.mode === 'edit' && props.unit) {
            form.name = props.unit.name;
            form.code = props.unit.code || '';
            const t = props.unit.unit_type;
            form.unit_type = (t === 'apartment' || t === 'room' || t === 'bed' || t === 'shop') ? t : 'apartment';
            form.parent_unit_id = props.unit.parent_unit_id ?? null;
            form.is_rentable = props.unit.is_rentable;
        } else {
            // Create mode
            form.name = '';
            form.code = '';
            form.is_rentable = true;

            if (props.presetUnitType) {
                form.unit_type = props.presetUnitType;
            } else {
                form.unit_type = 'apartment';
            }

            if (props.presetParentId !== undefined && props.presetParentId !== null) {
                form.parent_unit_id = props.presetParentId;
            } else {
                form.parent_unit_id = null;
            }
        }
    },
    { immediate: true }
);

// When unit_type changes in create mode without preset
watch(
    () => form.unit_type,
    (newType) => {
        if (props.mode === 'create' && props.presetParentId === undefined) {
            if (newType === 'apartment' || newType === 'shop') {
                form.parent_unit_id = null;
            } else if (newType === 'room') {
                // If current parent is not an apartment, clear or select first apartment
                const validApartments = availableApartments.value;
                if (!validApartments.some(a => a.id === form.parent_unit_id)) {
                    form.parent_unit_id = validApartments[0]?.id ?? null;
                }
            } else if (newType === 'bed') {
                // If current parent is not a room, clear or select first room
                const validRooms = availableRooms.value;
                if (!validRooms.some(r => r.id === form.parent_unit_id)) {
                    form.parent_unit_id = validRooms[0]?.id ?? null;
                }
            }
        }
    }
);

// Filter available parents based on type
const availableApartments = computed(() => {
    return props.availableUnits.filter(u => u.unit_type === 'apartment' && (!props.unit || u.id !== props.unit.id));
});

const availableRooms = computed(() => {
    return props.availableUnits.filter(u => u.unit_type === 'room' && (!props.unit || u.id !== props.unit.id));
});

const presetParentUnit = computed(() => {
    if (!props.presetParentId) return null;
    return props.availableUnits.find(u => u.id === props.presetParentId) || null;
});

const isTypeLocked = computed(() => {
    return props.mode === 'create' && Boolean(props.presetUnitType);
});

const isParentLocked = computed(() => {
    return props.mode === 'create' && props.presetParentId !== undefined && props.presetParentId !== null;
});

const hasChildrenWarning = computed(() => {
    if (props.mode !== 'edit' || !props.unit) return false;
    return props.unit.child_units && props.unit.child_units.length > 0;
});

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('properties.units.store', props.propertyId), {
            onSuccess: () => {
                emit('saved');
                emit('close');
            },
        });
    } else if (props.mode === 'edit' && props.unit) {
        form.put(route('properties.units.update', [props.propertyId, props.unit.id]), {
            onSuccess: () => {
                emit('saved');
                emit('close');
            },
        });
    }
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4" dir="rtl">
        <!-- Backdrop -->
        <div @click="emit('close')" class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs transition-opacity"></div>

        <!-- Modal Box -->
        <div class="relative w-full max-w-lg bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-6 z-10 animate-in fade-in zoom-in-95 my-8">
            <!-- Header -->
            <div class="flex items-center justify-between pb-4 border-b border-[#D6E0E7] mb-5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-[#E8F0F0] text-[#163E3E] flex items-center justify-center shrink-0">
                        <component
                            :is="form.unit_type === 'apartment' ? Home : form.unit_type === 'room' ? DoorClosed : form.unit_type === 'bed' ? Bed : Store"
                            class="w-5 h-5 stroke-[2]"
                        />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-[#163E3E]">
                            {{ mode === 'create' ? 'إضافة وحدة جديدة' : 'تعديل بيانات الوحدة' }}
                        </h3>
                        <p class="text-xs text-[#5B6770]">
                            {{ propertyName }}
                        </p>
                    </div>
                </div>

                <button
                    @click="emit('close')"
                    type="button"
                    class="p-1.5 text-[#5B6770] hover:text-[#163E3E] hover:bg-slate-100 rounded-xl transition cursor-pointer"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="space-y-4">
                <!-- Preset Context Banner -->
                <div
                    v-if="isParentLocked && presetParentUnit"
                    class="p-3.5 rounded-2xl bg-[#E8F0F0]/70 border border-[#163E3E]/30 flex items-start gap-2.5 text-xs text-[#163E3E]"
                >
                    <Info class="w-4 h-4 text-[#163E3E] shrink-0 mt-0.5" />
                    <div class="leading-relaxed">
                        <span class="font-bold">سياق التقسيم الهرمي:</span>
                        يتم إنشاء هذا
                        <span class="font-bold text-[#163E3E]">{{ form.unit_type === 'room' ? 'الأوضة' : 'السرير' }}</span>
                        مباشرة كتابع لـ:
                        <span class="font-bold underline">{{ presetParentUnit.name }}</span>
                        ({{ presetParentUnit.type_label }}).
                    </div>
                </div>

                <!-- Warning if editing unit with existing children -->
                <div
                    v-if="hasChildrenWarning"
                    class="p-3.5 rounded-2xl bg-amber-50 border border-amber-200 flex items-start gap-2.5 text-xs text-amber-900"
                >
                    <AlertCircle class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                    <div class="leading-relaxed">
                        <span class="font-bold">تنبيه بالوحدات التابعة:</span>
                        هذه الوحدة تحتوي على
                        <span class="font-bold">{{ unit?.child_units?.length }}</span>
                        وحدة فرعية تابعة لها. لا يمكن تغيير نوعها إلى نوع لا يقبل هذه الوحدات (لتجنب إفساد الهيكل العقاري).
                    </div>
                </div>

                <!-- General error / subscription error -->
                <div
                    v-if="form.errors.unit_limit"
                    class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold leading-relaxed"
                >
                    ⚠️ {{ form.errors.unit_limit }}
                </div>

                <!-- Unit Name -->
                <div>
                    <label class="block text-xs font-bold text-[#163E3E] mb-1.5">
                        اسم الوحدة *
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        maxlength="255"
                        :placeholder="form.unit_type === 'apartment' ? 'مثال: شقة 101 (الدور الأول)' : form.unit_type === 'room' ? 'مثال: أوضة 1 (غرفة النوم الرئيسية)' : form.unit_type === 'bed' ? 'مثال: سرير أ (بجوار النافذة)' : 'مثال: محل رقم 1 (واجهة رئيسية)'"
                        required
                        class="w-full h-11 px-3.5 rounded-xl border bg-[#F8FAFC] text-sm text-[#163E3E] transition"
                        :class="form.errors.name ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-[#D6E0E7] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E]'"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600 font-medium">
                        {{ form.errors.name }}
                    </p>
                </div>

                <!-- Code & Unit Type Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Unit Code -->
                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1.5">
                            كود الوحدة <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري)</span>
                        </label>
                        <input
                            v-model="form.code"
                            type="text"
                            maxlength="50"
                            placeholder="APT-101 / RM-1"
                            class="w-full h-11 px-3.5 rounded-xl border bg-[#F8FAFC] text-sm text-[#163E3E] font-mono transition"
                            :class="form.errors.code ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-[#D6E0E7] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E]'"
                        />
                        <p v-if="form.errors.code" class="mt-1 text-xs text-rose-600 font-medium">
                            {{ form.errors.code }}
                        </p>
                    </div>

                    <!-- Unit Type Selector -->
                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1.5">
                            نوع المستوى الهرمي *
                        </label>
                        <select
                            v-model="form.unit_type"
                            :disabled="isTypeLocked"
                            class="w-full h-11 px-3.5 rounded-xl border bg-[#F8FAFC] text-sm text-[#163E3E] transition disabled:opacity-75 disabled:cursor-not-allowed"
                            :class="form.errors.unit_type ? 'border-rose-400 bg-rose-50/20' : 'border-[#D6E0E7] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E]'"
                        >
                            <option value="apartment">🏠 شقة (مستوى رئيسي)</option>
                            <option value="room">🚪 أوضة (داخل شقة)</option>
                            <option value="bed">🛏️ سرير (داخل أوضة)</option>
                            <option value="shop">🏪 محل (وحدة مستقلة)</option>
                        </select>
                        <p v-if="form.errors.unit_type" class="mt-1 text-xs text-rose-600 font-medium">
                            {{ form.errors.unit_type }}
                        </p>
                    </div>
                </div>

                <!-- Parent Unit (Context-Aware) -->
                <!-- 1. Apartment or Shop: No parent allowed -->
                <div v-if="form.unit_type === 'apartment' || form.unit_type === 'shop'" class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs text-[#5B6770] flex items-center gap-2">
                    <Info class="w-4 h-4 text-[#163E3E] shrink-0" />
                    <span>
                        {{ form.unit_type === 'apartment' ? 'الشقة وحدة رئيسية بالعقار، ولا يمكن ربطها بوحدة أب.' : 'المحل التجاري وحدة مستقلة بالعقار، لا يقبل وحدة أب أو وحدات تابعة.' }}
                    </span>
                </div>

                <!-- 2. Room: Parent MUST be an Apartment -->
                <div v-else-if="form.unit_type === 'room'">
                    <label class="block text-xs font-bold text-[#163E3E] mb-1.5">
                        الشقة التابع لها (الوحدة الأب) *
                    </label>
                    <select
                        v-model="form.parent_unit_id"
                        :disabled="isParentLocked"
                        required
                        class="w-full h-11 px-3.5 rounded-xl border bg-[#F8FAFC] text-sm text-[#163E3E] transition disabled:opacity-75 disabled:cursor-not-allowed"
                        :class="form.errors.parent_unit_id ? 'border-rose-400 bg-rose-50/20' : 'border-[#D6E0E7] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E]'"
                    >
                        <option :value="null" disabled>-- اختر الشقة التابع لها هذا الأوضة --</option>
                        <option v-for="apt in availableApartments" :key="apt.id" :value="apt.id">
                            🏠 {{ apt.code ? apt.code + ' • ' : '' }}{{ apt.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.parent_unit_id" class="mt-1 text-xs text-rose-600 font-medium">
                        {{ form.errors.parent_unit_id }}
                    </p>
                    <p v-if="availableApartments.length === 0" class="mt-1 text-xs text-amber-700 font-medium">
                        ⚠️ لا توجد شقق مسجلة بهذا العقار حالياً. يجب إضافة شقة أولاً لإضافة غرف بداخلها.
                    </p>
                </div>

                <!-- 3. Bed: Parent MUST be a Room -->
                <div v-else-if="form.unit_type === 'bed'">
                    <label class="block text-xs font-bold text-[#163E3E] mb-1.5">
                        الأوضة التابع لها (الوحدة الأب) *
                    </label>
                    <select
                        v-model="form.parent_unit_id"
                        :disabled="isParentLocked"
                        required
                        class="w-full h-11 px-3.5 rounded-xl border bg-[#F8FAFC] text-sm text-[#163E3E] transition disabled:opacity-75 disabled:cursor-not-allowed"
                        :class="form.errors.parent_unit_id ? 'border-rose-400 bg-rose-50/20' : 'border-[#D6E0E7] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E]'"
                    >
                        <option :value="null" disabled>-- اختر الأوضة التابع لها هذا السرير --</option>
                        <option v-for="rm in availableRooms" :key="rm.id" :value="rm.id">
                            🚪 {{ rm.code ? rm.code + ' • ' : '' }}{{ rm.name }} (تابع لـ: {{ rm.parent_unit_name || 'شقة' }})
                        </option>
                    </select>
                    <p v-if="form.errors.parent_unit_id" class="mt-1 text-xs text-rose-600 font-medium">
                        {{ form.errors.parent_unit_id }}
                    </p>
                    <p v-if="availableRooms.length === 0" class="mt-1 text-xs text-amber-700 font-medium">
                        ⚠️ لا توجد غرف مسجلة بهذا العقار حالياً. يجب إضافة غرفة أولاً لإضافة أسرّة بداخلها.
                    </p>
                </div>

                <!-- Is Rentable Checkbox -->
                <div class="flex items-center gap-2.5 p-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC]">
                    <input
                        id="is_rentable_checkbox"
                        v-model="form.is_rentable"
                        type="checkbox"
                        class="w-4 h-4 rounded text-[#163E3E] focus:ring-[#163E3E] border-[#D6E0E7]"
                    />
                    <label for="is_rentable_checkbox" class="text-xs font-bold text-[#163E3E] cursor-pointer">
                        الوحدة قابلة للتأجير بشكل مستقل (تُحتسب في باقة الاشتراك إذا لم تكن مقسمة)
                    </label>
                </div>
                <p v-if="form.errors.is_rentable" class="mt-1 text-xs text-rose-600 font-medium">
                    {{ form.errors.is_rentable }}
                </p>

                <!-- Actions -->
                <div class="pt-4 flex items-center justify-end gap-3 border-t border-[#D6E0E7]">
                    <button
                        @click="emit('close')"
                        type="button"
                        class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold text-[#5B6770] hover:bg-slate-50 transition"
                    >
                        إلغاء
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing || (form.unit_type === 'room' && !form.parent_unit_id) || (form.unit_type === 'bed' && !form.parent_unit_id)"
                        class="h-10 px-5 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] disabled:opacity-50 text-white text-xs font-bold transition flex items-center gap-2 cursor-pointer shadow-xs"
                    >
                        <Check class="w-4 h-4" />
                        <span>{{ form.processing ? 'جاري الحفظ...' : (mode === 'create' ? 'حفظ الوحدة' : 'حفظ التعديلات') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
