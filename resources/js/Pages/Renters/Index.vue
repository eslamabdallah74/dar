<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import ModalRecordPayment from '@/Components/Common/ModalRecordPayment.vue';
import {
    Check,
    CreditCard,
    DollarSign,
    FileText,
    Image as ImageIcon,
    MessageCircle,
    Plus,
    Search,
    Trash2,
    Upload,
    User,
    UserPlus,
    Users,
    X,
} from 'lucide-vue-next';

interface RenterItem {
    id: number;
    name: string;
    phone: string;
    email: string | null;
    national_id: string;
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

        return matchesSearch && matchesStatus;
    });
});

const openPaymentForRenter = (renter: RenterItem) => {
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
        title="المستأجرون — دار"
        active-tab="renters"
        header-title="المستأجرون"
        header-subtitle="دليل المستأجرين ومتابعة الالتزام المالي وعقود الإيجار السارية"
        breadcrumb="المستأجرون"
    >
        <template #actions>
            <button
                @click="showAddModal = true"
                type="button"
                class="h-11 px-5 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-sm font-semibold transition shadow-xs flex items-center gap-2 cursor-pointer"
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
                    <span class="text-2xl font-bold text-[#0F2747] font-mono">{{ stats.total_renters }}</span>
                    <span class="text-xs text-[#5B6770]">ملفات نشطة</span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">العقود السارية</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#0F2747] font-mono">{{ stats.active_contracts }}</span>
                    <span class="text-xs text-[#0E6E7E] font-medium">مستأجرون حاليون</span>
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

        <!-- شريط البحث والتصفية -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-white p-4 rounded-2xl border border-[#D6E0E7] shadow-2xs">
            <div class="relative flex-1 max-w-md">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="ابحث بالاسم، الجوال أو رقم الهوية..."
                    class="w-full h-10 pr-10 pl-4 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] placeholder:text-[#5B6770] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-right"
                />
                <Search class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-[#5B6770]" />
            </div>

            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
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
                        ? 'bg-[#0E6E7E] text-white shadow-2xs'
                        : 'bg-[#F8FAFC] text-[#5B6770] hover:text-[#0F2747] border border-[#D6E0E7]'"
                >
                    {{ f.label }}
                </button>
            </div>
        </div>

        <!-- جدول المستأجرين (Renters Data Table) -->
        <div v-if="filteredRenters.length > 0" class="rounded-3xl border border-[#D6E0E7] bg-white overflow-hidden shadow-2xs">
            <div class="overflow-x-auto">
                <table class="w-full text-right text-sm">
                    <thead>
                        <tr class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] text-xs font-bold">
                            <th class="py-3.5 px-6">المستأجر</th>
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
                            class="hover:bg-[#F3F7FA] transition group"
                        >
                            <!-- الاسم والجوال -->
                            <td class="py-4 px-6">
                                <Link
                                    :href="route('renters.show', renter.id)"
                                    class="font-bold text-[#0F2747] hover:text-[#0E6E7E] transition flex items-center gap-2"
                                >
                                    <div class="w-8 h-8 rounded-lg bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center font-bold text-xs shrink-0">
                                        {{ renter.name.charAt(0) }}
                                    </div>
                                    <div class="flex flex-col">
                                        <span>{{ renter.name }}</span>
                                        <span class="text-xs text-[#5B6770] font-normal font-mono" dir="ltr">{{ renter.phone }}</span>
                                    </div>
                                </Link>
                            </td>

                            <!-- الهوية الوطنية -->
                            <td class="py-4 px-6 font-mono text-xs text-[#0F2747]">
                                {{ renter.national_id }}
                            </td>

                            <!-- الوحدة -->
                            <td class="py-4 px-6 text-xs text-[#0F2747]">
                                <div v-if="renter.active_unit" class="space-y-0.5">
                                    <span class="font-bold block">{{ renter.active_unit.unit_name }}</span>
                                    <span class="text-[11px] text-[#5B6770] block">{{ renter.active_unit.property_name }}</span>
                                </div>
                                <span v-else class="text-[#5B6770] italic">لا يوجد عقد نشط</span>
                            </td>

                            <!-- القيمة الشهرية -->
                            <td class="py-4 px-6 font-mono text-xs font-bold text-[#0F2747]">
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
                                        @click="openPaymentForRenter(renter)"
                                        type="button"
                                        class="h-8 px-3 rounded-lg bg-[#E3F1F4] hover:bg-[#0E6E7E] text-[#0E6E7E] hover:text-white text-xs font-bold transition flex items-center gap-1 cursor-pointer"
                                        title="تسجيل دفعة سريعة"
                                    >
                                        <CreditCard class="w-3.5 h-3.5" />
                                        <span>دفعة</span>
                                    </button>

                                    <Link
                                        :href="route('renters.show', renter.id)"
                                        class="h-8 px-3 rounded-lg border border-[#D6E0E7] hover:bg-slate-100 text-[#5B6770] hover:text-[#0F2747] text-xs font-semibold transition flex items-center gap-1"
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
            <div @click="closeModal" class="fixed inset-0 bg-[#0F2747]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-lg sm:max-w-xl bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-6 z-10 animate-in fade-in zoom-in-95 my-8" dir="rtl">
                <div class="flex items-center justify-between pb-4 border-b border-[#D6E0E7] mb-5">
                    <div>
                        <h3 class="text-base font-bold text-[#0F2747]">إضافة مستأجر جديد</h3>
                        <p class="text-xs text-[#5B6770] mt-0.5">تسجيل بيانات المستأجر وإرفاق صورة بطاقة الهوية</p>
                    </div>
                    <button @click="closeModal" type="button" class="p-1.5 text-[#5B6770] hover:text-[#0F2747]">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitRenter" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">اسم المستأجر *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            placeholder="مثال: أحمد محمود علي"
                            required
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition"
                        />
                        <p v-if="form.errors.name" class="text-xs text-rose-600 font-semibold mt-1">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-[#0F2747] mb-1.5">رقم الهاتف *</label>
                            <input
                                v-model="form.phone"
                                type="text"
                                placeholder="01XXXXXXXXX"
                                required
                                class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-right"
                            />
                            <p v-if="form.errors.phone" class="text-xs text-rose-600 font-semibold mt-1">
                                {{ form.errors.phone }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-[#0F2747] mb-1.5">الرقم القومي / الهوية <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري)</span></label>
                            <input
                                v-model="form.national_id"
                                type="text"
                                placeholder="الرقم القومي (14 رقم)"
                                class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-right"
                            />
                            <p v-if="form.errors.national_id" class="text-xs text-rose-600 font-semibold mt-1">
                                {{ form.errors.national_id }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">البريد الإلكتروني <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري)</span></label>
                        <input
                            v-model="form.email"
                            type="email"
                            placeholder="name@example.com"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-right"
                        />
                        <p v-if="form.errors.email" class="text-xs text-rose-600 font-semibold mt-1">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <!-- صور بطاقة الهوية (اختياري) -->
                    <div class="space-y-2 pt-1 border-t border-[#D6E0E7]/60">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-[#0F2747]">
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
                                    class="relative rounded-2xl border border-[#0E6E7E]/30 bg-[#F8FAFC] overflow-hidden group h-32 flex items-center justify-center"
                                >
                                    <img :src="frontPreview" alt="الوجه الأمامي" class="w-full h-full object-cover" />
                                    <div class="absolute inset-0 bg-[#0F2747]/60 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2">
                                        <button
                                            type="button"
                                            @click="frontInputRef?.click()"
                                            class="px-2.5 py-1.5 rounded-lg bg-white/90 text-[#0F2747] text-xs font-bold hover:bg-white transition"
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
                                    class="h-32 rounded-2xl border-2 border-dashed border-[#D6E0E7] hover:border-[#0E6E7E] bg-[#F8FAFC] hover:bg-[#E3F1F4]/20 transition cursor-pointer flex flex-col items-center justify-center p-3 text-center gap-1.5 group"
                                >
                                    <div class="w-9 h-9 rounded-xl bg-white group-hover:bg-[#E3F1F4] border border-[#D6E0E7] group-hover:border-[#0E6E7E]/40 flex items-center justify-center transition">
                                        <Upload class="w-4 h-4 text-[#5B6770] group-hover:text-[#0E6E7E]" />
                                    </div>
                                    <span class="text-xs font-bold text-[#0F2747]">رفع الوجه الأمامي</span>
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
                                    class="relative rounded-2xl border border-[#0E6E7E]/30 bg-[#F8FAFC] overflow-hidden group h-32 flex items-center justify-center"
                                >
                                    <img :src="backPreview" alt="الوجه الخلفي" class="w-full h-full object-cover" />
                                    <div class="absolute inset-0 bg-[#0F2747]/60 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2">
                                        <button
                                            type="button"
                                            @click="backInputRef?.click()"
                                            class="px-2.5 py-1.5 rounded-lg bg-white/90 text-[#0F2747] text-xs font-bold hover:bg-white transition"
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
                                    class="h-32 rounded-2xl border-2 border-dashed border-[#D6E0E7] hover:border-[#0E6E7E] bg-[#F8FAFC] hover:bg-[#E3F1F4]/20 transition cursor-pointer flex flex-col items-center justify-center p-3 text-center gap-1.5 group"
                                >
                                    <div class="w-9 h-9 rounded-xl bg-white group-hover:bg-[#E3F1F4] border border-[#D6E0E7] group-hover:border-[#0E6E7E]/40 flex items-center justify-center transition">
                                        <Upload class="w-4 h-4 text-[#5B6770] group-hover:text-[#0E6E7E]" />
                                    </div>
                                    <span class="text-xs font-bold text-[#0F2747]">رفع الوجه الخلفي</span>
                                    <span class="text-[10px] text-[#8A9AA7]">انقر لاختيار ملف</span>
                                </div>
                                <p v-if="form.errors.id_card_back" class="text-xs text-rose-600 font-semibold mt-1">
                                    {{ form.errors.id_card_back }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <label class="flex items-center gap-3 p-3 rounded-xl bg-[#E3F1F4]/40 border border-[#0E6E7E]/20 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="form.whatsapp_consent"
                            class="w-4 h-4 rounded text-[#0E6E7E] focus:ring-[#0E6E7E]"
                        />
                        <span class="text-xs font-bold text-[#0F2747]">
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
                            class="h-10 px-5 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-bold transition flex items-center gap-2 cursor-pointer disabled:opacity-50"
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
            :initial-renter-id="activeRenterForPayment?.id"
            :initial-amount="activeRenterForPayment?.active_unit?.rent_amount"
            @close="recordPaymentOpen = false"
        />
    </AppLayout>
</template>

