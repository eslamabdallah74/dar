<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { AlertOctagon, AlertTriangle, Check, History, X } from 'lucide-vue-next';

interface PaymentReceiptItem {
    id: number;
    reference: string;
    renter_id: number;
    renter_name: string;
    renter_phone?: string;
    unit_name: string;
    property_name?: string;
    amount: number;
    paid_at: string;
    raw_paid_at?: string;
    created_at?: string;
    raw_created_at?: string;
    payment_method: string;
    method: string;
    notes: string | null;
    has_receipt?: boolean;
    receipt_url?: string | null;
    is_corrected?: boolean;
    corrections_count?: number;
}

const props = defineProps<{
    show: boolean;
    payment: PaymentReceiptItem | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const page = usePage();
const currencySymbol = computed(() => {
    const curr = (page.props as any).auth?.workspace?.currency || 'EGP';
    switch (curr.toUpperCase()) {
        case 'EGP': return 'ج.م';
        case 'SAR': return 'ر.س';
        case 'AED': return 'د.إ';
        case 'USD': return '$';
        default: return curr;
    }
});

const form = useForm({
    amount: '',
    paid_at: '',
    record_date: '',
    payment_method: 'bank_transfer',
    reference: '',
    notes: '',
    reason: '',
});

watch(
    () => props.payment,
    (p) => {
        if (p) {
            form.amount = String(p.amount);
            form.paid_at = p.raw_paid_at || new Date().toISOString().split('T')[0];
            form.record_date = p.raw_created_at || new Date().toISOString().split('T')[0];
            form.payment_method = p.payment_method || 'bank_transfer';
            form.reference = p.reference || '';
            form.notes = p.notes || '';
            form.reason = '';
            form.clearErrors();
        }
    },
    { immediate: true }
);

const submit = () => {
    if (!props.payment) return;

    form.put(route('payments.update', props.payment.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
        },
    });
};
</script>

<template>
    <div
        v-if="show && payment"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs transition-opacity"
        dir="rtl"
    >
        <div class="relative w-full max-w-lg bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] overflow-hidden flex flex-col max-h-[90vh]">
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#D6E0E7] bg-[#F8FAFC]">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-2xl bg-amber-500/10 text-amber-700 flex items-center justify-center">
                        <History class="w-5 h-5 stroke-[2.2]" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-[#163E3E]">تصحيح وتعديل سند القبض</h3>
                        <p class="text-xs text-[#5B6770]">
                            {{ payment.reference }} · المستأجر: {{ payment.renter_name }}
                        </p>
                    </div>
                </div>

                <button
                    @click="emit('close')"
                    type="button"
                    class="w-8 h-8 rounded-xl text-[#5B6770] hover:text-[#163E3E] hover:bg-[#E8F0F0] flex items-center justify-center transition cursor-pointer"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- Body -->
            <form @submit.prevent="submit" class="p-6 space-y-4 overflow-y-auto">
                <!-- تنبيه عند وجود أخطاء في النموذج -->
                <div
                    v-if="form.hasErrors"
                    class="p-4 sm:p-5 rounded-2xl bg-[#FEF2F2] border-2 border-rose-400 text-rose-950 shadow-md flex items-start gap-3.5 border-r-8 border-r-rose-600"
                    style="background-color: #FEF2F2;"
                >
                    <div
                        class="p-2.5 rounded-xl bg-rose-100 text-rose-700 border border-rose-300 shrink-0 mt-0.5"
                        style="background-color: #FFE4E6;"
                    >
                        <AlertOctagon class="w-6 h-6 text-rose-600" />
                    </div>
                    <div class="space-y-1.5 flex-1 min-w-0">
                        <h4 class="text-sm font-bold text-rose-950">تعذر تعديل السند لوجود الملاحظات التالية:</h4>
                        <ul class="space-y-1.5 text-xs text-rose-900 font-semibold">
                            <li v-for="(error, key) in form.errors" :key="key" class="flex items-start gap-2 leading-relaxed">
                                <span class="text-rose-600 font-bold shrink-0">•</span>
                                <span>{{ error }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- تنبيه الحوكمة الرقابية -->
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-3.5 text-xs text-amber-900 space-y-1" style="background-color: #FFFBEB;">
                    <div class="flex items-center gap-1.5 font-bold">
                        <AlertTriangle class="w-4 h-4 text-amber-600 shrink-0" />
                        <span>حوكمة التعديل المالي والرقابي (Section 6)</span>
                    </div>
                    <p class="text-[11px] leading-relaxed text-amber-800">
                        الحذف النهائي للمعاملات المالية محظور. سيؤدي التعديل إلى إعادة حساب وتوزيع الالتزامات الإيجارية تلقائياً وتوثيق التغييرات بالكامل مع السبب والمستخدم في السجل الرقابي.
                    </p>
                </div>

                <!-- المبلغ وتاريخ التحصيل وتاريخ القيد -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">
                            المبلغ المعدل <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <input
                                v-model="form.amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                required
                                class="w-full h-10 pl-10 pr-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs font-bold text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right"
                                :class="{ 'border-rose-500 bg-[#FFF5F5] ring-1 ring-rose-500': form.errors.amount }"
                            />
                            <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-[11px] font-semibold text-[#5B6770]">
                                {{ currencySymbol }}
                            </span>
                        </div>
                        <div v-if="form.errors.amount" class="mt-1.5 p-2 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-1.5" style="background-color: #FFF1F2;">
                            <AlertOctagon class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                            <span>{{ form.errors.amount }}</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">
                            تاريخ التحصيل الفعلي <span class="text-rose-500">*</span>
                        </label>
                        <input
                            v-model="form.paid_at"
                            type="date"
                            required
                            class="w-full h-10 px-2.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right"
                            :class="{ 'border-rose-500 bg-[#FFF5F5] ring-1 ring-rose-500': form.errors.paid_at }"
                        />
                        <div v-if="form.errors.paid_at" class="mt-1.5 p-2 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-1.5" style="background-color: #FFF1F2;">
                            <AlertOctagon class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                            <span>{{ form.errors.paid_at }}</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">
                            تاريخ القيد والتسجيل
                        </label>
                        <input
                            v-model="form.record_date"
                            type="date"
                            class="w-full h-10 px-2.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right"
                            :class="{ 'border-rose-500 bg-[#FFF5F5] ring-1 ring-rose-500': form.errors.record_date }"
                        />
                        <div v-if="form.errors.record_date" class="mt-1.5 p-2 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-1.5" style="background-color: #FFF1F2;">
                            <AlertOctagon class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                            <span>{{ form.errors.record_date }}</span>
                        </div>
                    </div>
                </div>

                <!-- طريقة الدفع ورقم المرجع -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">
                            طريقة السداد <span class="text-rose-500">*</span>
                        </label>
                        <select
                            v-model="form.payment_method"
                            required
                            class="w-full h-10 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                            :class="{ 'border-rose-500 bg-[#FFF5F5] ring-1 ring-rose-500': form.errors.payment_method }"
                        >
                            <option value="bank_transfer">تحويل بنكي / إنستاباي</option>
                            <option value="other">بطاقة بنكية / إلكتروني</option>
                            <option value="cash">نقدي (كاش)</option>
                            <option value="cheque">شيك مصرفي</option>
                        </select>
                        <div v-if="form.errors.payment_method" class="mt-1.5 p-2 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-1.5" style="background-color: #FFF1F2;">
                            <AlertOctagon class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                            <span>{{ form.errors.payment_method }}</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1">
                            رقم المرجع / الإيصال
                        </label>
                        <input
                            v-model="form.reference"
                            type="text"
                            class="w-full h-10 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-[#163E3E] font-mono focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right"
                            :class="{ 'border-rose-500 bg-[#FFF5F5] ring-1 ring-rose-500': form.errors.reference }"
                        />
                        <div v-if="form.errors.reference" class="mt-1.5 p-2 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-1.5" style="background-color: #FFF1F2;">
                            <AlertOctagon class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                            <span>{{ form.errors.reference }}</span>
                        </div>
                    </div>
                </div>

                <!-- سبب التعديل والتصحيح (إلزامي للرقابة المالية) -->
                <div>
                    <label class="block text-xs font-bold text-[#163E3E] mb-1">
                        سبب التعديل والتصحيح المالي <span class="text-rose-500">* (إلزامي للتوثيق الرقابي)</span>
                    </label>
                    <textarea
                        v-model="form.reason"
                        rows="2"
                        required
                        placeholder="اذكر بوضوح سبب تعديل السند (مثال: تصحيح خطأ إدخال في المبلغ، تعديل تاريخ التحصيل، إلخ...)"
                        class="w-full p-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-[#163E3E] placeholder:text-[#8A9AA7] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                        :class="{ 'border-rose-500 bg-[#FFF5F5] ring-1 ring-rose-500': form.errors.reason }"
                    ></textarea>
                    <div v-if="form.errors.reason" class="mt-1.5 p-2 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-1.5" style="background-color: #FFF1F2;">
                        <AlertOctagon class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                        <span>{{ form.errors.reason }}</span>
                    </div>
                </div>

                <!-- ملاحظات السند -->
                <div>
                    <label class="block text-xs font-bold text-[#163E3E] mb-1">
                        ملاحظات السند العامة <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري)</span>
                    </label>
                    <input
                        v-model="form.notes"
                        type="text"
                        placeholder="أي ملاحظات إضافية على السند..."
                        class="w-full h-10 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                        :class="{ 'border-rose-500 bg-[#FFF5F5] ring-1 ring-rose-500': form.errors.notes }"
                    />
                    <div v-if="form.errors.notes" class="mt-1.5 p-2 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-1.5" style="background-color: #FFF1F2;">
                        <AlertOctagon class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                        <span>{{ form.errors.notes }}</span>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 pt-3 border-t border-[#D6E0E7]">
                    <button
                        @click="emit('close')"
                        type="button"
                        class="h-10 px-4 rounded-xl border border-[#D6E0E7] bg-white hover:bg-slate-50 text-xs font-bold text-[#5B6770] transition cursor-pointer"
                    >
                        إلغاء
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing || !form.reason"
                        class="h-10 px-5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition shadow-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                    >
                        <Check class="w-4 h-4" />
                        <span>اعتماد التصحيح وإعادة التسوية</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

