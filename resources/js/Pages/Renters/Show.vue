<script setup lang="ts">
import { ref } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import ModalRecordPayment from '@/Components/Common/ModalRecordPayment.vue';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import {
    AlertCircle,
    ArrowRight,
    CreditCard,
    Download,
    FileText,
    Mail,
    MessageCircle,
    MessageSquare,
    Paperclip,
    Pencil,
    Phone,
    Printer,
    Shield,
    ShieldCheck,
    Store,
    Trash2,
    Upload,
    User,
    X,
} from 'lucide-vue-next';

interface LedgerItem {
    id: number;
    period: string;
    period_start: string;
    period_end: string;
    due_date: string;
    amount: number;
    paid_amount: number;
    outstanding_balance: number;
    status: string;
    status_label: string;
}

interface PaymentItem {
    id: number;
    reference: string;
    amount: number;
    paid_at: string;
    payment_method: string;
    notes: string | null;
    has_receipt?: boolean;
    receipt_url?: string | null;
}

interface WhatsAppLogItem {
    id: number;
    template: string;
    status: string;
    status_label: string;
    sent_at: string;
    phone: string;
}

interface DocumentItem {
    id: number;
    title: string;
    original_filename: string;
    document_type: string;
    document_type_label: string;
    file_size: string | null;
    created_at: string;
    download_url: string;
}

const props = defineProps<{
    renter: {
        id: number;
        name: string;
        phone: string;
        email: string | null;
        national_id: string;
        gender?: 'male' | 'female' | null;
        gender_label?: string | null;
        whatsapp_opted_in: boolean;
        whatsapp_opt_in_date: string | null;
        notes: string | null;
        created_at: string;
    };
    active_lease: {
        id: number;
        contract_number: string;
        unit_name: string;
        unit_code: string;
        unit_type?: string;
        unit_type_label?: string;
        property_name: string;
        apartment_gender?: {
            status: string;
            label: string;
            short_label: string;
            description: string;
            badge_class: string;
        } | null;
        starts_on: string;
        ends_on: string;
        rent_amount: number;
        billing_interval: string;
        deposit_amount: number;
        deposit_received?: number;
        deposit_status?: string;
        deposit_status_label?: string;
        status: string;
    } | null;
    security_deposits?: Array<{
        lease_id: number;
        contract_number: string;
        unit_name: string;
        unit_type: string;
        unit_type_label: string;
        agreed_amount: number;
        total_received: number;
        total_deductions: number;
        total_refunded: number;
        refundable_balance: number;
        status: string;
        status_label: string;
    }>;
    ledger: LedgerItem[];
    payments: PaymentItem[];
    whatsapp_logs: WhatsAppLogItem[];
    documents?: DocumentItem[];
    summary: {
        total_billed: number;
        total_paid: number;
        total_outstanding: number;
    };
}>();

const activeTab = ref<'ledger' | 'payments' | 'deposits' | 'whatsapp' | 'documents'>('ledger');
const recordPaymentOpen = ref(false);
const noActiveLeaseWarning = ref(false);

const handleOpenPayment = () => {
    if (!props.active_lease) {
        noActiveLeaseWarning.value = true;
        setTimeout(() => {
            noActiveLeaseWarning.value = false;
        }, 6000);
        return;
    }
    recordPaymentOpen.value = true;
};

const documentUploadOpen = ref(false);
const docForm = useForm({
    document_type: 'other' as 'national_id' | 'passport' | 'employment_letter' | 'other',
    title: '',
    file: null as File | null,
});

const onFileSelected = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        docForm.file = target.files[0];
    }
};

const submitDocument = () => {
    if (!docForm.file) return;
    docForm.post(route('renters.documents.store', props.renter.id), {
        onSuccess: () => {
            docForm.reset();
            documentUploadOpen.value = false;
        },
    });
};

const showEditModal = ref(false);
const editForm = useForm({
    name: props.renter.name,
    phone: props.renter.phone,
    national_id: props.renter.national_id === '—' ? '' : props.renter.national_id,
    email: props.renter.email || '',
    gender: (props.renter.gender || '') as '' | 'male' | 'female',
    notes: props.renter.notes || '',
});

const openEditModal = () => {
    editForm.name = props.renter.name;
    editForm.phone = props.renter.phone;
    editForm.national_id = props.renter.national_id === '—' ? '' : props.renter.national_id;
    editForm.email = props.renter.email || '';
    editForm.gender = (props.renter.gender || '') as '' | 'male' | 'female';
    editForm.notes = props.renter.notes || '';
    showEditModal.value = true;
};

const submitEdit = () => {
    editForm.put(route('renters.update', props.renter.id), {
        onSuccess: () => {
            showEditModal.value = false;
        },
    });
};

const confirmDialog = useConfirmDialog();

const deleteDocument = async (renterDocument: DocumentItem) => {
    const confirmed = await confirmDialog.confirm({
        tone: 'danger',
        icon: Trash2,
        title: 'حذف هذا المستند؟',
        message: 'يُحذف الملف من ملف المستأجر نهائياً ولا يمكن استرجاعه.',
        details: [
            { label: 'المستند', value: renterDocument.title },
            { label: 'النوع', value: renterDocument.document_type_label },
        ],
        confirmLabel: 'حذف المستند',
    });

    if (confirmed) {
        router.delete(route('renters.documents.destroy', [props.renter.id, renterDocument.id]));
    }
};
</script>

<template>
    <AppLayout
        :title="`ملف المستأجر ${renter?.name || ''} — Nook Mate`"
        active-tab="renters"
        :header-title="renter?.name || 'مستأجر'"
        :header-subtitle="`ملف المستأجر • هوية رقم: ${renter?.national_id || '—'}`"
        :breadcrumb="`المستأجرون / ${renter?.name || ''}`"
    >
        <template #actions>
            <Link
                :href="route('renters.index')"
                class="h-10 px-4 rounded-xl border border-[#D6E0E7] bg-white hover:bg-[#F8F4EA] text-xs font-semibold text-[#5B6770] transition flex items-center gap-1.5"
            >
                <ArrowRight class="w-4 h-4" />
                <span>العودة للمستأجرين</span>
            </Link>

            <button
                @click="openEditModal"
                type="button"
                class="h-10 px-4 rounded-xl border border-[#D6E0E7] bg-white hover:bg-slate-50 text-xs font-semibold text-[#163E3E] transition flex items-center gap-1.5 cursor-pointer shadow-2xs"
            >
                <Pencil class="w-3.5 h-3.5 text-[#163E3E]" />
                <span>تعديل البيانات</span>
            </button>

            <a
                v-if="renter?.phone"
                :href="`https://wa.me/20${renter.phone.replace(/^0+/, '')}`"
                target="_blank"
                class="h-10 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-xs"
            >
                <MessageCircle class="w-4 h-4" />
                <span>محادثة WhatsApp</span>
            </a>

            <button
                @click="handleOpenPayment"
                type="button"
                :class="[
                    'h-10 px-5 rounded-xl text-xs font-bold transition flex items-center gap-2 shadow-xs',
                    active_lease
                        ? 'bg-[#163E3E] hover:bg-[#102F2F] text-white cursor-pointer'
                        : 'bg-slate-100 hover:bg-slate-200 text-[#8A9AA7] border border-[#D6E0E7] cursor-not-allowed'
                ]"
                :title="!active_lease ? 'المستأجر لا يستأجر أي وحدة حالياً' : 'تسجيل دفعة يدوية'"
            >
                <CreditCard class="w-4 h-4" />
                <span>تسجيل دفعة يدوية</span>
            </button>
        </template>

        <!-- تنبيه في حال محاولة تسجيل دفعة لمستأجر ليس لديه عقد نشط -->
        <div
            v-if="noActiveLeaseWarning"
            class="rounded-2xl bg-amber-50 border border-amber-200 p-4 text-xs font-semibold text-amber-900 flex items-center justify-between gap-3 shadow-xs"
        >
            <div class="flex items-center gap-2.5">
                <AlertCircle class="w-5 h-5 text-amber-600 shrink-0" />
                <span>لا يمكن تسجيل دفعة: هذا المستأجر لا يستأجر أي وحدة حالياً (لا يوجد عقد إيجار سارٍ).</span>
            </div>
            <button
                @click="noActiveLeaseWarning = false"
                type="button"
                class="text-amber-700 hover:text-amber-950 p-1 rounded-lg hover:bg-amber-100/50"
            >
                <X class="w-4 h-4" />
            </button>
        </div>

        <!-- كروت الملخص المالي للمستأجر (3 KPIs) -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">إجمالي المطالبات المفوترة</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#163E3E] font-mono">{{ summary.total_billed.toLocaleString() }} <span class="text-xs font-sans font-normal text-[#5B6770]">ج.م</span></span>
                    <span class="text-xs text-[#5B6770]">إجمالي الفترات</span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">إجمالي المبالغ المسددة</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-emerald-700 font-mono">{{ summary.total_paid.toLocaleString() }} <span class="text-xs font-sans font-normal text-emerald-600">ج.م</span></span>
                    <span class="text-xs text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md font-semibold">
                        سداد منتظم
                    </span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">المتبقي المستحق</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold font-mono" :class="summary.total_outstanding > 0 ? 'text-rose-600' : 'text-[#163E3E]'">
                        {{ summary.total_outstanding.toLocaleString() }} <span class="text-xs font-sans font-normal text-[#5B6770]">ج.م</span>
                    </span>
                    <span
                        class="text-xs px-2 py-0.5 rounded-md font-semibold"
                        :class="summary.total_outstanding > 0 ? 'text-rose-700 bg-rose-50' : 'text-emerald-700 bg-emerald-50'"
                    >
                        {{ summary.total_outstanding > 0 ? 'متأخرات معلقة' : 'لا توجد متأخرات' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- تخطيط الصفحة: الجانب الأيمن معلومات المستأجر والعقد + الجانب الأيسر السجلات والتبويبات -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- العمود الأيمن (معلومات المستأجر والعقد النشط) -->
            <div class="space-y-6">
                <!-- بطاقة البيانات الشخصية للمستأجر -->
                <div class="rounded-3xl border border-[#D6E0E7] bg-white p-6 shadow-2xs space-y-4">
                    <div class="flex items-center gap-3 pb-3 border-b border-[#D6E0E7]">
                        <div class="w-12 h-12 rounded-2xl bg-[#E8F0F0] text-[#163E3E] flex items-center justify-center font-bold text-base">
                            {{ renter.name.charAt(0) }}
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-[#163E3E]">{{ renter.name }}</h3>
                            <span class="text-xs text-[#5B6770]">مسجل منذ {{ renter.created_at }}</span>
                        </div>
                    </div>

                    <div class="space-y-3 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-[#5B6770] flex items-center gap-1.5">
                                <Phone class="w-3.5 h-3.5" />
                                <span>الجوال:</span>
                            </span>
                            <span class="font-mono font-bold text-[#163E3E]" dir="ltr">{{ renter.phone }}</span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-[#5B6770] flex items-center gap-1.5">
                                <User class="w-3.5 h-3.5" />
                                <span>الجنس:</span>
                            </span>
                            <span
                                v-if="renter.gender"
                                class="inline-flex items-center gap-1 font-bold px-2.5 py-0.5 rounded-lg border text-xs"
                                :class="renter.gender === 'female' ? 'bg-rose-50 text-rose-800 border-rose-200' : 'bg-sky-50 text-sky-800 border-sky-200'"
                            >
                                <span>{{ renter.gender_label || (renter.gender === 'female' ? 'أنثى' : 'ذكر') }}</span>
                            </span>
                            <span v-else class="text-[#8E9CA6] font-normal text-xs">— (غير محدد)</span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-[#5B6770] flex items-center gap-1.5">
                                <ShieldCheck class="w-3.5 h-3.5" />
                                <span>رقم الهوية:</span>
                            </span>
                            <span class="font-mono font-bold text-[#163E3E]">{{ renter.national_id }}</span>
                        </div>

                        <div v-if="renter.email" class="flex items-center justify-between">
                            <span class="text-[#5B6770] flex items-center gap-1.5">
                                <Mail class="w-3.5 h-3.5" />
                                <span>البريد:</span>
                            </span>
                            <span class="font-mono text-[#163E3E]">{{ renter.email }}</span>
                        </div>

                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-[#5B6770] flex items-center gap-1.5">
                                <MessageCircle class="w-3.5 h-3.5 text-emerald-600" />
                                <span>موافقة WhatsApp:</span>
                            </span>
                            <span class="text-emerald-800 font-bold bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200">
                                مفعل وموثق
                            </span>
                        </div>
                    </div>

                    <div v-if="renter.notes" class="p-3 rounded-xl bg-[#F8FAFC] border border-[#D6E0E7] text-xs text-[#5B6770]">
                        <span class="font-bold text-[#163E3E] block mb-1">ملاحظات الإدارة:</span>
                        {{ renter.notes }}
                    </div>
                </div>

                <!-- بطاقة العقد الساري -->
                <div v-if="active_lease" class="rounded-3xl border border-[#163E3E]/30 bg-[#E8F0F0]/20 p-6 shadow-2xs space-y-4">
                    <div class="flex items-center justify-between border-b border-[#163E3E]/10 pb-3">
                        <div class="flex items-center gap-2">
                            <FileText class="w-4 h-4 text-[#163E3E]" />
                            <span class="text-sm font-bold text-[#163E3E]">عقد الإيجار الساري</span>
                        </div>
                        <span class="font-mono text-xs font-bold text-[#163E3E] bg-white px-2 py-0.5 rounded border border-[#163E3E]/20">
                            {{ active_lease.contract_number }}
                        </span>
                    </div>

                    <div class="space-y-2.5 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-[#5B6770]">العقار والوحدة:</span>
                            <span class="font-bold text-[#163E3E] flex items-center gap-1">
                                <Store v-if="active_lease.unit_type === 'shop'" class="w-3.5 h-3.5 text-[#163E3E]" />
                                <span>{{ active_lease.unit_name }} ({{ active_lease.property_name }})</span>
                            </span>
                        </div>

                        <div v-if="active_lease.apartment_gender && active_lease.apartment_gender.status !== 'unspecified'" class="flex items-center justify-between">
                            <span class="text-[#5B6770]">تصنيف سكن الشقة:</span>
                            <span
                                class="inline-flex items-center gap-1 font-bold px-2 py-0.5 rounded-md border text-[11px]"
                                :class="[
                                    active_lease.apartment_gender.status === 'male' ? 'bg-sky-50 text-sky-800 border-sky-200' :
                                    active_lease.apartment_gender.status === 'female' ? 'bg-rose-50 text-rose-800 border-rose-200' :
                                    'bg-amber-50 text-amber-800 border-amber-200'
                                ]"
                            >
                                <User v-if="active_lease.apartment_gender.status === 'male' || active_lease.apartment_gender.status === 'female'" class="w-3 h-3" />
                                <Users v-else class="w-3 h-3" />
                                <span>{{ active_lease.apartment_gender.label }}</span>
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-[#5B6770]">القيمة الإيجارية:</span>
                            <span class="font-bold text-[#163E3E] font-mono text-sm">
                                {{ active_lease.rent_amount.toLocaleString() }} ج.م / شهرياً
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-[#5B6770]">مبلغ التأمين المسترد:</span>
                            <span class="font-mono font-bold text-[#163E3E]">
                                {{ active_lease.deposit_amount.toLocaleString() }} ج.م
                                <span v-if="active_lease.deposit_status_label" class="text-[10px] text-[#163E3E] font-sans font-semibold">
                                    ({{ active_lease.deposit_status_label }})
                                </span>
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-[#5B6770]">المحصل من التأمين:</span>
                            <span class="font-mono font-bold text-emerald-700">
                                {{ (active_lease.deposit_received ?? 0).toLocaleString() }} ج.م
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-[#5B6770]">مدة العقد:</span>
                            <span class="font-mono text-[#163E3E]">
                                {{ active_lease.starts_on }} — {{ active_lease.ends_on }}
                            </span>
                        </div>

                        <div class="pt-2 border-t border-[#163E3E]/10">
                            <Link
                                :href="route('leases.show', active_lease.id)"
                                class="w-full h-8 rounded-lg bg-white border border-[#163E3E]/30 hover:bg-[#E8F0F0] text-[#163E3E] text-[11px] font-bold transition flex items-center justify-center gap-1 shadow-2xs"
                            >
                                <span>عرض تفاصيل العقد والتأمين</span>
                                <span>←</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- العمود الأيسر (التبويبات: سجل الفواتير، سندات القبض، ورسائل WhatsApp) -->
            <div class="lg:col-span-2 space-y-4">
                <!-- شريط أزرار التبويبات -->
                <div class="flex items-center gap-2 border-b border-[#D6E0E7] pb-2">
                    <button
                        @click="activeTab = 'ledger'"
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-2"
                        :class="activeTab === 'ledger'
                            ? 'bg-[#163E3E] text-white shadow-2xs'
                            : 'bg-white text-[#5B6770] hover:text-[#163E3E] border border-[#D6E0E7]'"
                    >
                        <FileText class="w-3.5 h-3.5" />
                        <span>سجل الاستحقاقات والفواتير ({{ ledger.length }})</span>
                    </button>

                    <button
                        @click="activeTab = 'payments'"
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-2"
                        :class="activeTab === 'payments'
                            ? 'bg-[#163E3E] text-white shadow-2xs'
                            : 'bg-white text-[#5B6770] hover:text-[#163E3E] border border-[#D6E0E7]'"
                    >
                        <CreditCard class="w-3.5 h-3.5" />
                        <span>سندات القبض المسددة ({{ payments.length }})</span>
                    </button>

                    <button
                        @click="activeTab = 'deposits'"
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-2"
                        :class="activeTab === 'deposits'
                            ? 'bg-[#163E3E] text-white shadow-2xs'
                            : 'bg-white text-[#5B6770] hover:text-[#163E3E] border border-[#D6E0E7]'"
                    >
                        <Shield class="w-3.5 h-3.5" />
                        <span>سجل التأمين المسترد ({{ security_deposits?.length || 0 }})</span>
                    </button>

                    <button
                        @click="activeTab = 'whatsapp'"
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-2"
                        :class="activeTab === 'whatsapp'
                            ? 'bg-[#163E3E] text-white shadow-2xs'
                            : 'bg-white text-[#5B6770] hover:text-[#163E3E] border border-[#D6E0E7]'"
                    >
                        <MessageSquare class="w-3.5 h-3.5" />
                        <span>سجل رسائل WhatsApp ({{ whatsapp_logs.length }})</span>
                    </button>

                    <button
                        @click="activeTab = 'documents'"
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-2"
                        :class="activeTab === 'documents'
                            ? 'bg-[#163E3E] text-white shadow-2xs'
                            : 'bg-white text-[#5B6770] hover:text-[#163E3E] border border-[#D6E0E7]'"
                    >
                        <Paperclip class="w-3.5 h-3.5" />
                        <span>المستندات والوثائق ({{ documents?.length || 0 }})</span>
                    </button>
                </div>

                <!-- 1. تبويب الاستحقاقات والفواتير -->
                <div v-if="activeTab === 'ledger'" class="rounded-3xl border border-[#D6E0E7] bg-white overflow-hidden shadow-2xs">
                    <table class="w-full text-right text-xs">
                        <thead>
                            <tr class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] font-bold">
                                <th class="py-3 px-5">الفترة</th>
                                <th class="py-3 px-5">تاريخ الاستحقاق</th>
                                <th class="py-3 px-5">المبلغ المطلوب</th>
                                <th class="py-3 px-5">المسدد</th>
                                <th class="py-3 px-5">المتبقي</th>
                                <th class="py-3 px-5">الحالة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#D6E0E7]">
                            <tr v-for="item in ledger" :key="item.id" class="hover:bg-[#F8F4EA] transition">
                                <td class="py-3.5 px-5 font-mono font-bold text-[#163E3E]">{{ item.period }}</td>
                                <td class="py-3.5 px-5 font-mono text-[#5B6770]">{{ item.due_date }}</td>
                                <td class="py-3.5 px-5 font-mono font-bold text-[#163E3E]">{{ item.amount.toLocaleString() }} ج.م</td>
                                <td class="py-3.5 px-5 font-mono font-bold text-emerald-700">{{ item.paid_amount.toLocaleString() }} ج.م</td>
                                <td class="py-3.5 px-5 font-mono" :class="item.outstanding_balance > 0 ? 'text-rose-600 font-bold' : 'text-[#5B6770]'">
                                    {{ item.outstanding_balance.toLocaleString() }} ج.م
                                </td>
                                <td class="py-3.5 px-5">
                                    <StatusBadge :status="item.status" :label="item.status_label" size="sm" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 2. تبويب سندات القبض والدفعات -->
                <div v-if="activeTab === 'payments'" class="rounded-3xl border border-[#D6E0E7] bg-white overflow-hidden shadow-2xs">
                    <table class="w-full text-right text-xs">
                        <thead>
                            <tr class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] font-bold">
                                <th class="py-3 px-5">رقم السند</th>
                                <th class="py-3 px-5">تاريخ التحصيل</th>
                                <th class="py-3 px-5">المبلغ</th>
                                <th class="py-3 px-5">طريقة الدفع</th>
                                <th class="py-3 px-5">ملاحظات</th>
                                <th class="py-3 px-5 text-center">المرفقات والسند</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#D6E0E7]">
                            <tr v-for="pay in payments" :key="pay.id" class="hover:bg-[#F8F4EA] transition">
                                <td class="py-3.5 px-5 font-mono font-bold text-[#163E3E]">{{ pay.reference }}</td>
                                <td class="py-3.5 px-5 font-mono text-[#5B6770]">{{ pay.paid_at }}</td>
                                <td class="py-3.5 px-5 font-mono font-bold text-[#163E3E]">{{ pay.amount.toLocaleString() }} ج.م</td>
                                <td class="py-3.5 px-5 text-[#163E3E]">{{ pay.payment_method }}</td>
                                <td class="py-3.5 px-5 text-[#5B6770]">{{ pay.notes || '—' }}</td>
                                <td class="py-3.5 px-5 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <a
                                            v-if="pay.has_receipt && pay.receipt_url"
                                            :href="pay.receipt_url"
                                            target="_blank"
                                            class="h-7 px-2.5 rounded-lg border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-[11px] font-bold transition inline-flex items-center gap-1 shadow-2xs"
                                            title="عرض إشعار التحويل المرفق"
                                        >
                                            <Paperclip class="w-3 h-3 text-emerald-700" />
                                            <span>إشعار التحويل</span>
                                        </a>

                                        <a
                                            :href="route('payments.receipt', pay.id)"
                                            target="_blank"
                                            class="h-7 px-2.5 rounded-lg border border-[#D6E0E7] bg-white hover:bg-slate-50 text-[#163E3E] text-[11px] font-bold transition inline-flex items-center gap-1 shadow-2xs"
                                            title="عرض وطباعة سند القبض"
                                        >
                                            <Printer class="w-3 h-3" />
                                            <span>السند</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 3. تبويب سجل مبالغ التأمين المستردة (مفصول عن الإيجار) -->
                <div v-if="activeTab === 'deposits'" class="rounded-3xl border border-[#D6E0E7] bg-white overflow-hidden shadow-2xs">
                    <div v-if="security_deposits && security_deposits.length > 0">
                        <table class="w-full text-right text-xs">
                            <thead>
                                <tr class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] font-bold">
                                    <th class="py-3 px-5">رقم العقد</th>
                                    <th class="py-3 px-5">الوحدة</th>
                                    <th class="py-3 px-5">التأمين المتفق عليه</th>
                                    <th class="py-3 px-5">المحصل الفعلي</th>
                                    <th class="py-3 px-5">الخصومات</th>
                                    <th class="py-3 px-5">المسترد</th>
                                    <th class="py-3 px-5">الرصيد القابل للرد</th>
                                    <th class="py-3 px-5">حالة التأمين</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#D6E0E7]">
                                <tr v-for="dep in security_deposits" :key="dep.lease_id" class="hover:bg-[#F8F4EA] transition">
                                    <td class="py-3.5 px-5 font-mono font-bold text-[#163E3E]">
                                        <Link :href="route('leases.show', dep.lease_id)" class="hover:underline">
                                            {{ dep.contract_number }}
                                        </Link>
                                    </td>
                                    <td class="py-3.5 px-5">
                                        <div class="flex items-center gap-1 font-bold text-[#163E3E]">
                                            <Store v-if="dep.unit_type === 'shop'" class="w-3.5 h-3.5 text-[#163E3E]" />
                                            <span>{{ dep.unit_name }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-5 font-mono">{{ dep.agreed_amount.toLocaleString() }} ج.م</td>
                                    <td class="py-3.5 px-5 font-mono font-bold text-emerald-700">{{ dep.total_received.toLocaleString() }} ج.م</td>
                                    <td class="py-3.5 px-5 font-mono text-rose-700">{{ dep.total_deductions.toLocaleString() }} ج.م</td>
                                    <td class="py-3.5 px-5 font-mono text-[#163E3E]">{{ dep.total_refunded.toLocaleString() }} ج.م</td>
                                    <td class="py-3.5 px-5 font-mono font-bold text-[#163E3E]">{{ dep.refundable_balance.toLocaleString() }} ج.م</td>
                                    <td class="py-3.5 px-5">
                                        <span class="text-xs font-semibold px-2 py-0.5 rounded-md bg-[#E8F0F0] text-[#163E3E]">
                                            {{ dep.status_label }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="p-8 text-center text-[#5B6770] text-xs">
                        لا توجد سجلات تأمين سابقة أو حالية لهذا المستأجر.
                    </div>
                </div>

                <!-- 3. تبويب رسائل WhatsApp -->
                <div v-if="activeTab === 'whatsapp'" class="rounded-3xl border border-[#D6E0E7] bg-white overflow-hidden shadow-2xs">
                    <table class="w-full text-right text-xs">
                        <thead>
                            <tr class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] font-bold">
                                <th class="py-3 px-5">نوع الإشعار</th>
                                <th class="py-3 px-5">رقم المستلم</th>
                                <th class="py-3 px-5">وقت الإرسال</th>
                                <th class="py-3 px-5">حالة التسليم</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#D6E0E7]">
                            <tr v-for="log in whatsapp_logs" :key="log.id" class="hover:bg-[#F8F4EA] transition">
                                <td class="py-3.5 px-5 font-bold text-[#163E3E] flex items-center gap-2">
                                    <MessageCircle class="w-4 h-4 text-emerald-600 shrink-0" />
                                    <span>{{ log.template }}</span>
                                </td>
                                <td class="py-3.5 px-5 font-mono text-[#5B6770]" dir="ltr">{{ log.phone }}</td>
                                <td class="py-3.5 px-5 font-mono text-[#5B6770]">{{ log.sent_at }}</td>
                                <td class="py-3.5 px-5">
                                    <StatusBadge :status="log.status" :label="log.status_label" size="sm" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 4. تبويب المستندات والوثائق الخاصة -->
                <div v-if="activeTab === 'documents'" class="space-y-4">
                    <div class="flex items-center justify-between bg-white p-5 rounded-3xl border border-[#D6E0E7] shadow-2xs">
                        <div>
                            <h4 class="text-sm font-bold text-[#163E3E]">المستندات والوثائق الخاصة</h4>
                            <p class="text-xs text-[#5B6770] mt-0.5">تخزين آمن ومحمي للهويات الوطنية، عقود الإيجار، والخطابات الرسمية</p>
                        </div>
                        <button
                            @click="documentUploadOpen = !documentUploadOpen"
                            type="button"
                            class="h-9 px-4 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold transition flex items-center gap-1.5 shadow-2xs cursor-pointer"
                        >
                            <Upload class="w-3.5 h-3.5" />
                            <span>{{ documentUploadOpen ? 'إلغاء الرفع' : '+ رفع مستند جديد' }}</span>
                        </button>
                    </div>

                    <!-- نموذج رفع مستند جديد -->
                    <div v-if="documentUploadOpen" class="rounded-3xl border border-[#163E3E]/30 bg-[#E8F0F0]/20 p-5 shadow-2xs space-y-4 animate-in fade-in duration-200">
                        <div class="flex items-center justify-between border-b border-[#163E3E]/10 pb-3">
                            <span class="text-xs font-bold text-[#163E3E] flex items-center gap-1.5">
                                <Upload class="w-4 h-4 text-[#163E3E]" />
                                <span>رفع مستند جديد للمستأجر</span>
                            </span>
                        </div>

                        <form @submit.prevent="submitDocument" class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-[#163E3E] mb-1">نوع المستند *</label>
                                    <select
                                        v-model="docForm.document_type"
                                        required
                                        class="w-full h-10 px-3 rounded-xl border border-[#D6E0E7] bg-white text-xs text-[#163E3E] focus:border-[#163E3E] focus:ring-1 focus:ring-[#163E3E]"
                                    >
                                        <option value="national_id">بطاقة هوية وطنية</option>
                                        <option value="passport">جواز سفر</option>
                                        <option value="employment_letter">خطاب تعريف بالعمل / الراتب</option>
                                        <option value="other">عقد / وثيقة أخرى</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-[#163E3E] mb-1">عنوان / وصف المستند</label>
                                    <input
                                        v-model="docForm.title"
                                        type="text"
                                        placeholder="مثال: صورة عقد إيجار موثق أو بطاقة ضامنة"
                                        class="w-full h-10 px-3 rounded-xl border border-[#D6E0E7] bg-white text-xs text-[#163E3E] focus:border-[#163E3E] focus:ring-1 focus:ring-[#163E3E]"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-[#163E3E] mb-1">الملف (PDF، صورة، DOCX بحد أقصى 10 ميجابايت) *</label>
                                <input
                                    type="file"
                                    required
                                    accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"
                                    @change="onFileSelected"
                                    class="w-full text-xs text-[#5B6770] file:mr-0 file:ml-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-[#163E3E] file:text-white hover:file:bg-[#102F2F] cursor-pointer"
                                />
                                <span v-if="docForm.errors.file" class="text-xs text-rose-600 block mt-1">
                                    {{ docForm.errors.file }}
                                </span>
                            </div>

                            <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#163E3E]/10">
                                <button
                                    type="button"
                                    @click="documentUploadOpen = false"
                                    class="h-9 px-4 rounded-xl border border-[#D6E0E7] bg-white hover:bg-slate-50 text-xs font-bold text-[#5B6770] cursor-pointer"
                                >
                                    إلغاء
                                </button>
                                <button
                                    type="submit"
                                    :disabled="docForm.processing || !docForm.file"
                                    class="h-9 px-5 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer disabled:opacity-50"
                                >
                                    <Upload class="w-3.5 h-3.5" />
                                    <span>{{ docForm.processing ? 'جاري الرفع والتخزين الآمن...' : 'حفظ المستند' }}</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- جدول المستندات المحفوظة -->
                    <div class="rounded-3xl border border-[#D6E0E7] bg-white overflow-hidden shadow-2xs">
                        <table class="w-full text-right text-xs">
                            <thead>
                                <tr class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] font-bold">
                                    <th class="py-3 px-5">المستند</th>
                                    <th class="py-3 px-5">النوع</th>
                                    <th class="py-3 px-5">الحجم</th>
                                    <th class="py-3 px-5">تاريخ الإضافة</th>
                                    <th class="py-3 px-5 text-center">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#D6E0E7]">
                                <tr v-if="!documents || documents.length === 0">
                                    <td colspan="5" class="py-8 px-5 text-center text-[#8A9AA7]">
                                        <Paperclip class="w-6 h-6 mx-auto mb-2 text-[#BAC7D5]" />
                                        <span>لا توجد مستندات مرفقة لهذا المستأجر حتى الآن.</span>
                                    </td>
                                </tr>
                                <tr v-for="doc in documents" :key="doc.id" class="hover:bg-[#F8F4EA] transition">
                                    <td class="py-3.5 px-5 font-bold text-[#163E3E]">
                                        <div class="flex items-center gap-2">
                                            <FileText class="w-4 h-4 text-[#163E3E] shrink-0" />
                                            <div>
                                                <span>{{ doc.title }}</span>
                                                <span class="block text-[10px] text-[#8A9AA7] font-mono mt-0.5">{{ doc.original_filename }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-5">
                                        <span class="px-2 py-0.5 rounded-md bg-slate-100 text-[#5B6770] font-semibold text-[11px]">
                                            {{ doc.document_type_label }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-5 font-mono text-[#5B6770]">{{ doc.file_size || '—' }}</td>
                                    <td class="py-3.5 px-5 font-mono text-[#5B6770]">{{ doc.created_at }}</td>
                                    <td class="py-3.5 px-5 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <a
                                                :href="doc.download_url"
                                                class="h-7 px-2.5 rounded-lg border border-[#D6E0E7] bg-white hover:bg-slate-50 text-[#163E3E] text-[11px] font-bold transition flex items-center gap-1 shadow-2xs"
                                                title="تحميل المستند"
                                            >
                                                <Download class="w-3 h-3" />
                                                <span>تحميل</span>
                                            </a>
                                            <button
                                                @click="deleteDocument(doc)"
                                                type="button"
                                                class="h-7 px-2 rounded-lg border border-rose-200 bg-white hover:bg-rose-50 text-rose-600 transition flex items-center shadow-2xs cursor-pointer"
                                                title="حذف المستند"
                                            >
                                                <Trash2 class="w-3 h-3" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- نافذة تعديل بيانات المستأجر -->
        <div
            v-if="showEditModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
        >
            <div class="bg-white rounded-3xl max-w-lg w-full p-6 space-y-5 shadow-xl border border-[#D6E0E7] max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7]">
                    <div class="flex items-center gap-2">
                        <div class="w-9 h-9 rounded-xl bg-[#E8F0F0] text-[#163E3E] flex items-center justify-center">
                            <Pencil class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-[#163E3E]">تعديل بيانات المستأجر</h3>
                            <p class="text-xs text-[#5B6770]">تحديث معلومات الاتصال، الهوية، والجنس</p>
                        </div>
                    </div>
                    <button
                        @click="showEditModal = false"
                        type="button"
                        class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-[#5B6770] flex items-center justify-center transition cursor-pointer"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitEdit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1.5">الاسم بالكامل *</label>
                        <input
                            v-model="editForm.name"
                            type="text"
                            required
                            minlength="3"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                        />
                        <p v-if="editForm.errors.name" class="text-xs text-rose-600 font-semibold mt-1">
                            {{ editForm.errors.name }}
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-[#163E3E] mb-1.5">رقم الهاتف *</label>
                            <input
                                v-model="editForm.phone"
                                type="tel"
                                required
                                class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition font-mono text-right"
                            />
                            <p v-if="editForm.errors.phone" class="text-xs text-rose-600 font-semibold mt-1">
                                {{ editForm.errors.phone }}
                            </p>
                            <p v-else class="text-[11px] text-[#5B6770] mt-1">
                                رقم مصري (مثال: 01012345678 أو +201012345678)
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-[#163E3E] mb-1.5">الرقم القومي / الهوية</label>
                            <input
                                v-model="editForm.national_id"
                                type="text"
                                class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                            />
                            <p v-if="editForm.errors.national_id" class="text-xs text-rose-600 font-semibold mt-1">
                                {{ editForm.errors.national_id }}
                            </p>
                        </div>
                    </div>

                    <!-- حقل الجنس -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold text-[#163E3E]">
                                الجنس <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري)</span>
                            </label>
                            <span class="text-[10px] text-[#5B6770]">يحدد فئة سكن الشقة (شباب / بنات)</span>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                type="button"
                                @click="editForm.gender = ''"
                                class="h-10 rounded-xl border text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer"
                                :class="!editForm.gender ? 'border-[#163E3E] bg-[#E8F0F0] text-[#163E3E]' : 'border-[#D6E0E7] bg-white text-[#5B6770] hover:bg-slate-50'"
                            >
                                <span>غير محدد</span>
                            </button>
                            <button
                                type="button"
                                @click="editForm.gender = 'male'"
                                class="h-10 rounded-xl border text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer"
                                :class="editForm.gender === 'male' ? 'border-sky-500 bg-sky-50 text-sky-800 ring-1 ring-sky-400' : 'border-[#D6E0E7] bg-white text-[#5B6770] hover:bg-sky-50/50'"
                            >
                                <User class="w-3.5 h-3.5 text-sky-600" />
                                <span>ذكر (شباب)</span>
                            </button>
                            <button
                                type="button"
                                @click="editForm.gender = 'female'"
                                class="h-10 rounded-xl border text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer"
                                :class="editForm.gender === 'female' ? 'border-rose-500 bg-rose-50 text-rose-800 ring-1 ring-rose-400' : 'border-[#D6E0E7] bg-white text-[#5B6770] hover:bg-rose-50/50'"
                            >
                                <User class="w-3.5 h-3.5 text-rose-600" />
                                <span>أنثى (بنات)</span>
                            </button>
                        </div>
                        <p v-if="editForm.errors.gender" class="text-xs text-rose-600 font-semibold mt-1">
                            {{ editForm.errors.gender }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1.5">البريد الإلكتروني</label>
                        <input
                            v-model="editForm.email"
                            type="email"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                        />
                        <p v-if="editForm.errors.email" class="text-xs text-rose-600 font-semibold mt-1">
                            {{ editForm.errors.email }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1.5">ملاحظات الإدارة</label>
                        <textarea
                            v-model="editForm.notes"
                            rows="2"
                            class="w-full p-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition resize-none"
                        ></textarea>
                    </div>

                    <div class="pt-3 flex items-center justify-end gap-3 border-t border-[#D6E0E7]">
                        <button
                            @click="showEditModal = false"
                            type="button"
                            class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold text-[#5B6770] hover:bg-slate-50 transition"
                        >
                            إلغاء
                        </button>
                        <button
                            type="submit"
                            :disabled="editForm.processing"
                            class="h-10 px-5 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold transition flex items-center gap-2 cursor-pointer disabled:opacity-50"
                        >
                            <span>{{ editForm.processing ? 'جاري الحفظ...' : 'حفظ التعديلات' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- نافذة تسجيل دفعة -->
        <ModalRecordPayment
            :show="recordPaymentOpen"
            :renters="[{ id: renter.id, name: renter.name, phone: renter.phone, active_lease: active_lease }]"
            :initial-renter-id="renter.id"
            :initial-amount="active_lease?.rent_amount"
            @close="recordPaymentOpen = false"
        />
    </AppLayout>
</template>
