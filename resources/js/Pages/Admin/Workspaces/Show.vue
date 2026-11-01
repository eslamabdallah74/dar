<script setup lang="ts">
import { computed } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Checkbox from '@/Components/Checkbox.vue';
import {
    ArrowRight,
    Building2,
    Calendar,
    FileText,
    Home,
    Layers,
    LogIn,
    Mail,
    Sliders,
    Sparkles,
    User,
} from 'lucide-vue-next';

interface WorkspaceData {
    id: number;
    name: string;
    slug: string;
    currency: string;
    timezone: string;
    status: string;
    owner_name: string;
    owner_email: string;
    plan_name: string;
    plan_slug: string;
    created_at: string;
    parent_unit_limit: number | null;
    active_rental_limit: number | null;
}

interface UsageSummary {
    parent_units_created: number;
    parent_units_limit: number | null;
    parent_units_remaining: number | null;
    is_parent_units_unlimited: boolean;
    can_create_parent_unit: boolean;
    active_rental_slots_occupied: number;
    active_rental_limit: number | null;
    active_rental_slots_remaining: number | null;
    is_active_rental_unlimited: boolean;
    can_activate_rental: boolean;
}

interface PropertyItem {
    id: number;
    name: string;
    code: string | null;
    property_type: string;
    units_count: number;
    created_at: string;
}

interface ParentUnitItem {
    id: number;
    name: string;
    code: string | null;
    unit_type: string;
    property_name: string;
    is_rentable: boolean;
    active_leases_count: number;
}

interface ActiveLeaseItem {
    id: number;
    contract_number: string;
    renter_name: string;
    unit_name: string;
    property_name: string;
    starts_on: string;
    ends_on: string;
    rent_amount: number;
}

interface Props {
    workspace: WorkspaceData;
    usage_summary: UsageSummary;
    properties: PropertyItem[];
    parent_units: ParentUnitItem[];
    active_leases: ActiveLeaseItem[];
    plans: Array<{
        id: number;
        name: string;
        slug: string;
        parent_unit_limit: number | null;
        active_rental_limit: number | null;
        monthly_price: number | string;
    }>;
}

const props = defineProps<Props>();

const limitsForm = useForm({
    parent_unit_limit: (props.workspace.parent_unit_limit !== null ? props.workspace.parent_unit_limit : 1) as number | string,
    active_rental_limit: (props.workspace.active_rental_limit !== null ? props.workspace.active_rental_limit : 1) as number | string,
    is_parent_unit_unlimited: props.workspace.parent_unit_limit === null,
    is_active_rental_unlimited: props.workspace.active_rental_limit === null,
});

function submitLimits() {
    limitsForm.put(route('admin.workspaces.limits.update', props.workspace.id));
}

function impersonate() {
    router.post(route('admin.workspaces.impersonate', props.workspace.id));
}

const parentCreationPercent = computed(() => {
    if (props.usage_summary.is_parent_units_unlimited || !props.usage_summary.parent_units_limit) {
        return 0;
    }
    return Math.min(100, Math.round((props.usage_summary.parent_units_created / props.usage_summary.parent_units_limit) * 100));
});

const activeRentalPercent = computed(() => {
    if (props.usage_summary.is_active_rental_unlimited || !props.usage_summary.active_rental_limit) {
        return 0;
    }
    return Math.min(100, Math.round((props.usage_summary.active_rental_slots_occupied / props.usage_summary.active_rental_limit) * 100));
});
</script>

<template>
    <AppLayout :title="`إدارة مساحة العمل: ${workspace.name}`">
        <div class="space-y-6 pb-12" dir="rtl">
            <!-- Breadcrumb Navigation -->
            <div class="flex items-center gap-2 text-xs text-[#5B6770]">
                <Link :href="route('admin.dashboard')" class="hover:text-[#163E3E] transition flex items-center gap-1 font-semibold">
                    <ArrowRight class="w-3.5 h-3.5" />
                    <span>لوحة التحكم المركزية</span>
                </Link>
                <span>/</span>
                <span class="text-[#163E3E] font-bold">تفاصيل وحدود مساحة العمل: {{ workspace.name }}</span>
            </div>

            <!-- Header Card -->
            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-6 shadow-2xs">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-start gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-[#E8F0F0] text-[#163E3E] flex items-center justify-center font-bold text-xl shrink-0">
                            <Building2 class="w-7 h-7" />
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <h1 class="text-xl font-bold text-[#163E3E]">{{ workspace.name }}</h1>
                                <StatusBadge :status="workspace.status" />
                            </div>
                            <div class="flex flex-wrap items-center gap-4 text-xs text-[#5B6770]">
                                <div class="flex items-center gap-1">
                                    <User class="w-3.5 h-3.5 text-[#8899A6]" />
                                    <span>المالك: <strong class="text-[#163E3E]">{{ workspace.owner_name }}</strong></span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <Mail class="w-3.5 h-3.5 text-[#8899A6]" />
                                    <span>{{ workspace.owner_email }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <Calendar class="w-3.5 h-3.5 text-[#8899A6]" />
                                    <span>تاريخ التسجيل: {{ workspace.created_at }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <Layers class="w-3.5 h-3.5 text-[#8899A6]" />
                                    <span>الباقة الحالية: <strong class="text-[#163E3E]">{{ workspace.plan_name }}</strong></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            @click="impersonate"
                            type="button"
                            class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition flex items-center gap-1.5 cursor-pointer shadow-sm"
                        >
                            <LogIn class="w-4 h-4" />
                            <span>دخول كمدير نظام</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- KPI & Limit Usage Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Limit Card 1: Parent Unit Creation Limit -->
                <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-[#E8F0F0] text-[#163E3E] flex items-center justify-center font-bold">
                                <Home class="w-4 h-4" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-[#163E3E]">حد إنشاء الوحدات الرئيسية (Parent Units)</h3>
                                <p class="text-[11px] text-[#5B6770]">الشقق، المحلات، العمارات والمباني الرئيسية</p>
                            </div>
                        </div>
                        <span
                            class="px-2.5 py-1 rounded-full text-xs font-bold"
                            :class="usage_summary.can_create_parent_unit ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800'"
                        >
                            {{ usage_summary.can_create_parent_unit ? 'متاح للإنشاء' : 'تم بلوغ الحد الأقصى' }}
                        </span>
                    </div>

                    <div class="p-3 bg-[#F8FAFC] rounded-xl border border-[#E8EFF4] flex items-center justify-between">
                        <div>
                            <div class="text-xs text-[#5B6770]">الوحدات المنشأة حالياً</div>
                            <div class="text-2xl font-bold text-[#163E3E]">
                                {{ usage_summary.parent_units_created }}
                            </div>
                        </div>
                        <div class="text-left">
                            <div class="text-xs text-[#5B6770]">الحد الأقصى المسموح</div>
                            <div class="text-2xl font-bold" :class="usage_summary.is_parent_units_unlimited ? 'text-emerald-700' : 'text-[#163E3E]'">
                                {{ usage_summary.is_parent_units_unlimited ? 'غير محدود ∞' : usage_summary.parent_units_limit }}
                            </div>
                        </div>
                    </div>

                    <!-- Progress bar -->
                    <div v-if="!usage_summary.is_parent_units_unlimited" class="space-y-1">
                        <div class="w-full h-2 rounded-full bg-[#E8EFF4] overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                :class="parentCreationPercent >= 100 ? 'bg-rose-500' : parentCreationPercent >= 80 ? 'bg-amber-500' : 'bg-emerald-500'"
                                :style="{ width: `${parentCreationPercent}%` }"
                            ></div>
                        </div>
                        <div class="flex justify-between text-[11px] text-[#5B6770]">
                            <span>استهلاك السعة: {{ parentCreationPercent }}%</span>
                            <span>المتبقي: {{ usage_summary.parent_units_remaining }} وحدة رئيسية</span>
                        </div>
                    </div>
                    <div v-else class="text-xs text-emerald-800 font-semibold flex items-center gap-1.5 pt-1">
                        <Sparkles class="w-3.5 h-3.5 text-emerald-600" />
                        <span>مساحة العمل تتمتع بإنشاء غير محدود للوحدات الرئيسية دون أي قيود رقمية.</span>
                    </div>
                </div>

                <!-- Limit Card 2: Active Parent Rental Limit -->
                <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-800 flex items-center justify-center font-bold">
                                <FileText class="w-4 h-4" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-[#163E3E]">حد التأجير النشط المتزامن (Active Rental Slots)</h3>
                                <p class="text-[11px] text-[#5B6770]">عدد العقارات الرئيسية المشغولة بعقود إيجار سارية</p>
                            </div>
                        </div>
                        <span
                            class="px-2.5 py-1 rounded-full text-xs font-bold"
                            :class="usage_summary.can_activate_rental ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800'"
                        >
                            {{ usage_summary.can_activate_rental ? 'متاح للتأجير' : 'تم بلوغ الحد الأقصى' }}
                        </span>
                    </div>

                    <div class="p-3 bg-[#F8FAFC] rounded-xl border border-[#E8EFF4] flex items-center justify-between">
                        <div>
                            <div class="text-xs text-[#5B6770]">العقارات المؤجرة حالياً</div>
                            <div class="text-2xl font-bold text-[#163E3E]">
                                {{ usage_summary.active_rental_slots_occupied }}
                            </div>
                        </div>
                        <div class="text-left">
                            <div class="text-xs text-[#5B6770]">الحد الأقصى المسموح</div>
                            <div class="text-2xl font-bold" :class="usage_summary.is_active_rental_unlimited ? 'text-emerald-700' : 'text-[#163E3E]'">
                                {{ usage_summary.is_active_rental_unlimited ? 'غير محدود ∞' : usage_summary.active_rental_limit }}
                            </div>
                        </div>
                    </div>

                    <!-- Progress bar -->
                    <div v-if="!usage_summary.is_active_rental_unlimited" class="space-y-1">
                        <div class="w-full h-2 rounded-full bg-[#E8EFF4] overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                :class="activeRentalPercent >= 100 ? 'bg-rose-500' : activeRentalPercent >= 80 ? 'bg-amber-500' : 'bg-emerald-500'"
                                :style="{ width: `${activeRentalPercent}%` }"
                            ></div>
                        </div>
                        <div class="flex justify-between text-[11px] text-[#5B6770]">
                            <span>استهلاك السعة: {{ activeRentalPercent }}%</span>
                            <span>المتبقي: {{ usage_summary.active_rental_slots_remaining }} عقار مؤجر</span>
                        </div>
                    </div>
                    <div v-else class="text-xs text-emerald-800 font-semibold flex items-center gap-1.5 pt-1">
                        <Sparkles class="w-3.5 h-3.5 text-emerald-600" />
                        <span>مساحة العمل تتيح تأجير عدد غير محدود من العقارات بالتوازي.</span>
                    </div>
                </div>
            </div>

            <!-- Independent Limits Control Form -->
            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-6 shadow-2xs space-y-4">
                <div class="flex items-center gap-2 pb-3 border-b border-[#D6E0E7]">
                    <Sliders class="w-5 h-5 text-[#163E3E]" />
                    <h2 class="text-base font-bold text-[#163E3E]">تعديل وتخصيص حدود مساحة العمل المستقلة</h2>
                </div>
                <p class="text-xs text-[#5B6770]">
                    بصفتك مدير النظام، يمكنك تعيين حدود مساحة العمل هذه بصورة مستقلة تماماً عن أي باقة عامة، وتطبيقها فوراً بنقرة زر.
                </p>

                <form @submit.prevent="submitLimits" class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                    <!-- Creation Limit Input -->
                    <div class="p-4 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] space-y-3">
                        <div class="flex items-center justify-between">
                            <InputLabel for="parent_unit_limit" value="الحد الأقصى لإنشاء الوحدات الرئيسية" class="font-bold text-[#163E3E]" />
                            <label class="flex items-center gap-1.5 text-xs text-[#163E3E] font-semibold cursor-pointer">
                                <Checkbox v-model:checked="limitsForm.is_parent_unit_unlimited" />
                                <span>غير محدود (Unlimited)</span>
                            </label>
                        </div>
                        <p class="text-[11px] text-[#5B6770]">
                            يحدد عدد الشقق أو المحلات المستقلة التي يمكن للمستخدم إنشاؤها. (الوحدات الفرعية كالأسرة والغرف غير مقيدة).
                        </p>
                        <div v-if="!limitsForm.is_parent_unit_unlimited">
                            <TextInput
                                id="parent_unit_limit"
                                type="number"
                                min="0"
                                v-model="limitsForm.parent_unit_limit"
                                class="w-full h-10 px-3 bg-white"
                                required
                            />
                        </div>
                        <div v-else class="text-xs font-bold text-emerald-700 bg-emerald-50 p-2.5 rounded-lg text-center">
                            سعة غير محدودة لإنشاء الوحدات الرئيسية (∞)
                        </div>
                        <InputError :message="limitsForm.errors.parent_unit_limit" />
                    </div>

                    <!-- Active Rental Limit Input -->
                    <div class="p-4 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] space-y-3">
                        <div class="flex items-center justify-between">
                            <InputLabel for="active_rental_limit" value="الحد الأقصى للعقود التأجيرية النشطة" class="font-bold text-[#163E3E]" />
                            <label class="flex items-center gap-1.5 text-xs text-[#163E3E] font-semibold cursor-pointer">
                                <Checkbox v-model:checked="limitsForm.is_active_rental_unlimited" />
                                <span>غير محدود (Unlimited)</span>
                            </label>
                        </div>
                        <p class="text-[11px] text-[#5B6770]">
                            يحدد كم عقاراً رئيسياً يمكن أن يكون مؤجراً في نفس الوقت. تأجير غرف متعددة في نفس الشقة يستهلك خانة واحدة.
                        </p>
                        <div v-if="!limitsForm.is_active_rental_unlimited">
                            <TextInput
                                id="active_rental_limit"
                                type="number"
                                min="0"
                                v-model="limitsForm.active_rental_limit"
                                class="w-full h-10 px-3 bg-white"
                                required
                            />
                        </div>
                        <div v-else class="text-xs font-bold text-emerald-700 bg-emerald-50 p-2.5 rounded-lg text-center">
                            سعة غير محدودة للتأجير النشط المتزامن (∞)
                        </div>
                        <InputError :message="limitsForm.errors.active_rental_limit" />
                    </div>

                    <div class="md:col-span-2 flex items-center justify-end gap-3 pt-2">
                        <PrimaryButton :disabled="limitsForm.processing" class="!px-6 !py-2.5 !text-xs !bg-[#163E3E] !text-white hover:!bg-[#17334F]">
                            حفظ وتطبيق الحدود فوراً
                        </PrimaryButton>
                    </div>
                </form>
            </div>

            <!-- Content Lists Grid: Parent Units & Active Leases -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Parent Units List -->
                <div class="rounded-2xl border border-[#D6E0E7] bg-white overflow-hidden shadow-2xs">
                    <div class="p-4 border-b border-[#D6E0E7] bg-[#F8FAFC] flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Home class="w-4 h-4 text-[#163E3E]" />
                            <h3 class="text-sm font-bold text-[#163E3E]">الوحدات الرئيسية المنشأة ({{ parent_units.length }})</h3>
                        </div>
                        <span class="text-xs text-[#5B6770]">الوحدات المستقلة ذات parent_unit_id = null</span>
                    </div>

                    <div class="overflow-x-auto max-h-80 divide-y divide-[#E8EFF4]">
                        <table class="w-full text-right text-xs">
                            <thead class="bg-[#F8FAFC] text-[#5B6770] font-bold">
                                <tr>
                                    <th class="p-3">الوحدة</th>
                                    <th class="p-3">النوع</th>
                                    <th class="p-3">العقار</th>
                                    <th class="p-3 text-center">العقود النشطة</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E8EFF4]">
                                <tr v-for="unit in parent_units" :key="unit.id" class="hover:bg-[#F8FAFC]">
                                    <td class="p-3 font-bold text-[#163E3E]">
                                        {{ unit.name }}
                                        <span v-if="unit.code" class="text-[10px] text-[#5B6770] block font-mono">{{ unit.code }}</span>
                                    </td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#E8F0F0] text-[#163E3E]">
                                            {{ unit.unit_type === 'apartment' ? 'شقة' : unit.unit_type === 'shop' ? 'محل' : unit.unit_type }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-[#5B6770]">{{ unit.property_name }}</td>
                                    <td class="p-3 text-center">
                                        <span
                                            class="px-2 py-0.5 rounded-full text-[11px] font-bold"
                                            :class="unit.active_leases_count > 0 ? 'bg-emerald-50 text-emerald-800' : 'bg-gray-100 text-[#5B6770]'"
                                        >
                                            {{ unit.active_leases_count }} عقد
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="parent_units.length === 0">
                                    <td colspan="4" class="p-6 text-center text-[#5B6770]">لم يتم إنشاء أي وحدات رئيسية في مساحة العمل هذه بعد.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Active Leases List -->
                <div class="rounded-2xl border border-[#D6E0E7] bg-white overflow-hidden shadow-2xs">
                    <div class="p-4 border-b border-[#D6E0E7] bg-[#F8FAFC] flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <FileText class="w-4 h-4 text-emerald-700" />
                            <h3 class="text-sm font-bold text-[#163E3E]">العقود النشطة حالياً ({{ active_leases.length }})</h3>
                        </div>
                        <span class="text-xs text-[#5B6770]">تستهلك {{ usage_summary.active_rental_slots_occupied }} خانة عقار</span>
                    </div>

                    <div class="overflow-x-auto max-h-80 divide-y divide-[#E8EFF4]">
                        <table class="w-full text-right text-xs">
                            <thead class="bg-[#F8FAFC] text-[#5B6770] font-bold">
                                <tr>
                                    <th class="p-3">رقم العقد</th>
                                    <th class="p-3">المستأجر</th>
                                    <th class="p-3">الوحدة والعقار</th>
                                    <th class="p-3 text-center">الفترة</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E8EFF4]">
                                <tr v-for="lease in active_leases" :key="lease.id" class="hover:bg-[#F8FAFC]">
                                    <td class="p-3 font-mono font-bold text-[#163E3E]">{{ lease.contract_number }}</td>
                                    <td class="p-3 font-semibold text-[#163E3E]">{{ lease.renter_name }}</td>
                                    <td class="p-3">
                                        <div class="font-bold text-[#163E3E]">{{ lease.unit_name }}</div>
                                        <div class="text-[10px] text-[#5B6770]">{{ lease.property_name }}</div>
                                    </td>
                                    <td class="p-3 text-center text-[11px] text-[#5B6770]">
                                        {{ lease.starts_on }} - {{ lease.ends_on }}
                                    </td>
                                </tr>
                                <tr v-if="active_leases.length === 0">
                                    <td colspan="4" class="p-6 text-center text-[#5B6770]">لا توجد عقود تأجيرية نشطة حالياً في مساحة العمل.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
