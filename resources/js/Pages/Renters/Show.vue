<script setup lang="ts">
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
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
    FileCheck,
    FileText,
    Home,
    Mail,
    MessageCircle,
    MessageSquare,
    Phone,
    ShieldCheck,
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
        property_name: string;
        starts_on: string;
        ends_on: string;
        rent_amount: number;
        billing_interval: string;
        deposit_amount: number;
        status: string;
    } | null;
    ledger: LedgerItem[];
    payments: PaymentItem[];
    whatsapp_logs: WhatsAppLogItem[];
    summary: {
        total_billed: number;
        total_paid: number;
        total_outstanding: number;
    };
}>();

const activeTab = ref<'ledger' | 'payments' | 'whatsapp'>('ledger');
const recordPaymentOpen = ref(false);
</script>

<template>
    <AppLayout
        :title="`ملف المستأجر ${renter.name} — دار`"
        active-tab="renters"
        :header-title="renter.name"
        :header-subtitle="`ملف المستأجر • هوية رقم: ${renter.national_id}`"
        :breadcrumb="`المستأجرون / ${renter.name}`"
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
                            <span class="font-bold text-[#0F2747]">{{ active_lease.unit_name }} ({{ active_lease.property_name }})</span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-[#5B6770]">القيمة الإيجارية:</span>
                            <span class="font-bold text-[#0E6E7E] font-mono text-sm">
                                {{ active_lease.rent_amount.toLocaleString() }} ج.م / شهرياً
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-[#5B6770]">مبلغ التأمين / الوديعة:</span>
                            <span class="font-mono font-bold text-[#0F2747]">
                                {{ active_lease.deposit_amount.toLocaleString() }} ج.م
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-[#5B6770]">مدة العقد:</span>
                            <span class="font-mono text-[#0F2747]">
                                {{ active_lease.starts_on }} — {{ active_lease.ends_on }}
                            </span>
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

