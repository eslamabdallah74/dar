<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { CreditCard, X, Check, MessageSquare } from 'lucide-vue-next';

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
    reference: 'REC-' + Math.floor(100000 + Math.random() * 900000),
    notes: '',
    send_whatsapp: true,
    rent_charge_id: props.initialRentChargeId || null,
});

watch(
    () => props.show,
    (isOpen) => {
        if (isOpen) {
            if (props.initialRenterId) {
                form.renter_id = props.initialRenterId;
            } else if (!form.renter_id && effectiveRenters.value.length > 0) {
                form.renter_id = effectiveRenters.value[0].id;
            }

            if (props.initialAmount) {
                form.amount = props.initialAmount;
            } else if (!form.amount) {
                onRenterChange();
            }

            if (props.initialRentChargeId) {
                form.rent_charge_id = props.initialRentChargeId;
            }
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
    form.post(route('payments.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
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
            class="fixed inset-0 bg-[#0F2747]/60 backdrop-blur-xs transition-opacity"
        ></div>

        <!-- بطاقة النافذة المنبثقة (Modal Content) -->
        <div
            class="relative w-full max-w-lg bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] overflow-hidden z-10 animate-in fade-in zoom-in-95"
            dir="rtl"
        >
            <!-- الرأس (Modal Header) -->
            <div class="px-6 py-5 border-b border-[#D6E0E7] flex items-center justify-between bg-[#F8FAFC]">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center">
                        <CreditCard class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-[#0F2747]">تسجيل دفعة يدوية جديدة</h3>
                        <p class="text-xs text-[#5B6770]">تسجيل تحصيل نقدي أو بنكي وإصدار سند قبض</p>
                    </div>
                </div>

                <button
                    @click="emit('close')"
                    type="button"
                    class="p-2 text-[#5B6770] hover:text-[#0F2747] hover:bg-slate-200/50 rounded-xl transition cursor-pointer"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- النموذج (Form Body) -->
            <form @submit.prevent="submit" class="p-6 space-y-4">
                <!-- اختيار المستأجر -->
                <div>
                    <label class="block text-xs font-bold text-[#0F2747] mb-1.5">
                        المستأجر / الوحدة <span class="text-rose-500">*</span>
                    </label>
                    <select
                        v-model="form.renter_id"
                        @change="onRenterChange"
                        required
                        class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition"
                    >
                        <option value="" disabled>اختر المستأجر</option>
                        <option
                            v-for="r in effectiveRenters"
                            :key="r.id"
                            :value="r.id"
                        >
                            {{ r.name }} {{ r.active_lease?.unit_name ? `(${r.active_lease.unit_name})` : '' }}
                        </option>
                    </select>
                </div>

                <!-- المبلغ وتاريخ السداد -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">
                            المبلغ المقبوض <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <input
                                v-model="form.amount"
                                type="number"
                                step="0.01"
                                placeholder="0.00"
                                required
                                class="w-full h-11 pl-12 pr-4 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm font-bold text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-left"
                            />
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-[#5B6770]">
                                ج.م
                            </span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">
                            تاريخ التحصيل <span class="text-rose-500">*</span>
                        </label>
                        <input
                            v-model="form.paid_at"
                            type="date"
                            required
                            class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-left"
                        />
                    </div>
                </div>

                <!-- طريقة الدفع ورقم المرجع -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">
                            طريقة السداد <span class="text-rose-500">*</span>
                        </label>
                        <select
                            v-model="form.payment_method"
                            required
                            class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition"
                        >
                            <option value="bank_transfer">تحويل بنكي</option>
                            <option value="other">مدى / بطاقة مصرفية</option>
                            <option value="cash">نقدي (كاش)</option>
                            <option value="cheque">شيك مصرفي</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">
                            رقم المرجع / الإيصال
                        </label>
                        <input
                            v-model="form.reference"
                            type="text"
                            placeholder="REC-XXXXXX"
                            class="w-full h-11 px-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition font-mono text-left"
                        />
                    </div>
                </div>

                <!-- ملاحظات -->
                <div>
                    <label class="block text-xs font-bold text-[#0F2747] mb-1.5">
                        ملاحظات أو تفاصيل التحويل
                    </label>
                    <textarea
                        v-model="form.notes"
                        rows="2"
                        placeholder="مثل: تحويل عبر الراجحي إلى الحساب الاستثماري..."
                        class="w-full p-3 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition"
                    ></textarea>
                </div>

                <!-- خيار إرسال سند القبض عبر WhatsApp تلقائياً -->
                <label class="flex items-center gap-3 p-3.5 rounded-2xl bg-[#E3F1F4]/50 border border-[#0E6E7E]/20 cursor-pointer hover:bg-[#E3F1F4] transition">
                    <input
                        type="checkbox"
                        v-model="form.send_whatsapp"
                        class="w-4 h-4 rounded text-[#0E6E7E] focus:ring-[#0E6E7E] border-[#D6E0E7]"
                    />
                    <div class="flex items-center gap-2">
                        <MessageSquare class="w-4 h-4 text-[#0E6E7E]" />
                        <span class="text-xs font-bold text-[#0F2747]">
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
                        :disabled="form.processing"
                        class="h-11 px-6 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-sm font-bold transition flex items-center gap-2 shadow-xs cursor-pointer disabled:opacity-50"
                    >
                        <Check class="w-4 h-4" />
                        <span>{{ form.processing ? 'جاري الحفظ…' : 'حفظ السند والترحيل' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

