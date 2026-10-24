<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    AlertTriangle,
    ArrowRight,
    Building2,
    Calendar,
    Check,
    CheckCircle2,
    Clock,
    CreditCard,
    DollarSign,
    FileCheck,
    FilePlus,
    FileText,
    HelpCircle,
    Home,
    Info,
    Loader2,
    ShieldAlert,
    Trash2,
    Upload,
    User,
    UserCheck,
    UserPlus,
    Users,
    Store,
    X,
} from 'lucide-vue-next';

interface UnitOption {
    id: number;
    name: string;
    code: string | null;
    property_id: number;
    property_name: string;
    is_occupied: boolean;
    unit_type?: string;
    type_label?: string;
    active_leases: Array<{
        id: number;
        renter_name: string;
        starts_on: string;
        ends_on: string;
    }>;
}

interface RenterOption {
    id: number;
    name: string;
    phone: string;
    national_id: string | null;
    email?: string | null;
}

const props = defineProps<{
    units: UnitOption[];
    renters: RenterOption[];
    properties?: Array<{
        id: number;
        name: string;
    }>;
    currency?: string;
    preselected_unit_id?: string | number;
    preselected_renter_id?: string | number;
}>();

const localRenters = ref<RenterOption[]>([...props.renters]);

watch(
    () => props.renters,
    (newRenters) => {
        if (newRenters) {
            localRenters.value = [...newRenters];
        }
    }
);

const selectedPropertyId = ref<string | number>(
    props.units.find(u => String(u.id) === String(props.preselected_unit_id))?.property_id || (props.properties?.[0]?.id ?? '')
);

const filteredUnits = computed(() => {
    if (!selectedPropertyId.value) return props.units;
    return props.units.filter(u => u.property_id === Number(selectedPropertyId.value));
});

const todayDate = new Date().toISOString().split('T')[0];
const nextYearDate = new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

const form = useForm({
    unit_id: props.preselected_unit_id ? Number(props.preselected_unit_id) : (filteredUnits.value[0]?.id ?? ''),
    renter_id: props.preselected_renter_id ? Number(props.preselected_renter_id) : (localRenters.value[0]?.id ?? ''),
    starts_on: todayDate,
    ends_on: nextYearDate,
    rent_amount: 3200,
    security_deposit_amount: 0,
    billing_interval: 'monthly',
    notes: '',
});

watch(filteredUnits, (units) => {
    if (units.length > 0 && !units.some(u => u.id === Number(form.unit_id))) {
        form.unit_id = units[0]?.id ?? '';
    }
});

const selectedRenterDetails = computed(() => {
    if (!form.renter_id) return null;
    return localRenters.value.find(r => Number(r.id) === Number(form.renter_id)) || null;
});

// Conflict Detection State
const checkingConflict = ref(false);
const conflictData = ref<{
    has_conflict: boolean;
    conflicting_lease: {
        id: number;
        contract_number: string;
        renter_name: string;
        unit_name: string;
        starts_on: string;
        ends_on: string;
        rent_amount: number;
    } | null;
    message: string;
} | null>(null);

const runConflictCheck = async () => {
    if (!form.unit_id || !form.starts_on || !form.ends_on) return;
    if (new Date(form.ends_on) <= new Date(form.starts_on)) return;

    checkingConflict.value = true;
    try {
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
        const response = await fetch(route('leases.check-conflict'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                unit_id: form.unit_id,
                starts_on: form.starts_on,
                ends_on: form.ends_on,
            }),
        });
        if (response.ok) {
            conflictData.value = await response.json();
        }
    } catch (e) {
        console.error('Conflict check error', e);
    } finally {
        checkingConflict.value = false;
    }
};

watch(
    () => [form.unit_id, form.starts_on, form.ends_on],
    () => {
        runConflictCheck();
    },
    { immediate: true }
);

const submit = () => {
    form.post(route('leases.store'));
};

// ----------------------------------------------------
// New Renter Quick-Creation Modal & Phone Uniqueness State
// ----------------------------------------------------
const showRenterModal = ref(false);
const isSubmittingRenter = ref(false);
const renterModalError = ref('');
const renterErrors = ref<Record<string, string>>({});
const renterSuccessNotification = ref<string | null>(null);

const newRenter = ref({
    name: '',
    phone: '',
    national_id: '',
    email: '',
    whatsapp_consent: true,
    notes: '',
});

const frontInputRef = ref<HTMLInputElement | null>(null);
const backInputRef = ref<HTMLInputElement | null>(null);
const idFrontFile = ref<File | null>(null);
const idBackFile = ref<File | null>(null);
const idFrontPreview = ref<string | null>(null);
const idBackPreview = ref<string | null>(null);

const checkingPhone = ref(false);
const existingRenterFound = ref<RenterOption | null>(null);
let phoneCheckTimer: ReturnType<typeof setTimeout> | null = null;

const onFrontFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        idFrontFile.value = file;
        idFrontPreview.value = URL.createObjectURL(file);
    }
};

const clearFrontFile = () => {
    idFrontFile.value = null;
    if (idFrontPreview.value) {
        URL.revokeObjectURL(idFrontPreview.value);
        idFrontPreview.value = null;
    }
    if (frontInputRef.value) {
        frontInputRef.value.value = '';
    }
};

const onBackFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        idBackFile.value = file;
        idBackPreview.value = URL.createObjectURL(file);
    }
};

const clearBackFile = () => {
    idBackFile.value = null;
    if (idBackPreview.value) {
        URL.revokeObjectURL(idBackPreview.value);
        idBackPreview.value = null;
    }
    if (backInputRef.value) {
        backInputRef.value.value = '';
    }
};

const checkPhoneExistence = async () => {
    const rawPhone = newRenter.value.phone.trim();
    if (!rawPhone || rawPhone.length < 4) {
        existingRenterFound.value = null;
        checkingPhone.value = false;
        return;
    }

    // 1. Instant check against localRenters in memory
    const cleaned = rawPhone.replace(/\D/g, '');
    const clientMatch = localRenters.value.find(r => {
        if (!r.phone) return false;
        const rCleaned = r.phone.replace(/\D/g, '');
        return r.phone === rawPhone || (cleaned.length >= 6 && rCleaned && (rCleaned === cleaned || rCleaned.endsWith(cleaned) || cleaned.endsWith(rCleaned)));
    });

    if (clientMatch) {
        existingRenterFound.value = clientMatch;
        checkingPhone.value = false;
        return;
    }

    // 2. Server check
    checkingPhone.value = true;
    try {
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
        const res = await fetch(route('renters.check-phone'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ phone: rawPhone }),
        });

        if (res.ok) {
            const data = await res.json();
            if (data.exists && data.renter) {
                existingRenterFound.value = data.renter;
            } else {
                existingRenterFound.value = null;
            }
        }
    } catch (e) {
        console.error('Error checking phone existence:', e);
    } finally {
        checkingPhone.value = false;
    }
};

const onPhoneInput = () => {
    renterErrors.value.phone = '';
    if (phoneCheckTimer) clearTimeout(phoneCheckTimer);
    phoneCheckTimer = setTimeout(() => {
        checkPhoneExistence();
    }, 300);
};

const openCreateRenterModal = () => {
    newRenter.value = {
        name: '',
        phone: '',
        national_id: '',
        email: '',
        whatsapp_consent: true,
        notes: '',
    };
    clearFrontFile();
    clearBackFile();
    renterErrors.value = {};
    renterModalError.value = '';
    existingRenterFound.value = null;
    showRenterModal.value = true;
};

const closeRenterModal = () => {
    showRenterModal.value = false;
    newRenter.value = {
        name: '',
        phone: '',
        national_id: '',
        email: '',
        whatsapp_consent: true,
        notes: '',
    };
    clearFrontFile();
    clearBackFile();
    renterErrors.value = {};
    renterModalError.value = '';
    existingRenterFound.value = null;
};

const selectExistingRenter = (renter: RenterOption) => {
    if (!localRenters.value.some(r => r.id === renter.id)) {
        localRenters.value.unshift({
            id: renter.id,
            name: renter.name,
            phone: renter.phone,
            national_id: renter.national_id ?? null,
            email: renter.email ?? null,
        });
    }

    form.renter_id = renter.id;
    closeRenterModal();

    renterSuccessNotification.value = `تم اختيار المستأجر "${renter.name}" (${renter.phone}) مباشرة لهذا العقد بنجاح!`;
    setTimeout(() => {
        renterSuccessNotification.value = null;
    }, 6000);
};

const submitNewRenter = async () => {
    if (existingRenterFound.value) {
        return;
    }

    if (!newRenter.value.name.trim()) {
        renterErrors.value.name = 'يرجى إدخال اسم المستأجر';
        return;
    }

    if (!newRenter.value.phone.trim()) {
        renterErrors.value.phone = 'يرجى إدخال رقم هاتف المستأجر';
        return;
    }

    isSubmittingRenter.value = true;
    renterModalError.value = '';
    renterErrors.value = {};

    try {
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
        const formData = new FormData();
        formData.append('name', newRenter.value.name.trim());
        formData.append('phone', newRenter.value.phone.trim());
        if (newRenter.value.national_id) {
            formData.append('national_id', newRenter.value.national_id.trim());
        }
        if (newRenter.value.email) {
            formData.append('email', newRenter.value.email.trim());
        }
        formData.append('whatsapp_consent', newRenter.value.whatsapp_consent ? '1' : '0');
        if (newRenter.value.notes) {
            formData.append('notes', newRenter.value.notes.trim());
        }
        if (idFrontFile.value) {
            formData.append('id_card_front', idFrontFile.value);
        }
        if (idBackFile.value) {
            formData.append('id_card_back', idBackFile.value);
        }

        const response = await fetch(route('renters.store'), {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: formData,
        });

        const data = await response.json();

        if (!response.ok) {
            if (response.status === 422 && data.errors) {
                for (const [key, msgs] of Object.entries(data.errors)) {
                    renterErrors.value[key] = Array.isArray(msgs) ? msgs[0] : (msgs as string);
                }
                if (data.errors.phone) {
                    checkPhoneExistence();
                }
            } else {
                renterModalError.value = data.message || 'حدث خطأ أثناء حفظ بيانات المستأجر';
            }
            return;
        }

        if (data.renter) {
            const created = data.renter;
            localRenters.value.unshift(created);
            form.renter_id = created.id;
            closeRenterModal();

            renterSuccessNotification.value = `تمت إضافة المستأجر "${created.name}" بنجاح وتحديده تلقائياً لهذا العقد!`;
            setTimeout(() => {
                renterSuccessNotification.value = null;
            }, 6000);
        }
    } catch (err: any) {
        console.error('Error creating renter:', err);
        renterModalError.value = 'تعذر الاتصال بالخادم، يرجى المحاولة مرة أخرى.';
    } finally {
        isSubmittingRenter.value = false;
    }
};
</script>

<template>
    <AppLayout
        title="إنشاء عقد إيجار جديد — دار"
        active-tab="leases"
        header-title="إنشاء عقد إيجار جديد"
        header-subtitle="توثيق بيانات العقد، تحديد الوحدة والمستأجر مع فحص التعارض الزمني التلقائي"
        breadcrumb="العقود / عقد جديد"
    >
        <template #actions>
            <Link
                :href="route('leases.index')"
                class="h-10 px-4 rounded-xl border border-[#D6E0E7] bg-white hover:bg-[#F3F7FA] text-xs font-semibold text-[#5B6770] transition flex items-center gap-1.5"
            >
                <ArrowRight class="w-4 h-4" />
                <span>إلغاء والعودة للعقود</span>
            </Link>
        </template>

        <form @submit.prevent="submit" class="w-full space-y-6">
            <!-- 1. بطاقة اختيار العقار والوحدة التأجيرية -->
            <div class="rounded-3xl border border-[#D6E0E7] bg-white p-6 shadow-2xs space-y-5">
                <div class="flex items-center gap-2.5 pb-3 border-b border-[#D6E0E7]">
                    <div class="w-8 h-8 rounded-lg bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center">
                        <Home class="w-4 h-4" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-[#0F2747]">1. اختيار العقار والوحدة</h3>
                        <p class="text-xs text-[#5B6770]">حدد المبنى والوحدة السكنية المراد تأجيرها</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">العقار / المبنى *</label>
                        <select
                            v-model="selectedPropertyId"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition"
                        >
                            <option v-for="p in properties" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">الوحدة التأجيرية *</label>
                        <select
                            v-model="form.unit_id"
                            required
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition"
                        >
                            <option v-for="u in filteredUnits" :key="u.id" :value="u.id">
                                {{ u.type_label ? '[' + u.type_label + '] ' : '' }}{{ u.name }} {{ u.is_occupied ? '(مشغولة حالياً)' : '(شاغرة)' }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- 2. بطاقة بيانات المستأجر -->
            <div class="rounded-3xl border border-[#D6E0E7] bg-white p-6 shadow-2xs space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7]">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center">
                            <User class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-[#0F2747]">2. المستأجر الطرف الثاني</h3>
                            <p class="text-xs text-[#5B6770]">اختر مستأجراً مسجلاً أو أضف مستأجراً جديداً بسهولة تامة</p>
                        </div>
                    </div>

                    <!-- زر إضافة مستأجر جديد متاح دائماً -->
                    <button
                        type="button"
                        @click="openCreateRenterModal"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-bold transition shadow-2xs cursor-pointer"
                    >
                        <UserPlus class="w-4 h-4 stroke-[2.2]" />
                        <span>إضافة مستأجر جديد</span>
                    </button>
                </div>

                <!-- إشعار نجاح إضافة أو اختيار مستأجر -->
                <div
                    v-if="renterSuccessNotification"
                    class="p-3.5 rounded-2xl bg-emerald-50 border border-emerald-200 text-xs font-semibold text-emerald-900 flex items-center gap-2.5 animate-in fade-in"
                >
                    <CheckCircle2 class="w-5 h-5 text-emerald-600 shrink-0" />
                    <span class="flex-1">{{ renterSuccessNotification }}</span>
                    <button
                        type="button"
                        @click="renterSuccessNotification = null"
                        class="text-emerald-700 hover:text-emerald-900 cursor-pointer"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <!-- في حال عدم وجود أي مستأجر في مساحة العمل -->
                <div
                    v-if="localRenters.length === 0"
                    class="rounded-2xl border-2 border-dashed border-[#D6E0E7] bg-[#F8FAFC] p-8 text-center space-y-3"
                >
                    <div class="w-12 h-12 rounded-2xl bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center mx-auto">
                        <Users class="w-6 h-6 stroke-[2]" />
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-[#0F2747]">لا يوجد مستأجرون مسجلون في هذه المنشأة بعد</h4>
                        <p class="text-xs text-[#5B6770] mt-1 max-w-md mx-auto leading-relaxed">
                            لتتمكن من إنشاء العقد واعتماده، يمكنك إضافة بيانات المستأجر فوراً من هذه الصفحة دون الحاجة للمغادرة.
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="openCreateRenterModal"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-bold transition shadow-xs cursor-pointer"
                    >
                        <UserPlus class="w-4 h-4 stroke-[2.5]" />
                        <span>إضافة المستأجر الأول الآن</span>
                    </button>
                </div>

                <!-- عند توفر مستأجرين -->
                <div v-else class="space-y-3">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold text-[#0F2747]">المستأجر المعتمد للعقد *</label>
                            <span class="text-[11px] text-[#5B6770]">({{ localRenters.length }} مستأجر مسجل)</span>
                        </div>
                        <select
                            v-model="form.renter_id"
                            required
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition"
                        >
                            <option value="" disabled>-- اختر المستأجر --</option>
                            <option v-for="r in localRenters" :key="r.id" :value="r.id">
                                {{ r.name }} — هاتف: {{ r.phone }} {{ r.national_id ? `(هوية: ${r.national_id})` : '' }}
                            </option>
                        </select>
                        <p v-if="form.errors.renter_id" class="text-xs text-rose-600 font-semibold mt-1">
                            {{ form.errors.renter_id }}
                        </p>
                    </div>

                    <!-- بطاقة ملخص للمستأجر المختار -->
                    <div
                        v-if="selectedRenterDetails"
                        class="p-3.5 rounded-2xl bg-[#F8FAFC] border border-[#D6E0E7]/80 flex flex-wrap items-center justify-between gap-3 text-xs"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center font-bold">
                                {{ selectedRenterDetails.name.charAt(0) }}
                            </div>
                            <div>
                                <span class="font-bold text-[#0F2747] block">{{ selectedRenterDetails.name }}</span>
                                <span class="font-mono text-[#5B6770] text-[11px]">{{ selectedRenterDetails.phone }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2.5 text-[11px]">
                            <span v-if="selectedRenterDetails.national_id" class="px-2.5 py-1 rounded-lg bg-white border border-[#D6E0E7] text-[#5B6770]">
                                الرقم القومي: <span class="font-mono font-semibold text-[#0F2747]">{{ selectedRenterDetails.national_id }}</span>
                            </span>
                            <span class="inline-flex items-center gap-1 text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg font-semibold border border-emerald-200/60">
                                <Check class="w-3.5 h-3.5 stroke-[2.5]" />
                                <span>محدد لهذا العقد</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. التواريخ والبيانات المالية -->
            <div class="rounded-3xl border border-[#D6E0E7] bg-white p-6 shadow-2xs space-y-5">
                <div class="flex items-center gap-2.5 pb-3 border-b border-[#D6E0E7]">
                    <div class="w-8 h-8 rounded-lg bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center">
                        <Calendar class="w-4 h-4" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-[#0F2747]">3. مدة العقد والالتزامات المالية</h3>
                        <p class="text-xs text-[#5B6770]">تحديد تواريخ السريان والقيمة الإيجارية الشهرية</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">تاريخ بداية العقد *</label>
                        <input
                            v-model="form.starts_on"
                            type="date"
                            required
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-right"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">تاريخ نهاية العقد *</label>
                        <input
                            v-model="form.ends_on"
                            type="date"
                            required
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-right"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">القيمة الإيجارية الشهرية *</label>
                        <div class="relative">
                            <input
                                v-model="form.rent_amount"
                                type="number"
                                step="0.01"
                                required
                                class="w-full h-11 pl-12 pr-4 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm font-bold text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-right"
                            />
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-[#5B6770]">
                                ج.م
                            </span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">
                            مبلغ التأمين المسترد / الوديعة <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري — صفر في حال عدم طلبه)</span>
                        </label>
                        <div class="relative">
                            <input
                                v-model="form.security_deposit_amount"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0"
                                class="w-full h-11 pl-12 pr-4 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm font-bold text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-right"
                            />
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-[#5B6770]">
                                {{ currency || 'ج.م' }}
                            </span>
                        </div>
                        <p class="mt-1 text-[11px] text-[#5B6770]">
                            مبلغ تأمين مسترد للمستأجر عند نهاية العقد، لا يُحسب كإيراد إيجار.
                        </p>
                    </div>
                </div>
            </div>

            <!-- 4. كارت كشف التعارض الزمني -->
            <div
                v-if="conflictData"
                class="rounded-3xl border p-6 transition-all duration-300"
                :class="conflictData.has_conflict
                    ? 'border-amber-300 bg-amber-50/70 shadow-sm'
                    : 'border-emerald-300 bg-emerald-50/60'"
            >
                <div v-if="conflictData.has_conflict" class="space-y-4">
                    <div class="flex items-start gap-3.5">
                        <div class="w-10 h-10 rounded-2xl bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-xs">
                            <AlertTriangle class="w-5 h-5 stroke-[2.2]" />
                        </div>
                        <div class="flex-1">
                            <h4 class="text-sm font-bold text-[#0F2747]">
                                تنبيه تعارض زمني: الوحدة محجوزة بالفعل بعقد سارٍ!
                            </h4>
                            <p class="text-xs text-[#5B6770] mt-1 leading-relaxed">
                                {{ conflictData.message }}
                            </p>
                        </div>
                    </div>

                    <div v-if="conflictData.conflicting_lease" class="rounded-2xl border border-amber-300/80 bg-white p-4 space-y-3">
                        <div class="flex items-center justify-between text-xs pb-2 border-b border-amber-100">
                            <span class="font-bold text-[#0F2747]">تفاصيل العقد القائم المتعارض:</span>
                            <span class="font-mono font-bold text-[#0E6E7E] bg-[#E3F1F4] px-2 py-0.5 rounded">
                                {{ conflictData.conflicting_lease.contract_number }}
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                            <div>
                                <span class="text-[#5B6770] block">المستأجر الحالي:</span>
                                <span class="font-bold text-[#0F2747]">{{ conflictData.conflicting_lease.renter_name }}</span>
                            </div>
                            <div>
                                <span class="text-[#5B6770] block">فترة العقد الحالي:</span>
                                <span class="font-mono text-[#0F2747]">
                                    {{ conflictData.conflicting_lease.starts_on }} — {{ conflictData.conflicting_lease.ends_on }}
                                </span>
                            </div>
                            <div>
                                <span class="text-[#5B6770] block">القيمة الإيجارية:</span>
                                <span class="font-mono font-bold text-[#0F2747]">{{ conflictData.conflicting_lease.rent_amount }} ج.م</span>
                            </div>
                        </div>

                        <div class="p-2.5 rounded-xl bg-amber-100/60 text-[11px] text-amber-950 flex items-center gap-2">
                            <Info class="w-4 h-4 text-amber-700 shrink-0" />
                            <span>
                                اقتراح النظام: اختر تاريخ بداية بعد تاريخ انتهاء العقد الحالي ({{ conflictData.conflicting_lease.ends_on }}) أو اختر وحدة شاغرة أخرى.
                            </span>
                        </div>
                    </div>
                </div>

                <div v-else class="flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-xs">
                        <CheckCircle2 class="w-5 h-5" />
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-emerald-950">
                            الوحدة متاحة للتأجير في هذه الفترة
                        </h4>
                        <p class="text-xs text-emerald-800 mt-0.5">
                            لا يوجد أي تعارض زمني أو حجوزات سابقة لهذه الوحدة بين {{ form.starts_on }} و {{ form.ends_on }}.
                        </p>
                    </div>
                </div>
            </div>

            <!-- أزرار الحفظ والاعتماد -->
            <div class="pt-4 flex items-center justify-end gap-3">
                <Link
                    :href="route('leases.index')"
                    class="h-11 px-5 rounded-xl border border-[#D6E0E7] bg-white text-xs font-semibold text-[#5B6770] hover:bg-slate-100 transition flex items-center justify-center"
                >
                    إلغاء
                </Link>

                <button
                    type="submit"
                    :disabled="form.processing || !form.renter_id || !form.unit_id || (conflictData?.has_conflict ?? false)"
                    class="h-11 px-6 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-sm font-bold transition flex items-center gap-2 shadow-xs cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    <Check class="w-4 h-4 stroke-[2.5]" />
                    <span>{{ form.processing ? 'جاري الاعتماد...' : 'اعتماد وتفعيل العقد' }}</span>
                </button>
            </div>
        </form>

        <!-- نافذة إضافة مستأجر جديد (Modal) -->
        <div
            v-if="showRenterModal"
            class="fixed inset-0 z-50 bg-[#0F2747]/50 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto"
            @click.self="closeRenterModal"
        >
            <div class="bg-white rounded-3xl border border-[#D6E0E7] shadow-xl w-full max-w-xl overflow-hidden my-8 animate-in fade-in zoom-in-95 duration-200">
                <!-- الرأس -->
                <div class="px-6 py-4.5 border-b border-[#D6E0E7] flex items-center justify-between bg-[#F8FAFC]">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center">
                            <UserPlus class="w-5 h-5 stroke-[2.2]" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-[#0F2747]">إضافة مستأجر جديد للعقد</h3>
                            <p class="text-[11px] text-[#5B6770]">تسجيل بيانات المستأجر واختياره مباشرة لهذا العقد</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="closeRenterModal"
                        class="w-8 h-8 rounded-lg border border-[#D6E0E7] hover:bg-slate-100 flex items-center justify-center text-[#5B6770] transition cursor-pointer"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <!-- النموذج -->
                <form @submit.prevent="submitNewRenter" class="p-6 space-y-4 max-h-[calc(85vh-130px)] overflow-y-auto">
                    <!-- رسالة خطأ عامة إن وجدت -->
                    <div v-if="renterModalError" class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs font-semibold text-rose-700">
                        {{ renterModalError }}
                    </div>

                    <!-- اسم المستأجر -->
                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">اسم المستأجر بالكامل *</label>
                        <input
                            v-model="newRenter.name"
                            type="text"
                            placeholder="مثال: أحمد عبد الله محمد"
                            required
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-right"
                        />
                        <p v-if="renterErrors.name" class="text-xs text-rose-600 font-semibold mt-1">
                            {{ renterErrors.name }}
                        </p>
                    </div>

                    <!-- رقم الهاتف مع الفحص الفوري والتنبيه الذكي -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-[#0F2747]">رقم الهاتف *</label>
                            <span v-if="checkingPhone" class="inline-flex items-center gap-1 text-[11px] text-[#0E6E7E]">
                                <Loader2 class="w-3 h-3 animate-spin" />
                                <span>جاري التحقق من الرقم...</span>
                            </span>
                        </div>
                        <div class="relative">
                            <input
                                v-model="newRenter.phone"
                                @input="onPhoneInput"
                                type="tel"
                                placeholder="01XXXXXXXXX"
                                required
                                class="w-full h-11 px-3.5 rounded-xl border bg-[#F8FAFC] text-sm text-[#0F2747] focus:bg-white focus:ring-1 transition text-right font-mono"
                                :class="existingRenterFound
                                    ? 'border-amber-400 focus:border-amber-500 focus:ring-amber-500'
                                    : (renterErrors.phone ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-500' : 'border-[#D6E0E7] focus:border-[#0E6E7E] focus:ring-[#0E6E7E]')"
                            />
                        </div>

                        <p v-if="renterErrors.phone" class="text-xs text-rose-600 font-semibold mt-1">
                            {{ renterErrors.phone }}
                        </p>

                        <!-- التنبيه الذكي: المستأجر موجود بالفعل في المنشأة مع زر اختياره مباشرة للعقد -->
                        <div
                            v-if="existingRenterFound"
                            class="rounded-2xl border-2 border-amber-300 bg-amber-50/90 p-4 space-y-3 animate-in fade-in slide-in-from-top-1"
                        >
                            <div class="flex items-start gap-3">
                                <div class="w-9 h-9 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-xs">
                                    <UserCheck class="w-5 h-5 stroke-[2.2]" />
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <h5 class="text-xs font-bold text-amber-950">
                                            هذا المستأجر مسجل بالفعل في هذه المنشأة!
                                        </h5>
                                        <span class="text-[10px] bg-amber-200 text-amber-900 font-bold px-2 py-0.5 rounded-full">
                                            رقم هاتف موجود
                                        </span>
                                    </div>
                                    <p class="text-xs text-amber-900 mt-1 leading-relaxed">
                                        رقم الهاتف مسجل باسم: <strong class="font-bold underline decoration-amber-400">{{ existingRenterFound.name }}</strong>
                                        <span v-if="existingRenterFound.national_id"> (رقم قومي: {{ existingRenterFound.national_id }})</span>.
                                    </p>
                                </div>
                            </div>

                            <!-- سؤال الـ UX والزر للاختيار المباشر -->
                            <div class="pt-2 border-t border-amber-200/70 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2.5">
                                <span class="text-xs font-medium text-amber-950">
                                    المستأجر موجود، هل تريد اختياره مباشرة لهذا العقد؟
                                </span>
                                <button
                                    type="button"
                                    @click="selectExistingRenter(existingRenterFound)"
                                    class="h-9 px-4 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-xs cursor-pointer shrink-0"
                                >
                                    <Check class="w-4 h-4 stroke-[2.5]" />
                                    <span>نعم، اختياره للعقد</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- الرقم القومي والبريد -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-[#0F2747] mb-1.5">
                                الرقم القومي / الهوية <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري)</span>
                            </label>
                            <input
                                v-model="newRenter.national_id"
                                type="text"
                                placeholder="14 رقم"
                                class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-right"
                            />
                            <p v-if="renterErrors.national_id" class="text-xs text-rose-600 font-semibold mt-1">
                                {{ renterErrors.national_id }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-[#0F2747] mb-1.5">
                                البريد الإلكتروني <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري)</span>
                            </label>
                            <input
                                v-model="newRenter.email"
                                type="email"
                                placeholder="name@example.com"
                                class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-right"
                            />
                            <p v-if="renterErrors.email" class="text-xs text-rose-600 font-semibold mt-1">
                                {{ renterErrors.email }}
                            </p>
                        </div>
                    </div>

                    <!-- إشعارات الواتساب -->
                    <div class="p-3 rounded-2xl bg-[#F8FAFC] border border-[#D6E0E7] flex items-center gap-3">
                        <input
                            id="modal_whatsapp_consent"
                            v-model="newRenter.whatsapp_consent"
                            type="checkbox"
                            class="w-4 h-4 rounded text-[#0E6E7E] focus:ring-[#0E6E7E] cursor-pointer"
                        />
                        <label for="modal_whatsapp_consent" class="text-xs font-semibold text-[#0F2747] cursor-pointer">
                            الموافقة على استقبال إشعارات الاستحقاق وسندات القبض عبر WhatsApp تلقائياً
                        </label>
                    </div>

                    <!-- صور بطاقة الهوية (اختياري) -->
                    <div class="space-y-2 pt-2 border-t border-[#D6E0E7]/60">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-[#0F2747]">
                                صورة بطاقة الهوية <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري)</span>
                            </label>
                            <span class="text-[11px] text-[#5B6770]">JPG, PNG, WEBP</span>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <!-- الوجه الأمامي -->
                            <div>
                                <input
                                    ref="frontInputRef"
                                    type="file"
                                    accept="image/jpeg,image/png,image/jpg,image/webp"
                                    class="hidden"
                                    @change="onFrontFileChange"
                                />
                                <div
                                    v-if="idFrontPreview"
                                    class="relative rounded-xl border border-[#0E6E7E]/30 bg-[#F8FAFC] overflow-hidden group h-24 flex items-center justify-center"
                                >
                                    <img :src="idFrontPreview" alt="الوجه الأمامي" class="w-full h-full object-cover" />
                                    <div class="absolute inset-0 bg-[#0F2747]/60 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-1.5">
                                        <button
                                            type="button"
                                            @click="frontInputRef?.click()"
                                            class="px-2 py-1 rounded bg-white text-[10px] font-bold text-[#0F2747]"
                                        >
                                            تغيير
                                        </button>
                                        <button
                                            type="button"
                                            @click="clearFrontFile"
                                            class="p-1 rounded bg-rose-600 text-white"
                                        >
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                                <div
                                    v-else
                                    @click="frontInputRef?.click()"
                                    class="h-24 rounded-xl border-2 border-dashed border-[#D6E0E7] hover:border-[#0E6E7E] bg-[#F8FAFC] transition cursor-pointer flex flex-col items-center justify-center p-2 text-center gap-1"
                                >
                                    <Upload class="w-4 h-4 text-[#5B6770]" />
                                    <span class="text-[11px] font-bold text-[#0F2747]">الوجه الأمامي</span>
                                </div>
                            </div>

                            <!-- الوجه الخلفي -->
                            <div>
                                <input
                                    ref="backInputRef"
                                    type="file"
                                    accept="image/jpeg,image/png,image/jpg,image/webp"
                                    class="hidden"
                                    @change="onBackFileChange"
                                />
                                <div
                                    v-if="idBackPreview"
                                    class="relative rounded-xl border border-[#0E6E7E]/30 bg-[#F8FAFC] overflow-hidden group h-24 flex items-center justify-center"
                                >
                                    <img :src="idBackPreview" alt="الوجه الخلفي" class="w-full h-full object-cover" />
                                    <div class="absolute inset-0 bg-[#0F2747]/60 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-1.5">
                                        <button
                                            type="button"
                                            @click="backInputRef?.click()"
                                            class="px-2 py-1 rounded bg-white text-[10px] font-bold text-[#0F2747]"
                                        >
                                            تغيير
                                        </button>
                                        <button
                                            type="button"
                                            @click="clearBackFile"
                                            class="p-1 rounded bg-rose-600 text-white"
                                        >
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                                <div
                                    v-else
                                    @click="backInputRef?.click()"
                                    class="h-24 rounded-xl border-2 border-dashed border-[#D6E0E7] hover:border-[#0E6E7E] bg-[#F8FAFC] transition cursor-pointer flex flex-col items-center justify-center p-2 text-center gap-1"
                                >
                                    <Upload class="w-4 h-4 text-[#5B6770]" />
                                    <span class="text-[11px] font-bold text-[#0F2747]">الوجه الخلفي</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- أزرار الإجراءات داخل النافذة -->
                    <div class="pt-3 border-t border-[#D6E0E7] flex items-center justify-end gap-2.5">
                        <button
                            type="button"
                            @click="closeRenterModal"
                            class="h-10 px-4 rounded-xl border border-[#D6E0E7] bg-white text-xs font-semibold text-[#5B6770] hover:bg-slate-50 transition cursor-pointer"
                        >
                            إلغاء
                        </button>

                        <button
                            type="submit"
                            :disabled="isSubmittingRenter || !!existingRenterFound || !newRenter.name || !newRenter.phone"
                            class="h-10 px-5 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-bold transition flex items-center gap-2 shadow-xs cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            <Loader2 v-if="isSubmittingRenter" class="w-4 h-4 animate-spin" />
                            <Check v-else class="w-4 h-4 stroke-[2.5]" />
                            <span>{{ isSubmittingRenter ? 'جاري الحفظ...' : 'حفظ واختيار المستأجر للعقد' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
