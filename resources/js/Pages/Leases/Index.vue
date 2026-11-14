<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import TenantStatusIndicator from '@/Components/Common/TenantStatusIndicator.vue';
import ResourceTableHeading from '@/Components/Common/ResourceTableHeading.vue';
import {
    ArrowLeft,
    Building2,
    Calendar,
    Coins,
    Clock,
    FileCheck,
    FileText,
    Home,
    MessageCircle,
    Paperclip,
    Phone,
    Plus,
    Search,
    Shield,
    ShieldCheck,
    Store,
    X,
} from 'lucide-vue-next';

interface LeaseItem {
    id: number;
    contract_number: string;
    renter_id: number;
    renter_name: string;
    renter_phone: string;
    renter_status?: 'green' | 'yellow' | 'red' | string;
    renter_status_details?: {
        value?: string;
        label?: string;
        description?: string;
        color?: string;
        badge_class?: string;
        dot_class?: string;
    };
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
    contract_image_url?: string | null;
    has_contract_image?: boolean;
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
        case 'settled':
            return 'bg-[#EDF3F0] text-[#49685E]';
        case 'partially_received':
        case 'due':
            return 'bg-[#F5F0E6] text-[#806B45]';
        case 'under_review':
            return 'bg-[#F1F0EC] text-[#68675F]';
        default:
            return 'bg-[#F3F2EE] text-[#77756D]';
    }
};

const leaseStatusClass = (status: string) => {
    switch (status) {
        case 'active':
            return 'bg-[#EDF3F0] text-[#49685E]';
        case 'expiring':
        case 'past_due':
        case 'overdue':
            return 'bg-[#F5F0E6] text-[#806B45]';
        default:
            return 'bg-[#F3F2EE] text-[#77756D]';
    }
};
</script>

<template>
    <AppLayout
        title="عقود الإيجار والتأمين — Nook Mate"
        active-tab="leases"
        header-title="عقود الإيجار والتأمين"
        header-subtitle="إدارة وتوثيق عقود الإيجار السكنية والتجارية، متابعة التأمين المسترد وتواريخ التجديد"
        breadcrumb="العقود"
    >
        <template #actions>
            <Link
                :href="route('leases.create')"
                class="w-full sm:w-auto h-11 px-5 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs sm:text-sm font-semibold transition duration-200 ease-out flex items-center justify-center gap-2 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#163E3E] focus-visible:ring-offset-2 active:scale-[0.98]"
            >
                <Plus class="h-4 w-4" />
                <span>إنشاء عقد إيجار جديد</span>
            </Link>
        </template>

        <div class="grid grid-cols-2 lg:grid-cols-4 overflow-hidden rounded-2xl border border-[#E6E3DA] bg-[#E6E3DA] gap-px">
            <div class="min-w-0 bg-white p-4 sm:p-5">
                <div class="flex items-center gap-2 text-xs font-medium text-[#77756D]">
                    <FileCheck class="h-4 w-4 text-[#52766C]" />
                    <span>العقود السارية</span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="font-mono text-2xl font-semibold tabular-nums text-[#163E3E]">{{ stats.active_leases }}</span>
                    <span class="text-[11px] text-[#8A887F]">من {{ stats.total_leases }} عقد</span>
                </div>
            </div>
            <div class="min-w-0 bg-white p-4 sm:p-5">
                <div class="flex items-center gap-2 text-xs font-medium text-[#77756D]">
                    <Clock class="h-4 w-4 text-[#9A8050]" />
                    <span>تنتهي خلال 30 يوماً</span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="font-mono text-2xl font-semibold tabular-nums text-[#163E3E]">{{ stats.expiring_soon }}</span>
                    <span class="text-[11px] text-[#8A887F]">تحتاج متابعة</span>
                </div>
            </div>
            <div class="min-w-0 bg-white p-4 sm:p-5">
                <div class="flex items-center gap-2 text-xs font-medium text-[#77756D]">
                    <Coins class="h-4 w-4 text-[#52766C]" />
                    <span>القيمة السنوية</span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="truncate font-mono text-xl sm:text-2xl font-semibold tabular-nums text-[#163E3E]">
                        {{ Math.round(stats.total_annual_value ?? 0).toLocaleString() }}
                    </span>
                    <span class="shrink-0 text-[11px] text-[#8A887F]">{{ currencySymbol }} / سنة</span>
                </div>
            </div>
            <div class="min-w-0 bg-white p-4 sm:p-5">
                <div class="flex items-center gap-2 text-xs font-medium text-[#77756D]">
                    <ShieldCheck class="h-4 w-4 text-[#52766C]" />
                    <span>التأمينات المحتجزة</span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="truncate font-mono text-xl sm:text-2xl font-semibold tabular-nums text-[#163E3E]">
                        {{ Math.round(stats.total_deposits_held ?? 0).toLocaleString() }}
                    </span>
                    <span class="shrink-0 text-[11px] text-[#8A887F]">{{ currencySymbol }}</span>
                </div>
            </div>
        </div>

        <!-- تنبيه بالعقود التي تجاوزت موعدها وبانتظار قرار الإدارة -->
        <div v-if="(stats.past_end_date ?? 0) > 0" class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-2xl border border-[#E8E0CE] bg-[#F7F3E9] p-4 sm:px-5">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#EEE5D2] text-[#806B45]">
                    <Clock class="h-4 w-4" />
                </div>
                <div>
                    <span class="block text-xs sm:text-sm font-semibold text-[#4E493E]">يوجد {{ stats.past_end_date }} عقود تجاوزت تاريخ انتهائها</span>
                    <span class="mt-1 block text-[11px] leading-relaxed text-[#777064]">تحتاج هذه العقود إلى قرار بالتجديد أو الإنهاء والتسوية.</span>
                </div>
            </div>
            <button
                type="button"
                @click="statusFilter = 'past_due'"
                class="shrink-0 self-start rounded-lg px-3.5 py-2 text-xs font-semibold text-[#6E5B38] transition duration-200 ease-out hover:bg-[#EEE5D2] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#9A8050] sm:self-auto"
            >
                عرض العقود
            </button>
        </div>

        <!-- شريط البحث والتصفية المتجاوب -->
        <div class="flex flex-col gap-3 rounded-2xl border border-[#E6E3DA] bg-white p-3 sm:flex-row sm:items-center sm:justify-between sm:p-3.5">
            <div class="relative w-full sm:max-w-sm">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="ابحث برقم العقد، اسم المستأجر، رقم الهاتف أو الوحدة..."
                    class="h-10 w-full rounded-xl border border-transparent bg-[#F7F6F2] pl-4 pr-10 text-xs text-[#313B38] placeholder:text-[#929087] transition duration-200 ease-out focus:border-[#A9B9B1] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#52766C]/15"
                />
                <Search class="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8A887F]" />
                <button
                    v-if="searchQuery"
                    @click="searchQuery = ''"
                    type="button"
                    class="absolute left-3 top-1/2 -translate-y-1/2 rounded-md p-1 text-[#8A887F] transition hover:bg-[#EAE8E0] hover:text-[#313B38] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#52766C]"
                    aria-label="مسح البحث"
                >
                    <X class="w-3.5 h-3.5" />
                </button>
            </div>

            <!-- أزرار التصفية السريعة -->
            <div class="flex items-center gap-1 overflow-x-auto pb-1 sm:pb-0 scrollbar-none">
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
                    class="shrink-0 rounded-lg px-3 py-2 text-[11px] font-medium transition duration-200 ease-out cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#52766C]"
                    :class="statusFilter === f.id
                        ? 'bg-[#E8EFEB] text-[#36594F]'
                        : 'text-[#77756D] hover:bg-[#F3F2EE] hover:text-[#313B38]'"
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
                class="space-y-4 rounded-2xl border border-[#E6E3DA] bg-white p-4 transition duration-200 ease-out hover:border-[#D5D1C5] sm:p-5"
            >
                <!-- رأس البطاقة: رقم العقد + الحالة -->
                <div class="flex items-center justify-between gap-3 border-b border-[#EEECE5] pb-3">
                    <div class="flex min-w-0 items-center gap-2">
                        <Link
                            :href="route('leases.show', lease.id)"
                            class="truncate font-mono text-xs font-semibold text-[#36594F] hover:text-[#163E3E] hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#52766C]"
                        >
                            {{ lease.contract_number }}
                        </Link>
                        <span v-if="lease.is_past_end_date" class="shrink-0 text-[10px] font-medium text-[#806B45]">
                            يحتاج قرار
                        </span>
                        <span v-else-if="lease.is_expiring_soon" class="shrink-0 text-[10px] font-medium text-[#806B45]">
                            ينتهي قريباً
                        </span>
                    </div>

                    <span
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-medium"
                        :class="leaseStatusClass(lease.is_past_end_date ? 'past_due' : (lease.is_expiring_soon ? 'expiring' : lease.status))"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                        {{ lease.status_label }}
                    </span>
                </div>

                <!-- الوحدة والعقار -->
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#F3F2EE] text-[#52766C]">
                            <Store v-if="lease.unit_type === 'shop'" class="h-4 w-4" />
                            <Home v-else class="h-4 w-4" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                <span class="truncate text-sm font-semibold text-[#313B38]">{{ lease.unit_name }}</span>
                                <span class="text-[10px] font-medium text-[#8A887F]">
                                    {{ lease.unit_type_label }}
                                </span>
                            </div>
                            <span class="mt-0.5 flex items-center gap-1 text-xs text-[#77756D]">
                                <Building2 class="h-3 w-3 shrink-0" />
                                <span class="truncate">{{ lease.property_name }}</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- المستأجر ومعلومات الاتصال -->
                <div class="flex items-center justify-between gap-3 rounded-xl bg-[#F7F6F2] p-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#E5ECE8] text-xs font-semibold text-[#36594F]">
                            {{ lease.renter_name.charAt(0) }}
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5 min-w-0">
                                <Link
                                    :href="route('renters.show', lease.renter_id)"
                                    class="truncate text-xs font-semibold text-[#313B38] hover:text-[#36594F] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#52766C]"
                                >
                                    {{ lease.renter_name }}
                                </Link>
                                <TenantStatusIndicator
                                    :status="lease.renter_status"
                                    :details="lease.renter_status_details"
                                    size="xs"
                                />
                            </div>
                            <span class="mt-0.5 block font-mono text-[11px] text-[#77756D]" dir="ltr">
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
                            class="rounded-lg p-2 text-[#77756D] transition duration-200 ease-out hover:bg-[#EAE8E0] hover:text-[#36594F] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#52766C]"
                            title="محادثة واتساب"
                            aria-label="محادثة واتساب"
                        >
                            <MessageCircle class="w-4 h-4" />
                        </a>
                        <a
                            v-if="lease.renter_phone"
                            :href="`tel:${lease.renter_phone}`"
                            class="rounded-lg p-2 text-[#77756D] transition duration-200 ease-out hover:bg-[#EAE8E0] hover:text-[#36594F] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#52766C]"
                            title="اتصال هاتفي"
                            aria-label="اتصال هاتفي"
                        >
                            <Phone class="w-4 h-4" />
                        </a>
                    </div>
                </div>

                <!-- تفاصيل الحساب: الإيجار الشهري + التأمين المسترد -->
                <div class="grid grid-cols-2 gap-2.5 text-xs">
                    <!-- الإيجار الشهري -->
                    <div class="space-y-1 rounded-xl bg-[#F7F6F2] p-3">
                        <span class="block text-[11px] text-[#77756D]">الإيجار الشهري</span>
                        <div class="font-mono text-sm font-semibold tabular-nums text-[#313B38]">
                            {{ (lease.rent_amount ?? 0).toLocaleString() }}
                            <span class="font-sans text-[10px] font-normal text-[#77756D]">{{ currencySymbol }}</span>
                        </div>
                    </div>

                    <!-- التأمين المسترد -->
                    <div class="space-y-1 rounded-xl bg-[#F7F6F2] p-3">
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-1 text-[11px] text-[#77756D]">
                                <Shield class="h-3 w-3" />
                                <span>التأمين</span>
                            </span>
                        </div>

                        <div v-if="(lease.security_deposit_amount ?? 0) > 0">
                            <div class="font-mono text-sm font-semibold tabular-nums text-[#313B38]">
                                {{ (lease.security_deposit_amount ?? 0).toLocaleString() }}
                                <span class="font-sans text-[10px] font-normal text-[#77756D]">{{ currencySymbol }}</span>
                            </div>
                            <span class="mt-1 inline-block rounded px-1.5 py-0.5 text-[10px] font-medium" :class="depositStatusClass(lease.security_deposit_status)">
                                {{ lease.security_deposit_status_label }}
                            </span>
                        </div>
                        <div v-else class="pt-1 text-[11px] font-medium text-[#929087]">
                            بدون تأمين
                        </div>
                    </div>
                </div>

                <!-- تواريخ العقد -->
                <div class="flex items-center justify-between gap-3 pt-0.5 text-xs text-[#77756D]">
                    <span class="flex min-w-0 items-center gap-1.5 font-mono tabular-nums" dir="ltr">
                        <Calendar class="h-3.5 w-3.5 shrink-0" />
                        <span>{{ lease.starts_on }} ➔ {{ lease.ends_on }}</span>
                    </span>
                    <span class="shrink-0 text-[11px] font-medium text-[#77756D]">{{ lease.billing_interval }}</span>
                </div>

                <!-- زر تفاصيل العقد والتأمين الكامل -->
                <div class="flex items-center gap-2 pt-1">
                    <a
                        v-if="lease.has_contract_image && lease.contract_image_url"
                        :href="lease.contract_image_url"
                        target="_blank"
                        class="flex h-11 shrink-0 items-center justify-center gap-1.5 rounded-xl border border-[#E6E3DA] px-3.5 text-xs font-medium text-[#52766C] transition duration-200 ease-out hover:bg-[#F7F6F2] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#52766C]"
                        title="عرض وثيقة العقد المعتمد"
                    >
                        <Paperclip class="h-4 w-4" />
                        <span>العقد</span>
                    </a>

                    <Link
                        :href="route('leases.show', lease.id)"
                        class="flex h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-[#163E3E] text-xs font-semibold text-white transition duration-200 ease-out hover:bg-[#102F2F] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#163E3E] focus-visible:ring-offset-2 active:scale-[0.98]"
                    >
                        <span>تفاصيل العقد والتأمين</span>
                        <ArrowLeft class="w-4 h-4" />
                    </Link>
                </div>
            </div>
        </div>

        <!-- 2. جدول العقود المطور للشاشات الكبيرة (Desktop Table Layout for lg+) -->
        <div v-if="filteredLeases.length > 0" class="hidden overflow-hidden rounded-2xl border border-[#E6E3DA] bg-white lg:block">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1120px] text-right text-sm">
                    <thead>
                        <tr class="border-b border-[#EAE8E0] bg-[#F8F7F3] text-[11px] font-medium text-[#77756D]">
                            <th class="px-4 py-3.5">رقم العقد</th>
                            <th class="px-4 py-3.5">
                                <ResourceTableHeading :href="route('renters.index')">المستأجر</ResourceTableHeading>
                            </th>
                            <th class="px-4 py-3.5">
                                <ResourceTableHeading :href="route('properties.index')">العقار والوحدة</ResourceTableHeading>
                            </th>
                            <th class="px-4 py-3.5">مدة العقد</th>
                            <th class="px-4 py-3.5">الإيجار الشهري</th>
                            <th class="px-4 py-3.5">التأمين المسترد</th>
                            <th class="px-4 py-3.5">الحالة</th>
                            <th class="px-4 py-3.5 text-left">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#EEECE5]">
                        <tr
                            v-for="lease in filteredLeases"
                            :key="lease.id"
                            class="transition-colors duration-200 ease-out hover:bg-[#FBFAF7]"
                        >
                            <!-- رقم العقد -->
                            <td class="px-4 py-3.5">
                                <Link
                                    :href="route('leases.show', lease.id)"
                                    class="inline-flex items-center gap-1.5 rounded-md px-1.5 py-1 font-mono text-xs font-semibold text-[#36594F] transition-colors duration-200 ease-out hover:bg-[#E8EFEB] hover:text-[#163E3E] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#52766C]"
                                >
                                    <FileText class="h-3.5 w-3.5" />
                                    <span>{{ lease.contract_number }}</span>
                                </Link>
                            </td>

                            <!-- المستأجر -->
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#E5ECE8] text-xs font-semibold text-[#36594F]">
                                        {{ lease.renter_name.charAt(0) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <Link
                                                :href="route('renters.show', lease.renter_id)"
                                                class="truncate text-xs font-semibold text-[#313B38] transition-colors duration-200 ease-out hover:text-[#36594F] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#52766C]"
                                            >
                                                {{ lease.renter_name }}
                                            </Link>
                                            <TenantStatusIndicator
                                                :status="lease.renter_status"
                                                :details="lease.renter_status_details"
                                                size="xs"
                                            />
                                        </div>
                                        <span class="mt-0.5 block font-mono text-[11px] text-[#8A887F]" dir="ltr">{{ lease.renter_phone }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- العقار والوحدة -->
                            <td class="px-4 py-3.5 text-xs text-[#313B38]">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#F3F2EE] text-[#52766C]">
                                        <Store v-if="lease.unit_type === 'shop'" class="h-4 w-4" />
                                        <Home v-else class="h-4 w-4" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <span class="truncate font-semibold">{{ lease.unit_name }}</span>
                                            <span class="shrink-0 text-[10px] font-medium text-[#8A887F]">
                                                {{ lease.unit_type_label }}
                                            </span>
                                        </div>
                                        <span class="mt-0.5 block truncate text-[11px] text-[#8A887F]">{{ lease.property_name }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- مدة وسريان العقد -->
                            <td class="px-4 py-3.5 font-mono text-xs text-[#555A54]">
                                <div class="flex items-center gap-1.5 whitespace-nowrap tabular-nums" dir="ltr">
                                    <Calendar class="h-3.5 w-3.5 shrink-0 text-[#8A887F]" />
                                    <span>{{ lease.starts_on }} – {{ lease.ends_on }}</span>
                                </div>
                                <span class="mt-1 block font-sans text-[10px] text-[#8A887F]">الاستحقاق: {{ lease.billing_interval }}</span>
                            </td>

                            <!-- الإيجار الشهري -->
                            <td class="px-4 py-3.5 text-xs">
                                <div class="flex items-baseline gap-1 whitespace-nowrap font-mono text-sm font-semibold tabular-nums text-[#313B38]">
                                    <span>
                                        {{ (lease.rent_amount ?? 0).toLocaleString() }}
                                        <span class="font-sans text-[10px] font-normal text-[#8A887F]">{{ currencySymbol }}</span>
                                    </span>
                                </div>
                            </td>

                            <!-- تأمين الوحدة المسترد -->
                            <td class="px-4 py-3.5 text-xs">
                                <div v-if="(lease.security_deposit_amount ?? 0) > 0" class="space-y-1">
                                    <div class="flex items-center gap-1 font-mono font-semibold tabular-nums text-[#49685E]">
                                        <ShieldCheck class="h-3.5 w-3.5" />
                                        <span>{{ (lease.security_deposit_amount ?? 0).toLocaleString() }} {{ currencySymbol }}</span>
                                    </div>
                                    <span class="inline-block rounded px-1.5 py-0.5 text-[10px] font-medium" :class="depositStatusClass(lease.security_deposit_status)">
                                        {{ lease.security_deposit_status_label }}
                                    </span>
                                </div>
                                <div v-else class="flex items-center gap-1 text-[11px] text-[#929087]">
                                    <Shield class="h-3.5 w-3.5" />
                                    <span>بدون تأمين</span>
                                </div>
                            </td>

                            <!-- حالة العقد -->
                            <td class="px-4 py-3.5">
                                <span
                                    class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-[10px] font-medium"
                                    :class="leaseStatusClass(lease.is_past_end_date ? 'past_due' : (lease.is_expiring_soon ? 'expiring' : lease.status))"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                    {{ lease.status_label }}
                                </span>
                            </td>

                            <!-- إجراءات -->
                            <td class="whitespace-nowrap px-4 py-3.5 text-left">
                                <div class="flex items-center justify-end gap-2">
                                    <a
                                        v-if="lease.has_contract_image && lease.contract_image_url"
                                        :href="lease.contract_image_url"
                                        target="_blank"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-2 text-xs font-medium text-[#52766C] transition-colors duration-200 ease-out hover:bg-[#F3F2EE] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#52766C]"
                                        title="عرض وثيقة العقد المشفرة"
                                    >
                                        <Paperclip class="h-3.5 w-3.5" />
                                        <span>وثيقة العقد</span>
                                    </a>

                                    <Link
                                        :href="route('leases.show', lease.id)"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-2 text-xs font-medium text-[#36594F] transition-colors duration-200 ease-out hover:bg-[#E8EFEB] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#52766C]"
                                    >
                                        <span>تفاصيل العقد والتأمين</span>
                                        <ArrowLeft class="h-3.5 w-3.5" />
                                    </Link>
                                </div>
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
