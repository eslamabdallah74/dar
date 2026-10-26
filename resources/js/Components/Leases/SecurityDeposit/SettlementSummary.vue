<script setup lang="ts">
import { computed } from 'vue';
import { CheckCircle2, Clock, Download, Printer } from 'lucide-vue-next';

interface Settlement {
    status: string;
    settlement_type_label?: string | null;
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

const props = defineProps<{
    settlement: Settlement;
    currencySymbol: string;
    exportUrl: string;
    statementUrl?: string;
}>();

const totalRefundDue = computed(() => (props.settlement.total_refunded || 0) + (props.settlement.refundable_balance || 0));
const retainedAmount = computed(() => (props.settlement.total_deductions !== undefined && props.settlement.total_deductions !== null)
    ? Number(props.settlement.total_deductions)
    : Math.max(0, (props.settlement.total_received || 0) - totalRefundDue.value)
);
const isSettled = computed(() => props.settlement.status === 'settled');
</script>

<template>
    <section class="rounded-xl border border-[#E6ECF0]" aria-labelledby="settlement-heading">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-[#E6ECF0] px-4 py-3">
            <div>
                <h3 id="settlement-heading" class="text-sm font-bold text-[#163E3E]">{{ isSettled ? 'قرار التسوية' : 'فحص الوحدة' }}</h3>
                <p class="mt-0.5 text-xs text-[#5B6770]">
                    {{ isSettled ? settlement.settlement_type_label || 'تسوية معتمدة' : 'التسوية لم تُعتمد بعد' }}
                    <span v-if="settlement.settled_at"> · <span class="font-mono tabular-nums">{{ settlement.settled_at }}</span></span>
                </p>
            </div>
            <div v-if="isSettled" class="flex flex-wrap items-center gap-2">
                <a
                    v-if="statementUrl"
                    :href="statementUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex h-9 items-center gap-2 rounded-xl bg-[#163E3E] px-3.5 text-xs font-semibold text-white transition hover:bg-[#102F2F]"
                >
                    <Printer class="h-4 w-4" />
                    طباعة بيان التسوية
                </a>
                <a
                    :href="exportUrl"
                    class="inline-flex h-9 items-center gap-2 rounded-xl border border-[#D6E0E7] bg-white px-3 text-xs font-semibold text-[#163E3E] transition hover:bg-[#F8F4EA]"
                >
                    <Download class="h-4 w-4" />
                    تصدير Excel
                </a>
            </div>
        </header>

        <dl v-if="isSettled" class="grid grid-cols-2 divide-x divide-x-reverse divide-[#E6ECF0] border-b border-[#E6ECF0]">
            <div class="px-4 py-3">
                <dt class="text-xs text-[#5B6770]">يُرد للمستأجر</dt>
                <dd class="mt-1 font-mono text-base font-semibold tabular-nums text-[#163E3E]">
                    {{ totalRefundDue.toLocaleString() }} <span class="font-sans text-xs font-medium text-[#5B6770]">{{ currencySymbol }}</span>
                </dd>
            </div>
            <div class="px-4 py-3">
                <dt class="text-xs text-[#5B6770]">يحتفظ به المالك</dt>
                <dd class="mt-1 font-mono text-base font-semibold tabular-nums" :class="retainedAmount > 0 ? 'text-rose-700' : 'text-[#163E3E]'">
                    {{ retainedAmount.toLocaleString() }} <span class="font-sans text-xs font-medium text-[#5B6770]">{{ currencySymbol }}</span>
                </dd>
            </div>
        </dl>

        <div class="space-y-2 px-4 py-3 text-xs leading-5 text-[#5B6770]">
            <p v-if="isSettled" class="flex flex-wrap items-center gap-1.5">
                <CheckCircle2 v-if="settlement.refund_status === 'refunded'" class="h-4 w-4 text-emerald-700" />
                <Clock v-else-if="settlement.refund_status === 'pending'" class="h-4 w-4 text-amber-700" />
                <span class="font-semibold text-[#163E3E]">حالة الرد: {{ settlement.refund_status_label || 'قيد المراجعة' }}</span>
                <span v-if="settlement.refund_payment_date">· <span class="font-mono tabular-nums">{{ settlement.refund_payment_date }}</span></span>
            </p>
            <p><span class="font-semibold text-[#163E3E]">نتيجة الفحص:</span> {{ settlement.inspection_category_label }}</p>
            <p v-if="settlement.retention_reason"><span class="font-semibold text-[#163E3E]">سبب الاحتفاظ:</span> {{ settlement.retention_reason }}</p>
            <p v-if="settlement.inspection_notes"><span class="font-semibold text-[#163E3E]">ملاحظات الفحص:</span> {{ settlement.inspection_notes }}</p>
            <p v-if="settlement.notes"><span class="font-semibold text-[#163E3E]">ملاحظات التسوية:</span> {{ settlement.notes }}</p>
            <p v-if="settlement.settled_by">اعتمدها {{ settlement.settled_by }}</p>
        </div>
    </section>
</template>
