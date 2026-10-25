<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import {
    ArrowLeft,
    Building2,
    Calendar,
    Coins,
    Clock,
    FileCheck,
    FilePlus,
    FileText,
    Home,
    MessageCircle,
    Phone,
    Plus,
    Search,
    Shield,
    ShieldAlert,
    ShieldCheck,
    Store,
    User,
    X,
} from 'lucide-vue-next';

interface LeaseItem {
    id: number;
    contract_number: string;
    renter_id: number;
    renter_name: string;
    renter_phone: string;
    property_name: string;
    unit_name: string;
    unit_code: string;
    unit_type?: string;
    unit_type_label?: string;
    security_deposit_amount?: number;
    security_deposit_status?: string;
    security_deposit_status_label?: string;
    starts_on: string;
    ends_on: string;
    rent_amount: number;
    billing_interval: string;
    status: string;
    status_label: string;
    is_expiring_soon: boolean;
    is_past_end_date?: boolean;
}

const props = defineProps<{
    leases: LeaseItem[];
    stats: {
        active_leases: number;
        past_end_date?: number;
        expiring_soon: number;
        total_annual_value: number;
        total_leases: number;
        total_deposits_held?: number;
    };
    currency?: string;
}>();

const searchQuery = ref('');
const statusFilter = ref('all');

const currencySymbol = computed(() => {
    const c = props.currency || 'EGP';
    return c === 'EGP' ? 'ج.م' : c === 'SAR' ? 'ر.س' : c;
});

const filteredLeases = computed(() => {
    return props.leases.filter(l => {
        const query = searchQuery.value.trim().toLowerCase();
        const matchesSearch = !query
            || l.contract_number.toLowerCase().includes(query)
            || l.renter_name.toLowerCase().includes(query)
            || l.unit_name.toLowerCase().includes(query)
            || l.property_name.toLowerCase().includes(query)
            || (l.renter_phone && l.renter_phone.includes(query));

        const matchesStatus = statusFilter.value === 'all'
            || (statusFilter.value === 'active' && l.status === 'active' && !l.is_expiring_soon && !l.is_past_end_date)
            || (statusFilter.value === 'past_due' && l.is_past_end_date)
            || (statusFilter.value === 'expiring' && l.is_expiring_soon)
            || (statusFilter.value === 'with_deposit' && (l.security_deposit_amount ?? 0) > 0);

        return matchesSearch && matchesStatus;
    });
});

const depositStatusClass = (status?: string) => {
    switch (status) {
        case 'held':
            return 'bg-emerald-50 text-emerald-800 border-emerald-200';
        case 'partially_received':
            return 'bg-amber-50 text-amber-800 border-amber-200';
        case 'due':
            return 'bg-blue-50 text-blue-800 border-blue-200';
        case 'under_review':
            return 'bg-purple-50 text-purple-800 border-purple-200';
        case 'settled':
            return 'bg-slate-100 text-slate-700 border-slate-200';
        default:
            return 'bg-slate-50 text-slate-600 border-slate-200';
    }
};
</script>

<template>
    <AppLayout
        title="عقود الإيجار والتأمين — دار"
        active-tab="leases"
        header-title="عقود الإيجار والتأمين"
        header-subtitle="إدارة وتوثيق عقود الإيجار السكنية والتجارية، متابعة التأمين المسترد وتواريخ التجديد"
        breadcrumb="العقود"
    >
        <template #actions>
            <Link
                :href="route('leases.create')"
                class="w-full sm:w-auto h-11 px-5 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs sm:text-sm font-bold transition shadow-xs flex items-center justify-center gap-2 cursor-pointer"
            >
                <Plus class="h-4 w-4 stroke-[2.5]" />
                <span>إنشاء عقد إيجار جديد</span>
            </Link>
        </template>

        <!-- كروت مؤشرات العقود الأربعة المتجاوبة للموبايل والشاشات الكبيرة -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <!-- 1. العقود السارية -->
            <div class="rounded-2xl sm:rounded-3xl border border-[#D6E0E7] bg-white p-3.5 sm:p-5 shadow-2xs space-y-2 hover:border-[#0E6E7E]/40 transition">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] sm:text-xs font-semibold text-[#5B6770]">العقود السارية</span>
                    <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg sm:rounded-xl bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center">
                        <FileCheck class="w-3.5 h-3.5 sm:w-4 sm:h-4 stroke-[2.2]" />
                    </div>
                </div>
                <div class="flex items-baseline justify-between gap-1">
                    <span class="text-xl sm:text-2xl font-black text-[#0F2747] font-mono">{{ stats.active_leases }}</span>
                    <span class="text-[10px] sm:text-xs text-[#0E6E7E] font-semibold bg-[#E3F1F4] px-1.5 sm:px-2 py-0.5 rounded-md">عقد نشط</span>
                </div>
            </div>

            <!-- 2. عقود تنتهي قريباً -->
            <div class="rounded-2xl sm:rounded-3xl border border-[#D6E0E7] bg-white p-3.5 sm:p-5 shadow-2xs space-y-2 hover:border-amber-300 transition">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] sm:text-xs font-semibold text-[#5B6770]">تنتهي خلال 30 يوم</span>
                    <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg sm:rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center">
                        <Clock class="w-3.5 h-3.5 sm:w-4 sm:h-4 stroke-[2.2]" />
                    </div>
                </div>
                <div class="flex items-baseline justify-between gap-1">
                    <span class="text-xl sm:text-2xl font-black text-amber-700 font-mono">{{ stats.expiring_soon }}</span>
                    <span class="text-[10px] sm:text-xs text-amber-800 bg-amber-50 px-1.5 sm:px-2 py-0.5 rounded-md font-bold">
                        تتطلب متابعة
                    </span>
                </div>
            </div>

            <!-- 3. إجمالي الإيجار السنوي -->
            <div class="rounded-2xl sm:rounded-3xl border border-[#D6E0E7] bg-white p-3.5 sm:p-5 shadow-2xs space-y-2 hover:border-[#0E6E7E]/40 transition">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] sm:text-xs font-semibold text-[#5B6770]">القيمة السنوية للعقود</span>
                    <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg sm:rounded-xl bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center">
                        <Coins class="w-3.5 h-3.5 sm:w-4 sm:h-4 stroke-[2.2]" />
                    </div>
                </div>
                <div class="flex items-baseline justify-between gap-1">
                    <span class="text-lg sm:text-2xl font-black text-[#0F2747] font-mono truncate">
                        {{ Math.round(stats.total_annual_value ?? 0).toLocaleString() }}
                        <span class="text-[10px] sm:text-xs font-sans font-normal text-[#5B6770]">{{ currencySymbol }}</span>
                    </span>
                    <span class="text-[10px] sm:text-xs text-[#5B6770]">سنوياً</span>
                </div>
            </div>

            <!-- 4. إجمالي التأمين المسترد المحتجز -->
            <div class="rounded-2xl sm:rounded-3xl border border-[#D6E0E7] bg-white p-3.5 sm:p-5 shadow-2xs space-y-2 hover:border-emerald-300 transition">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] sm:text-xs font-semibold text-[#5B6770]">تأمينات محتجزة</span>
                    <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg sm:rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
                        <ShieldCheck class="w-3.5 h-3.5 sm:w-4 sm:h-4 stroke-[2.2]" />
                    </div>
                </div>
                <div class="flex items-baseline justify-between gap-1">
                    <span class="text-lg sm:text-2xl font-black text-emerald-700 font-mono truncate">
                        {{ Math.round(stats.total_deposits_held ?? 0).toLocaleString() }}
                        <span class="text-[10px] sm:text-xs font-sans font-normal text-emerald-600">{{ currencySymbol }}</span>
                    </span>
                    <span class="text-[10px] sm:text-xs text-emerald-800 bg-emerald-50 px-1.5 sm:px-2 py-0.5 rounded-md font-bold">
                        تأمين مسترد
                    </span>
                </div>
            </div>
        </div>

        <!-- تنبيه بالعقود التي تجاوزت موعدها وبانتظار قرار الإدارة -->
        <div v-if="(stats.past_end_date ?? 0) > 0" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-2xl sm:rounded-3xl bg-amber-50 border border-amber-200 text-amber-900 shadow-2xs">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-amber-200/80 text-amber-900 flex items-center justify-center shrink-0">
                    <Clock class="w-5 h-5 stroke-[2.2]" />
                </div>
                <div>
                    <span class="font-bold text-xs sm:text-sm block text-amber-950">يوجد {{ stats.past_end_date }} عقود تجاوزت تاريخ انتهائها وبانتظار قرار الإدارة</span>
                    <span class="text-[11px] text-amber-800">العقود لا تنتهي تلقائياً حتى لا تضيع حقوق التأمين والتسليم. يرجى اتخاذ إجراء (التجديد أو الإنهاء والتسوية).</span>
                </div>
            </div>
            <button
                type="button"
                @click="statusFilter = 'past_due'"
                class="px-4 py-2 rounded-xl bg-amber-800 hover:bg-amber-900 text-white text-xs font-bold transition shrink-0 cursor-pointer shadow-xs self-start sm:self-auto"
            >
                عرض العقود المتطلبة للقرار
            </button>
        </div>

        <!-- شريط البحث والتصفية المتجاوب -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-white p-3.5 sm:p-4 rounded-2xl sm:rounded-3xl border border-[#D6E0E7] shadow-2xs">
            <div class="relative flex-1 max-w-md">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="ابحث برقم العقد، اسم المستأجر، رقم الهاتف أو الوحدة..."
                    class="w-full h-11 pr-10 pl-4 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-xs sm:text-sm text-[#0F2747] placeholder:text-[#5B6770] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-right"
                />
                <Search class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-[#5B6770]" />
                <button
                    v-if="searchQuery"
                    @click="searchQuery = ''"
                    type="button"
                    class="absolute left-3 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600"
                >
                    <X class="w-3.5 h-3.5" />
                </button>
            </div>

            <!-- أزرار التصفية السريعة -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0 scrollbar-none">
                <button
                    v-for="f in [
                        { id: 'all', label: 'كافة العقود' },
                        { id: 'past_due', label: 'انتهت مدتها (' + (stats.past_end_date ?? 0) + ')' },
                        { id: 'active', label: 'سارية' },
                        { id: 'expiring', label: 'تنتهي قريباً (' + stats.expiring_soon + ')' },
                        { id: 'with_deposit', label: 'تتضمن تأمين' },
                    ]"
                    :key="f.id"
                    @click="statusFilter = f.id"
                    type="button"
                    class="px-3 sm:px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer shrink-0"
                    :class="statusFilter === f.id
                        ? 'bg-[#0E6E7E] text-white shadow-2xs'
                        : 'bg-[#F8FAFC] text-[#5B6770] hover:text-[#0F2747] border border-[#D6E0E7]'"
                >
                    {{ f.label }}
                </button>
            </div>
        </div>

        <!-- 1. عرض بطاقات الموبايل المتجاوبة (Mobile Card Layout for < lg) -->
        <div v-if="filteredLeases.length > 0" class="block lg:hidden space-y-4">
            <div
                v-for="lease in filteredLeases"
                :key="lease.id"
                class="rounded-2xl border border-[#D6E0E7] bg-white p-4 sm:p-5 shadow-2xs hover:shadow-xs transition space-y-3.5"
            >
                <!-- رأس البطاقة: رقم العقد + الحالة -->
                <div class="flex items-center justify-between pb-3 border-b border-[#D6E0E7]">
                    <div class="flex items-center gap-2">
                        <span class="font-mono font-bold text-xs px-2.5 py-1 rounded-lg bg-[#E3F1F4] text-[#0E6E7E] flex items-center gap-1">
                            <FileText class="w-3 h-3" />
                            <span>{{ lease.contract_number }}</span>
                        </span>
                        <span v-if="lease.is_past_end_date" class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300">
                            انتهى موعده (يتطلب إجراء)
                        </span>
                        <span v-else-if="lease.is_expiring_soon" class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                            ينتهي قريباً
                        </span>
                    </div>

                    <StatusBadge
                        :status="lease.is_past_end_date ? 'past_due' : (lease.is_expiring_soon ? 'expiring' : lease.status)"
                        :label="lease.status_label"
                        size="sm"
                    />
                </div>

                <!-- الوحدة والعقار -->
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-[#F8FAFC] border border-[#D6E0E7] text-[#0E6E7E] flex items-center justify-center shrink-0">
                            <Store v-if="lease.unit_type === 'shop'" class="w-4 h-4 stroke-[2.2]" />
                            <Home v-else class="w-4 h-4 stroke-[2.2]" />
                        </div>
                        <div>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="font-bold text-sm text-[#0F2747]">{{ lease.unit_name }}</span>
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-[#5B6770] font-bold">
                                    {{ lease.unit_type_label }}
                                </span>
                            </div>
                            <span class="text-xs text-[#5B6770] flex items-center gap-1 mt-0.5">
                                <Building2 class="w-3 h-3 text-[#5B6770]" />
                                <span>{{ lease.property_name }}</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- المستأجر ومعلومات الاتصال -->
                <div class="p-3 rounded-xl bg-[#F8FAFC] border border-[#D6E0E7] flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-[#0E6E7E] text-white flex items-center justify-center font-bold text-xs shrink-0">
                            {{ lease.renter_name.charAt(0) }}
                        </div>
                        <div class="min-w-0">
                            <Link
                                :href="route('renters.show', lease.renter_id)"
                                class="font-bold text-xs text-[#0F2747] hover:text-[#0E6E7E] transition truncate block"
                            >
                                {{ lease.renter_name }}
                            </Link>
                            <span class="text-[11px] text-[#5B6770] font-mono block mt-0.5" dir="ltr">
                                {{ lease.renter_phone }}
                            </span>
                        </div>
                    </div>

                    <!-- أزرار الاتصال السريع والواتساب -->
                    <div class="flex items-center gap-1 shrink-0">
                        <a
                            v-if="lease.renter_phone"
                            :href="`https://wa.me/20${lease.renter_phone.replace(/^0+/, '')}`"
                            target="_blank"
                            class="p-2 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition"
                            title="محادثة واتساب"
                        >
                            <MessageCircle class="w-4 h-4" />
                        </a>
                        <a
                            v-if="lease.renter_phone"
                            :href="`tel:${lease.renter_phone}`"
                            class="p-2 rounded-lg bg-slate-100 text-[#0F2747] hover:bg-slate-200 transition"
                            title="اتصال هاتفي"
                        >
                            <Phone class="w-4 h-4" />
                        </a>
                    </div>
                </div>

                <!-- تفاصيل الحساب: الإيجار الشهري + التأمين المسترد -->
                <div class="grid grid-cols-2 gap-2.5 text-xs">
                    <!-- الإيجار الشهري -->
                    <div class="p-2.5 rounded-xl border border-[#D6E0E7] bg-white space-y-1">
                        <span class="text-[11px] text-[#5B6770] block">الإيجار الشهري</span>
                        <div class="font-mono font-black text-sm text-[#0F2747]">
                            {{ (lease.rent_amount ?? 0).toLocaleString() }}
                            <span class="text-[10px] font-sans font-normal text-[#5B6770]">{{ currencySymbol }}</span>
                        </div>
                    </div>

                    <!-- التأمين المسترد -->
                    <div class="p-2.5 rounded-xl border border-[#D6E0E7] bg-white space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] text-[#5B6770] flex items-center gap-1">
                                <Shield class="w-3 h-3 text-[#0E6E7E]" />
                                <span>التأمين</span>
                            </span>
                        </div>

                        <div v-if="(lease.security_deposit_amount ?? 0) > 0">
                            <div class="font-mono font-black text-sm text-emerald-700">
                                {{ (lease.security_deposit_amount ?? 0).toLocaleString() }}
                                <span class="text-[10px] font-sans font-normal text-emerald-600">{{ currencySymbol }}</span>
                            </div>
                            <span class="inline-block mt-1 text-[10px] px-1.5 py-0.5 rounded font-bold border" :class="depositStatusClass(lease.security_deposit_status)">
                                {{ lease.security_deposit_status_label }}
                            </span>
                        </div>
                        <div v-else class="text-[11px] text-slate-400 font-semibold pt-1">
                            بدون تأمين
                        </div>
                    </div>
                </div>

                <!-- تواريخ العقد -->
                <div class="flex items-center justify-between text-xs text-[#5B6770] pt-1">
                    <span class="flex items-center gap-1.5 font-mono">
                        <Calendar class="w-3.5 h-3.5 text-[#5B6770]" />
                        <span>{{ lease.starts_on }} ➔ {{ lease.ends_on }}</span>
                    </span>
                    <span class="text-[11px] font-semibold text-[#0E6E7E]">{{ lease.billing_interval }}</span>
                </div>

                <!-- زر تفاصيل العقد والتأمين الكامل -->
                <Link
                    :href="route('leases.show', lease.id)"
                    class="w-full h-11 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-bold transition flex items-center justify-center gap-2 shadow-2xs"
                >
                    <span>تفاصيل العقد والتأمين</span>
                    <ArrowLeft class="w-4 h-4" />
                </Link>
            </div>
        </div>

        <!-- 2. جدول العقود المطور للشاشات الكبيرة (Desktop Table Layout for lg+) -->
        <div v-if="filteredLeases.length > 0" class="hidden lg:block rounded-3xl border border-[#D6E0E7] bg-white overflow-hidden shadow-2xs">
            <div class="overflow-x-auto">
                <table class="w-full text-right text-sm">
                    <thead>
                        <tr class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] text-xs font-bold">
                            <th class="py-4 px-6">رقم العقد</th>
                            <th class="py-4 px-6">المستأجر</th>
                            <th class="py-4 px-6">العقار والوحدة</th>
                            <th class="py-4 px-6">مدة وسريان العقد</th>
                            <th class="py-4 px-6">الإيجار الشهري</th>
                            <th class="py-4 px-6">تأمين الوحدة المسترد</th>
                            <th class="py-4 px-6">حالة العقد</th>
                            <th class="py-4 px-6 text-left">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#D6E0E7]">
                        <tr
                            v-for="lease in filteredLeases"
                            :key="lease.id"
                            class="hover:bg-[#F3F7FA] transition group"
                        >
                            <!-- رقم العقد -->
                            <td class="py-4 px-6">
                                <Link
                                    :href="route('leases.show', lease.id)"
                                    class="font-mono font-bold text-xs px-2.5 py-1 rounded-lg bg-[#E3F1F4] text-[#0E6E7E] hover:bg-[#0E6E7E] hover:text-white transition inline-flex items-center gap-1 shadow-2xs"
                                >
                                    <FileText class="w-3.5 h-3.5" />
                                    <span>{{ lease.contract_number }}</span>
                                </Link>
                            </td>

                            <!-- المستأجر -->
                            <td class="py-4 px-6">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg bg-[#0E6E7E] text-white flex items-center justify-center font-bold text-xs shrink-0">
                                        {{ lease.renter_name.charAt(0) }}
                                    </div>
                                    <div>
                                        <Link
                                            :href="route('renters.show', lease.renter_id)"
                                            class="font-bold text-xs text-[#0F2747] hover:text-[#0E6E7E] transition block"
                                        >
                                            {{ lease.renter_name }}
                                        </Link>
                                        <span class="text-[11px] text-[#5B6770] font-mono block mt-0.5" dir="ltr">{{ lease.renter_phone }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- العقار والوحدة -->
                            <td class="py-4 px-6 text-xs text-[#0F2747]">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg bg-[#F8FAFC] border border-[#D6E0E7] text-[#0E6E7E] flex items-center justify-center shrink-0">
                                        <Store v-if="lease.unit_type === 'shop'" class="w-3.5 h-3.5 stroke-[2.2]" />
                                        <Home v-else class="w-3.5 h-3.5 stroke-[2.2]" />
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-bold">{{ lease.unit_name }}</span>
                                            <span class="text-[10px] bg-slate-100 text-[#5B6770] px-1.5 py-0.5 rounded font-bold">
                                                {{ lease.unit_type_label }}
                                            </span>
                                        </div>
                                        <span class="text-[#5B6770] block text-[11px] mt-0.5">{{ lease.property_name }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- مدة وسريان العقد -->
                            <td class="py-4 px-6 font-mono text-xs text-[#5B6770]">
                                <div class="flex items-center gap-1.5">
                                    <Calendar class="w-3.5 h-3.5 text-[#5B6770]" />
                                    <span>{{ lease.starts_on }} ➔ {{ lease.ends_on }}</span>
                                </div>
                                <span class="text-[10px] text-[#5B6770] block mt-0.5 font-sans">دورية الاستحقاق: {{ lease.billing_interval }}</span>
                            </td>

                            <!-- الإيجار الشهري -->
                            <td class="py-4 px-6 text-xs">
                                <div class="flex items-baseline gap-1.5 whitespace-nowrap font-mono font-bold text-sm text-[#0F2747]">
                                    <span>
                                        {{ (lease.rent_amount ?? 0).toLocaleString() }}
                                        <span class="text-[10px] font-sans font-normal text-[#5B6770]">{{ currencySymbol }}</span>
                                    </span>
                                    <span class="font-sans text-[10px] font-normal text-[#5B6770]">شهرياً</span>
                                </div>
                            </td>

                            <!-- تأمين الوحدة المسترد -->
                            <td class="py-4 px-6 text-xs">
                                <div v-if="(lease.security_deposit_amount ?? 0) > 0" class="space-y-1">
                                    <div class="flex items-center gap-1 font-mono font-bold text-emerald-700">
                                        <ShieldCheck class="w-3.5 h-3.5 text-emerald-600" />
                                        <span>{{ (lease.security_deposit_amount ?? 0).toLocaleString() }} {{ currencySymbol }}</span>
                                    </div>
                                    <span class="inline-block text-[10px] px-2 py-0.5 rounded-md font-bold border" :class="depositStatusClass(lease.security_deposit_status)">
                                        {{ lease.security_deposit_status_label }}
                                    </span>
                                </div>
                                <div v-else class="text-[11px] text-slate-400 flex items-center gap-1">
                                    <Shield class="w-3.5 h-3.5 text-slate-300" />
                                    <span>بدون تأمين</span>
                                </div>
                            </td>

                            <!-- حالة العقد -->
                            <td class="py-4 px-6">
                                <StatusBadge
                                    :status="lease.is_past_end_date ? 'past_due' : (lease.is_expiring_soon ? 'expiring' : lease.status)"
                                    :label="lease.status_label"
                                    size="sm"
                                />
                            </td>

                            <!-- إجراءات -->
                            <td class="py-4 px-6 text-left whitespace-nowrap">
                                <Link
                                    :href="route('leases.show', lease.id)"
                                    class="my-2 px-3.5 py-2 rounded-xl border border-[#D6E0E7] bg-white hover:bg-[#0E6E7E] hover:text-white hover:border-[#0E6E7E] text-[#0E6E7E] text-xs font-bold transition inline-flex items-center gap-1.5 whitespace-nowrap shadow-2xs group/btn"
                                >
                                    <span>تفاصيل العقد والتأمين</span>
                                    <ArrowLeft class="w-3.5 h-3.5 group-hover/btn:-translate-x-0.5 transition-transform" />
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- حالة عدم وجود بيانات -->
        <EmptyState
            v-else
            type="document"
            title="لم يتم العثور على أي عقود"
            description="لم نجد أي عقد إيجار يطابق معايير البحث والتصفية المحددة."
            action-label="إنشاء عقد إيجار جديد الآن"
            @action="$inertia.visit(route('leases.create'))"
        />
    </AppLayout>
</template>
