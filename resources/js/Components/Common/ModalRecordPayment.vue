<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { CreditCard, X, Check, MessageSquare, AlertTriangle, AlertOctagon, Upload, FileText, Trash2 } from 'lucide-vue-next';

const props = defineProps<{
    show: boolean;
    renters?: Array<{
        id: number;
        name: string;
        phone: string;
        active_lease?: {
            id: number;
            unit_name?: string;
            rent_amount: number;
        } | null;
    }>;
    initialRenterId?: number;
    initialAmount?: number;
    initialRentChargeId?: number;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const page = usePage();
const currencySymbol = computed(() => {
    const curr = (page.props as any).auth?.workspace?.currency || 'SAR';
    switch (curr.toUpperCase()) {
        case 'EGP': return 'ج.م';
        case 'SAR': return 'ر.س';
        case 'AED': return 'د.إ';
        case 'USD': return '$';
        default: return curr;
    }
});
const effectiveRenters = computed(() => {
    if (props.renters && props.renters.length > 0) {
        return props.renters;
    }
    return (page.props as any)?.quickRenters ?? [];
});

const form = useForm({
    renter_id: props.initialRenterId || '',
    amount: props.initialAmount || '',
    payment_method: 'bank_transfer',
    paid_at: new Date().toISOString().split('T')[0],
    record_date: new Date().toISOString().split('T')[0],
    reference: 'REC-' + Math.floor(100000 + Math.random() * 900000),
    notes: '',
    send_whatsapp: true,
    rent_charge_id: props.initialRentChargeId || null,
    receipt_image: null as File | null,
});

const receiptPreviewUrl = ref<string | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);

const onFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        form.receipt_image = file;
        if (file.type.startsWith('image/')) {
            receiptPreviewUrl.value = URL.createObjectURL(file);
        } else {
            receiptPreviewUrl.value = null;
        }
    }
};

const clearReceiptFile = () => {
    form.receipt_image = null;
    if (receiptPreviewUrl.value) {
        URL.revokeObjectURL(receiptPreviewUrl.value);
        receiptPreviewUrl.value = null;
    }
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
};

const selectedRenter = computed(() => {
    if (!form.renter_id) return null;
    return effectiveRenters.value.find((r: any) => Number(r.id) === Number(form.renter_id)) || null;
});

const isRentingAnything = computed(() => {
    if (!selectedRenter.value) return false;
    return Boolean(selectedRenter.value.active_lease);
});

watch(
    () => props.show,
    (isOpen) => {
        if (isOpen) {
            if (props.initialRenterId) {
                form.renter_id = props.initialRenterId;
            } else if (!form.renter_id && effectiveRenters.value.length > 0) {
                // Default to first renter with active lease if possible
                const activeOne = effectiveRenters.value.find((r: any) => r.active_lease);
                form.renter_id = (activeOne || effectiveRenters.value[0]).id;
            }

            if (props.initialAmount) {
                form.amount = props.initialAmount;
            } else if (!form.amount) {
                onRenterChange();
            }

            if (props.initialRentChargeId) {
                form.rent_charge_id = props.initialRentChargeId;
            }
        } else {
            clearReceiptFile();
        }
    },
    { immediate: true }
);

const onRenterChange = () => {
    const selected = effectiveRenters.value.find((r: any) => r.id === Number(form.renter_id));
    if (selected?.active_lease?.rent_amount && !form.amount) {
        form.amount = selected.active_lease.rent_amount;
    }
};

const submit = () => {
    if (!isRentingAnything.value) {
        return;
    }
    form.post(route('payments.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            clearReceiptFile();
            emit('close');
        },
    });
};
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 sm:p-6"
    >
        <!-- الخلفية المظلمة المعتمة (Backdrop) -->
        <div
            @click="emit('close')"
            class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs transition-opacity"
        ></div>

        <!-- بطاقة النافذة المنبثقة (Modal Content) -->
        <div
            class="relative w-full max-w-lg bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] overflow-hidden z-10 animate-in fade-in zoom-in-95 max-h-[92vh] flex flex-col"
            dir="rtl"
        >
            <!-- الرأس (Modal Header) -->
            <div class="px-6 py-5 border-b border-[#D6E0E7] flex items-center justify-between bg-[#F8FAFC] shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-[#E8F0F0] text-[#163E3E] flex items-center justify-center">
                        <CreditCard class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-[#163E3E]">تسجيل دفعة يدوية جديدة</h3>
                        <p class="text-xs text-[#5B6770]">تسجيل تحصيل نقدي أو بنكي وإصدار سند قبض</p>
                    </div>
                </div>

                <button
                    @click="emit('close')"
                    type="button"
                    class="p-2 text-[#5B6770] hover:text-[#163E3E] hover:bg-slate-200/50 rounded-xl transition cursor-pointer"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- النموذج (Form Body) -->
            <form @submit.prevent="submit" class="p-6 space-y-4 overflow-y-auto">
                <!-- تنبيه الأخطاء الصادرة من النظام والتحقق المالي -->
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
                        <h4 class="text-sm font-bold text-rose-950">تعذر تسجيل السند لوجود الملاحظات الرقابية التالية:</h4>
                        <ul class="space-y-1.5 text-xs text-rose-900 font-semibold">
                            <li v-for="(errMsg, errField) in form.errors" :key="errField" class="flex items-start gap-2 leading-relaxed">
                                <span class="text-rose-600 font-bold shrink-0">•</span>
                                <span>{{ errMsg }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- اختيار المستأجر -->
                <div>
                    <label class="block text-xs font-bold text-[#163E3E] mb-1.5">
                        المستأجر / الوحدة <span class="text-rose-500">*</span>
                    </label>
                    <select
                        v-model="form.renter_id"
                        @change="onRenterChange"
                        required
                        class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                        :class="{ 'border-rose-500 bg-[#FFF5F5] ring-1 ring-rose-500': form.errors.renter_id }"
                    >
                        <option value="" disabled>اختر المستأجر</option>
                        <option
                            v-for="r in effectiveRenters"
                            :key="r.id"
                            :value="r.id"
                        >
                            {{ r.name }} {{ r.active_lease?.unit_name ? `(${r.active_lease.unit_name})` : '— بدون عقد نشط' }}
                        </option>
                    </select>
                    <div v-if="form.errors.renter_id" class="mt-1.5 p-2 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-1.5" style="background-color: #FFF1F2;">
                        <AlertOctagon class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                        <span>{{ form.errors.renter_id }}</span>
                    </div>
                </div>

                <!-- استحقاق محدد موجه له السند إن وجد -->
                <div
                    v-if="form.rent_charge_id"
                    class="p-4 rounded-2xl border text-xs space-y-1.5 transition"
                    :class="form.errors.rent_charge_id
                        ? 'bg-[#FEF2F2] border-rose-400 text-rose-950 border-r-6 border-r-rose-600'
                        : 'bg-[#E8F0F0] border-[#163E3E]/20 text-[#163E3E]'"
                    :style="{ backgroundColor: form.errors.rent_charge_id ? '#FEF2F2' : '#E8F0F0' }"
                >
                    <div class="flex items-center justify-between">
                        <span class="font-bold flex items-center gap-1.5">
                            <AlertOctagon v-if="form.errors.rent_charge_id" class="w-4 h-4 text-rose-600" />
                            <span>استحقاق محدد للتوجيه (رقم #{{ form.rent_charge_id }})</span>
                        </span>
                        <button
                            type="button"
                            @click="form.rent_charge_id = null"
                            class="text-xs text-rose-600 hover:text-rose-800 underline font-semibold cursor-pointer"
                        >
                            إلغاء التخصيص التلقائي
                        </button>
                    </div>
                    <p class="text-[11px]" :class="form.errors.rent_charge_id ? 'text-rose-800' : 'text-[#5B6770]'">
                        يتم توجيه السند نحو هذا الاستحقاق وفق القواعد المالية (الأولوية دائماً لسداد المتأخرات أولاً).
                    </p>
                    <div
                        v-if="form.errors.rent_charge_id"
                        class="mt-2.5 p-3 rounded-xl bg-rose-100 border border-rose-300 text-rose-900 text-xs font-bold flex items-start gap-2"
                        style="background-color: #FFE4E6;"
                    >
                        <AlertOctagon class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" />
                        <span class="leading-relaxed">{{ form.errors.rent_charge_id }}</span>
                    </div>
                </div>

                <!-- تنبيه عند وجود خطأ في rent_charge_id دون وجود استحقاق محدد حالياً -->
                <div
                    v-else-if="form.errors.rent_charge_id"
                    class="p-4 rounded-2xl bg-[#FEF2F2] border-2 border-rose-400 text-rose-950 text-xs font-bold flex items-start gap-3 border-r-6 border-r-rose-600"
                    style="background-color: #FEF2F2;"
                >
                    <div class="p-2 rounded-xl bg-rose-100 text-rose-700 shrink-0 mt-0.5" style="background-color: #FFE4E6;">
                        <AlertOctagon class="w-4 h-4 text-rose-600" />
                    </div>
                    <div class="space-y-1 flex-1">
                        <h4 class="text-xs font-bold text-rose-950">قيد استحقاق الإيجار:</h4>
                        <p class="text-xs font-semibold text-rose-900 leading-relaxed">{{ form.errors.rent_charge_id }}</p>
                    </div>
                </div>

                <!-- تنبيه عند اختيار مستأجر غير مسكن / بدون عقد سارٍ -->
                <div
                    v-if="selectedRenter && !isRentingAnything"
                    class="p-3.5 rounded-2xl bg-amber-50 border border-amber-200 flex items-start gap-3"
                >
                    <AlertTriangle class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                    <div class="space-y-0.5">
                        <p class="text-xs font-bold text-amber-900">المستأجر غير مرتبط بأي عقد إيجار حالياً</p>
                        <p class="text-[11px] text-amber-700 leading-relaxed">
                            المستأجر "{{ selectedRenter.name }}" لا يستأجر أي وحدة سكنية أو تجارية حالياً، لذلك لا يمكن تسجيل دفعات أو تحصيل إيجار له.
                        </p>
                    </div>
                </div>

                <!-- المبلغ وتاريخ التحصيل وتاريخ القيد -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1.5">
                            المبلغ المقبوض <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <input
                                v-model="form.amount"
                                type="number"
                                step="0.01"
                                placeholder="0.00"
                                required
                                class="w-full h-11 pl-12 pr-4 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm font-bold text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right"
                                :class="{ 'border-rose-500 bg-[#FFF5F5] ring-1 ring-rose-500': form.errors.amount }"
                            />
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-[#5B6770]">
                                {{ currencySymbol }}
                            </span>
                        </div>
                        <div v-if="form.errors.amount" class="mt-1.5 p-2 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-1.5" style="background-color: #FFF1F2;">
                            <AlertOctagon class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                            <span>{{ form.errors.amount }}</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1.5">
                            تاريخ التحصيل الفعلي <span class="text-rose-500">*</span>
                        </label>
                        <input
                            v-model="form.paid_at"
                            type="date"
                            required
                            class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right"
                            :class="{ 'border-rose-500 bg-[#FFF5F5] ring-1 ring-rose-500': form.errors.paid_at }"
                        />
                        <div v-if="form.errors.paid_at" class="mt-1.5 p-2 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-1.5" style="background-color: #FFF1F2;">
                            <AlertOctagon class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                            <span>{{ form.errors.paid_at }}</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1.5">
                            تاريخ القيد والتسجيل
                        </label>
                        <input
                            v-model="form.record_date"
                            type="date"
                            class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right"
                            :class="{ 'border-rose-500 bg-[#FFF5F5] ring-1 ring-rose-500': form.errors.record_date }"
                        />
                        <div v-if="form.errors.record_date" class="mt-1.5 p-2 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-1.5" style="background-color: #FFF1F2;">
                            <AlertOctagon class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                            <span>{{ form.errors.record_date }}</span>
                        </div>
                    </div>
                </div>

                <!-- طريقة الدفع ورقم المرجع -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1.5">
                            طريقة السداد <span class="text-rose-500">*</span>
                        </label>
                        <select
                            v-model="form.payment_method"
                            required
                            class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                            :class="{ 'border-rose-500 bg-[#FFF5F5] ring-1 ring-rose-500': form.errors.payment_method }"
                        >
                            <option value="bank_transfer">تحويل بنكي / إنستاباي</option>
                            <option value="other">بطاقة بنكية / فيزا / ميزة</option>
                            <option value="cash">نقدي (كاش)</option>
                            <option value="cheque">شيك مصرفي</option>
                        </select>
                        <div v-if="form.errors.payment_method" class="mt-1.5 p-2 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-1.5" style="background-color: #FFF1F2;">
                            <AlertOctagon class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                            <span>{{ form.errors.payment_method }}</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1.5">
                            رقم المرجع / الإيصال <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري / تلقائي)</span>
                        </label>
                        <input
                            v-model="form.reference"
                            type="text"
                            placeholder="REC-XXXXXX"
                            class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition font-mono text-right"
                            :class="{ 'border-rose-500 bg-[#FFF5F5] ring-1 ring-rose-500': form.errors.reference }"
                        />
                        <div v-if="form.errors.reference" class="mt-1.5 p-2 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-1.5" style="background-color: #FFF1F2;">
                            <AlertOctagon class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                            <span>{{ form.errors.reference }}</span>
                        </div>
                    </div>
                </div>

                <!-- صورة أو لقطة شاشة لإيصال التحويل (اختياري - NOT REQUIRED) -->
                <div class="space-y-2 pt-2 border-t border-[#D6E0E7]/60">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-bold text-[#163E3E]">
                            صورة إشعار التحويل / الإيصال
                            <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري)</span>
                        </label>
                        <span class="text-[11px] text-[#5B6770]">JPG, PNG, WEBP, PDF حتى 10MB</span>
                    </div>

                    <!-- معاينة الملف المرفق في حال تم اختياره -->
                    <div
                        v-if="form.receipt_image"
                        class="rounded-2xl border border-[#163E3E]/20 bg-[#F8FAFC] p-3 flex items-center justify-between gap-3"
                    >
                        <div class="flex items-center gap-3 min-w-0">
                            <div
                                v-if="receiptPreviewUrl"
                                class="w-12 h-12 rounded-xl overflow-hidden border border-[#D6E0E7] bg-white shrink-0"
                            >
                                <img :src="receiptPreviewUrl" alt="معاينة الإيصال" class="w-full h-full object-cover" />
                            </div>
                            <div
                                v-else
                                class="w-12 h-12 rounded-xl bg-[#E8F0F0] text-[#163E3E] flex items-center justify-center shrink-0"
                            >
                                <FileText class="w-6 h-6" />
                            </div>

                            <div class="min-w-0">
                                <p class="text-xs font-bold text-[#163E3E] truncate">{{ form.receipt_image.name }}</p>
                                <p class="text-[11px] text-[#5B6770] font-mono">{{ (form.receipt_image.size / 1024).toFixed(1) }} KB</p>
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="clearReceiptFile"
                            class="p-2 text-rose-600 hover:bg-rose-50 rounded-xl transition cursor-pointer"
                            title="إزالة المرفق"
                        >
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- زر رفع الملف إذا لم يتم اختيار ملف بعد -->
                    <div
                        v-else
                        @click="fileInputRef?.click()"
                        class="border-2 border-dashed border-[#D6E0E7] hover:border-[#163E3E] rounded-2xl p-4 text-center cursor-pointer bg-[#F8FAFC] hover:bg-[#E8F0F0]/30 transition group"
                    >
                        <input
                            ref="fileInputRef"
                            type="file"
                            accept="image/jpeg,image/png,image/jpg,image/webp,application/pdf"
                            class="hidden"
                            @change="onFileChange"
                        />
                        <div class="flex flex-col items-center gap-1.5">
                            <div class="w-9 h-9 rounded-xl bg-[#E8F0F0] text-[#163E3E] flex items-center justify-center group-hover:scale-105 transition">
                                <Upload class="w-4 h-4" />
                            </div>
                            <p class="text-xs font-bold text-[#163E3E]">انقر لإرفاق صورة إشعار إنستاباي أو إيصال التحويل</p>
                            <p class="text-[11px] text-[#5B6770]">لقطة شاشة للتحويل البنكي أو صورة السند (اختياري)</p>
                        </div>
                    </div>

                    <div v-if="form.errors.receipt_image" class="mt-1.5 p-2 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-1.5" style="background-color: #FFF1F2;">
                        <AlertOctagon class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                        <span>{{ form.errors.receipt_image }}</span>
                    </div>
                </div>

                <!-- ملاحظات -->
                <div>
                    <label class="block text-xs font-bold text-[#163E3E] mb-1.5">
                        ملاحظات أو تفاصيل التحويل <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري)</span>
                    </label>
                    <textarea
                        v-model="form.notes"
                        rows="2"
                        placeholder="مثل: تحويل عبر إنستاباي أو إيداع بنكي..."
                        class="w-full p-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                        :class="{ 'border-rose-500 bg-[#FFF5F5] ring-1 ring-rose-500': form.errors.notes }"
                    ></textarea>
                    <div v-if="form.errors.notes" class="mt-1.5 p-2 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-1.5" style="background-color: #FFF1F2;">
                        <AlertOctagon class="w-3.5 h-3.5 text-rose-600 shrink-0" />
                        <span>{{ form.errors.notes }}</span>
                    </div>
                </div>

                <!-- خيار إرسال سند القبض عبر WhatsApp تلقائياً -->
                <label class="flex items-center gap-3 p-3.5 rounded-2xl bg-[#E8F0F0]/50 border border-[#163E3E]/20 cursor-pointer hover:bg-[#E8F0F0] transition">
                    <input
                        type="checkbox"
                        v-model="form.send_whatsapp"
                        class="w-4 h-4 rounded text-[#163E3E] focus:ring-[#163E3E] border-[#D6E0E7]"
                    />
                    <div class="flex items-center gap-2">
                        <MessageSquare class="w-4 h-4 text-[#163E3E]" />
                        <span class="text-xs font-bold text-[#163E3E]">
                            إرسال سند قبض إلكتروني فوري عبر WhatsApp للمستأجر
                        </span>
                    </div>
                </label>

                <!-- أزرار الإجراءات -->
                <div class="pt-4 flex items-center justify-end gap-3 border-t border-[#D6E0E7]">
                    <button
                        @click="emit('close')"
                        type="button"
                        class="h-11 px-5 rounded-xl border border-[#D6E0E7] text-sm font-semibold text-[#5B6770] hover:bg-slate-100 transition cursor-pointer"
                    >
                        إلغاء
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing || !isRentingAnything"
                        class="h-11 px-6 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-sm font-bold transition flex items-center gap-2 shadow-xs cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <Check class="w-4 h-4" />
                        <span>{{ form.processing ? 'جاري الحفظ…' : 'حفظ السند والترحيل' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
