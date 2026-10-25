<script setup lang="ts">
import { computed } from 'vue';
import { Plus, Trash2, AlertTriangle, Paperclip } from 'lucide-vue-next';

export interface DeductionDraft {
    id?: number | null;
    category: string;
    description: string;
    amount: number | string;
    notes?: string;
    attachment?: File | null;
    attachment_url?: string | null;
}

const props = withDefaults(defineProps<{
    modelValue: DeductionDraft[];
    availableDeposit: number;
    currencySymbol?: string;
    errors?: Record<string, string>;
    canAdd?: boolean;
}>(), {
    currencySymbol: 'ج.م',
    errors: () => ({}),
    canAdd: true,
});

const emit = defineEmits<{
    'update:modelValue': [value: DeductionDraft[]];
}>();

const createNewDeduction = (): DeductionDraft => ({
    category: 'unit_damage',
    description: '',
    amount: '',
    notes: '',
    attachment: null,
});

const addDeduction = () => {
    const updated = [...props.modelValue, createNewDeduction()];
    emit('update:modelValue', updated);
};

const removeDeduction = (index: number) => {
    const updated = [...props.modelValue];
    updated.splice(index, 1);
    emit('update:modelValue', updated);
};

const handleFileChange = (index: number, event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        props.modelValue[index].attachment = target.files[0];
    } else {
        props.modelValue[index].attachment = null;
    }
};

const totalDeductions = computed(() => {
    return Math.round(
        props.modelValue.reduce((sum, d) => sum + (Number(d.amount) || 0), 0) * 100
    ) / 100;
});

const refundDue = computed(() => {
    return Math.max(0, Math.round((props.availableDeposit - totalDeductions.value) * 100) / 100);
});

const exceedsBalance = computed(() => {
    return totalDeductions.value > props.availableDeposit;
});
</script>

<template>
    <section class="space-y-4 rounded-2xl border border-[#D6E0E7] bg-white p-4 sm:p-5" dir="rtl">
        <!-- Section Header -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#D6E0E7] pb-3.5">
            <div>
                <h4 class="text-sm font-bold text-[#163E3E] flex items-center gap-2">
                    <span>خصومات التأمين</span>
                    <span
                        v-if="modelValue.length > 0"
                        class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#E8F0F0] text-[#163E3E]"
                    >
                        {{ modelValue.length }} بند
                    </span>
                </h4>
                <p class="text-xs text-[#5B6770] mt-0.5">
                    سجل بنود الاستقطاع مع توضيح سبب الخصم ومبلغه، وسيتم احتساب المتبقي تلقائياً.
                </p>
            </div>

            <button
                v-if="canAdd"
                type="button"
                @click="addDeduction"
                class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-[#163E3E] bg-[#E8F0F0]/50 px-3 text-xs font-bold text-[#163E3E] hover:bg-[#E8F0F0] transition cursor-pointer shadow-2xs"
            >
                <Plus class="h-4 w-4" />
                <span>إضافة بند خصم</span>
            </button>
        </div>

        <!-- Global deductions error message -->
        <div
            v-if="errors?.deductions"
            role="alert"
            class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-semibold text-rose-800 flex items-center gap-2"
        >
            <AlertTriangle class="h-4 w-4 shrink-0" />
            <span>{{ errors.deductions }}</span>
        </div>

        <!-- Empty State -->
        <div
            v-if="modelValue.length === 0"
            class="rounded-xl border border-dashed border-[#D6E0E7] bg-[#F8FAFC] p-6 text-center text-xs text-[#5B6770] space-y-2"
        >
            <p class="font-semibold text-[#163E3E]">لا توجد أي خصومات مسجلة على التأمين</p>
            <p class="text-[11px] text-[#8A9AA7]">
                إذا كانت الوحدة بحالة سليمة، سيُعاد كامل مبلغ التأمين إلى المستأجر دون استقطاعات.
            </p>
            <button
                v-if="canAdd"
                type="button"
                @click="addDeduction"
                class="mt-2 inline-flex h-8 items-center gap-1.5 rounded-lg border border-[#D6E0E7] bg-white px-3 text-xs font-bold text-[#163E3E] hover:bg-slate-50 transition"
            >
                <Plus class="h-3.5 w-3.5" />
                <span>إضافة خصم أضرار أو مستحقات</span>
            </button>
        </div>

        <!-- Deductions list -->
        <div v-else class="space-y-3.5">
            <article
                v-for="(item, idx) in modelValue"
                :key="item.id ?? idx"
                class="space-y-3 rounded-2xl border border-[#D6E0E7] bg-[#F8FAFC] p-3.5 sm:p-4 transition hover:border-[#163E3E]/40"
            >
                <div class="flex items-center justify-between border-b border-[#D6E0E7]/60 pb-2.5">
                    <span class="text-xs font-bold text-[#163E3E] flex items-center gap-1.5">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-[#163E3E] text-[10px] text-white">
                            {{ idx + 1 }}
                        </span>
                        <span>بند خصم رقم {{ idx + 1 }}</span>
                    </span>

                    <button
                        type="button"
                        @click="removeDeduction(idx)"
                        class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-semibold text-rose-700 hover:bg-rose-50 transition cursor-pointer"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                        <span>حذف البند</span>
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <!-- Reason / Category -->
                    <div>
                        <label class="block text-[11px] font-bold text-[#163E3E] mb-1">
                            سبب / نوع الخصم *
                        </label>
                        <select
                            v-model="item.category"
                            required
                            class="h-10 w-full rounded-xl border border-[#D6E0E7] bg-white px-3 text-xs text-[#163E3E] focus:border-[#163E3E] focus:ring-1 focus:ring-[#163E3E]"
                        >
                            <option value="unit_damage">تلفيات في الوحدة</option>
                            <option value="maintenance">إصلاحات وصيانة</option>
                            <option value="unpaid_dues">مستحقات غير مدفوعة</option>
                            <option value="cleaning">تنظيف الوحدة</option>
                            <option value="other">أخرى</option>
                        </select>
                        <span v-if="errors?.[`deductions.${idx}.category`]" class="block text-[11px] text-rose-700 mt-0.5">
                            {{ errors[`deductions.${idx}.category`] }}
                        </span>
                    </div>

                    <!-- Amount -->
                    <div>
                        <label class="block text-[11px] font-bold text-[#163E3E] mb-1">
                            مبلغ الخصم (بالجنيه) *
                        </label>
                        <div class="relative">
                            <input
                                v-model="item.amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                required
                                placeholder="0.00"
                                class="h-10 w-full rounded-xl border border-[#D6E0E7] bg-white py-2 pl-12 pr-3 text-sm font-mono font-bold text-[#163E3E] focus:border-[#163E3E] focus:ring-1 focus:ring-[#163E3E]"
                            />
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[11px] font-bold text-[#5B6770]">
                                {{ currencySymbol }}
                            </span>
                        </div>
                        <span v-if="errors?.[`deductions.${idx}.amount`]" class="block text-[11px] text-rose-700 mt-0.5">
                            {{ errors[`deductions.${idx}.amount`] }}
                        </span>
                    </div>

                    <!-- Description -->
                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-bold text-[#163E3E] mb-1">
                            {{ item.category === 'other' ? 'توضيح سبب الخصم (مطلوب عند اختيار أخرى) *' : 'بيان ومبرر الخصم بالتفصيل *' }}
                        </label>
                        <input
                            v-model="item.description"
                            type="text"
                            maxlength="1000"
                            required
                            :placeholder="item.category === 'other' ? 'يرجى كتابة سبب الخصم بالتفصيل...' : 'مثال: إصلاح باب الحمام المكسور، دهان الحائط، فاتورة كهرباء متأخرة...'"
                            class="h-10 w-full rounded-xl border border-[#D6E0E7] bg-white px-3 text-xs text-[#163E3E] focus:border-[#163E3E] focus:ring-1 focus:ring-[#163E3E]"
                        />
                        <span v-if="errors?.[`deductions.${idx}.description`]" class="block text-[11px] text-rose-700 mt-0.5">
                            {{ errors[`deductions.${idx}.description`] }}
                        </span>
                    </div>

                    <!-- Additional notes -->
                    <div>
                        <label class="block text-[11px] font-semibold text-[#5B6770] mb-1">
                            ملاحظات إضافية (اختياري)
                        </label>
                        <input
                            v-model="item.notes"
                            type="text"
                            maxlength="1000"
                            placeholder="أي تفاصيل توثيقية إضافية..."
                            class="h-10 w-full rounded-xl border border-[#D6E0E7] bg-white px-3 text-xs text-[#163E3E] focus:border-[#163E3E] focus:ring-1 focus:ring-[#163E3E]"
                        />
                    </div>

                    <!-- Attachment -->
                    <div>
                        <label class="block text-[11px] font-semibold text-[#5B6770] mb-1">
                            مرفق إثبات (صورة تلف / فاتورة إصلاح - اختياري)
                        </label>
                        <div class="flex items-center gap-2">
                            <input
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp,.pdf"
                                @change="handleFileChange(idx, $event)"
                                class="block w-full text-[11px] text-[#5B6770] file:ml-2 file:rounded-lg file:border-0 file:bg-[#E8F0F0] file:px-2.5 file:py-1.5 file:text-[11px] file:font-semibold file:text-[#163E3E]"
                            />
                            <a
                                v-if="item.attachment_url"
                                :href="item.attachment_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="shrink-0 text-[11px] font-bold text-[#163E3E] hover:underline flex items-center gap-1"
                                title="عرض المرفق الحالي"
                            >
                                <Paperclip class="h-3.5 w-3.5" />
                                <span>المرفق</span>
                            </a>
                        </div>
                    </div>
                </div>
            </article>
        </div>

        <!-- Automatic Settlement Live Summary Box -->
        <div
            class="rounded-2xl border p-4 text-xs transition"
            :class="exceedsBalance ? 'border-rose-300 bg-rose-50' : 'border-[#163E3E]/20 bg-[#F8FAFC]'"
        >
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl bg-white p-3 border border-[#D6E0E7]">
                    <span class="block text-[11px] text-[#5B6770]">رصيد التأمين المتاح</span>
                    <strong class="mt-0.5 block font-mono text-sm text-[#163E3E]">
                        {{ availableDeposit.toLocaleString() }} {{ currencySymbol }}
                    </strong>
                </div>

                <div class="rounded-xl bg-white p-3 border border-rose-200">
                    <span class="block text-[11px] font-semibold text-rose-700">إجمالي الخصومات</span>
                    <strong class="mt-0.5 block font-mono text-sm text-rose-700">
                        {{ totalDeductions.toLocaleString() }} {{ currencySymbol }}
                    </strong>
                </div>

                <div class="rounded-xl bg-white p-3 border border-emerald-200">
                    <span class="block text-[11px] font-semibold text-emerald-800">المستحق رده للمستأجر</span>
                    <strong class="mt-0.5 block font-mono text-sm text-emerald-700">
                        {{ refundDue.toLocaleString() }} {{ currencySymbol }}
                    </strong>
                </div>

                <div class="rounded-xl bg-white p-3 border border-amber-200">
                    <span class="block text-[11px] font-semibold text-amber-800">المحتفظ به للمالك</span>
                    <strong class="mt-0.5 block font-mono text-sm text-amber-700">
                        {{ totalDeductions.toLocaleString() }} {{ currencySymbol }}
                    </strong>
                </div>
            </div>

            <!-- Balance warning alert -->
            <p v-if="exceedsBalance" role="alert" class="mt-3 font-bold text-rose-700 flex items-center gap-1.5">
                <AlertTriangle class="h-4 w-4 shrink-0" />
                <span>تنبيه: مجموع الخصومات ({{ totalDeductions.toLocaleString() }} {{ currencySymbol }}) أكبر من رصيد التأمين المتاح ({{ availableDeposit.toLocaleString() }} {{ currencySymbol }}).</span>
            </p>
        </div>
    </section>
</template>
