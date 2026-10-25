<script setup lang="ts">
import { computed } from 'vue';
import { CheckCircle2, Clock, Download, FileCheck, Paperclip, Printer } from 'lucide-vue-next';

interface Settlement {
    status: string;
    settlement_type_label?: string | null;
    original_deposit?: number | null;
    retention_reason?: string | null;
    inspection_category_label: string;
    inspection_notes: string | null;
    total_received?: number;
    total_deductions?: number;
    total_refunded?: number;
    refundable_balance?: number;
    refund_status?: 'pending' | 'refunded' | 'not_applicable';
    refund_status_label?: string;
    refund_payment_date?: string | null;
    settled_at: string | null;
    settled_by: string | null;
    notes: string | null;
}

interface Deduction {
    id: number;
    amount: number;
    category_label: string;
    description: string;
    notes?: string | null;
    attachment_url?: string | null;
    status: string;
    deducted_at: string;
}

const props = defineProps<{
    settlement: Settlement;
    deductions: Deduction[];
    currencySymbol: string;
    exportUrl: string;
    statementUrl?: string;
}>();

const confirmedDeductions = computed(() => props.deductions.filter((deduction) => deduction.status === 'confirmed'));
const totalRefundDue = computed(() => (props.settlement.total_refunded || 0) + (props.settlement.refundable_balance || 0));
const retainedAmount = computed(() => (props.settlement.total_deductions !== undefined && props.settlement.total_deductions !== null)
    ? Number(props.settlement.total_deductions)
    : Math.max(0, (props.settlement.total_received || 0) - totalRefundDue.value)
);
</script>

<template>
    <section class="space-y-4 rounded-2xl border border-[#D6E0E7] bg-[#F8FAFC] p-4 sm:p-5" dir="rtl">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-[#D6E0E7] pb-3">
            <div class="flex items-center gap-2.5">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#163E3E] text-white">
                    <FileCheck class="h-4 w-4" />
                </span>
                <div>
                    <h4 class="text-sm font-bold text-[#163E3E]">قرار تسوية التأمين</h4>
                    <p class="text-[11px] text-[#5B6770]">
                        {{ settlement.settlement_type_label || 'التسوية قيد المراجعة' }}
                        <span v-if="settlement.settled_at"> · {{ settlement.settled_at }}</span>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a
                    v-if="settlement.status === 'settled' && statementUrl"
                    :href="statementUrl"
                    target="_blank"
                    class="inline-flex h-9 items-center gap-2 rounded-xl bg-[#163E3E] px-3.5 text-xs font-bold text-white hover:bg-[#102F2F] shadow-xs transition"
                >
                    <Printer class="h-4 w-4" />
                    طباعة بيان التسوية
                </a>
                <a
                    v-if="settlement.status === 'settled'"
                    :href="exportUrl"
                    class="inline-flex h-9 items-center gap-2 rounded-xl border border-[#163E3E]/25 bg-white px-3 text-xs font-bold text-[#163E3E] hover:bg-[#E8F0F0] transition"
                >
                    <Download class="h-4 w-4" />
                    تصدير Excel
                </a>
            </div>
        </header>

        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
            <div class="rounded-xl border border-[#D6E0E7] bg-white p-3">
                <span class="block text-[10px] font-semibold text-[#5B6770]">التأمين المستلم</span>
                <strong class="mt-1 block font-mono text-sm text-[#163E3E]">
                    {{ (settlement.total_received || 0).toLocaleString() }} {{ currencySymbol }}
                </strong>
            </div>
            <div class="rounded-xl border border-rose-200 bg-white p-3">
                <span class="block text-[10px] font-semibold text-rose-700">إجمالي الخصومات</span>
                <strong class="mt-1 block font-mono text-sm text-rose-700">
                    {{ (settlement.total_deductions || 0).toLocaleString() }} {{ currencySymbol }}
                </strong>
            </div>
            <div class="rounded-xl border border-[#D6E0E7] bg-white p-3">
                <span class="block text-[10px] font-semibold text-[#5B6770]">إجمالي المستحق رده</span>
                <strong class="mt-1 block font-mono text-sm text-[#163E3E]">
                    {{ totalRefundDue.toLocaleString() }} {{ currencySymbol }}
                </strong>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-white p-3">
                <span class="block text-[10px] font-semibold text-emerald-700">المتبقي للصرف</span>
                <strong class="mt-1 block font-mono text-sm text-emerald-700">
                    {{ (settlement.refundable_balance || 0).toLocaleString() }} {{ currencySymbol }}
                </strong>
            </div>
            <div class="rounded-xl border border-[#D6E0E7] bg-white p-3">
                <span class="block text-[10px] font-semibold text-[#5B6770]">المردود فعلياً</span>
                <strong class="mt-1 block font-mono text-sm text-[#163E3E]">
                    {{ (settlement.total_refunded || 0).toLocaleString() }} {{ currencySymbol }}
                </strong>
            </div>
            <div class="rounded-xl border border-amber-200 bg-white p-3">
                <span class="block text-[10px] font-semibold text-amber-700">المحتفظ به</span>
                <strong class="mt-1 block font-mono text-sm text-amber-700">
                    {{ retainedAmount.toLocaleString() }} {{ currencySymbol }}
                </strong>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 rounded-xl border border-[#D6E0E7] bg-white px-3 py-2.5 text-xs">
            <CheckCircle2 v-if="settlement.refund_status === 'refunded'" class="h-4 w-4 text-emerald-700" />
            <Clock v-else class="h-4 w-4 text-amber-700" />
            <span class="font-semibold text-[#163E3E]">حالة الرد: {{ settlement.refund_status_label || 'قيد المراجعة' }}</span>
            <span v-if="settlement.refund_payment_date" class="text-[#5B6770]">· {{ settlement.refund_payment_date }}</span>
            <span class="text-[#5B6770]">· نتيجة الفحص: {{ settlement.inspection_category_label }}</span>
        </div>

        <div>
            <div class="mb-2 flex items-center justify-between gap-2">
                <h5 class="text-xs font-bold text-[#163E3E]">تفاصيل الخصومات</h5>
                <span class="text-[11px] text-[#5B6770]">{{ confirmedDeductions.length }} بنود</span>
            </div>
            <div v-if="confirmedDeductions.length" class="divide-y divide-[#D6E0E7] rounded-xl border border-[#D6E0E7] bg-white">
                <article v-for="deduction in confirmedDeductions" :key="deduction.id" class="flex flex-wrap items-start justify-between gap-2 p-3 text-xs">
                    <div class="min-w-0 flex-1">
                        <p class="font-bold text-[#163E3E]">{{ deduction.description }}</p>
                        <p class="mt-1 text-[11px] text-[#5B6770]">{{ deduction.category_label }} · {{ deduction.deducted_at }}</p>
                        <p v-if="deduction.notes" class="mt-1 text-[11px] text-[#5B6770]">{{ deduction.notes }}</p>
                        <a
                            v-if="deduction.attachment_url"
                            :href="deduction.attachment_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold text-[#163E3E] underline"
                        >
                            <Paperclip class="h-3 w-3" />
                            عرض المرفق
                        </a>
                    </div>
                    <strong class="shrink-0 font-mono text-rose-700">−{{ deduction.amount.toLocaleString() }} {{ currencySymbol }}</strong>
                </article>
            </div>
            <p v-else class="rounded-xl border border-dashed border-[#D6E0E7] bg-white p-3 text-center text-xs text-[#5B6770]">
                لا توجد خصومات مسجلة؛ يعاد كامل المبلغ المستلم.
            </p>
        </div>

        <div v-if="settlement.retention_reason || settlement.inspection_notes || settlement.notes" class="space-y-1 text-xs leading-5 text-[#5B6770]">
            <p v-if="settlement.retention_reason"><strong class="text-[#163E3E]">سبب الاحتفاظ:</strong> {{ settlement.retention_reason }}</p>
            <p v-if="settlement.inspection_notes"><strong class="text-[#163E3E]">ملاحظات الفحص:</strong> {{ settlement.inspection_notes }}</p>
            <p v-if="settlement.notes"><strong class="text-[#163E3E]">ملاحظات التسوية:</strong> {{ settlement.notes }}</p>
        </div>
        <p v-if="settlement.settled_by" class="border-t border-[#D6E0E7] pt-3 text-[11px] text-[#5B6770]">
            اعتمدت التسوية بواسطة {{ settlement.settled_by }}
        </p>
    </section>
</template>
