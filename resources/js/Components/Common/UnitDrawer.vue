<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    Calendar,
    CreditCard,
    DollarSign,
    FileText,
    Home,
    DoorClosed,
    Bed,
    Layers,
    MessageCircle,
    Phone,
    Plus,
    Shield,
    Sparkles,
    Store,
    Tag,
    User,
    Wrench,
    X,
    ExternalLink,
    Clock,
    AlertCircle,
    CheckCircle2,
    Edit3,
    Trash2,
    Lock,
} from 'lucide-vue-next';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import type { UnitItem } from '@/Components/Properties/UnitHierarchyTree.vue';

export interface ChildUnit {
    id: number;
    code: string;
    name: string;
    unit_type: string;
    type_label: string;
    status: string;
    status_label: string;
    is_rentable: boolean;
}

export interface MaintenanceItem {
    id: number;
    title: string;
    status: string;
    reported_at: string;
}

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

export type UnitData = UnitItem;

const props = withDefaults(
    defineProps<{
        show: boolean;
        unit: UnitItem | null;
        propertyName?: string;
        currencySymbol?: string;
    }>(),
    {
        currencySymbol: 'ج.م',
    }
);

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'recordPayment', leaseId: number, renterId: number, amount: number): void;
    (e: 'selectUnit', unitId: number): void;
    (e: 'addSubUnit', parentUnitId: number, childType: 'room' | 'bed'): void;
    (e: 'editUnit', unit: UnitItem): void;
    (e: 'deleteUnit', unit: UnitItem): void;
}>();

const formattedWhatsAppUrl = computed(() => {
    if (!props.unit?.active_lease?.renter_phone) return '';
    let phone = props.unit.active_lease.renter_phone.replace(/[^0-9]/g, '');
    if (phone.startsWith('00')) {
        phone = phone.substring(2);
    } else if (phone.startsWith('0')) {
        if (phone.length === 11 && phone.startsWith('01')) {
            phone = '20' + phone.substring(1);
        } else if (phone.length === 10 && phone.startsWith('05')) {
            phone = '966' + phone.substring(1);
        }
    }
    const unitName = props.unit.name;
    const msg = encodeURIComponent(`مرحباً أستاذ ${props.unit.active_lease.renter_name}، نتواصل معك بخصوص (${unitName}).`);
    return `https://wa.me/${phone}?text=${msg}`;
});

const leaseProgress = computed(() => {
    if (!props.unit?.active_lease?.starts_on || !props.unit?.active_lease?.ends_on) return null;
    const days = props.unit.active_lease.days_remaining;
    if (days === null || days === undefined) return 50;
    if (days <= 0) return 100;
    if (days >= 365) return 20;
    return Math.min(100, Math.max(10, Math.round(((365 - days) / 365) * 100)));
});

const unitIcon = computed(() => {
    switch (props.unit?.unit_type) {
        case 'shop': return Store;
        case 'room': return DoorClosed;
        case 'bed': return Bed;
        default: return Home;
    }
});

const canAddChild = computed(() => {
    if (!props.unit) return false;
    return (props.unit.unit_type === 'apartment' || props.unit.unit_type === 'room') && Boolean(props.unit.can_subdivide);
});

const canDelete = computed(() => {
    if (!props.unit) return false;
    const hasChildren = props.unit.child_units && props.unit.child_units.length > 0;
    const hasActiveLease = Boolean(props.unit.active_lease);
    return !hasChildren && !hasActiveLease;
});
</script>

<template>
    <div v-if="show && unit" class="fixed inset-0 z-50 overflow-hidden" dir="rtl">
        <!-- Backdrop -->
        <div
            @click="emit('close')"
            class="absolute inset-0 bg-[#0F2747]/60 backdrop-blur-xs transition-opacity animate-in fade-in duration-200"
        ></div>

        <div class="fixed inset-y-0 right-0 max-w-full flex pr-0 sm:pr-6">
            <!-- Sliding Drawer -->
            <div class="w-screen max-w-lg bg-white shadow-2xl border-l border-[#D6E0E7] flex flex-col justify-between animate-in slide-in-from-right duration-300">
                <!-- 1. Drawer Header -->
                <div class="p-5 sm:p-6 pb-4 border-b border-[#E8EFF4] bg-white sticky top-0 z-10">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3 min-w-0">
                            <div
                                class="w-11 h-11 rounded-2xl flex items-center justify-center shrink-0 shadow-2xs"
                                :class="[
                                    unit.unit_type === 'shop' ? 'bg-purple-100 text-purple-700' :
                                    unit.unit_type === 'room' ? 'bg-teal-100 text-teal-800' :
                                    unit.unit_type === 'bed' ? 'bg-amber-100 text-amber-800' :
                                    'bg-[#E8F3F5] text-[#0E6E7E]'
                                ]"
                            >
                                <component :is="unitIcon" class="w-6 h-6 stroke-[2]" />
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3 class="text-lg font-bold text-[#0F2747] truncate">
                                        {{ unit.name }}
                                    </h3>
                                    <span v-if="unit.code" class="text-xs font-mono font-bold text-[#0E6E7E] bg-[#E3F1F4] px-2.5 py-0.5 rounded-lg border border-[#0E6E7E]/20">
                                        {{ unit.code }}
                                    </span>
                                </div>
                                <!-- Full Hierarchy Breadcrumb -->
                                <p class="text-xs text-[#5B6770] font-medium mt-1 truncate">
                                    {{ unit.breadcrumb || (propertyName + ' / ' + unit.name) }}
                                </p>
                            </div>
                        </div>

                        <button
                            @click="emit('close')"
                            type="button"
                            class="p-2 text-[#5B6770] hover:text-[#0F2747] hover:bg-[#F3F7FA] rounded-xl transition cursor-pointer shrink-0"
                            title="إغلاق"
                        >
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- Type and Status Row -->
                    <div class="mt-4 flex items-center justify-between gap-2 p-2.5 rounded-xl bg-[#F8FAFC] border border-[#E8EFF4]">
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-[#5B6770] font-medium">نوع الوحدة:</span>
                            <span class="text-xs font-bold text-[#0F2747] bg-white px-2 py-0.5 rounded-md border border-[#D6E0E7]">
                                {{ unit.type_label || 'وحدة' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-[#5B6770] font-medium">الحالة:</span>
                            <StatusBadge :status="unit.status" :label="unit.status_label" size="sm" />
                        </div>
                    </div>
                </div>

                <!-- 2. Scrollable Body Content -->
                <div class="overflow-y-auto p-5 sm:p-6 space-y-5 flex-1">
                    <!-- IF RENTED: Active Lease Card -->
                    <div v-if="unit.active_lease" class="rounded-2xl border border-emerald-200/80 bg-gradient-to-b from-emerald-50/60 to-white p-5 shadow-xs space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-emerald-100">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span class="text-xs font-bold text-emerald-950">بيانات عقد الإيجار الساري</span>
                            </div>
                            <span v-if="unit.active_lease.contract_number" class="text-xs font-mono font-bold text-[#0E6E7E]">
                                {{ unit.active_lease.contract_number }}
                            </span>
                        </div>

                        <!-- Renter Profile Summary -->
                        <div class="flex items-center justify-between gap-3 p-3 bg-white rounded-xl border border-emerald-100">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-10 h-10 rounded-xl bg-[#0E6E7E] text-white flex items-center justify-center font-bold text-sm shrink-0">
                                    {{ unit.active_lease.renter_name.charAt(0) }}
                                </div>
                                <div class="min-w-0">
                                    <Link
                                        :href="route('renters.show', unit.active_lease.renter_id)"
                                        class="font-bold text-sm text-[#0F2747] hover:text-[#0E6E7E] transition truncate block"
                                    >
                                        {{ unit.active_lease.renter_name }}
                                    </Link>
                                    <span v-if="unit.active_lease.renter_national_id" class="text-[11px] text-[#5B6770] font-mono block">
                                        هوية: {{ unit.active_lease.renter_national_id }}
                                    </span>
                                </div>
                            </div>

                            <!-- Quick contact buttons -->
                            <div class="flex items-center gap-1.5 shrink-0">
                                <a
                                    v-if="formattedWhatsAppUrl"
                                    :href="formattedWhatsAppUrl"
                                    target="_blank"
                                    class="p-2 rounded-xl bg-emerald-100 text-emerald-800 hover:bg-emerald-200 transition"
                                    title="مراسلة عبر واتساب"
                                >
                                    <MessageCircle class="w-4 h-4" />
                                </a>
                                <a
                                    v-if="unit.active_lease.renter_phone"
                                    :href="`tel:${unit.active_lease.renter_phone}`"
                                    class="p-2 rounded-xl bg-slate-100 text-[#0F2747] hover:bg-slate-200 transition"
                                    title="اتصال هاتفي"
                                >
                                    <Phone class="w-4 h-4" />
                                </a>
                            </div>
                        </div>

                        <!-- Financial details -->
                        <div class="grid grid-cols-2 gap-3 pt-1">
                            <div class="p-3 bg-white rounded-xl border border-emerald-100/70">
                                <span class="text-[11px] text-[#5B6770] font-medium block">القيمة الإيجارية</span>
                                <span class="text-base font-black text-[#0F2747] block mt-0.5">
                                    {{ unit.active_lease.rent_amount.toLocaleString() }} {{ currencySymbol }}
                                </span>
                                <span class="text-[10px] text-[#5B6770]">دفع شهري</span>
                            </div>

                            <div class="p-3 bg-white rounded-xl border border-emerald-100/70">
                                <span class="text-[11px] text-[#5B6770] font-medium block">المدة المتبقية</span>
                                <span class="text-base font-black text-[#0E6E7E] block mt-0.5">
                                    {{ unit.active_lease.days_remaining !== null ? unit.active_lease.days_remaining + ' يوم' : 'مستمر' }}
                                </span>
                                <span class="text-[10px] text-[#5B6770]">
                                    حتى {{ unit.active_lease.ends_on }}
                                </span>
                            </div>
                        </div>

                        <!-- Security Deposit info (if exists) -->
                        <div v-if="unit.active_lease.security_deposit_amount" class="p-3 bg-white rounded-xl border border-emerald-100/70 flex items-center justify-between text-xs">
                            <span class="text-[#5B6770] flex items-center gap-1.5">
                                <Shield class="w-4 h-4 text-[#0E6E7E]" />
                                <span>مبلغ التأمين المسترد:</span>
                            </span>
                            <span class="font-bold text-[#0F2747]">
                                {{ unit.active_lease.security_deposit_amount.toLocaleString() }} {{ currencySymbol }}
                                <span v-if="unit.active_lease.security_deposit_status" class="text-[10px] text-[#5B6770] font-normal mr-1">
                                    ({{ unit.active_lease.security_deposit_status }})
                                </span>
                            </span>
                        </div>

                        <!-- Progress Bar -->
                        <div v-if="leaseProgress !== null" class="space-y-1.5 pt-1">
                            <div class="flex items-center justify-between text-[11px] text-[#5B6770]">
                                <span>بداية: {{ unit.active_lease.starts_on }}</span>
                                <span>نهاية: {{ unit.active_lease.ends_on }}</span>
                            </div>
                            <div class="h-2 w-full bg-emerald-100 rounded-full overflow-hidden">
                                <div
                                    class="h-full bg-emerald-600 rounded-full transition-all duration-500"
                                    :style="{ width: `${leaseProgress}%` }"
                                ></div>
                            </div>
                        </div>

                        <!-- Record Payment Button -->
                        <div class="pt-2">
                            <button
                                @click="emit('recordPayment', unit.active_lease.id, unit.active_lease.renter_id, unit.active_lease.rent_amount)"
                                type="button"
                                class="w-full h-11 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-bold transition flex items-center justify-center gap-2 shadow-xs cursor-pointer"
                            >
                                <CreditCard class="w-4 h-4" />
                                <span>تسجيل دفعة إيجار لهذا العقد</span>
                            </button>
                        </div>
                    </div>

                    <!-- IF VACANT: Card -->
                    <div v-else class="rounded-2xl border border-dashed border-amber-300 bg-gradient-to-b from-amber-50/70 to-white p-6 text-center space-y-4">
                        <div class="w-14 h-14 rounded-2xl bg-amber-100 text-amber-700 mx-auto flex items-center justify-center shadow-2xs">
                            <Tag class="w-7 h-7 stroke-[2]" />
                        </div>
                        <div>
                            <h4 class="text-base font-bold text-[#0F2747]">الوحدة شاغرة حالياً</h4>
                            <p class="text-xs text-[#5B6770] leading-relaxed mt-1 max-w-sm mx-auto">
                                لا يوجد عقد إيجار نشط حالياً لهذه الوحدة.
                            </p>
                        </div>

                        <!-- Conflict Alert if parent or child has active lease -->
                        <div v-if="unit.descendant_has_active_lease" class="p-3 bg-amber-100/70 border border-amber-200 rounded-xl text-xs text-amber-900 leading-relaxed text-right">
                            ⚠️ لا يمكن تأجير هذه الوحدة بالكامل حالياً، نظراً لوجود عقود إيجار نشطة على وحدات فرعية تابعة لها في نفس الهيكل العقاري.
                        </div>

                        <div v-else-if="unit.ancestor_has_active_lease" class="p-3 bg-amber-100/70 border border-amber-200 rounded-xl text-xs text-amber-900 leading-relaxed text-right">
                            ⚠️ لا يمكن تأجير هذه الوحدة بشكل مستقل، نظراً لوجود عقد إيجار سارٍ على الوحدة الرئيسية الحاوية لها.
                        </div>

                        <!-- Rent Button if eligible -->
                        <div v-else-if="unit.can_rent" class="pt-2">
                            <Link
                                :href="route('leases.create', { unit_id: unit.id })"
                                class="inline-flex items-center justify-center gap-2 w-full h-11 px-5 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-bold transition shadow-xs cursor-pointer"
                            >
                                <Plus class="w-4 h-4" />
                                <span>إنشاء عقد إيجار لهذه الوحدة الآن</span>
                            </Link>
                        </div>
                    </div>

                    <!-- 3. Sub-Units Section (Context-Aware) -->
                    <div v-if="unit.unit_type === 'apartment' || unit.unit_type === 'room'" class="rounded-2xl border border-[#D6E0E7] bg-white p-5 space-y-4 shadow-2xs">
                        <div class="flex items-center justify-between pb-3 border-b border-[#E8EFF4]">
                            <div class="flex items-center gap-2">
                                <Layers class="w-4 h-4 text-[#0E6E7E]" />
                                <h4 class="text-xs font-bold text-[#0F2747]">
                                    {{ unit.unit_type === 'apartment' ? 'الغرف التابعة لهذه الشقة' : 'الأسِرَّة التابعة لهذه الغرفة' }}
                                </h4>
                                <span class="text-[11px] font-bold text-[#5B6770] bg-[#F3F7FA] px-2 py-0.5 rounded-full">
                                    {{ unit.child_units?.length || 0 }}
                                </span>
                            </div>

                            <button
                                v-if="canAddChild"
                                @click="emit('addSubUnit', unit.id, unit.unit_type === 'apartment' ? 'room' : 'bed')"
                                type="button"
                                class="text-xs font-bold text-[#0E6E7E] hover:underline flex items-center gap-1 cursor-pointer"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>{{ unit.unit_type === 'apartment' ? 'إضافة أوضة' : 'إضافة سرير' }}</span>
                            </button>
                        </div>

                        <!-- Sub-units list -->
                        <div v-if="unit.child_units && unit.child_units.length > 0" class="space-y-2">
                            <div
                                v-for="child in unit.child_units"
                                :key="child.id"
                                @click="emit('selectUnit', child.id)"
                                class="p-3 rounded-xl border border-[#E8EFF4] bg-[#F8FAFC] hover:bg-[#E3F1F4]/40 hover:border-[#0E6E7E]/40 transition flex items-center justify-between cursor-pointer group"
                            >
                                <div class="flex items-center gap-2.5">
                                    <span class="w-2 h-2 rounded-full" :class="child.status === 'occupied' ? 'bg-emerald-500' : 'bg-amber-400'"></span>
                                    <div>
                                        <span class="text-xs font-bold text-[#0F2747] group-hover:text-[#0E6E7E] transition block">
                                            {{ child.name }}
                                        </span>
                                        <span class="text-[10px] text-[#5B6770] font-mono">
                                            {{ child.code ? child.code + ' • ' : '' }}{{ child.type_label }}
                                        </span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <StatusBadge :status="child.status" :label="child.status_label" size="sm" />
                                    <span class="text-xs text-[#8E9CA6] group-hover:text-[#0E6E7E] transition">←</span>
                                </div>
                            </div>
                        </div>

                        <div v-else class="py-4 text-center">
                            <p class="text-xs text-[#8E9CA6]">
                                {{ unit.unit_type === 'apartment' ? 'لا توجد غرف مضافة داخل هذه الشقة حتى الآن.' : 'لا توجد أسِرَّة مضافة داخل هذه الغرفة حتى الآن.' }}
                            </p>
                        </div>
                    </div>

                    <!-- 4. Maintenance Tickets -->
                    <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 space-y-3 shadow-2xs">
                        <div class="flex items-center justify-between pb-3 border-b border-[#E8EFF4]">
                            <div class="flex items-center gap-2">
                                <Wrench class="w-4 h-4 text-[#0E6E7E]" />
                                <h4 class="text-xs font-bold text-[#0F2747]">سجل الصيانة والبلاغات</h4>
                            </div>
                            <Link
                                :href="route('maintenance.index')"
                                class="text-xs font-bold text-[#0E6E7E] hover:underline flex items-center gap-1"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>طلب صيانة</span>
                            </Link>
                        </div>

                        <div v-if="unit.maintenance_requests && unit.maintenance_requests.length > 0" class="space-y-2">
                            <div
                                v-for="ticket in unit.maintenance_requests"
                                :key="ticket.id"
                                class="p-3 rounded-xl border border-amber-200/60 bg-amber-50/40 flex items-center justify-between"
                            >
                                <div>
                                    <span class="text-xs font-bold text-[#0F2747] block">{{ ticket.title }}</span>
                                    <span class="text-[10px] text-[#5B6770]">{{ ticket.reported_at }}</span>
                                </div>
                                <span class="text-[10px] font-bold text-amber-800 bg-amber-100 px-2 py-0.5 rounded-full">
                                    قيد المتابعة
                                </span>
                            </div>
                        </div>

                        <div v-else class="py-2 text-center">
                            <p class="text-xs text-emerald-700 font-medium flex items-center justify-center gap-1.5">
                                <CheckCircle2 class="w-4 h-4 text-emerald-600" />
                                لا توجد بلاغات صيانة معلقة على هذه الوحدة
                            </p>
                        </div>
                    </div>

                    <!-- 5. Metadata & Subscription Billing Status -->
                    <div class="rounded-2xl border border-[#D6E0E7] bg-[#F8FAFC] p-4 text-xs space-y-2.5">
                        <div class="flex items-center justify-between text-[#5B6770]">
                            <span>احتساب الوحدة في باقة الاشتراك:</span>
                            <span class="font-bold text-[#0F2747]">
                                {{ unit.is_rentable ? 'وحدة قابلة للتأجير' : 'حاوية هيكلية (غير محتسبة)' }}
                            </span>
                        </div>
                        <div v-if="unit.parent_unit_name" class="flex items-center justify-between text-[#5B6770]">
                            <span>الوحدة الأم / المستوى الأعلى:</span>
                            <span class="font-bold text-[#0E6E7E]">{{ unit.parent_unit_name }}</span>
                        </div>
                    </div>
                </div>

                <!-- 3. Drawer Footer Actions -->
                <div class="p-4 border-t border-[#E8EFF4] bg-white flex items-center justify-between gap-2.5 sticky bottom-0">
                    <div class="flex items-center gap-2">
                        <!-- Edit Unit -->
                        <button
                            @click="emit('editUnit', unit)"
                            type="button"
                            class="h-10 px-3.5 rounded-xl border border-[#D6E0E7] hover:bg-[#F3F7FA] text-xs font-bold text-[#0F2747] transition flex items-center gap-1.5 cursor-pointer"
                        >
                            <Edit3 class="w-4 h-4 text-[#0E6E7E]" />
                            <span>تعديل</span>
                        </button>

                        <!-- Delete Unit (if allowed) -->
                        <button
                            v-if="canDelete"
                            @click="emit('deleteUnit', unit)"
                            type="button"
                            class="h-10 px-3 rounded-xl border border-rose-200 hover:bg-rose-50 text-xs font-bold text-rose-600 transition flex items-center gap-1 cursor-pointer"
                            title="حذف الوحدة"
                        >
                            <Trash2 class="w-4 h-4" />
                            <span>حذف</span>
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <Link
                            v-if="unit.active_lease"
                            :href="route('renters.show', unit.active_lease.renter_id)"
                            class="h-10 px-4 rounded-xl border border-[#D6E0E7] hover:bg-[#F3F7FA] text-xs font-bold text-[#0F2747] transition flex items-center gap-1.5"
                        >
                            <User class="w-4 h-4 text-[#0E6E7E]" />
                            <span>ملف المستأجر</span>
                        </Link>

                        <button
                            @click="emit('close')"
                            type="button"
                            class="h-10 px-4 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs font-bold text-[#5B6770] hover:text-[#0F2747] hover:bg-slate-200/60 transition cursor-pointer"
                        >
                            إغلاق
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
