<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import ModalRecordPayment from '@/Components/Common/ModalRecordPayment.vue';
import {
    AlertTriangle,
    Check,
    CreditCard,
    MessageCircle,
    Search,
    Trash2,
    Upload,
    User,
    UserPlus,
    X,
} from 'lucide-vue-next';

interface RenterItem {
    id: number;
    name: string;
    phone: string;
    email: string | null;
    national_id: string;
    gender?: 'male' | 'female' | null;
    gender_label?: string | null;
    active_unit: {
        unit_name: string;
        property_name: string;
        rent_amount: number;
        ends_on: string;
    } | null;
    status: string;
    status_label: string;
    total_paid: number;
    whatsapp_opted_in: boolean;
    created_at: string;
}

const props = defineProps<{
    renters: RenterItem[];
    stats: {
        total_renters: number;
        active_contracts: number;
        overdue_count: number;
        whatsapp_opted_in: number;
    };
}>();

const searchQuery = ref(typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('search') || '' : '');
const statusFilter = ref('all');
const genderFilter = ref('all');
const showAddModal = ref(false);
const recordPaymentOpen = ref(false);
const activeRenterForPayment = ref<RenterItem | null>(null);

const frontInputRef = ref<HTMLInputElement | null>(null);
const backInputRef = ref<HTMLInputElement | null>(null);
const frontPreview = ref<string | null>(null);
const backPreview = ref<string | null>(null);

const form = useForm({
    name: '',
    phone: '',
    email: '',
    national_id: '',
    gender: '' as '' | 'male' | 'female',
    whatsapp_consent: true,
    notes: '',
    id_card_front: null as File | null,
    id_card_back: null as File | null,
});

const onFrontFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        form.id_card_front = file;
        frontPreview.value = URL.createObjectURL(file);
    }
};

const clearFrontFile = () => {
    form.id_card_front = null;
    if (frontPreview.value) {
        URL.revokeObjectURL(frontPreview.value);
        frontPreview.value = null;
    }
    if (frontInputRef.value) {
        frontInputRef.value.value = '';
    }
};

const onBackFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        form.id_card_back = file;
        backPreview.value = URL.createObjectURL(file);
    }
};

const clearBackFile = () => {
    form.id_card_back = null;
    if (backPreview.value) {
        URL.revokeObjectURL(backPreview.value);
        backPreview.value = null;
    }
    if (backInputRef.value) {
        backInputRef.value.value = '';
    }
};

const closeModal = () => {
    showAddModal.value = false;
    form.reset();
    clearFrontFile();
    clearBackFile();
};

const filteredRenters = computed(() => {
    return props.renters.filter(r => {
        const matchesSearch = r.name.includes(searchQuery.value)
            || r.phone.includes(searchQuery.value)
            || (r.national_id && r.national_id.includes(searchQuery.value));

        const matchesStatus = statusFilter.value === 'all'
            || (statusFilter.value === 'regular' && r.status === 'regular')
            || (statusFilter.value === 'overdue' && r.status === 'overdue');

        const matchesGender = genderFilter.value === 'all'
            || (genderFilter.value === 'male' && r.gender === 'male')
            || (genderFilter.value === 'female' && r.gender === 'female')
            || (genderFilter.value === 'unspecified' && !r.gender);

        return matchesSearch && matchesStatus && matchesGender;
    });
});

const inactiveRenterWarning = ref<string | null>(null);

const notifyNoActiveLease = (renter: RenterItem) => {
    inactiveRenterWarning.value = `المستأجر "${renter.name}" غير مرتبط بأي وحدة أو عقد إيجار سارٍ حالياً لتسجيل دفعة له.`;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    setTimeout(() => {
        inactiveRenterWarning.value = null;
    }, 6000);
};

const openPaymentForRenter = (renter: RenterItem) => {
    if (!renter.active_unit) {
        notifyNoActiveLease(renter);
        return;
    }
    activeRenterForPayment.value = renter;
    recordPaymentOpen.value = true;
};

const submitRenter = () => {
    form.post(route('renters.store'), {
        forceFormData: true,
        onSuccess: () => {
            closeModal();
        },
    });
};
</script>

<template>
    <AppLayout
        title="المستأجرون — Nook Mate"
        active-tab="renters"
        header-title="المستأجرون"
        header-subtitle="دليل المستأجرين ومتابعة الالتزام المالي وعقود الإيجار السارية"
        breadcrumb="المستأجرون"
    >
        <template #actions>
            <button
                @click="showAddModal = true"
                type="button"
                class="h-11 px-5 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-sm font-semibold transition shadow-xs flex items-center gap-2 cursor-pointer"
            >
                <UserPlus class="h-4 w-4 stroke-[2.5]" />
                <span>إضافة مستأجر جديد</span>
            </button>
        </template>

        <!-- كروت مؤشرات المستأجرين (4 KPIs) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">إجمالي المستأجرين</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#163E3E] font-mono">{{ stats.total_renters }}</span>
                    <span class="text-xs text-[#5B6770]">ملفات نشطة</span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">العقود السارية</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#163E3E] font-mono">{{ stats.active_contracts }}</span>
                    <span class="text-xs text-[#163E3E] font-medium">مستأجرون حاليون</span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">متأخرون في السداد</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-rose-600 font-mono">{{ stats.overdue_count }}</span>
                    <span class="text-xs text-rose-700 bg-rose-50 px-2 py-0.5 rounded-md font-semibold">
                        تتطلب متابعة
                    </span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">مفعلين لتذكيرات WhatsApp</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-emerald-700 font-mono">{{ stats.whatsapp_opted_in }}</span>
                    <span class="text-xs text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md font-semibold">
                        100% اشتراك
                    </span>
                </div>
            </div>
        </div>

        <!-- تنبيه عند محاولة تسجيل دفعة لمستأجر غير مسكن / بدون عقد نشط -->
        <div
            v-if="inactiveRenterWarning"
            class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-950 text-xs font-bold flex items-center justify-between gap-3 shadow-xs animate-in fade-in"
        >
            <div class="flex items-center gap-2.5">
                <AlertTriangle class="w-5 h-5 text-amber-600 shrink-0" />
                <span>{{ inactiveRenterWarning }}</span>
            </div>
            <button
                type="button"
                @click="inactiveRenterWarning = null"
                class="text-amber-700 hover:text-amber-950 p-1 cursor-pointer font-normal text-sm"
                title="إغلاق التنبيه"
            >
                ✕
            </button>
        </div>

        <!-- شريط البحث والتصفية -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-white p-4 rounded-2xl border border-[#D6E0E7] shadow-2xs">
            <div class="relative flex-1 max-w-md">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="ابحث بالاسم، الجوال أو رقم الهوية..."
                    class="w-full h-10 pr-10 pl-4 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] placeholder:text-[#5B6770] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right"
                />
                <Search class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-[#5B6770]" />
            </div>

            <div class="flex items-center gap-2 flex-wrap pb-1 sm:pb-0">
                <!-- تصفية الجنس -->
                <div class="flex items-center gap-1 bg-[#F8FAFC] p-1 rounded-xl border border-[#D6E0E7]">
                    <button
                        v-for="g in [
                            { id: 'all', label: 'كافة الأجناس' },
                            { id: 'male', label: 'ذكور' },
                            { id: 'female', label: 'إناث' },
                        ]"
                        :key="g.id"
                        @click="genderFilter = g.id"
                        type="button"
                        class="px-2.5 py-1 rounded-lg text-xs font-semibold transition cursor-pointer shrink-0"
                        :class="genderFilter === g.id
                            ? 'bg-[#163E3E] text-white shadow-2xs'
                            : 'text-[#5B6770] hover:text-[#163E3E]'"
                    >
                        {{ g.label }}
                    </button>
                </div>

                <!-- تصفية الحالة -->
                <div class="flex items-center gap-1.5 overflow-x-auto">
                    <button
                        v-for="f in [
                            { id: 'all', label: 'الكل' },
                            { id: 'regular', label: 'المنتظمون' },
                            { id: 'overdue', label: 'المتأخرون' },
                        ]"
                        :key="f.id"
                        @click="statusFilter = f.id"
                        type="button"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition cursor-pointer shrink-0"
                        :class="statusFilter === f.id
                            ? 'bg-[#163E3E] text-white shadow-2xs'
                            : 'bg-[#F8FAFC] text-[#5B6770] hover:text-[#163E3E] border border-[#D6E0E7]'"
                    >
                        {{ f.label }}
                    </button>
                </div>
            </div>
        </div>

        <!-- جدول المستأجرين (Renters Data Table) -->
        <div v-if="filteredRenters.length > 0" class="rounded-3xl border border-[#D6E0E7] bg-white overflow-hidden shadow-2xs">
            <div class="overflow-x-auto">
                <table class="w-full text-right text-sm">
                    <thead>
                        <tr class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] text-xs font-bold">
                            <th class="py-3.5 px-6">المستأجر</th>
                            <th class="py-3.5 px-6">الجنس</th>
                            <th class="py-3.5 px-6">رقم الهوية / الإقامة</th>
                            <th class="py-3.5 px-6">الوحدة المستأجرة</th>
                            <th class="py-3.5 px-6">القيمة الشهرية</th>
                            <th class="py-3.5 px-6">حالة السداد</th>
                            <th class="py-3.5 px-6">WhatsApp</th>
                            <th class="py-3.5 px-6 text-start sm:text-left">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#D6E0E7]">
                        <tr
                            v-for="renter in filteredRenters"
                            :key="renter.id"
                            class="hover:bg-[#F8F4EA] transition group"
                        >
                            <!-- الاسم والجوال -->
                            <td class="py-4 px-6">
                                <Link
                                    :href="route('renters.show', renter.id)"
                                    class="font-bold text-[#163E3E] hover:text-[#163E3E] transition flex items-center gap-2"
                                >
                                    <div class="w-8 h-8 rounded-lg bg-[#E8F0F0] text-[#163E3E] flex items-center justify-center font-bold text-xs shrink-0">
                                        {{ renter.name.charAt(0) }}
                                    </div>
                                    <div class="flex flex-col">
                                        <span>{{ renter.name }}</span>
                                        <span class="text-xs text-[#5B6770] font-normal font-mono" dir="ltr">{{ renter.phone }}</span>
                                    </div>
                                </Link>
                            </td>

                            <!-- الجنس -->
                            <td class="py-4 px-6">
                                <span
                                    v-if="renter.gender"
                                    class="inline-flex items-center gap-1 text-xs font-bold px-2 py-0.5 rounded-lg border"
                                    :class="renter.gender === 'female' ? 'bg-rose-50 text-rose-800 border-rose-200' : 'bg-sky-50 text-sky-800 border-sky-200'"
                                >
                                    <User class="w-3 h-3" />
                                    <span>{{ renter.gender_label || (renter.gender === 'female' ? 'أنثى' : 'ذكر') }}</span>
                                </span>
                                <span v-else class="text-[#8E9CA6] font-normal text-xs">—</span>
                            </td>

                            <!-- الهوية الوطنية -->
                            <td class="py-4 px-6 font-mono text-xs text-[#163E3E]">
                                {{ renter.national_id }}
                            </td>

                            <!-- الوحدة -->
                            <td class="py-4 px-6 text-xs text-[#163E3E]">
                                <div v-if="renter.active_unit" class="space-y-0.5">
                                    <span class="font-bold block">{{ renter.active_unit.unit_name }}</span>
                                    <span class="text-[11px] text-[#5B6770] block">{{ renter.active_unit.property_name }}</span>
                                </div>
                                <span v-else class="text-[#5B6770] italic">لا يوجد عقد نشط</span>
                            </td>

                            <!-- القيمة الشهرية -->
                            <td class="py-4 px-6 font-mono text-xs font-bold text-[#163E3E]">
                                <span v-if="renter.active_unit">
                                    {{ renter.active_unit.rent_amount.toLocaleString() }} ج.م
                                </span>
                                <span v-else class="text-[#5B6770] font-normal">—</span>
                            </td>

                            <!-- حالة السداد -->
                            <td class="py-4 px-6">
                                <StatusBadge :status="renter.status" :label="renter.status_label" size="sm" />
                            </td>

                            <!-- حالة WhatsApp -->
                            <td class="py-4 px-6">
                                <span
                                    v-if="renter.whatsapp_opted_in"
                                    class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200"
                                >
                                    <MessageCircle class="w-3.5 h-3.5 text-emerald-600" />
                                    <span>مفعل</span>
                                </span>
                                <span v-else class="text-xs text-[#5B6770]">غير مفعل</span>
                            </td>

                            <!-- الإجراءات -->
                            <td class="py-4 px-6 text-start sm:text-left">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        v-if="renter.active_unit"
                                        @click="openPaymentForRenter(renter)"
                                        type="button"
                                        class="h-8 px-3 rounded-lg bg-[#E8F0F0] hover:bg-[#163E3E] text-[#163E3E] hover:text-white text-xs font-bold transition flex items-center gap-1 cursor-pointer"
                                        title="تسجيل دفعة سريعة"
                                    >
                                        <CreditCard class="w-3.5 h-3.5" />
                                        <span>دفعة</span>
                                    </button>
                                    <button
                                        v-else
                                        @click="notifyNoActiveLease(renter)"
                                        type="button"
                                        class="h-8 px-3 rounded-lg bg-slate-100 text-[#8A9AA7] hover:bg-amber-50 hover:text-amber-800 text-xs font-semibold flex items-center gap-1 cursor-not-allowed border border-[#D6E0E7]/60 transition"
                                        title="المستأجر غير مرتبط بأي عقد إيجار سارٍ حالياً"
                                    >
                                        <CreditCard class="w-3.5 h-3.5 opacity-40" />
                                        <span>دفعة</span>
                                    </button>

                                    <Link
                                        :href="route('renters.show', renter.id)"
                                        class="h-8 px-3 rounded-lg border border-[#D6E0E7] hover:bg-slate-100 text-[#5B6770] hover:text-[#163E3E] text-xs font-semibold transition flex items-center gap-1"
                                    >
                                        <span>الملف</span>
                                        <span>←</span>
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <EmptyState
            v-else
            type="document"
            title="لم يتم العثور على أي مستأجرين"
            description="لم نجد أي مستأجر يطابق معايير البحث الحالية."
            action-label="إضافة مستأجر جديد"
            @action="showAddModal = true"
        />

        <!-- نافذة إضافة مستأجر جديد (Add Renter Modal) -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="closeModal" class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-lg sm:max-w-xl bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-6 z-10 animate-in fade-in zoom-in-95 my-8" dir="rtl">
                <div class="flex items-center justify-between pb-4 border-b border-[#D6E0E7] mb-5">
                    <div>
                        <h3 class="text-base font-bold text-[#163E3E]">إضافة مستأجر جديد</h3>
                        <p class="text-xs text-[#5B6770] mt-0.5">تسجيل بيانات المستأجر وإرفاق صورة بطاقة الهوية</p>
                    </div>
                    <button @click="closeModal" type="button" class="p-1.5 text-[#5B6770] hover:text-[#163E3E]">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitRenter" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1.5">اسم المستأجر *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            placeholder="مثال: أحمد محمود علي"
                            required
                            minlength="3"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                        />
                        <p v-if="form.errors.name" class="text-xs text-rose-600 font-semibold mt-1">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-[#163E3E] mb-1.5">رقم الهاتف *</label>
                            <input
                                v-model="form.phone"
                                type="tel"
                                placeholder="01XXXXXXXXX"
                                required
                                class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right font-mono"
                            />
                            <p v-if="form.errors.phone" class="text-xs text-rose-600 font-semibold mt-1">
                                {{ form.errors.phone }}
                            </p>
                            <p v-else class="text-[11px] text-[#5B6770] mt-1">
                                رقم مصري (مثال: 01012345678 أو +201012345678)
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-[#163E3E] mb-1.5">الرقم القومي / الهوية <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري)</span></label>
                            <input
                                v-model="form.national_id"
                                type="text"
                                placeholder="الرقم القومي (14 رقم)"
                                class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right"
                            />
                            <p v-if="form.errors.national_id" class="text-xs text-rose-600 font-semibold mt-1">
                                {{ form.errors.national_id }}
                            </p>
                        </div>
                    </div>

                    <!-- الجنس (اختياري لتحديد فئة سكن الشقة) -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold text-[#163E3E]">
                                الجنس <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري)</span>
                            </label>
                            <span class="text-[10px] text-[#5B6770]">يحدد فئة سكن الشقة تلقائياً (شباب / بنات)</span>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                type="button"
                                @click="form.gender = ''"
                                class="h-10 rounded-xl border text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer"
                                :class="!form.gender ? 'border-[#163E3E] bg-[#E8F0F0] text-[#163E3E]' : 'border-[#D6E0E7] bg-white text-[#5B6770] hover:bg-slate-50'"
                            >
                                <span>غير محدد</span>
                            </button>
                            <button
                                type="button"
                                @click="form.gender = 'male'"
                                class="h-10 rounded-xl border text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer"
                                :class="form.gender === 'male' ? 'border-sky-500 bg-sky-50 text-sky-800 ring-1 ring-sky-400' : 'border-[#D6E0E7] bg-white text-[#5B6770] hover:bg-sky-50/50'"
                            >
                                <User class="w-3.5 h-3.5 text-sky-600" />
                                <span>ذكر (شباب)</span>
                            </button>
                            <button
                                type="button"
                                @click="form.gender = 'female'"
                                class="h-10 rounded-xl border text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer"
                                :class="form.gender === 'female' ? 'border-rose-500 bg-rose-50 text-rose-800 ring-1 ring-rose-400' : 'border-[#D6E0E7] bg-white text-[#5B6770] hover:bg-rose-50/50'"
                            >
                                <User class="w-3.5 h-3.5 text-rose-600" />
                                <span>أنثى (بنات)</span>
                            </button>
                        </div>
                        <p v-if="form.errors.gender" class="text-xs text-rose-600 font-semibold mt-1">
                            {{ form.errors.gender }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1.5">البريد الإلكتروني <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري)</span></label>
                        <input
                            v-model="form.email"
                            type="email"
                            placeholder="name@example.com"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right"
                        />
                        <p v-if="form.errors.email" class="text-xs text-rose-600 font-semibold mt-1">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <!-- صور بطاقة الهوية (اختياري) -->
                    <div class="space-y-2 pt-1 border-t border-[#D6E0E7]/60">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-[#163E3E]">
                                صورة بطاقة الهوية / الإقامة <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري)</span>
                            </label>
                            <span class="text-[11px] text-[#5B6770]">JPG, PNG, WEBP حتى 5MB</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <!-- الوجه الأمامي -->
                            <div class="space-y-1.5">
                                <span class="text-[11px] font-semibold text-[#5B6770]">الوجه الأمامي</span>
                                <input
                                    ref="frontInputRef"
                                    type="file"
                                    accept="image/jpeg,image/png,image/jpg,image/webp"
                                    class="hidden"
                                    @change="onFrontFileChange"
                                />

                                <div
                                    v-if="frontPreview"
                                    class="relative rounded-2xl border border-[#163E3E]/30 bg-[#F8FAFC] overflow-hidden group h-32 flex items-center justify-center"
                                >
                                    <img :src="frontPreview" alt="الوجه الأمامي" class="w-full h-full object-cover" />
                                    <div class="absolute inset-0 bg-[#163E3E]/60 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2">
                                        <button
                                            type="button"
                                            @click="frontInputRef?.click()"
                                            class="px-2.5 py-1.5 rounded-lg bg-white/90 text-[#163E3E] text-xs font-bold hover:bg-white transition"
                                        >
                                            تغيير
                                        </button>
                                        <button
                                            type="button"
                                            @click="clearFrontFile"
                                            class="p-1.5 rounded-lg bg-rose-600 text-white hover:bg-rose-700 transition"
                                            title="حذف الصورة"
                                        >
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>

                                <div
                                    v-else
                                    @click="frontInputRef?.click()"
                                    class="h-32 rounded-2xl border-2 border-dashed border-[#D6E0E7] hover:border-[#163E3E] bg-[#F8FAFC] hover:bg-[#E8F0F0]/20 transition cursor-pointer flex flex-col items-center justify-center p-3 text-center gap-1.5 group"
                                >
                                    <div class="w-9 h-9 rounded-xl bg-white group-hover:bg-[#E8F0F0] border border-[#D6E0E7] group-hover:border-[#163E3E]/40 flex items-center justify-center transition">
                                        <Upload class="w-4 h-4 text-[#5B6770] group-hover:text-[#163E3E]" />
                                    </div>
                                    <span class="text-xs font-bold text-[#163E3E]">رفع الوجه الأمامي</span>
                                    <span class="text-[10px] text-[#8A9AA7]">انقر لاختيار ملف</span>
                                </div>
                                <p v-if="form.errors.id_card_front" class="text-xs text-rose-600 font-semibold mt-1">
                                    {{ form.errors.id_card_front }}
                                </p>
                            </div>

                            <!-- الوجه الخلفي -->
                            <div class="space-y-1.5">
                                <span class="text-[11px] font-semibold text-[#5B6770]">الوجه الخلفي</span>
                                <input
                                    ref="backInputRef"
                                    type="file"
                                    accept="image/jpeg,image/png,image/jpg,image/webp"
                                    class="hidden"
                                    @change="onBackFileChange"
                                />

                                <div
                                    v-if="backPreview"
                                    class="relative rounded-2xl border border-[#163E3E]/30 bg-[#F8FAFC] overflow-hidden group h-32 flex items-center justify-center"
                                >
                                    <img :src="backPreview" alt="الوجه الخلفي" class="w-full h-full object-cover" />
                                    <div class="absolute inset-0 bg-[#163E3E]/60 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2">
                                        <button
                                            type="button"
                                            @click="backInputRef?.click()"
                                            class="px-2.5 py-1.5 rounded-lg bg-white/90 text-[#163E3E] text-xs font-bold hover:bg-white transition"
                                        >
                                            تغيير
                                        </button>
                                        <button
                                            type="button"
                                            @click="clearBackFile"
                                            class="p-1.5 rounded-lg bg-rose-600 text-white hover:bg-rose-700 transition"
                                            title="حذف الصورة"
                                        >
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>

                                <div
                                    v-else
                                    @click="backInputRef?.click()"
                                    class="h-32 rounded-2xl border-2 border-dashed border-[#D6E0E7] hover:border-[#163E3E] bg-[#F8FAFC] hover:bg-[#E8F0F0]/20 transition cursor-pointer flex flex-col items-center justify-center p-3 text-center gap-1.5 group"
                                >
                                    <div class="w-9 h-9 rounded-xl bg-white group-hover:bg-[#E8F0F0] border border-[#D6E0E7] group-hover:border-[#163E3E]/40 flex items-center justify-center transition">
                                        <Upload class="w-4 h-4 text-[#5B6770] group-hover:text-[#163E3E]" />
                                    </div>
                                    <span class="text-xs font-bold text-[#163E3E]">رفع الوجه الخلفي</span>
                                    <span class="text-[10px] text-[#8A9AA7]">انقر لاختيار ملف</span>
                                </div>
                                <p v-if="form.errors.id_card_back" class="text-xs text-rose-600 font-semibold mt-1">
                                    {{ form.errors.id_card_back }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <label class="flex items-center gap-3 p-3 rounded-xl bg-[#E8F0F0]/40 border border-[#163E3E]/20 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="form.whatsapp_consent"
                            class="w-4 h-4 rounded text-[#163E3E] focus:ring-[#163E3E]"
                        />
                        <span class="text-xs font-bold text-[#163E3E]">
                            موافقة المستأجر على استلام تذكيرات وسندات القبض عبر WhatsApp
                        </span>
                    </label>

                    <div class="pt-4 flex items-center justify-end gap-3 border-t border-[#D6E0E7]">
                        <button
                            @click="closeModal"
                            type="button"
                            class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold text-[#5B6770] hover:bg-slate-50 transition"
                        >
                            إلغاء
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="h-10 px-5 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold transition flex items-center gap-2 cursor-pointer disabled:opacity-50"
                        >
                            <Check class="w-4 h-4" />
                            <span>{{ form.processing ? 'جاري الإضافة...' : 'حفظ المستأجر' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- نافذة تسجيل دفعة -->
        <ModalRecordPayment
            :show="recordPaymentOpen"
            :renters="renters.map(r => ({
                id: r.id,
                name: r.name,
                phone: r.phone,
                active_lease: r.active_unit ? {
                    id: 0,
                    unit_name: r.active_unit.unit_name,
                    rent_amount: r.active_unit.rent_amount,
                } : null,
            }))"
            :initial-renter-id="activeRenterForPayment?.id"
            :initial-amount="activeRenterForPayment?.active_unit?.rent_amount"
            @close="recordPaymentOpen = false"
        />
    </AppLayout>
</template>
