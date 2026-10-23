<script setup lang="ts">
import { ArrowDownLeft, Banknote, CreditCard, Receipt, Smartphone } from 'lucide-vue-next';

interface RecentPayment {
    id: number;
    renter_name: string;
    amount: number;
    payment_method: string;
    paid_at: string;
    reference?: string;
}

const props = defineProps<{
    payments: RecentPayment[];
    currency?: string;
}>();

function formatCurrency(val: number): string {
    return new Intl.NumberFormat('en-US').format(Math.round(val || 0));
}

function getMethodLabel(method: string): string {
    switch (method) {
        case 'cash': return 'نقداً (كاش)';
        case 'bank_transfer': return 'تحويل بنكي';
        case 'instapay': return 'إنستاباي InstaPay';
        case 'cheque': return 'شيك بنكي';
        default: return 'سداد إلكتروني';
    }
}
</script>

<template>
    <div class="rounded-2xl border border-[#D6E0E7] bg-white p-6 shadow-xs flex flex-col h-full">
        <!-- الرأس -->
        <div class="flex items-center justify-between pb-4 border-b border-[#D6E0E7]">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center border border-emerald-100">
                    <Receipt class="h-4 w-4" />
                </div>
                <div>
                    <h2 class="text-base font-extrabold text-[#0F2747]">أحدث عمليات التحصيل</h2>
                    <p class="text-[11px] font-semibold text-[#5B6770]">سجل المدفوعات المسجلة مؤخراً بالجنيه</p>
                </div>
            </div>
        </div>

        <!-- قائمة العمليات -->
        <div v-if="payments.length > 0" class="mt-4 divide-y divide-[#D6E0E7] overflow-y-auto max-h-[240px]">
            <div
                v-for="payment in payments"
                :key="payment.id"
                class="py-3 first:pt-0 last:pb-0 flex items-center justify-between"
            >
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                        <ArrowDownLeft class="h-4 w-4" />
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-[#0F2747]">{{ payment.renter_name }}</h4>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="text-[10px] font-semibold text-[#5B6770]">
                                {{ getMethodLabel(payment.payment_method) }}
                            </span>
                            <span class="text-zinc-300">•</span>
                            <span class="text-[10px] text-[#5B6770] font-mono">{{ payment.paid_at }}</span>
                        </div>
                    </div>
                </div>

                <div class="text-left font-mono">
                    <span class="text-xs font-black text-emerald-700">
                        +{{ formatCurrency(payment.amount) }}
                    </span>
                    <span class="text-[10px] font-bold text-emerald-600 mr-1">{{ currency || 'ج.م' }}</span>
                </div>
            </div>
        </div>

        <div v-else class="my-auto py-8 text-center text-xs text-[#5B6770] font-semibold">
            لم تسجل أي دفعات إيجار بعد في هذا الشهر.
        </div>
    </div>
</template>
