<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    AlertTriangle,
    ArrowRight,
    Building2,
    Calendar,
    Check,
    CheckCircle2,
    Clock,
    CreditCard,
    DollarSign,
    FileCheck,
    FilePlus,
    FileText,
    HelpCircle,
    Home,
    Info,
    ShieldAlert,
    User,
    X,
} from 'lucide-vue-next';

interface UnitOption {
    id: number;
    name: string;
    code: string | null;
    property_id: number;
    property_name: string;
    is_occupied: boolean;
    active_leases: Array<{
        id: number;
        renter_name: string;
        starts_on: string;
        ends_on: string;
    }>;
}

const props = defineProps<{
    units: UnitOption[];
    renters: Array<{
        id: number;
        name: string;
        phone: string;
        national_id: string | null;
    }>;
    properties: Array<{
        id: number;
        name: string;
    }>;
    preselected_unit_id?: string | number;
    preselected_renter_id?: string | number;
}>();

const selectedPropertyId = ref<string | number>(
    props.units.find(u => String(u.id) === String(props.preselected_unit_id))?.property_id || (props.properties[0]?.id ?? '')
);

const filteredUnits = computed(() => {
    if (!selectedPropertyId.value) return props.units;
    return props.units.filter(u => u.property_id === Number(selectedPropertyId.value));
});

const todayDate = new Date().toISOString().split('T')[0];
const nextYearDate = new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

const form = useForm({
    unit_id: props.preselected_unit_id ? Number(props.preselected_unit_id) : (filteredUnits.value[0]?.id ?? ''),
    renter_id: props.preselected_renter_id ? Number(props.preselected_renter_id) : (props.renters[0]?.id ?? ''),
    starts_on: todayDate,
    ends_on: nextYearDate,
    rent_amount: 3200,
    deposit_amount: 3000,
    billing_interval: 'monthly',
    notes: '',
});

// Conflict Detection State (مطابق لفيجما: Create contract — Conflict)
const checkingConflict = ref(false);
const conflictData = ref<{
    has_conflict: boolean;
    conflicting_lease: {
        id: number;
        contract_number: string;
        renter_name: string;
        unit_name: string;
        starts_on: string;
        ends_on: string;
        rent_amount: number;
    } | null;
    message: string;
} | null>(null);

const runConflictCheck = async () => {
    if (!form.unit_id || !form.starts_on || !form.ends_on) return;
    if (new Date(form.ends_on) <= new Date(form.starts_on)) return;

    checkingConflict.value = true;
    try {
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
        const response = await fetch(route('leases.check-conflict'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                unit_id: form.unit_id,
                starts_on: form.starts_on,
                ends_on: form.ends_on,
            }),
        });
        if (response.ok) {
            conflictData.value = await response.json();
        }
    } catch (e) {
        console.error('Conflict check error', e);
    } finally {
        checkingConflict.value = false;
    }
};

watch(
    () => [form.unit_id, form.starts_on, form.ends_on],
    () => {
        runConflictCheck();
    },
    { immediate: true }
);

const submit = () => {
    form.post(route('leases.store'));
};
</script>

<template>
    <AppLayout
        title="إنشاء عقد إيجار جديد — دار"
        active-tab="leases"
        header-title="إنشاء عقد إيجار جديد"
        header-subtitle="توثيق بيانات العقد، تحديد الوحدة والمستأجر مع فحص التعارض الزمني التلقائي"
        breadcrumb="العقود / عقد جديد"
    >
        <template #actions>
            <Link
                :href="route('leases.index')"
                class="h-10 px-4 rounded-xl border border-[#D6E0E7] bg-white hover:bg-[#F3F7FA] text-xs font-semibold text-[#5B6770] transition flex items-center gap-1.5"
            >
                <ArrowRight class="w-4 h-4" />
                <span>إلغاء والعودة للعقود</span>
            </Link>
        </template>

        <form @submit.prevent="submit" class="max-w-4xl mx-auto space-y-6">
            <!-- 1. بطاقة اختيار العقار والوحدة التأجيرية -->
            <div class="rounded-3xl border border-[#D6E0E7] bg-white p-6 shadow-2xs space-y-5">
                <div class="flex items-center gap-2.5 pb-3 border-b border-[#D6E0E7]">
                    <div class="w-8 h-8 rounded-lg bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center">
                        <Home class="w-4 h-4" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-[#0F2747]">1. اختيار العقار والوحدة</h3>
                        <p class="text-xs text-[#5B6770]">حدد المبنى والوحدة السكنية المراد تأجيرها</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">العقار / المبنى *</label>
                        <select
                            v-model="selectedPropertyId"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition"
                        >
                            <option v-for="p in properties" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">الوحدة التأجيرية *</label>
                        <select
                            v-model="form.unit_id"
                            required
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition"
                        >
                            <option v-for="u in filteredUnits" :key="u.id" :value="u.id">
                                {{ u.name }} {{ u.is_occupied ? '(مشغولة حالياً)' : '(شاغرة)' }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- 2. بطاقة بيانات المستأجر -->
            <div class="rounded-3xl border border-[#D6E0E7] bg-white p-6 shadow-2xs space-y-5">
                <div class="flex items-center gap-2.5 pb-3 border-b border-[#D6E0E7]">
                    <div class="w-8 h-8 rounded-lg bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center">
                        <User class="w-4 h-4" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-[#0F2747]">2. المستأجر الطرف الثاني</h3>
                        <p class="text-xs text-[#5B6770]">اختر مستأجر مسجل أو أضف مستأجراً جديداً</p>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-[#0F2747] mb-1.5">المستأجر *</label>
                    <select
                        v-model="form.renter_id"
                        required
                        class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition"
                    >
                        <option v-for="r in renters" :key="r.id" :value="r.id">
                            {{ r.name }} — هاتف: {{ r.phone }} (هوية: {{ r.national_id || '—' }})
                        </option>
                    </select>
                </div>
            </div>

            <!-- 3. التواريخ والبيانات المالية -->
            <div class="rounded-3xl border border-[#D6E0E7] bg-white p-6 shadow-2xs space-y-5">
                <div class="flex items-center gap-2.5 pb-3 border-b border-[#D6E0E7]">
                    <div class="w-8 h-8 rounded-lg bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center">
                        <Calendar class="w-4 h-4" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-[#0F2747]">3. مدة العقد والالتزامات المالية</h3>
                        <p class="text-xs text-[#5B6770]">تحديد تواريخ السريان والقيمة الإيجارية الشهرية</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">تاريخ بداية العقد *</label>
                        <input
                            v-model="form.starts_on"
                            type="date"
                            required
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-left"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">تاريخ نهاية العقد *</label>
                        <input
                            v-model="form.ends_on"
                            type="date"
                            required
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-left"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">القيمة الإيجارية الشهرية *</label>
                        <div class="relative">
                            <input
                                v-model="form.rent_amount"
                                type="number"
                                step="0.01"
                                required
                                class="w-full h-11 pl-12 pr-4 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm font-bold text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-left"
                            />
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-[#5B6770]">
                                ر.س
                            </span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">مبلغ التأمين / الوديعة المستلمة</label>
                        <div class="relative">
                            <input
                                v-model="form.deposit_amount"
                                type="number"
                                step="0.01"
                                class="w-full h-11 pl-12 pr-4 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm font-bold text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-left"
                            />
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-[#5B6770]">
                                ر.س
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. كارت كشف التعارض الزمني المتطابق تماماً مع فيجما (Create contract — Conflict) -->
            <div
                v-if="conflictData"
                class="rounded-3xl border p-6 transition-all duration-300"
                :class="conflictData.has_conflict
                    ? 'border-amber-300 bg-amber-50/70 shadow-sm'
                    : 'border-emerald-300 bg-emerald-50/60'"
            >
                <!-- في حالة وجود تعارض -->
                <div v-if="conflictData.has_conflict" class="space-y-4">
                    <div class="flex items-start gap-3.5">
                        <div class="w-10 h-10 rounded-2xl bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-xs">
                            <AlertTriangle class="w-5 h-5 stroke-[2.2]" />
                        </div>
                        <div class="flex-1">
                            <h4 class="text-sm font-bold text-[#0F2747]">
                                تنبيه تعارض زمني: الوحدة محجوزة بالفعل بعقد سارٍ!
                            </h4>
                            <p class="text-xs text-[#5B6770] mt-1 leading-relaxed">
                                {{ conflictData.message }}
                            </p>
                        </div>
                    </div>

                    <!-- بطاقة تفاصيل العقد المتعارض -->
                    <div v-if="conflictData.conflicting_lease" class="rounded-2xl border border-amber-300/80 bg-white p-4 space-y-3">
                        <div class="flex items-center justify-between text-xs pb-2 border-b border-amber-100">
                            <span class="font-bold text-[#0F2747]">تفاصيل العقد القائم المتعارض:</span>
                            <span class="font-mono font-bold text-[#0E6E7E] bg-[#E3F1F4] px-2 py-0.5 rounded">
                                {{ conflictData.conflicting_lease.contract_number }}
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                            <div>
                                <span class="text-[#5B6770] block">المستأجر الحالي:</span>
                                <span class="font-bold text-[#0F2747]">{{ conflictData.conflicting_lease.renter_name }}</span>
                            </div>
                            <div>
                                <span class="text-[#5B6770] block">فترة العقد الحالي:</span>
                                <span class="font-mono text-[#0F2747]">
                                    {{ conflictData.conflicting_lease.starts_on }} — {{ conflictData.conflicting_lease.ends_on }}
                                </span>
                            </div>
                            <div>
                                <span class="text-[#5B6770] block">القيمة الإيجارية:</span>
                                <span class="font-mono font-bold text-[#0F2747]">{{ conflictData.conflicting_lease.rent_amount }} ر.س</span>
                            </div>
                        </div>

                        <div class="p-2.5 rounded-xl bg-amber-100/60 text-[11px] text-amber-950 flex items-center gap-2">
                            <Info class="w-4 h-4 text-amber-700 shrink-0" />
                            <span>
                                اقتراح النظام: اختر تاريخ بداية بعد تاريخ انتهاء العقد الحالي ({{ conflictData.conflicting_lease.ends_on }}) أو اختر وحدة شاغرة أخرى.
                            </span>
                        </div>
                    </div>
                </div>

                <!-- في حالة عدم وجود أي تعارض -->
                <div v-else class="flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-xs">
                        <CheckCircle2 class="w-5 h-5" />
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-emerald-950">
                            الوحدة متاحة للتأجير في هذه الفترة
                        </h4>
                        <p class="text-xs text-emerald-800 mt-0.5">
                            لا يوجد أي تعارض زمني أو حجوزات سابقة لهذه الوحدة بين {{ form.starts_on }} و {{ form.ends_on }}.
                        </p>
                    </div>
                </div>
            </div>

            <!-- أزرار الحفظ والاعتماد -->
            <div class="pt-4 flex items-center justify-end gap-3">
                <Link
                    :href="route('leases.index')"
                    class="h-11 px-5 rounded-xl border border-[#D6E0E7] bg-white text-xs font-semibold text-[#5B6770] hover:bg-slate-100 transition flex items-center justify-center"
                >
                    إلغاء
                </Link>

                <button
                    type="submit"
                    :disabled="form.processing || (conflictData?.has_conflict ?? false)"
                    class="h-11 px-6 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-sm font-bold transition flex items-center gap-2 shadow-xs cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    <Check class="w-4 h-4 stroke-[2.5]" />
                    <span>{{ form.processing ? 'جاري الاعتماد...' : 'اعتماد وتفعيل العقد' }}</span>
                </button>
            </div>
        </form>
    </AppLayout>
</template>
