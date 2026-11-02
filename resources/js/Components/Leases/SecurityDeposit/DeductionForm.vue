<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Plus, Trash2, X } from 'lucide-vue-next';

interface DeductionDraft {
    category: string;
    description: string;
    amount: number | string;
    notes: string;
    attachment: File | null;
}

const props = defineProps<{
    show: boolean;
    leaseId: number;
    availableBalance: number;
    currencySymbol: string;
}>();

const emit = defineEmits<{
    'update:show': [value: boolean];
}>();

const createDeductionDraft = (): DeductionDraft => ({
    category: 'unit_damage',
    description: '',
    amount: '',
    notes: '',
    attachment: null,
});

const form = useForm<{ deductions: DeductionDraft[] }>({
    deductions: [createDeductionDraft()],
});
const rowKeys = ref([1]);
let nextRowKey = 2;

const total = computed(() => Math.round(
    form.deductions.reduce((sum, deduction) => sum + (Number(deduction.amount) || 0), 0) * 100,
) / 100);
const remainingBalance = computed(() => Math.max(0, props.availableBalance - total.value));
const exceedsAvailableBalance = computed(() => total.value > props.availableBalance);

watch(() => props.show, (isVisible) => {
    if (isVisible) {
        form.clearErrors();
    }
});

const addDeduction = () => {
    if (form.deductions.length >= 25) {
        return;
    }

    form.deductions.push(createDeductionDraft());
    rowKeys.value.push(nextRowKey++);
};

const removeDeduction = (index: number) => {
    form.deductions.splice(index, 1);
    rowKeys.value.splice(index, 1);
};

const setAttachment = (index: number, event: Event) => {
    const input = event.target as HTMLInputElement;
    form.deductions[index].attachment = input.files?.[0] ?? null;
};

const close = () => {
    emit('update:show', false);
    form.reset();
    form.clearErrors();
    rowKeys.value = [nextRowKey++];
};

const submit = () => {
    form.post(route('leases.security-deposit.deduction', props.leaseId), {
        forceFormData: true,
        onSuccess: close,
    });
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4">
        <button
            type="button"
            aria-label="إغلاق نافذة الخصومات"
            class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs"
            @click="close"
        />

        <section
            role="dialog"
            aria-modal="true"
            aria-labelledby="deposit-deduction-title"
            dir="rtl"
            class="relative z-10 max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-3xl border border-[#D6E0E7] bg-white p-5 shadow-2xl sm:p-7"
        >
            <header class="mb-5 flex items-start justify-between gap-3 border-b border-[#D6E0E7] pb-4">
                <div>
                    <h2 id="deposit-deduction-title" class="text-base font-bold text-[#163E3E]">خصومات التأمين</h2>
                    <p class="mt-1 text-xs leading-5 text-[#5B6770]">أضف بنود الخصم والمبالغ المقتطعة مع توثيق الأسباب، وسيتم حساب المتبقي تلقائياً.</p>
                </div>
                <button type="button" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100" aria-label="إغلاق" @click="close">
                    <X class="h-5 w-5" />
                </button>
            </header>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-[#D6E0E7] bg-[#F8FAFC] p-3 text-xs">
                    <span class="text-[#5B6770]">الرصيد المتاح قبل الخصم</span>
                    <strong class="font-mono text-[#163E3E]">{{ availableBalance.toLocaleString() }} {{ currencySymbol }}</strong>
                </div>

                <div
                    v-if="form.errors.deductions"
                    role="alert"
                    class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800"
                >
                    {{ form.errors.deductions }}
                </div>

                <div class="space-y-3">
                    <article
                        v-for="(deduction, index) in form.deductions"
                        :key="rowKeys[index]"
                        class="space-y-3 rounded-2xl border border-[#D6E0E7] bg-white p-3.5 sm:p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-xs font-bold text-[#163E3E]">بند الخصم {{ index + 1 }}</h3>
                            <button
                                v-if="form.deductions.length > 1"
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-semibold text-rose-700 hover:bg-rose-50"
                                @click="removeDeduction(index)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                                حذف البند
                            </button>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <label class="space-y-1.5">
                                <span class="block text-[11px] font-semibold text-[#5B6770]">سبب / تصنيف الخصم *</span>
                                <select
                                    v-model="deduction.category"
                                    :name="`deductions[${index}][category]`"
                                    required
                                    class="h-10 w-full rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] px-3 text-xs"
                                >
                                    <option value="unit_damage">تلفيات في الوحدة</option>
                                    <option value="maintenance">إصلاحات وصيانة</option>
                                    <option value="unpaid_dues">مستحقات غير مدفوعة</option>
                                    <option value="cleaning">تنظيف الوحدة</option>
                                    <option value="other">أخرى</option>
                                </select>
                            </label>

                            <label class="space-y-1.5">
                                <span class="block text-[11px] font-semibold text-[#5B6770]">مبلغ الخصم *</span>
                                <div class="relative">
                                    <input
                                        v-model="deduction.amount"
                                        :name="`deductions[${index}][amount]`"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        required
                                        placeholder="0.00"
                                        class="h-10 w-full rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] py-2 pl-12 pr-3 text-sm font-mono font-bold"
                                    />
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[11px] text-[#5B6770]">{{ currencySymbol }}</span>
                                </div>
                                <span v-if="form.errors[`deductions.${index}.amount`]" class="block text-[11px] text-rose-700">
                                    {{ form.errors[`deductions.${index}.amount`] }}
                                </span>
                            </label>

                            <label class="space-y-1.5 sm:col-span-2">
                                <span class="block text-[11px] font-semibold text-[#5B6770]">
                                    {{ deduction.category === 'other' ? 'تفصيل وتوضيح سبب الخصم (إجباري عند اختيار أخرى) *' : 'سبب ومبرر الخصم بالتفصيل *' }}
                                </span>
                                <input
                                    v-model="deduction.description"
                                    :name="`deductions[${index}][description]`"
                                    type="text"
                                    maxlength="1000"
                                    required
                                    :placeholder="deduction.category === 'other' ? 'يرجى توضيح سبب الخصم بالتفصيل...' : 'مثال: إصلاح باب الحمام المكسور، تنظيف الوحدة بعد الإخلاء...'"
                                    class="h-10 w-full rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] px-3 text-xs"
                                />
                                <span v-if="form.errors[`deductions.${index}.description`]" class="block text-[11px] text-rose-700">
                                    {{ form.errors[`deductions.${index}.description`] }}
                                </span>
                            </label>

                            <label class="space-y-1.5">
                                <span class="block text-[11px] font-semibold text-[#5B6770]">ملاحظات إضافية (اختياري)</span>
                                <input
                                    v-model="deduction.notes"
                                    :name="`deductions[${index}][notes]`"
                                    type="text"
                                    maxlength="1000"
                                    class="h-10 w-full rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] px-3 text-xs"
                                />
                            </label>

                            <label class="space-y-1.5">
                                <span class="block text-[11px] font-semibold text-[#5B6770]">مرفق إثبات (اختياري)</span>
                                <input
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp,.pdf"
                                    class="block w-full text-[11px] text-[#5B6770] file:ml-2 file:rounded-lg file:border-0 file:bg-[#E8F0F0] file:px-2.5 file:py-1.5 file:text-[11px] file:font-semibold file:text-[#163E3E]"
                                    @change="setAttachment(index, $event)"
                                />
                            </label>
                        </div>
                    </article>
                </div>

                <button
                    v-if="form.deductions.length < 25"
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-xl border border-[#163E3E]/30 px-3 text-xs font-bold text-[#163E3E] hover:bg-[#E8F0F0]"
                    @click="addDeduction"
                >
                    <Plus class="h-4 w-4" />
                    إضافة مشكلة أخرى
                </button>

                <div
                    class="grid grid-cols-1 gap-2 rounded-2xl border p-3.5 text-xs sm:grid-cols-3"
                    :class="exceedsAvailableBalance ? 'border-rose-200 bg-rose-50' : 'border-emerald-200 bg-emerald-50'"
                >
                    <div class="flex items-center justify-between gap-2 sm:block">
                        <span class="block text-[#5B6770]">إجمالي الخصومات</span>
                        <strong class="font-mono text-rose-700">{{ total.toLocaleString() }} {{ currencySymbol }}</strong>
                    </div>
                    <div class="flex items-center justify-between gap-2 sm:block">
                        <span class="block text-[#5B6770]">المتبقي للمستأجر</span>
                        <strong class="font-mono text-emerald-700">{{ remainingBalance.toLocaleString() }} {{ currencySymbol }}</strong>
                    </div>
                    <p v-if="exceedsAvailableBalance" role="alert" class="font-semibold text-rose-700 sm:col-span-3">
                        مجموع الخصومات أكبر من الرصيد المتاح.
                    </p>
                </div>

                <footer class="flex justify-end gap-2 border-t border-[#D6E0E7] pt-4">
                    <button type="button" class="h-10 rounded-xl border border-[#D6E0E7] px-4 text-xs font-semibold" @click="close">
                        إلغاء
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing || exceedsAvailableBalance"
                        class="h-10 rounded-xl bg-[#163E3E] px-4 text-xs font-bold text-white hover:bg-[#102F2F] disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{ form.processing ? 'جارٍ الحفظ...' : 'حفظ الخصومات' }}
                    </button>
                </footer>
            </form>
        </section>
    </div>
</template>
