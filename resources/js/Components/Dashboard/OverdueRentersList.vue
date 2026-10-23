<script setup lang="ts">
import { computed } from 'vue';
import { AlertTriangle, CheckCircle2, CreditCard, MessageCircle, Phone } from 'lucide-vue-next';

interface OverdueRenter {
    id: number;
    renter_id: number;
    renter_name: string;
    renter_phone: string;
    unit_name: string;
    unit_code: string;
    property_name: string;
    amount: number;
    due_date: string;
    days_overdue: number;
}

const props = defineProps<{
    renters: OverdueRenter[];
    currency?: string;
}>();

const emit = defineEmits<{
    (e: 'recordPaymentFor', renter: OverdueRenter): void;
}>();

function formatCurrency(val: number): string {
    return new Intl.NumberFormat('en-US').format(Math.round(val || 0));
}

// إنشاء رابط الواتساب الجاهز مع رسالة تذكير مهذبة باللغة العربية
function getWhatsAppUrl(renter: OverdueRenter): string {
    if (!renter.renter_phone) return '#';
    // تنظيف رقم الهاتف (دعم أرقام مصر)
    let phone = renter.renter_phone.replace(/[^0-9]/g, '');
    if (phone.startsWith('01')) {
        phone = '2' + phone; // إضافة مفتاح مصر الدولي 201
    }

    const message = `السلام عليكم ورحمة الله وبركاته يا فندم،\nنود تذكيركم بمستحقات الإيجار الخاصة بـ (${renter.unit_name} - ${renter.property_name}) وقيمتها ${formatCurrency(renter.amount)} ${props.currency || 'ج.م'}.\nشاكرين جداً لتعاونكم وحرصكم الدائم.`;
    return `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
}
</script>

<template>
    <div class="rounded-2xl border border-[#D6E0E7] bg-white p-6 shadow-xs flex flex-col h-full">
        <!-- الرأس -->
        <div class="flex items-center justify-between pb-4 border-b border-[#D6E0E7]">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center border border-rose-100">
                    <AlertTriangle class="h-4 w-4" />
                </div>
                <div>
                    <h2 class="text-base font-extrabold text-[#0F2747]">أولويات التحصيل وتذكيرات الواتساب</h2>
                    <p class="text-[11px] font-semibold text-[#5B6770]">مستأجرين تجاوزوا موعد استحقاق الإيجار</p>
                </div>
            </div>

            <span v-if="renters.length > 0" class="px-2.5 py-1 rounded-full bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold">
                {{ renters.length }} متأخر
            </span>
        </div>

        <!-- قائمة المستأجرين المتأخرين -->
        <div v-if="renters.length > 0" class="mt-4 divide-y divide-[#D6E0E7] overflow-y-auto max-h-[360px] pr-1">
            <div
                v-for="renter in renters"
                :key="renter.id"
                class="py-3.5 first:pt-0 last:pb-0 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 group"
            >
                <!-- بيانات المستأجر والوحدة -->
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-[#F8FAFC] border border-[#D6E0E7] flex items-center justify-center font-bold text-xs text-[#0F2747] shrink-0 mt-0.5">
                        {{ renter.renter_name.charAt(0) }}
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-xs font-extrabold text-[#0F2747]">{{ renter.renter_name }}</h3>
                            <span class="px-1.5 py-0.5 rounded bg-rose-50 text-rose-600 text-[10px] font-bold border border-rose-100 font-mono">
                                متأخر {{ renter.days_overdue }} يوم
                            </span>
                        </div>
                        <p class="text-[11px] font-semibold text-[#5B6770] mt-0.5">
                            <span>{{ renter.unit_name }}</span>
                            <span v-if="renter.unit_code" class="text-zinc-400 font-mono"> ({{ renter.unit_code }})</span>
                            <span class="mx-1">•</span>
                            <span>{{ renter.property_name }}</span>
                        </p>
                    </div>
                </div>

                <!-- المبلغ والإجراءات السريعة -->
                <div class="flex items-center justify-between sm:justify-end gap-3 shrink-0">
                    <div class="text-right sm:text-left">
                        <span class="text-sm font-black text-rose-600 font-mono">
                            {{ formatCurrency(renter.amount) }}
                        </span>
                        <span class="text-[10px] font-bold text-rose-500 mr-1">{{ currency || 'ج.م' }}</span>
                    </div>

                    <!-- أزرار الإجراء الفوري -->
                    <div class="flex items-center gap-1.5">
                        <!-- زر الواتساب -->
                        <a
                            :href="getWhatsAppUrl(renter)"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white text-[11px] font-bold transition shadow-xs"
                            title="إرسال تذكير سداد على الواتساب"
                        >
                            <MessageCircle class="h-3.5 w-3.5" />
                            <span>تذكير</span>
                        </a>

                        <!-- زر تسجيل السداد -->
                        <button
                            @click="emit('recordPaymentFor', renter)"
                            type="button"
                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-[#D6E0E7] bg-white hover:border-[#0E6E7E] hover:text-[#0E6E7E] text-[11px] font-bold text-[#0F2747] transition shadow-xs"
                            title="تسجيل دفعة إيجار سريعة"
                        >
                            <CreditCard class="h-3.5 w-3.5" />
                            <span>سداد</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- حالة عدم وجود متأخرات (Empty State) -->
        <div v-else class="my-auto py-10 text-center flex flex-col items-center justify-center">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-3 border border-emerald-100">
                <CheckCircle2 class="h-6 w-6" />
            </div>
            <h3 class="text-sm font-extrabold text-[#0F2747]">محفظتك المالية سليمة تماماً!</h3>
            <p class="text-xs font-semibold text-[#5B6770] mt-1 max-w-xs">
                لا توجد أي إيجارات متأخرة حالياً، جميع المستأجرين مسددين لمستحقاتهم في موعدها.
            </p>
        </div>
    </div>
</template>
