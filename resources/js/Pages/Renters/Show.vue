<script setup lang="ts">
import { ref } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import ModalRecordPayment from '@/Components/Common/ModalRecordPayment.vue';
import {
    AlertCircle,
    ArrowRight,
    Building2,
    Calendar,
    CheckCircle2,
    Coins,
    CreditCard,
    DollarSign,
    Download,
    FileCheck,
    FileText,
    Home,
    Mail,
    MessageCircle,
    MessageSquare,
    Paperclip,
    Phone,
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

const deleteDocument = (docId: number) => {
    if (confirm('هل أنت متأكد من رغبتك بحذف هذا المستند نهائياً؟')) {
        router.delete(route('renters.documents.destroy', [props.renter.id, docId]));
    }
};
</script>

<template>
    <AppLayout
        :title="`ملف المستأجر ${renter?.name || ''} — دار`"
        active-tab="renters"
        :header-title="renter?.name || 'مستأجر'"
        :header-subtitle="`ملف المستأجر • هوية رقم: ${renter?.national_id || '—'}`"
        :breadcrumb="`المستأجرون / ${renter?.name || ''}`"
    >
        <template #actions>
            <Link
                :href="route('renters.index')"
                class="h-10 px-4 rounded-xl border border-[#D6E0E7] bg-white hover:bg-[#F3F7FA] text-xs font-semibold text-[#5B6770] transition flex items-center gap-1.5"
            >
                <ArrowRight class="w-4 h-4" />
                <span>العودة للمستأجرين</span>
            </Link>

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
                @click="recordPaymentOpen = true"
                type="button"
                class="h-10 px-5 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-bold transition flex items-center gap-2 shadow-xs cursor-pointer"
            >
                <CreditCard class="w-4 h-4" />
                <span>تسجيل دفعة يدوية</span>
            </button>
        </template>

        <!-- كروت الملخص المالي للمستأجر (3 KPIs) -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">إجمالي المطالبات المفوترة</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#0F2747] font-mono">{{ summary.total_billed.toLocaleString() }} <span class="text-xs font-sans font-normal text-[#5B6770]">ج.م</span></span>
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
                    <span class="text-2xl font-bold font-mono" :class="summary.total_outstanding > 0 ? 'text-rose-600' : 'text-[#0F2747]'">
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
                        <div class="w-12 h-12 rounded-2xl bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center font-bold text-base">
                            {{ renter.name.charAt(0) }}
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-[#0F2747]">{{ renter.name }}</h3>
                            <span class="text-xs text-[#5B6770]">مسجل منذ {{ renter.created_at }}</span>
                        </div>
                    </div>

                    <div class="space-y-3 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-[#5B6770] flex items-center gap-1.5">
                                <Phone class="w-3.5 h-3.5" />
                                <span>الجوال:</span>
                            </span>
                            <span class="font-mono font-bold text-[#0F2747]" dir="ltr">{{ renter.phone }}</span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-[#5B6770] flex items-center gap-1.5">
                                <ShieldCheck class="w-3.5 h-3.5" />
                                <span>رقم الهوية:</span>
                            </span>
                            <span class="font-mono font-bold text-[#0F2747]">{{ renter.national_id }}</span>
                        </div>

                        <div v-if="renter.email" class="flex items-center justify-between">
                            <span class="text-[#5B6770] flex items-center gap-1.5">
                                <Mail class="w-3.5 h-3.5" />
                                <span>البريد:</span>
                            </span>
                            <span class="font-mono text-[#0F2747]">{{ renter.email }}</span>
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
                        <span class="font-bold text-[#0F2747] block mb-1">ملاحظات الإدارة:</span>
                        {{ renter.notes }}
                    </div>
                </div>

                <!-- بطاقة العقد الساري -->
                <div v-if="active_lease" class="rounded-3xl border border-[#0E6E7E]/30 bg-[#E3F1F4]/20 p-6 shadow-2xs space-y-4">
                    <div class="flex items-center justify-between border-b border-[#0E6E7E]/10 pb-3">
                        <div class="flex items-center gap-2">
                            <FileText class="w-4 h-4 text-[#0E6E7E]" />
                            <span class="text-sm font-bold text-[#0F2747]">عقد الإيجار الساري</span>
                        </div>
                        <span class="font-mono text-xs font-bold text-[#0E6E7E] bg-white px-2 py-0.5 rounded border border-[#0E6E7E]/20">
                            {{ active_lease.contract_number }}
                        </span>
                    </div>

                    <div class="space-y-2.5 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-[#5B6770]">العقار والوحدة:</span>
                            <span class="font-bold text-[#0F2747] flex items-center gap-1">
                                <Store v-if="active_lease.unit_type === 'shop'" class="w-3.5 h-3.5 text-[#0E6E7E]" />
                                <span>{{ active_lease.unit_name }} ({{ active_lease.property_name }})</span>
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-[#5B6770]">القيمة الإيجارية:</span>
                            <span class="font-bold text-[#0E6E7E] font-mono text-sm">
                                {{ active_lease.rent_amount.toLocaleString() }} ج.م / شهرياً
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-[#5B6770]">مبلغ التأمين المسترد:</span>
                            <span class="font-mono font-bold text-[#0F2747]">
                                {{ active_lease.deposit_amount.toLocaleString() }} ج.م
                                <span v-if="active_lease.deposit_status_label" class="text-[10px] text-[#0E6E7E] font-sans font-semibold">
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
                            <span class="font-mono text-[#0F2747]">
                                {{ active_lease.starts_on }} — {{ active_lease.ends_on }}
                            </span>
                        </div>

                        <div class="pt-2 border-t border-[#0E6E7E]/10">
                            <Link
                                :href="route('leases.show', active_lease.id)"
                                class="w-full h-8 rounded-lg bg-white border border-[#0E6E7E]/30 hover:bg-[#E3F1F4] text-[#0E6E7E] text-[11px] font-bold transition flex items-center justify-center gap-1 shadow-2xs"
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
                            ? 'bg-[#0E6E7E] text-white shadow-2xs'
                            : 'bg-white text-[#5B6770] hover:text-[#0F2747] border border-[#D6E0E7]'"
                    >
                        <FileText class="w-3.5 h-3.5" />
                        <span>سجل الاستحقاقات والفواتير ({{ ledger.length }})</span>
                    </button>

                    <button
                        @click="activeTab = 'payments'"
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-2"
                        :class="activeTab === 'payments'
                            ? 'bg-[#0E6E7E] text-white shadow-2xs'
                            : 'bg-white text-[#5B6770] hover:text-[#0F2747] border border-[#D6E0E7]'"
                    >
                        <CreditCard class="w-3.5 h-3.5" />
                        <span>سندات القبض المسددة ({{ payments.length }})</span>
                    </button>

                    <button
                        @click="activeTab = 'deposits'"
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-2"
                        :class="activeTab === 'deposits'
                            ? 'bg-[#0E6E7E] text-white shadow-2xs'
                            : 'bg-white text-[#5B6770] hover:text-[#0F2747] border border-[#D6E0E7]'"
                    >
                        <Shield class="w-3.5 h-3.5" />
                        <span>سجل التأمين المسترد ({{ security_deposits?.length || 0 }})</span>
                    </button>

                    <button
                        @click="activeTab = 'whatsapp'"
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-2"
                        :class="activeTab === 'whatsapp'
                            ? 'bg-[#0E6E7E] text-white shadow-2xs'
                            : 'bg-white text-[#5B6770] hover:text-[#0F2747] border border-[#D6E0E7]'"
                    >
                        <MessageSquare class="w-3.5 h-3.5" />
                        <span>سجل رسائل WhatsApp ({{ whatsapp_logs.length }})</span>
                    </button>

                    <button
                        @click="activeTab = 'documents'"
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-2"
                        :class="activeTab === 'documents'
                            ? 'bg-[#0E6E7E] text-white shadow-2xs'
                            : 'bg-white text-[#5B6770] hover:text-[#0F2747] border border-[#D6E0E7]'"
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
                            <tr v-for="item in ledger" :key="item.id" class="hover:bg-[#F3F7FA] transition">
                                <td class="py-3.5 px-5 font-mono font-bold text-[#0F2747]">{{ item.period }}</td>
                                <td class="py-3.5 px-5 font-mono text-[#5B6770]">{{ item.due_date }}</td>
                                <td class="py-3.5 px-5 font-mono font-bold text-[#0F2747]">{{ item.amount.toLocaleString() }} ج.م</td>
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
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#D6E0E7]">
                            <tr v-for="pay in payments" :key="pay.id" class="hover:bg-[#F3F7FA] transition">
                                <td class="py-3.5 px-5 font-mono font-bold text-[#0E6E7E]">{{ pay.reference }}</td>
                                <td class="py-3.5 px-5 font-mono text-[#5B6770]">{{ pay.paid_at }}</td>
                                <td class="py-3.5 px-5 font-mono font-bold text-[#0F2747]">{{ pay.amount.toLocaleString() }} ج.م</td>
                                <td class="py-3.5 px-5 text-[#0F2747]">{{ pay.payment_method }}</td>
                                <td class="py-3.5 px-5 text-[#5B6770]">{{ pay.notes || '—' }}</td>
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
                                <tr v-for="dep in security_deposits" :key="dep.lease_id" class="hover:bg-[#F3F7FA] transition">
                                    <td class="py-3.5 px-5 font-mono font-bold text-[#0E6E7E]">
                                        <Link :href="route('leases.show', dep.lease_id)" class="hover:underline">
                                            {{ dep.contract_number }}
                                        </Link>
                                    </td>
                                    <td class="py-3.5 px-5">
                                        <div class="flex items-center gap-1 font-bold text-[#0F2747]">
                                            <Store v-if="dep.unit_type === 'shop'" class="w-3.5 h-3.5 text-[#0E6E7E]" />
                                            <span>{{ dep.unit_name }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-5 font-mono">{{ dep.agreed_amount.toLocaleString() }} ج.م</td>
                                    <td class="py-3.5 px-5 font-mono font-bold text-emerald-700">{{ dep.total_received.toLocaleString() }} ج.م</td>
                                    <td class="py-3.5 px-5 font-mono text-rose-700">{{ dep.total_deductions.toLocaleString() }} ج.م</td>
                                    <td class="py-3.5 px-5 font-mono text-[#0E6E7E]">{{ dep.total_refunded.toLocaleString() }} ج.م</td>
                                    <td class="py-3.5 px-5 font-mono font-bold text-[#0F2747]">{{ dep.refundable_balance.toLocaleString() }} ج.م</td>
                                    <td class="py-3.5 px-5">
                                        <span class="text-xs font-semibold px-2 py-0.5 rounded-md bg-[#E3F1F4] text-[#0E6E7E]">
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
                            <tr v-for="log in whatsapp_logs" :key="log.id" class="hover:bg-[#F3F7FA] transition">
                                <td class="py-3.5 px-5 font-bold text-[#0F2747] flex items-center gap-2">
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
                            <h4 class="text-sm font-bold text-[#0F2747]">المستندات والوثائق الخاصة</h4>
                            <p class="text-xs text-[#5B6770] mt-0.5">تخزين آمن ومحمي للهويات الوطنية، عقود الإيجار، والخطابات الرسمية</p>
                        </div>
                        <button
                            @click="documentUploadOpen = !documentUploadOpen"
                            type="button"
                            class="h-9 px-4 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-bold transition flex items-center gap-1.5 shadow-2xs cursor-pointer"
                        >
                            <Upload class="w-3.5 h-3.5" />
                            <span>{{ documentUploadOpen ? 'إلغاء الرفع' : '+ رفع مستند جديد' }}</span>
                        </button>
                    </div>

                    <!-- نموذج رفع مستند جديد -->
                    <div v-if="documentUploadOpen" class="rounded-3xl border border-[#0E6E7E]/30 bg-[#E3F1F4]/20 p-5 shadow-2xs space-y-4 animate-in fade-in duration-200">
                        <div class="flex items-center justify-between border-b border-[#0E6E7E]/10 pb-3">
                            <span class="text-xs font-bold text-[#0F2747] flex items-center gap-1.5">
                                <Upload class="w-4 h-4 text-[#0E6E7E]" />
                                <span>رفع مستند جديد للمستأجر</span>
                            </span>
                        </div>

                        <form @submit.prevent="submitDocument" class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-[#0F2747] mb-1">نوع المستند *</label>
                                    <select
                                        v-model="docForm.document_type"
                                        required
                                        class="w-full h-10 px-3 rounded-xl border border-[#D6E0E7] bg-white text-xs text-[#0F2747] focus:border-[#0E6E7E] focus:ring-1 focus:ring-[#0E6E7E]"
                                    >
                                        <option value="national_id">بطاقة هوية وطنية</option>
                                        <option value="passport">جواز سفر</option>
                                        <option value="employment_letter">خطاب تعريف بالعمل / الراتب</option>
                                        <option value="other">عقد / وثيقة أخرى</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-[#0F2747] mb-1">عنوان / وصف المستند</label>
                                    <input
                                        v-model="docForm.title"
                                        type="text"
                                        placeholder="مثال: صورة عقد إيجار موثق أو بطاقة ضامنة"
                                        class="w-full h-10 px-3 rounded-xl border border-[#D6E0E7] bg-white text-xs text-[#0F2747] focus:border-[#0E6E7E] focus:ring-1 focus:ring-[#0E6E7E]"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-[#0F2747] mb-1">الملف (PDF، صورة، DOCX بحد أقصى 10 ميجابايت) *</label>
                                <input
                                    type="file"
                                    required
                                    accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"
                                    @change="onFileSelected"
                                    class="w-full text-xs text-[#5B6770] file:mr-0 file:ml-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-[#0E6E7E] file:text-white hover:file:bg-[#0B5A68] cursor-pointer"
                                />
                                <span v-if="docForm.errors.file" class="text-xs text-rose-600 block mt-1">
                                    {{ docForm.errors.file }}
                                </span>
                            </div>

                            <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#0E6E7E]/10">
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
                                    class="h-9 px-5 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer disabled:opacity-50"
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
                                <tr v-for="doc in documents" :key="doc.id" class="hover:bg-[#F3F7FA] transition">
                                    <td class="py-3.5 px-5 font-bold text-[#0F2747]">
                                        <div class="flex items-center gap-2">
                                            <FileText class="w-4 h-4 text-[#0E6E7E] shrink-0" />
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
                                                class="h-7 px-2.5 rounded-lg border border-[#D6E0E7] bg-white hover:bg-slate-50 text-[#0E6E7E] text-[11px] font-bold transition flex items-center gap-1 shadow-2xs"
                                                title="تحميل المستند"
                                            >
                                                <Download class="w-3 h-3" />
                                                <span>تحميل</span>
                                            </a>
                                            <button
                                                @click="deleteDocument(doc.id)"
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

